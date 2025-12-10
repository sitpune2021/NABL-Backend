<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\DocumentEditor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class DocumentEditorController extends Controller
{
    /**
     * Display a listing of document editors.
     */
    public function index(Request $request)
    {
        try {
            $query = DocumentEditor::query();

            // Search by type or selected_day
            if ($request->filled('query')) {
                $search = $request->input('query');
                $query->where('type', 'LIKE', "%{$search}%")
                      ->orWhere('selected_day', 'LIKE', "%{$search}%");
            }

            // Sorting
            $sortKey = $request->input('sort.key', 'id');
            $sortOrder = $request->input('sort.order', 'asc');

            $allowedSortColumns = ['id', 'type', 'start_date', 'created_at'];
            $allowedSortOrder = ['asc', 'desc'];

            if (in_array($sortKey, $allowedSortColumns) && in_array(strtolower($sortOrder), $allowedSortOrder)) {
                $query->orderBy($sortKey, $sortOrder);
            }

            // Pagination
            $pageIndex = (int) $request->input('pageIndex', 1);
            $pageSize = (int) $request->input('pageSize', 10);

            $editors = $query->paginate($pageSize, ['*'], 'page', $pageIndex);

            return response()->json([
                'success' => true,
                'data' => $editors->items(),
                'total' => $editors->total()
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch document editors',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created document editor.
     */
    public function store(Request $request)
    {
         $validator = Validator::make($request->all(), [
            'documentId' => 'required|exists:documents,id',
            'document' => 'required|array',
            'dataEntrySchedule' => 'nullable|array',
            'frequency' => 'nullable|array',
            'startDate' => 'nullable|date',
            'settings' => 'nullable|array',
        ]);

        if ($request->has('settings')) {
            foreach ($request->input('settings') as $header => $config) {
                if (!isset($config['type']) || !isset($config['validation'])) {
                    return response()->json([
                        'success' => false,
                        'message' => "Each setting must have 'type' and 'validation'. Error at {$header}"
                    ], 422);
                }
            }
        }

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
        $editor = new DocumentEditor();
        $editor->document_id = $request->documentId;
        $editor->document = $request->document;
        $editor->data_entry_schedule = $request->dataEntrySchedule ?? null;
        $editor->frequency = $request->frequency ?? null;
        $editor->start_date = $request->startDate ?? null;
        $editor->settings = $request->settings ?? null; // store as JSON

        $editor->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $editor
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create document editor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified document editor.
     */
    public function show($id)
    {
        try {
            $editor = DocumentEditor::findOrFail($id);
            return response()->json([
                'success' => true,
                'data' => $editor
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Document editor not found'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch document editor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified document editor.
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'document_id' => 'sometimes|exists:documents,id',
            'data_entry_schedule' => 'nullable|array',
            'frequency' => 'nullable|array',
            'item_configs' => 'nullable|array',
            'selected_items' => 'nullable|array',
            'selected_day' => 'nullable|string|max:50',
            'selected_month' => 'nullable|string|max:50',
            'type' => 'sometimes|string|max:50',
            'start_date' => 'nullable|date',
            'document' => 'nullable|array',
            'settings' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $editor = DocumentEditor::findOrFail($id);
            $editor->update($request->all());
            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $editor
            ], 200);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Document editor not found'
            ], 404);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update document editor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified document editor.
     */
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $editor = DocumentEditor::findOrFail($id);
            $editor->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Document editor deleted successfully'
            ], 200);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Document editor not found'
            ], 404);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete document editor',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
