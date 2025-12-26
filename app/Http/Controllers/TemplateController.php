<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\TemplateChangeHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class TemplateController extends Controller
{
    /**
     * List templates with current version info
     */
    public function index(Request $request)
    {
        try {
            $query = Template::with('currentVersion')->withTrashed();

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
        $validator = Validator::make($request->all(), [
            'name'=>'required|string|max:255|unique:templates,name',
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
                'name'=>$data['name'],
                'type'=>$data['type'],
                'status'=>$data['status'],
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
            $template = Template::with('currentVersion')->findOrFail($id);
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

    /**
     * Update template (minor/major) and versioning
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name'=>'sometimes|required|string|max:255|unique:templates,name,'.$id,
            'type'=>'sometimes|required|in:header,footer',
            'template.css'=>'nullable|string',
            'template.html'=>'nullable|string',
            'template.json'=>'nullable|array',
            'change_type'=>'required|in:minor,major',
            'message'=>'nullable|string|max:255',
        ]);

        if ($validator->fails()) return response()->json(['success'=>false,'errors'=>$validator->errors()],422);

        DB::beginTransaction();
        try {
            $template = Template::findOrFail($id);
            $data = $validator->validated();
            $userId = Auth::user()->id;

            $template->update([
                'name'=>$data['name'] ?? $template->name,
                'type'=>$data['type'] ?? $template->type,
            ]);

            // Versioning
            $currentVersion = $template->versions()->where('is_current',true)->first();
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
    public function versions($templateId)
    {
        try {
            $template = Template::findOrFail($templateId);
            $versions = $template->versions()->orderBy('major','desc')->orderBy('minor','desc')->get();
            return response()->json(['success'=>true,'data'=>$versions],200);
        } catch(Exception $e) {
            return response()->json(['success'=>false,'message'=>'Template not found','error'=>$e->getMessage()],404);
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
            $template = Template::findOrFail($templateId);
            $versionId = $request->input('version_id');
            $userId = auth()->id();
            $message = $request->input('message');

            // Unset old current
            $template->versions()->where('is_current',true)->update(['is_current'=>false]);

            // Set new current
            $version = TemplateVersion::findOrFail($versionId);
            $version->is_current = true;
            $version->save();

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
                'change_context'=>'change_current',
                'changed_by'=>$userId,
                'message'=>$message ?? 'Changed current version manually'
            ]);

            DB::commit();
            return response()->json(['success'=>true,'data'=>$version],200);

        } catch(Exception $e) {
            DB::rollBack();
            return response()->json(['success'=>false,'message'=>$e->getMessage()],500);
        }
    }
}
