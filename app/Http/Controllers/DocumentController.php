<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Services\DocumentAmendmentVersionService;
use Spatie\Permission\PermissionRegistrar;
use App\Services\DocumentWorkflowService;

use App\Models\{
    Document,
    DocumentDepartment,
    DocumentVersion,
    DocumentVersionTemplate,
    DocumentVersionWorkflowLog,
    LabDocumentsEntryData,
    LabUser,
    Template,
    User,
};

class DocumentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $ctx = $this->labContext($request);
            $query = Document::with('currentVersion.workflowLogs','currentVersion.amendments', 'category');
            
            if ($ctx['lab_id'] == 0) {
                 $query->where('owner_type', 'super_admin');
            } else {
                $query->where('owner_type', 'lab')
              ->where('owner_id', $ctx['lab_id']);
            }

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
           
            $documents->getCollection()->transform(function ($doc) {
                $doc->versions_count  = $doc->versions()->count();
                $doc->current_vrsn = optional($doc->currentVersion)->full_version;

                return $doc;
            });


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
            'number' => 'required|string|unique:documents,number',

            // Common
            'name' => 'required|string|max:255',
            'status' => 'required|in:controlled,uncontrolled',
            'copy_no' => 'nullable|string|max:50',
            'quantity_prepared' => 'nullable|min:0',
            'effective_date' => 'nullable|date',
            'schedule' => 'nullable|array',
            'review_frequency' => 'nullable|string',
            'notification_unit' => 'nullable|string',
            'notification_value' => 'nullable|integer|min:0',

            // Create mode only
            'editor_schema' => 'nullable|array',
            'form_fields' => 'nullable|array',

            // Templates (create only)
            'header.template_id' => 'nullable|integer|exists:templates,id',
            'footer.template_id' => 'nullable|integer|exists:templates,id',

            // Workflow log (create only)
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
            $ctx = $this->labContext($request);

            $document = Document::create([
                'name' => $request->name,
                'category_id' => $request->category_id,
                'status' => $request->status,
                'mode' => $request->mode,
                'number' =>  $request->number, // Temporary, will update later
                'owner_type' => $ctx['owner_type'],
                'owner_id'   => $ctx['owner_id'],
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
                'copy_no' => $request->copy_no,
                'quantity_prepared' => $request->quantity_prepared,
                'is_current' => true,
                'version_status' => 'active',
                'effective_date' => $request->effective_date,
                'schedule' => $request->schedule,
                'review_frequency' => $request->review_frequency,
                'notification_unit' => $request->notification_unit,
                'notification_value' => $request->notification_value,
                'workflow_state' => $request->mode === 'create' ? 'prepared' : 'issued',
            ];

            if ($request->mode === 'create') {
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
            if ($request->mode === 'create' && $request->filled(['performed_by'])) {
                $performedBy = User::where('username', $request->performed_by)->first();
                if ($performedBy) {
                    DocumentVersionWorkflowLog::create([
                        'document_version_id' => $version->id,
                        'step_type' => 'prepared',
                        'step_status' => 'pending',
                        'performed_by' =>auth()->id(),
                        'comments' => 'Initial preparation',
                        'created_at' => $request->performed_date ?? now(),
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $document->load(['departments', 'versions.templates', 'versions.workflowLogs'])
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

                'number' => $document->number,
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
                    'issue_no' => $version->major_version,
                    'amendment_no' => $version->minor_version,
                    'full_version' => $version->full_version
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
                $logs = $version->workflowLogs->sortByDesc('created_at');
                $map = [
                    'prepared' => 'preparedBy',
                    'reviewed' => 'reviewedBy',
                    'approved' => 'approvedBy',
                    'issued' => 'issuedBy',
                    'effective' => 'effectiveBY'
                ];

                $workflowPayload = [];

                foreach ($logs as $log) {
                    if (!isset($map[$log->step_type])) continue;

                    $workflowPayload[$map[$log->step_type]] = [
                        'name' => $log->user->name,
                        'designation' => optional($log->user->roles->first())->name,
                        'signature' => $log->user->signature == null ? $log->user->name : $log->user->signature ,
                        $log->step_type => $log->created_at,
                    ];
                }
                $payload['workflow'] = $workflowPayload;
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
    public function update(Request $request,string $id,DocumentAmendmentVersionService $service)
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
            'copy_no' => 'nullable|string|max:50',
            'quantity_prepared' => 'nullable|integer|min:0',
            'effective_date' => 'nullable|date',
            'schedule' => 'nullable|array',
            'review_frequency' => 'nullable|string',
            'notification_unit' => 'nullable|string',
            'notification_value' => 'nullable|integer|min:0',

            // Create mode only
            'editor_schema' => 'nullable|array',
            'form_fields' => 'nullable|array',

            // Templates (create only)
            'header.template_id' => 'nullable|integer|exists:templates,id',
            'footer.template_id' => 'nullable|integer|exists:templates,id',

            // Amendment (MANDATORY)
            'amendment_type' => 'required|in:major,minor',
            'amendment_reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $document = Document::with('currentVersion')->findOrFail($id);
            $document->update($request->only([
                'name',
                'category_id',
                'status'
            ]));

            if ($request->has('department')) {
                $document->departments()->sync($request->department);
            }
            $version = $document->currentVersion;
            $version->update($request->only([
                'copy_no',
                'quantity_prepared',
                'effective_date',
                'schedule',
                'review_frequency',
                'notification_unit',
                'notification_value',
            ]));

            if ($document->mode === 'create') {
                $version->update($request->only([
                    'editor_schema',
                    'form_fields'
                ]));
                DocumentVersionTemplate::where('document_version_id', $version->id)
                    ->whereIn('type', ['header', 'footer'])
                    ->delete();

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
                $newVersion = $service->handle(
                    $version,
                    $request->amendment_type,
                    auth()->id(),
                    $request->amendment_reason
                );
            }
            DB::commit();

            return response()->json([
                    'success' => true,
                    'message' => !isset($newVersion)
                        ? 'Updated successfully'
                        : ($newVersion->id === $version->id
                            ? 'Amendment recorded'
                            : 'New version created'),
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
                'message' => $e->getMessage(),
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

    public function dataEntry(Request $request)
    {
        $validated = $request->validate([
            'document_id' => 'required|exists:documents,id',
            'fields_entry' => 'required|array',
        ]);

        $authUser = Auth::user();

        $labUser = LabUser::where('user_id', $authUser->id)->first();

        if (!$labUser) {
            return response()->json([
                'message' => 'Lab user not found.'
            ], 404);
        }

        DB::beginTransaction();

        try {
            $document = Document::with('currentVersion')->findOrFail($validated['document_id']);

            $fieldsEntry = $validated['fields_entry'];

            // Handle file upload only if document mode is not 'create' and file exists
            if ($document->mode !== 'create' && $request->hasFile('fields_entry.document')) {
                $file = $request->file('fields_entry.document');

                    // Generate unique filename
                $filename = time() . '_' . $file->getClientOriginalName();

                    // Store file in storage/app/private/documents
                $file->storeAs('documents', $filename, 'public');

                    // Replace file object with filename
                    $fieldsEntry['document'] = $filename;
            }

                // Create LabDocumentsEntryData
            $entry = LabDocumentsEntryData::create([
                'user_id' => $labUser->user_id,
                'lab_id' => $labUser->lab_id,
                    'document_id' => $validated['document_id'],
                    'document_version_id' => $document->currentVersion?->id ?? $validated['document_id'],
                    'fields_entry' => json_encode($fieldsEntry),
            ]);

            DB::commit(); // Commit transaction

            return response()->json([
                'message' => 'Data entry saved successfully',
                'data' => $entry
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack(); // Rollback if any error occurs

            return response()->json([
                'message' => 'Failed to save data entry',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getDataEntriesByDocument($id)
    {
        $authUser = Auth::user();

        $labUser = LabUser::where('user_id', $authUser->id)->first();

        if (!$labUser) {
            return response()->json([
                'message' => 'Lab not found for this user'
            ], 404);
        }

        $document = Document::with('currentVersion')->findOrFail($id);
        $version = $document->currentVersion;

        $entries = LabDocumentsEntryData::where('lab_id', $labUser->lab_id)
            ->where('document_id', $id)
            ->with(['document', 'documentVersion', 'user'])
            ->get();

        // 🔹 Determine headers
        if ($document->mode === 'create' && $version?->form_fields) {
            $headers = array_keys($version->form_fields);
        } else {
            $headers = ['document']; // For file upload mode
        }

        // 🔹 Build rows
        $rows = $entries->map(function ($entry) use ($headers) {
            $fields = $entry->fields_entry;

            if (is_string($fields)) {
                $fields = json_decode($fields, true) ?? [];
            }

            if (!is_array($fields)) {
                $fields = [];
            }

            $values = collect($headers)->map(function ($key) use ($fields) {
                $value = $fields[$key] ?? null;
                if ($key === 'document' && $value) {
                    $value = Storage::disk('public')->url('documents/' . $value);
                }

                // Handle array values (checkbox/multiselect)
                if (is_array($value)) {
                    return collect($value)->implode(', ');
                }

                return $value;
            });

            return [
                'id' => $entry->id,
                'document_name' => optional($entry->document)->name,
                'document_version' => optional($entry->documentVersion)->version,
                'user' => optional($entry->user)->name,
                'values' => $values,
                'created_at' => $entry->created_at->toDateTimeString(),
            ];
        });

        return response()->json([
            'message' => 'Data entries fetched successfully',
            'headers' => $headers,
            'rows' => $rows
        ], 200);
    }

    public function workflowAction(Request $request,DocumentWorkflowService $workflow) 
    {
        $request->validate([
            'document_version_id' => 'required|exists:document_versions,id',
            'action' => 'required|in:pending,completed,sent_back,rejected',
            'comments' => 'nullable|string',
        ]);

        $state = $workflow->act(
            $request->document_version_id,
            $request->action,
            auth()->id(),
            $request->comments
        );

        return response()->json([
            'success' => true,
            'workflow_state' => $state
        ]);
    }

    public function generateDocumentNumber(Request $request)
    {
        $deptName = trim($request->input('departmentName'));

        if (!$deptName) {
            return response()->json([
                'success' => false,
                'message' => 'Department name is required'
            ], 400);
        }

        $lastNumber = Document::where('number', 'like', $deptName . '-%')
            ->selectRaw("
                MAX(
                    CAST(
                        split_part(
                            number,
                            '-',
                            array_length(string_to_array(number, '-'),1)
                        ) AS INTEGER
                    )
                ) as max_seq
            ")
            ->value('max_seq');

        $newSuffix = $lastNumber ? $lastNumber + 1 : 1;

        $newNumber = $deptName . '-' . $newSuffix;

        return response()->json([
            'success' => true,
            'documentNumber' => $newNumber
        ], 200);
    }

    public function dataEntryTask(Request $request)
    {
        try {
            $ctx = $this->labContext($request);
            $query = Document::with('currentVersion.workflowLogs','currentVersion.amendments', 'category');
            
            if ($ctx['lab_id'] == 0) {
                 $query->where('owner_type', 'super_admin');
            } else {
                $query->where('owner_type', 'lab')
              ->where('owner_id', $ctx['lab_id']);
            }

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
           
            $documents->getCollection()->transform(function ($doc) {
                $doc->versions_count  = $doc->versions()->count();
                $doc->current_vrsn = optional($doc->currentVersion)->full_version;

                return $doc;
            });


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

}
