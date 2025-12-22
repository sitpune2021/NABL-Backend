<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Document;
use App\Models\DocumentDepartment;
use App\Models\DocumentVersion;
use App\Models\DocumentVersionTemplate;
use App\Models\DocumentVersionWorkflowLog;
use App\Models\User;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class DocumentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = Document::with('currentVersion', 'category');

            // Search
            if ($request->filled('query')) {
                $search = $request->input('query');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%");
                });
            }

            // Sorting
            $sortKey = $request->input('sort.key', 'id'); // default sort by id
            $sortOrder = $request->input('sort.order', 'asc'); // default ascending

            $allowedSortColumns = ['id', 'name','status','created_at', 'updated_at'];
            $allowedSortOrder = ['asc', 'desc'];

            if (in_array($sortKey, $allowedSortColumns) && in_array(strtolower($sortOrder), $allowedSortOrder)) {
                $query->orderBy($sortKey, $sortOrder);
            }

            // Pagination
            $pageIndex = (int) $request->input('pageIndex', 1);
            $pageSize = (int) $request->input('pageSize', 10);

            $documents = $query->paginate($pageSize, ['*'], 'page', $pageIndex);


            return response()->json([
                'status' => true,
                'data' => $documents->items(),
                'total' => $documents->total()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch documents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mode' => 'required|in:create,upload',
            'category_id' => 'required|integer|exists:categories,id',
            'department' => 'required|array|min:1',
            'department.*' => 'integer|exists:departments,id',

            // Common
            'number' => 'required|string|unique:document_versions,number',
            'name' => 'required|string|max:255',
            'status' => 'required|in:controlled,uncontrolled',
            'copy_no' => 'nullable|string|max:50',
            'quantity_prepared' => 'nullable|integer|min:0',
            'effective_date' => 'nullable|date',
            'schedule' => 'nullable|array',
            'review_frequency' => 'nullable|string',
            'notification_unit' => 'nullable|string',
            'notification_value' => 'nullable|integer|min:0',

            // Create mode only
            'workflow_state' => 'nullable|in:draft,prepared,reviewed,approved,issued,effective',
            'editor_schema' => 'nullable|array',
            'form_fields' => 'nullable|array',

            // Templates (create only)
            'header.template_id' => 'nullable|integer|exists:templates,id',
            'footer.template_id' => 'nullable|integer|exists:templates,id',

            // Workflow log (create only)
            'step_type' => 'nullable|in:prepared,reviewed,approved,issued,effective',
            'performed_by' => 'nullable|exists:users,username',
            'performed_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $document = Document::create([
                'name' => $request->name,
                'category_id' => $request->category_id,
                'status' => $request->status,
                'mode' => $request->mode,
            ]);

            foreach ($request->department as $deptId) {
                DocumentDepartment::create([
                    'document_id' => $document->id,
                    'department_id' => $deptId,
                ]);
            }

            $versionData = [
                'document_id' => $document->id,
                'major_version' => 1,
                'minor_version' => 0,
                'full_version' => '1.0',
                'number' => $request->number,
                'copy_no' => $request->copy_no,
                'quantity_prepared' => $request->quantity_prepared,
                'is_current' => true,
                'version_status' => 'active',
                'effective_date' => $request->effective_date,
                'schedule' => $request->schedule,
                'review_frequency' => $request->review_frequency,
                'notification_unit' => $request->notification_unit,
                'notification_value' => $request->notification_value,
            ];

            if ($request->mode === 'create') {
                $versionData['workflow_state'] = $request->workflow_state ?? 'draft';
                $versionData['editor_schema'] = $request->editor_schema;
                $versionData['form_fields'] = $request->form_fields;
            }

            $version = DocumentVersion::create($versionData);

            // Templates
            if ($request->mode === 'create') {
                foreach (['header', 'footer'] as $type) {
                    $templateId = data_get($request, "$type.template_id");

                    if ($templateId) {
                        $template = Template::find($templateId);

                        DocumentVersionTemplate::create([
                            'document_version_id' => $version->id,
                            'template_id' => $template->id,
                            'template_version_id' => $template?->currentVersion?->id,
                            'type' => $type,
                        ]);
                    }
                }
            }

            // Workflow log
            if (
                $request->mode === 'create' &&
                $request->filled(['step_type', 'performed_by'])
            ) {
                $user = User::where('username', $request->performed_by)->first();

                DocumentVersionWorkflowLog::create([
                    'document_version_id' => $version->id,
                    'step_type' => $request->step_type,
                    'step_status' => 'completed',
                    'performed_by' => $user?->id,
                    'comments' => 'Initial workflow step',
                    'created_at' => $request->performed_date ?? now(),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $document->load([
                    'departments',
                    'versions.templates',
                    'versions.workflowLogs',
                ])
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error creating document',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $document = Document::with([
                'departments:id',
                'versions.templates.template.currentVersion',
                'versions.workflowLogs.user',
                'currentVersion.templates.template.currentVersion'
            ])->findOrFail($id);

            $version = $document->currentVersion;

            $payload = [
                'mode' => $document->mode,
                'category_id' => $document->category_id,
                'department' => $document->departments->pluck('id')->toArray(),

                'number' => $version->number,
                'name' => $document->name,
                'status' => $document->status,
                'copy_no' => $version->copy_no,
                'quantity_prepared' => $version->quantity_prepared,
                'effective_date' => $version->effective_date,
                'schedule' => $version->schedule,
                'review_frequency' => $version->review_frequency,
                'notification_unit' => $version->notification_unit,
                'notification_value' => $version->notification_value,
            ];

            // Create-mode only fields
            if ($document->mode === 'create') {
                $payload += [
                    'workflow_state' => $version->workflow_state,
                    'editor_schema' => $version->editor_schema,
                    'form_fields' => $version->form_fields,
                ];

                // Header & Footer templates
                foreach (['header', 'footer'] as $type) {
                    $template = $version->templates
                        ->where('type', $type)
                        ->first();

                    if ($template) {
                        $payload[$type] = [
                            'template_id' => $template->template_id,
                            'type' => $template->type,
                            'current_version' => $template->template->currentVersion 
                                ? sprintf(
                                    '%s.%s',
                                    $template->template->currentVersion->major ?? '0',
                                    $template->template->currentVersion->minor ?? '0'
                                )
                                : null,
                        ];
                    }
                }

                // Workflow log (latest)
                $log = $version->workflowLogs->sortByDesc('created_at')->first();

                if ($log) {
                    $payload += [
                        'step_type' => $log->step_type,
                        'performed_by' => $log->user?->username,
                        'performed_date' => $log->created_at,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $payload
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found'
            ], 404);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            // Document
            'name' => 'sometimes|string|max:255',
            'category_id' => 'sometimes|integer|exists:categories,id',
            'status' => 'sometimes|in:controlled,uncontrolled',

            // Departments
            'department' => 'sometimes|array|min:1',
            'department.*' => 'integer|exists:departments,id',

            // Document Version
            'number' => 'sometimes|string|unique:document_versions,number,' . $id . ',document_id',
            'copy_no' => 'nullable|string|max:50',
            'quantity_prepared' => 'nullable|integer|min:0',
            'effective_date' => 'nullable|date',
            'schedule' => 'nullable|array',

            // ✅ Correct frequency fields
            'review_frequency' => 'nullable|string',
            'notification_unit' => 'nullable|string',
            'notification_value' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $document = Document::findOrFail($id);

            /** -------------------------
             * Update Document
             * --------------------------*/
            $document->update($request->only([
                'name',
                'category_id',
                'status',
            ]));

            /** -------------------------
             * Update Departments
             * --------------------------*/
            if ($request->has('department')) {
                DocumentDepartment::where('document_id', $document->id)->delete();

                foreach ($request->department as $deptId) {
                    DocumentDepartment::create([
                        'document_id' => $document->id,
                        'department_id' => $deptId,
                    ]);
                }
            }

            /** -------------------------
             * Update Current Version
             * --------------------------*/
            $version = $document->versions()->where('is_current', true)->first();

            if ($version) {
                $version->update($request->only([
                    'number',
                    'copy_no',
                    'quantity_prepared',
                    'effective_date',
                    'schedule',
                    'review_frequency',
                    'notification_unit',
                    'notification_value',
                ]));
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $document->load([
                    'departments',
                    'versions',
                ])
            ], 200);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Document not found'
            ], 404);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        DB::beginTransaction();
        try {
            $department = Document::findOrFail($id);
            $department->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully'
            ], 200);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Document not found'
            ], 404);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete department',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
