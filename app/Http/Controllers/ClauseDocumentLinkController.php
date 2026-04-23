<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\ClauseDocumentLink;
use App\Models\Standard;
use App\Models\Clause;
use App\Models\Assignment;
use Illuminate\Support\Facades\Validator;
use App\Models\Document;
use App\Models\Lab;

class ClauseDocumentLinkController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */

    public function store(Request $request)
    {
        $validator = $this->validateStandardClauses($request);
        $ctx = $this->labContext($request);
        $ownerType = $ctx['owner_type'];
        $ownerId   = $ctx['owner_id'];
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $links = [];

            foreach ($request->standard_clauses as $clause) {
                $clause_id = $clause['clause_id'];
                $documents = $clause['clause_documents_tagging'] ?? [];
                $notes = $clause['notes'] ?? null;

                $Clause = Clause::findOrFail($clause_id);
                if ($notes !== null) {
                    $Clause->update(['note_message' => $notes]);
                }

                foreach ($documents as $doc) {
                    if (isset($doc['documents']['id'])) {
                        $links[] = [
                            'standard_id' => $request->standard_id,
                            'clause_id' => $clause_id,
                            'document_id' => $doc['documents']['id'],
                            'document_version_id' => $doc['documents']['version_id'] ?? null,
                                    'owner_type' => $ownerType,
                                'owner_id' => $ownerId,

                        ];
                    }
                }
            }

            if (!empty($links)) {
                ClauseDocumentLink::insert($links);
            }

            Standard::where('id', $request->standard_id)
            ->where('status', 'draft')
            ->update([
                'status' => 'published'
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Clause document links saved successfully',
                'links_saved' => count($links)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to save clause document links: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to save clause document links',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, Request $request)
    {
        $ctx = $this->labContext($request);

        $ownerType = $ctx['owner_type'];
        $ownerId   = $ctx['owner_id'];
        $locationId   = $ctx['location_id'];
        $departmentId   = $ctx['department_id'];
        
        // ✅ STEP 1: Get user assigned tasks (only if bysingle)
        $clauseIds = collect();
        $documentIds = collect();

        if ($request->type === 'bysingle') {
            $userAccess = \App\Models\LabUserAccess::with([
                'location',
                'department'
            ])
            ->whereHas('labUser', function ($q) {
                $q->where('user_id', Auth::id());
            })
            ->get();

            $locationIds = $userAccess->pluck('location_id')->filter()->unique();

            $departmentIds = $userAccess->pluck('department.department_id')->filter()->unique()->values();

            $tasks = Assignment::where(function ($q) use ($locationIds, $departmentIds, $locationId, $departmentId) {

                // ✅ USER LEVEL
                $q->where('user_id',Auth::id())

                // ✅ DEPARTMENT LEVEL
                ->orWhere(function ($q2) use ($departmentIds, $departmentId) {
                    $q2->whereNull('user_id')
                    ->whereIn('department_id', $departmentIds)
                    ->where('department_id', $departmentId);
                })

                // ✅ LOCATION LEVEL
                ->orWhere(function ($q3) use ($locationIds, $locationId) {
                    $q3->whereNull('user_id')
                    ->whereNull('department_id')
                    ->whereIn('location_id', $locationIds)
                    ->where('location_id', $locationId);

                });

            })->get();

            $clauseIds = $tasks->pluck('clause_id')->unique();
            $documentIds = $tasks->pluck('document_id')->unique();
        }

        $standard = Standard::with([
                'clauses' => function ($q) use ($ownerType, $ownerId) {
                    $q->whereNull('parent_id')
                    ->orderBy('sort_order')
                    ->with($this->clauseWithRelations($ownerType, $ownerId));
                }
            ])->findOrFail($id);

        // ✅ STEP 2: Filter only assigned data
        if ($request->type === 'bysingle') {
            $this->filterByUserAssignments($standard->clauses, $clauseIds, $documentIds);
        }
    

        return response()->json($standard);
    }

    /**
     * ✅ Filter clauses & documents based on logged-in user assignment
     */
    private function filterByUserAssignments($clauses, $clauseIds, $documentIds)
    {
        foreach ($clauses as $key => $clause) {

            // ❌ Remove clause if not assigned
            if (!$clauseIds->contains($clause->id)) {
                unset($clauses[$key]);
                continue;
            }

            // ✅ Filter documents inside clause
            $filteredDocs = $clause->documents->filter(function ($doc) use ($documentIds) {
                return $documentIds->contains($doc->id);
            });

            $clause->setRelation('documents', $filteredDocs->values());

            // ✅ Recursive for children
            if ($clause->children && $clause->children->count()) {
                $this->filterByUserAssignments($clause->children, $clauseIds, $documentIds);
            }
        }
    }

    private function clauseWithRelations($ownerType, $ownerId)
    {
        return [
            'documentLinks' => function ($q) use ($ownerType, $ownerId) {
                if ($ownerType === 'super_admin') {
                    $q->superAdmin();
                } else {
                    $q->forLab($ownerId);
                }
                $q->with('document.currentVersion');
            },

            'children' => function ($q) use ($ownerType, $ownerId) {
                $q->orderBy('sort_order')
                ->with($this->clauseWithRelations($ownerType, $ownerId)); // 🔁 RECURSION
            }
        ];
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validator = $this->validateStandardClauses($request);
        dd($request->all());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }
            $ctx = $this->labContext($request);
            $ownerType = $ctx['owner_type'];
            $ownerId   = $ctx['owner_id'];

        DB::beginTransaction();

        try {
            if ((int) $id !== (int) $request->standard_id) {
                throw new \Exception('Standard ID mismatch');
            }

            ClauseDocumentLink::where('standard_id', $id)->delete();

            $links = [];

            foreach ($request->standard_clauses as $clause) {
                $clauseId = $clause['clause_id'];
                $documents = $clause['clause_documents_tagging'] ?? [];
                $notes = $clause['notes'] ?? null;

                //Update clause notes
                if ($notes !== null) {
                    Clause::where('id', $clauseId)
                        ->update(['note_message' => $notes]);
                }

                //Rebuild document links
                foreach ($documents as $doc) {
                    if (!empty($doc['documents']['id'])) {
                        $links[] = [
                            'standard_id' => $id,
                            'clause_id' => $clauseId,
                            'document_id' => $doc['documents']['id'],
                            'document_version_id' => $doc['documents']['version_id'] ?? null,
                            'owner_type' => $ownerType,
                            'owner_id' => $ownerId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
            }

            //Bulk insert
            if (!empty($links)) {
                ClauseDocumentLink::insert($links);
            }

            DB::commit();

            return response()->json([
                'message' => 'Standard clauses updated successfully',
                'links_saved' => count($links),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to update standard clauses', [
                'standard_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to update standard clauses',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    private function validateStandardClauses(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'standard_id' => ['required', 'exists:standards,id'],
            'standard_clauses' => ['required', 'array'],
            'standard_clauses.*.clause_id' => ['required', 'exists:clauses,id'],
            'standard_clauses.*.clause_documents_tagging' => ['nullable', 'array'],
            'standard_clauses.*.notes' => ['nullable', 'string'],
        ]);

        $validator->after(function ($validator) use ($request) {

            $clausesData = collect($request->standard_clauses);

            $clauseIds = $clausesData->pluck('clause_id')->filter()->unique();

            $documentIds = $clausesData
                ->pluck('clause_documents_tagging')
                ->flatten(1)
                ->pluck('documents.id')
                ->filter()
                ->unique();

            $clauses = Clause::whereIn('id', $clauseIds)->pluck('title', 'id');
            $documents = Document::whereIn('id', $documentIds)->pluck('name', 'id');

            foreach ($clausesData as $index => $clause) {
                $clauseId = $clause['clause_id'] ?? null;
                $clauseName = $clauses[$clauseId] ?? 'Unknown Clause';

                $seenDocs = [];

                foreach ($clause['clause_documents_tagging'] ?? [] as $docIndex => $doc) {
                    $docId = $doc['documents']['id'] ?? null;

                    if (!$docId) continue;

                    if (isset($seenDocs[$docId])) {
                        $docName = $documents[$docId] ?? 'Unknown Document';

                        $validator->errors()->add(
                            "standard_clauses.$index.clause_documents_tagging.$docIndex",
                            "Duplicate document \"{$docName}\" in clause: {$clauseName}"
                        );
                    }

                    $seenDocs[$docId] = true;
                }
            }
        });

        return $validator;
    }

    public function flatDocuments(Request $request)
    {
        try {
            $ctx = $this->labContext($request);

            $ownerType = $ctx['owner_type'];
            $ownerId   = $ctx['owner_id'];

            $lab = Lab::findOrFail($ownerId);

            $locationId   = $ctx['location_id'];
            $departmentId   = $ctx['department_id'];
            
            // ✅ STEP 1: Get user assigned tasks (only if bysingle)
            $clauseIds = collect();
            $documentIds = collect();

            if ($request->type === 'bysingle') 
            {
                $userAccess = \App\Models\LabUserAccess::with([  'location', 'department'])->whereHas('labUser', function ($q) {$q->where('user_id', Auth::id());})->get();

                $locationIds = $userAccess->pluck('location_id')->filter()->unique();

                $departmentIds = $userAccess->pluck('department.department_id')->filter()->unique()->values();

                $tasks = Assignment::where(function ($q) use ($locationIds, $departmentIds, $locationId, $departmentId) {

                    // ✅ USER LEVEL
                    $q->where('user_id',Auth::id())

                    // ✅ DEPARTMENT LEVEL
                    ->orWhere(function ($q2) use ($departmentIds, $departmentId) {
                        $q2->whereNull('user_id')
                        ->whereIn('department_id', $departmentIds)
                        ->where('department_id', $departmentId);
                    })

                    // ✅ LOCATION LEVEL
                    ->orWhere(function ($q3) use ($locationIds, $locationId) {
                        $q3->whereNull('user_id')
                        ->whereNull('department_id')
                        ->whereIn('location_id', $locationIds)
                        ->where('location_id', $locationId);

                    });

                })->get();

                $clauseIds = $tasks->pluck('clause_id')->unique();
                $documentIds = $tasks->pluck('document_id')->unique();
            }

            $pageIndex = (int) $request->input('pageIndex', 1);
            $pageSize  = (int) $request->input('pageSize', 10);

            $query = ClauseDocumentLink::query()
                ->with([
                    'clause.parentRecursive', // ✅ important
                    'clause:id,title,parent_id,numbering_value',
                    'document:id,name,number,status',
                    'document.currentVersion'
                ])
                ->where('standard_id', $lab->standard_id)
                ->when($ownerType === 'super_admin', fn($q) => $q->superAdmin())
                ->when($ownerType === 'lab', fn($q) => $q->forLab($ownerId))
                ->when($request->type === 'bysingle', function ($q) use ($clauseIds, $documentIds) {
                    $q->whereIn('clause_id', $clauseIds)
                    ->whereIn('document_id', $documentIds);
                });

            // ✅ PAGINATE (DB level)
            $links = $query->paginate($pageSize, ['*'], 'page', $pageIndex);

            $data = collect($links->items())->values()->map(function ($link, $index) use ($links) {

                $doc = $link->document;
                $version = $doc?->currentVersion;

                return [
                    'sr'       => str_pad($links->firstItem() + $index, 4, '0', STR_PAD_LEFT),
                    'clause'   => trim($link->clause->full_number . ' ' . $link->clause->title),
                    'document' => $doc?->name,
                    'id'       => $doc?->id,
                    'status'   => $doc?->status,
                    'number'   => $doc?->number,
                    'version'  => $version?->full_version,
                    'schedule' => $version?->schedule['type'] ?? null,
                ];
            });

            return response()->json([
                'success'   => true,
                'data'      => $data,
                'total'     => $links->total(),
                'pageIndex' => $links->currentPage(),
                'pageSize'  => $links->perPage(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch flat documents',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    private function buildClauseNumber($clause)
    {
        $numbers = [];

        while ($clause) {
            if (!empty($clause->numbering_value)) {
                array_unshift($numbers, $clause->numbering_value);
            }
            $clause = $clause->parent;
        }

        return implode('.', $numbers);
    }

}
