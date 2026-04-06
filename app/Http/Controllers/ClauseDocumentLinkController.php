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
        $request->validate([
            'standard_id' => 'required|exists:standards,id',
            'standard_clauses' => 'required|array',
            'standard_clauses.*.clause_id' => 'required|exists:clauses,id',
            'standard_clauses.*.clause_documents_tagging' => 'nullable|array',
            'standard_clauses.*.notes' => 'nullable|string',
        ]);

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

        $with = [
            'clauses.children'
        ];

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

            $departmentIds = $userAccess->pluck('lab_location_department_id')
                ->filter()
                ->unique();

            $tasks = Assignment::where(function ($q) use ($locationIds, $departmentIds) {

                // ✅ USER LEVEL
                $q->where('user_id',Auth::id())

                // ✅ DEPARTMENT LEVEL
                ->orWhere(function ($q2) use ($departmentIds) {
                    $q2->whereNull('user_id')
                    ->whereIn('department_id', $departmentIds);
                })

                // ✅ LOCATION LEVEL
                ->orWhere(function ($q3) use ($locationIds) {
                    $q3->whereNull('user_id')
                    ->whereNull('department_id')
                    ->whereIn('location_id', $locationIds);
                });

            })->get();

            $clauseIds = $tasks->pluck('clause_id')->unique();
            $documentIds = $tasks->pluck('document_id')->unique();
        }

        // ✅ LAB CONTEXT
        if ($ctx['owner_type'] === 'lab') {

            $with['clauses.documents.labVersion'] = function ($q) use ($ctx) {
                $q->where('owner_id', $ctx['owner_id']);
            };

            $with['clauses.documents.labVersion.currentVersion'] = function ($q) {
                $q->where('is_current', true);
            };

            $with['clauses.children.documents.labVersion'] = function ($q) use ($ctx) {
                $q->where('owner_id', $ctx['owner_id']);
            };

            $with['clauses.children.documents.labVersion.currentVersion'] = function ($q) {
                $q->where('is_current', true);
            };

        } else {

            // ✅ NORMAL (super_admin)
            $with[] = 'clauses.documents.currentVersion';
            $with[] = 'clauses.children.documents.currentVersion';
        }

        $standard = Standard::with($with)->findOrFail($id);

        // ✅ IMPORTANT: Replace documents with lab version
        if ($ctx['owner_type'] === 'lab') {
            $this->replaceWithLabDocuments($standard->clauses);
        }

        // ✅ STEP 2: Filter only assigned data
        if ($request->type === 'bysingle') {
            $this->filterByUserAssignments($standard->clauses, $clauseIds, $documentIds);
        }

        return response()->json($standard);
    }

    private function replaceWithLabDocuments($clauses)
    {
        foreach ($clauses as $clause) {

            $newDocs = [];

            foreach ($clause->documents as $doc) {
                if ($doc->labVersion) {
                    $newDocs[] = $doc->labVersion;
                } else {
                    $newDocs[] = $doc;
                }
            }

            $clause->setRelation('documents', collect($newDocs));

            if ($clause->children && $clause->children->count()) {
                $this->replaceWithLabDocuments($clause->children);
            }
        }
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

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'standard_id' => 'required|exists:standards,id',
            'standard_clauses' => 'required|array',
            'standard_clauses.*.clause_id' => 'required|exists:clauses,id',
            'standard_clauses.*.clause_documents_tagging' => 'nullable|array',
            'standard_clauses.*.notes' => 'nullable|string',
        ]);

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
                            'document_version_id' =>
                                $doc['documents']['version_id'] ?? null,
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
}
