<?php

namespace App\Http\Controllers;

use Exception;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Models\{Template, TemplateVersion, TemplateChangeHistory, LabUser, DocumentVersionTemplate, DocumentVersion};

class TemplateController extends Controller
{

    /**
     * List templates with current version info
     */
    public function index(Request $request)
    {
        try {
            $ctx = $this->labContext($request);
            $query = Template::with('currentVersion');
            
            if ($ctx['lab_id'] == null) {
                $query->SuperAdmin();
            } else {
                $query->ForLab($ctx['lab_id']);
            }

            // Search
            if ($request->filled('query')) {
                $search = strtolower($request->input('query'));

                $query->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(type) LIKE ?', ["%{$search}%"]);
                });
            }

            // Pagination
            $pageSize = (int) $request->input('pageSize', 10);
            $pageIndex = (int) $request->input('pageIndex', 1);

            $templates = $query->paginate($pageSize, ['*'], 'page', $pageIndex);

            $data = $templates->map(function ($template) {
                $currentVersion = $template->currentVersion;

                $createdBy = TemplateChangeHistory::where('template_id', $template->id)
                    ->where('change_context', 'create')
                    ->orderBy('created_at','asc')
                    ->first();

                $lastChangedBy = TemplateChangeHistory::where('template_id', $template->id)
                    ->where('template_version_id', $currentVersion?->id)
                    ->orderBy('created_at','desc')
                    ->first();

                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'type' => $template->type,
                    'status' => $template->status,
                    'versions_count' => $template->versions()->count(),
                    'current_version' => $currentVersion ? "{$currentVersion->major}.{$currentVersion->minor}" : null,
                    'created_by' => $createdBy?->changed_by,
                    'last_changed_by' => $lastChangedBy?->changed_by,
                    'deleted_at' => $template->deleted_at,
                    'updated_at' => $template->updated_at,
                    'template' => [
                        'css' => $currentVersion?->css,
                        'html' => $currentVersion?->html,
                        'json' => $currentVersion?->json_data,
                    ]
                ];
            });

            return response()->json([
                'success'=>true,
                'data'=>$data,
                'total'=>$templates->total()
            ],200);

        } catch(Exception $e) {
            return response()->json(['success'=>false,'message'=>$e->getMessage()],500);
        }
    }

    /**
     * Store a new template with initial version
     */
    public function store(Request $request)
    {
        $ctx = $this->labContext($request);

        $validator = Validator::make($request->all(), [
            'name'=>'required',
            'parent_id' => [
                'nullable',
                'exists:templates,id',
                fn ($attr, $value, $fail) =>
                    $value && $ctx['owner_type'] !== 'lab'
                        ? $fail('Only lab users can override master departments')
                        : null
            ],
            'name' => [
                'required',
                Rule::unique('templates')->where(fn ($q) =>
                    $q->where('owner_type', $ctx['owner_type'])
                      ->where('owner_id', $ctx['owner_id'])
                      ->whereNull('deleted_at')
                ),
            ],
            'type'=>'required|in:header,footer',
            'template.css'=>'nullable|string',
            'template.html'=>'nullable|string',
            'template.json'=>'nullable|array',
            'message'=>'nullable|string|max:255',
            'status' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) return response()->json(['success'=>false,'errors'=>$validator->errors()],422);

        DB::beginTransaction();
        try {
            $data = $validator->validated();
            $userId = Auth::user()->id;

            $template = Template::create([
                'parent_id'  => $request->parent_id,
                'name'=>$data['name'],
                'type'=>$data['type'],
                'status'=>$data['status'],
                'owner_type' => $ctx['owner_type'],
                'owner_id'   => $ctx['owner_id'],
            ]);

            $version = TemplateVersion::create([
                'template_id'=>$template->id,
                'major'=>1,
                'minor'=>0,
                'css'=>$data['template']['css'] ?? null,
                'html'=>$data['template']['html'] ?? null,
                'json_data'=>$data['template']['json'] ?? null,
                'is_current'=>true,
                'change_type'=>'major',
                'message'=>$data['message'] ?? 'Initial version',
                'changed_by'=>$userId,
            ]);

            TemplateChangeHistory::create([
                'template_id'=>$template->id,
                'template_version_id'=>$version->id,
                'field_name'=>null,
                'old_value'=>null,
                'new_value'=>json_encode([
                    'name'=>$template->name,
                    'type'=>$template->type,
                    'css'=>$version->css,
                    'html'=>$version->html,
                    'json'=>$version->json_data
                ]),
                'change_context'=>'create',
                'changed_by'=>$userId,
                'message'=>$data['message'] ?? 'Template created'
            ]);

            DB::commit();
            return response()->json(['success'=>true,'data'=>$template],201);

        } catch(Exception $e) {
            DB::rollBack();
            return response()->json(['success'=>false,'message'=>$e->getMessage()],500);
        }
    }

    /**
     * Show template with current version (simple format)
     */
    public function show($id)
    {
        try {
            $ctx = $this->labContext(request());
            
            $template = Template::with('currentVersion')->accessible($ctx['lab_id'])->findOrFail($id);
            $currentVersion = $template->currentVersion;

            $formatted = [
                'id' => $template->id,
                'name' => $template->name,
                'type' => $template->type,
                'template' => [
                    'css' => $currentVersion?->css,
                    'html' => $currentVersion?->html,
                    'json' => $currentVersion?->json_data,
                ]
            ];

            return response()->json(['success'=>true,'data'=>$formatted],200);

        } catch(Exception $e) {
            return response()->json(['success'=>false,'message'=>'Template not found','error'=>$e->getMessage()],404);
        }
    }

    private function applyTemplateToDocuments(Template $template, TemplateVersion $newVersion)
    {
        $docTemplates = DocumentVersionTemplate::where('template_id', $template->id)
            ->get();

        foreach ($docTemplates as $docTemplate) {
            $docVersion = $docTemplate->version;

            if (!$docVersion) continue;

            $docTemplate->update([
                'template_version_id' => $newVersion->id,
            ]);

            $schema = $docVersion->editor_schema ?? [];

            $type = $docTemplate->type;

            $schema[$type] = [
                'html' => $newVersion->html,
                'css'  => $newVersion->css,
                'json' => $newVersion->json_data,
            ];

            $docVersion->update([
                'editor_schema' => $schema,
            ]);
        }
    }

    /**
     * Update template (minor/major) and versioning
     */
    public function update(Request $request, $id)
    {
        $template = Template::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => [
                'sometimes',
                Rule::unique('templates')->ignore($id)->where(fn ($q) =>
                    $q->where('owner_type', $template->owner_type)
                      ->where('owner_id', $template->owner_id)
                      ->whereNull('deleted_at')
                ),
            ],
            'type'=>'sometimes|required|in:header,footer',
            'template.css'=>'nullable|string',
            'template.html'=>'nullable|string',
            'template.json'=>'nullable|array',
            'change_type'=>'required|in:minor,major',
            'message'=>'nullable|string|max:255',
            'apply_all_documents'=>'sometimes|boolean',
        ]);

        if ($validator->fails()) return response()->json(['success'=>false,'errors'=>$validator->errors()],422);

        DB::beginTransaction();
        try {
            $data = $validator->validated();
            $userId = Auth::user()->id;
            $applyAll = $request->boolean('apply_all_documents');

            $template->update([
                'name' => $data['name'] ?? $template->name,
                'type' => $data['type'] ?? $template->type,
            ]);

            // Versioning
            $currentVersion = $template->versions()->where('is_current',true)->lockForUpdate()->firstOrFail();
            $major = $currentVersion->major;
            $minor = $currentVersion->minor;

            if($data['change_type'] === 'major'){
                $major++;
                $minor=0;
            } else {
                $minor++;
            }

            // Set old version to not current
            $currentVersion->update(['is_current'=>false]);

            $version = TemplateVersion::create([
                'template_id'=>$template->id,
                'major'=>$major,
                'minor'=>$minor,
                'css'=>$data['template']['css'] ?? $currentVersion->css,
                'html'=>$data['template']['html'] ?? $currentVersion->html,
                'json_data'=>$data['template']['json'] ?? $currentVersion->json_data,
                'is_current'=>true,
                'change_type'=>$data['change_type'],
                'message'=>$data['message'] ?? null,
                'changed_by'=>$userId,
            ]);

            if ($data['change_type'] === 'minor' && $applyAll) {
                $this->applyTemplateToDocuments($template, $version);
            }

            TemplateChangeHistory::create([
                'template_id'=>$template->id,
                'template_version_id'=>$version->id,
                'field_name'=>null,
                'old_value'=>json_encode([
                    'css'=>$currentVersion->css,
                    'html'=>$currentVersion->html,
                    'json'=>$currentVersion->json_data
                ]),
                'new_value'=>json_encode([
                    'css'=>$version->css,
                    'html'=>$version->html,
                    'json'=>$version->json_data
                ]),
                'change_context'=>'current',
                'changed_by'=>$userId,
                'message'=>$data['message'] ?? null
            ]);

            DB::commit();
            return response()->json(['success'=>true,'data'=>$version],200);

        } catch(Exception $e) {
            DB::rollBack();
            return response()->json(['success'=>false,'message'=>$e->getMessage()],500);
        }
    }

    /**
     * Soft delete template
     */
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $template = Template::findOrFail($id);
             if ($template->is_master) {
                return response()->json([
                    'success' => false,
                    'message' => 'Master template cannot be deleted',
                ], 403);
            }

            $template->delete();
            DB::commit();
            return response()->json(['success'=>true,'message'=>'Template soft deleted'],200);
        } catch(Exception $e) {
            DB::rollBack();
            return response()->json(['success'=>false,'message'=>$e->getMessage()],500);
        }
    }

    /**
     * Restore soft-deleted template
     */
    public function restore($id)
    {
        DB::beginTransaction();
        try {
            $template = Template::withTrashed()->findOrFail($id);
            $template->restore();
            DB::commit();
            return response()->json(['success'=>true,'message'=>'Template restored'],200);
        } catch(Exception $e) {
            DB::rollBack();
            return response()->json(['success'=>false,'message'=>$e->getMessage()],500);
        }
    }

    /**
     * List all versions of a template
     */
    public function versions(Request $request, $templateId)
    {
        try {
            $template = Template::findOrFail($templateId);

            $query = $template->versions()->with('template');

            if ($request->filled('query')) {
                $search = strtolower($request->input('query'));

                $query->where(function ($q) use ($search) {
                    $q->whereRaw("CAST(major AS TEXT) LIKE ?", ["%{$search}%"])
                        ->orWhereRaw("CAST(minor AS TEXT) LIKE ?", ["%{$search}%"])
                        ->orWhereRaw("LOWER(status) LIKE ?", ["%{$search}%"]);
                });
            }

            $pageSize  = (int) $request->input('pageSize', 10);
            $pageIndex = (int) $request->input('pageIndex', 1);

            $versions = $query
                ->orderBy('major', 'desc')
                ->orderBy('minor', 'desc')
                ->paginate($pageSize, ['*'], 'page', $pageIndex);

            $data = $versions->map(function ($version) use ($template) {

                $createdBy = TemplateChangeHistory::where('template_id', $template->id)
                    ->where('template_version_id', $version->id)
                    ->where('change_context', 'create')
                    ->orderBy('created_at', 'asc')
                    ->first();

                $lastChangedBy = TemplateChangeHistory::where('template_id', $template->id)
                    ->where('template_version_id', $version->id)
                    ->orderBy('created_at', 'desc')
                    ->first();

                return [
                    'id' => $version->id,
                    'name' => $template->name,
                    'temp_id' => $template->id,
                    'version' => "{$version->major}.{$version->minor}",
                    'major' => $version->major,
                    'minor' => $version->minor,
                    'status' => $version->status,
                    'is_current' => $version->is_current,

                    'created_by' => $createdBy?->changed_by,
                    'last_changed_by' => $lastChangedBy?->changed_by,

                    'created_at' => $version->created_at,
                    'updated_at' => $version->updated_at,

                    'template' => [
                        'css' => $version->css,
                        'html' => $version->html,
                        'json' => $version->json_data,
                    ],
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $data,
                'total'   => $versions->total(),
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found',
                'error'   => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Edit old version
     */
    public function editOldVersion(Request $request, $versionId)
    {
        $validator = Validator::make($request->all(), [
            'template.css'=>'nullable|string',
            'template.html'=>'nullable|string',
            'template.json'=>'nullable|array',
            'message'=>'nullable|string|max:255'
        ]);

        if ($validator->fails()) return response()->json(['success'=>false,'errors'=>$validator->errors()],422);

        DB::beginTransaction();
        try {
            $version = TemplateVersion::findOrFail($versionId);
            $userId = auth()->id();
            $data = $validator->validated();
            $oldData = ['css'=>$version->css,'html'=>$version->html,'json'=>$version->json_data];

            foreach(['css','html','json_data'] as $field){
                if(isset($data['template'][$field])) $version->$field = $data['template'][$field];
            }

            $version->save();

            TemplateChangeHistory::create([
                'template_id'=>$version->template_id,
                'template_version_id'=>$version->id,
                'field_name'=>null,
                'old_value'=>json_encode($oldData),
                'new_value'=>json_encode($data['template']),
                'change_context'=>'old_version',
                'changed_by'=>$userId,
                'message'=>$data['message'] ?? 'Edited old version'
            ]);

            DB::commit();
            return response()->json(['success'=>true,'data'=>$version],200);

        } catch(Exception $e) {
            DB::rollBack();
            return response()->json(['success'=>false,'message'=>$e->getMessage()],500);
        }
    }

    /**
     * Change current version manually
     */
    public function changeCurrentVersion(Request $request, $templateId)
    {
        $validator = Validator::make($request->all(), [
            'version_id'=>'required|exists:template_versions,id',
            'message'=>'nullable|string|max:255'
        ]);

        if ($validator->fails()) return response()->json(['success'=>false,'errors'=>$validator->errors()],422);

        DB::beginTransaction();
        try {

            $template = Template::with('versions')->findOrFail($templateId);
            $versionId = $request->version_id;
            $userId = auth()->id();
            $message = $request->message;

            $version = $template->versions()
                ->where('id', $versionId)
                ->firstOrFail();

            if ($version->is_current) {
                return response()->json([
                    'success' => true,
                    'data'    => $version,
                    'message' => 'This version is already current',
                ]);
            }

            $template->versions()->where('is_current',true)->update(['is_current'=>false]);

            $version->update(['is_current'=> true]);

            TemplateChangeHistory::create([
                'template_id'=>$template->id,
                'template_version_id'=>$version->id,
                'field_name'=>null,
                'old_value'=>null,
                'new_value'=>json_encode([
                    'css'=>$version->css,
                    'html'=>$version->html,
                    'json'=>$version->json_data
                ]),
                'change_context'=>'current',
                'changed_by'=>$userId,
                'message'=>$message ?? 'Changed current version manually'
            ]);

            DB::commit();
            return response()->json(['success'=>true,'data'=>$version->fresh()],200);

        } catch(Exception $e) {
            DB::rollBack();
            return response()->json(['success'=>false,'message'=>$e->getMessage()],500);
        }
    }

    public function showVersion($templateId, $versionId)
    {
        $version = TemplateVersion::where('template_id', $templateId)
            ->where('id', $versionId)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $version->id,
                'version' => "{$version->major}.{$version->minor}",
                'is_current' => $version->is_current,
                'template' => [
                    'css' => $version->css,
                    'html' => $version->html,
                    'json' => $version->json_data,
                ],
            ],
        ]);
    }
    public function labMasterTemplates(Request $request)
    {
        $labId = $request->query('lab_id');

        if (!$labId) {
            return response()->json([
                'success' => false,
                'message' => 'lab_id is required'
            ], 422);
        }

        $templates = Template::query()
            ->where('owner_type', 'lab')
            ->where('owner_id', $labId)
            ->whereNull('parent_id')
            ->whereDoesntHave('overrides', function ($q) {
                $q->where('owner_type', 'super_admin');
            })
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $templates
        ]);
    }

    public function appendLabTemplateToMaster(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lab_template_id' => ['required', 'exists:templates,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $labTemplate = Template::where('id', $request->lab_template_id)
            ->where('owner_type', 'lab')
            ->whereNull('parent_id')
            ->firstOrFail();

        $alreadyExists = Template::where('owner_type', 'super_admin')
            ->where('parent_id', $labTemplate->id)
            ->exists();

        if ($alreadyExists) {
            return response()->json([
                'success' => false,
                'message' => 'Template already appended to master',
            ], 409);
        }

        DB::beginTransaction();
        try {

            $masterTemplate = Template::create([
                'parent_id'              => $labTemplate->id,
                'name'                   => $labTemplate->name,
                'type'                   => $labTemplate->type,
                'status'                 => $labTemplate->status,
                'owner_type'             => 'super_admin',
                'owner_id'               => null,
                'appended_from_lab_id'   => $labTemplate->owner_id,
            ]);

            $currentVersion = $labTemplate->currentVersion;

            if ($currentVersion) {

                TemplateVersion::where('template_id', $masterTemplate->id)
                    ->update(['is_current' => false]);

                $newVersion = TemplateVersion::create([
                    'template_id' => $masterTemplate->id,
                    'major'       => $currentVersion->major,
                    'minor'       => $currentVersion->minor,
                    'css'         => $currentVersion->css,
                    'html'        => $currentVersion->html,
                    'json_data'   => $currentVersion->json_data,
                    'is_current'  => true,
                    'change_type' => 'major',
                    'message'     => 'Appended from lab template',
                    'changed_by'  => auth()->id(),
                ]);

                TemplateChangeHistory::create([
                    'template_id'         => $masterTemplate->id,
                    'template_version_id' => $newVersion->id,
                    'field_name'          => null,
                    'old_value'           => null,
                    'new_value'           => json_encode([
                        'css'  => $newVersion->css,
                        'html' => $newVersion->html,
                        'json' => $newVersion->json_data,
                    ]),
                    'change_context'      => 'create',
                    'changed_by'          => auth()->id(),
                    'message'             => 'Template appended from lab',
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data'    => $masterTemplate->load('currentVersion'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to append template',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
