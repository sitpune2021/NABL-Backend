<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\ClauseDocumentLink;

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
            'standard_clauses.*.clause_documents_tagging' => 'nullable|array'
        ]);

        DB::beginTransaction();

        try {
            $links = [];

            foreach ($request->standard_clauses as $clause) {
                $clause_id = $clause['clause_id'];
                $documents = $clause['clause_documents_tagging'] ?? [];

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
    public function show(string $id)
    {
        
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
}
