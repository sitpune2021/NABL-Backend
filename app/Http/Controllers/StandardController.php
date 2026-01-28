<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Standard;
use App\Models\LabUser;
use App\Models\Clause;
use App\Models\ClauseDocumentLink;

class StandardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            $labUser = LabUser::where('user_id', $user->id)->first();
            // Base query with eager loading of top-level clauses and their children
            $query = Standard::with(['clauses.children'])->withExists([
                'clauseDocumentLinks as is_document_link'
            ]);

            // Lab-based filter: only standards linked to lab documents
            if ($labUser) {
                $query->whereHas('clauseDocumentLinks', function ($q) use ($labUser) {
                    $q->whereIn('document_id', function ($subQuery) use ($labUser) {
                        $subQuery->select('document_id')
                                ->from('lab_clause_documents')
                                ->where('lab_id', $labUser->lab_id);
                    });
                });
            }

            // Search by name or UUID
            if ($request->filled('query')) {
                $search = $request->input('query');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('uuid', 'LIKE', "%{$search}%")
                    ->orWhereHas('clauses', function ($q2) use ($search) {
                        $q2->where('title', 'LIKE', "%{$search}%");
                    });
                });
            }

            // Filter by name
            if ($request->filled('name')) {
                $query->where('name', 'LIKE', '%' . $request->input('name') . '%');
            }

            // Filter by status
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            // Filter by publish date range
            if ($request->filled('publish_from')) {
                $query->whereDate('created_at', '>=', $request->input('publish_from'));
            }
            if ($request->filled('publish_to')) {
                $query->whereDate('created_at', '<=', $request->input('publish_to'));
            }

            // Sorting
            $sortKey = $request->input('sort.key', 'id');
            $sortOrder = $request->input('sort.order', 'asc');
            $allowedSortColumns = ['id', 'name', 'uuid', 'status', 'created_at', 'updated_at'];
            $allowedSortOrder = ['asc', 'desc'];

            if (in_array($sortKey, $allowedSortColumns) && in_array(strtolower($sortOrder), $allowedSortOrder)) {
                $query->orderBy($sortKey, $sortOrder);
            }

            // Pagination
            $pageIndex = (int) $request->input('pageIndex', 1);
            $pageSize = (int) $request->input('pageSize', 10);

            $standards = $query->paginate($pageSize, ['*'], 'page', $pageIndex);

            return response()->json([
                'status' => true,
                'data' => $standards->items(),
                'total' => $standards->total()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch standards',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'uuid' => 'required|uuid',
            'name' => 'required|string',
            'clauses' => 'required|array',
        ]);

        DB::beginTransaction();

        try {
            // Create Standard (version 1.0 for first time)
            $standard = Standard::create([
                'uuid' => $data['uuid'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'version_major' => 1,
                'version_minor' => 0,
                'changes_type' => 'minor',
                'status' => 'draft',
                'is_current' => true,
                'created_by' => auth()->id()
            ]);

            // Recursive store clauses
            foreach ($data['clauses'] as $clause) {
                $this->storeClause($clause, $standard->id, null);
            }

            DB::commit();

            return response()->json($standard->load('clauses.children'), 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to create standard: '.$e->getMessage(), ['stack' => $e->getTraceAsString()]);
            return response()->json([
                'message' => 'Failed to create standard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function storeClause($clause, $standardId, $parentId)
    {
        $newClause = Clause::create([
            'standard_id' => $standardId,
            'parent_id' => $parentId,
            'title' => $clause['title'],
            'message' => $clause['message'] ?? null,
            'note' => $clause['note'] ?? false,
            'is_child' => isset($clause['children']) && count($clause['children']) > 0,
            'numbering_type' => $clause['numbering_type'] ?? 'numerical',
            'numbering_value' => $clause['numbering_value'] ?? null,
            'sort_order' => $clause['sortOrder'] ?? 0
        ]);

        // Store child clauses recursively
        if (isset($clause['children']) && is_array($clause['children'])) {
            foreach ($clause['children'] as $child) {
                $this->storeClause($child, $standardId, $newClause->id);
            }
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Standard::with('clauses.children')->findOrFail($id);
    }


    public function clauses(string $id)
    {
        $standard = Standard::with('clauses.children')->findOrFail($id);

        // Map clauses for frontend form
        $standardClauses = $this->mapClausesForForm($standard->clauses);

        return response()->json([
            'id' => $standard->id,
            'uuid' => $standard->uuid,
            'name' => $standard->name,
            'version_major' => $standard->version_major,
            'version_minor' => $standard->version_minor,
            'status' => $standard->status,
            'clauses' => $standardClauses,
        ]);
    }


    protected function mapClausesForForm($clauses)
    {
        return $clauses->map(function ($clause) {
            return [
                'id' => $clause->id,
                'parentId' => $clause->parent_id,
                'notes' => '',
                'clause_documents_tagging' => $clause->note ? [
                    [
                        'category_id' => '',
                        'document' => [
                            'id' => '',
                            'version_id' => '',
                            'version' => '',
                        ],
                    ]
                ] : [],
                'children' => $clause->children->isNotEmpty()
                    ? $this->mapClausesForForm($clause->children)
                    : [],
            ];
        });
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function currentStandards(Request $request)
    {
        $standardId = $request->standard_id;
        $standards = Standard::with([
            'clauses.documents' ,// eager load clauses and related documents
            'clauses.children'          // recursive children will load documents via children() relation

        ])->when($standardId, function ($q) use ($standardId) {
            $q->where('id', $standardId);
        })->first();

        return response()->json([
            'success' => true,
            'data' => $standards
        ]);
    }
}
