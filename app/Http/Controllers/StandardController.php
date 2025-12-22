<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Standard;
use App\Models\Clause;

class StandardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index() {
        return Standard::with('clauses.children')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'uuid' => 'required|uuid',
            'name' => 'required|string',
            'standards' => 'required|array',
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
                'status' => 'published',
                'is_current' => true,
                'created_by' => auth()->id()
            ]);

            // Recursive store clauses
            foreach ($data['standards'] as $clause) {
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
            'numbering_type' => $clause['numberingType'] ?? 'numerical',
            'numbering_value' => $clause['numberingValue'] ?? null,
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

      public function currentStandards()
    {
        $standards = Standard::with([
            'clauses.documents' ,// eager load clauses and related documents
            'clauses.children'          // recursive children will load documents via children() relation

        ])->current()->first();

        return response()->json([
            'success' => true,
            'data' => $standards
        ]);
    }
}
