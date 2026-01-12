<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\ClauseDocumentLink;
use App\Models\Standard;
use App\Models\Clause;

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
    public function show(string $id)
    {
        $standard = Standard::with([
            'clauses.documents.currentVersion' ,// eager load clauses and related documents
            'clauses.children'          // recursive children will load documents via children() relation

        ])->findOrFail($id);

        return response()->json($standard);
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
