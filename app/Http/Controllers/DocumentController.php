<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Document;
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
            $query = Document::with('editor');

            // Search
            if ($request->filled('query')) {
                $search = $request->input('query');
                $query->where(function ($q) use ($search) {
                    $q->where('labName', 'LIKE', "%{$search}%");
                });
            }

            // Sorting
            $sortKey = $request->input('sort.key', 'id'); // default sort by id
            $sortOrder = $request->input('sort.order', 'asc'); // default ascending

            $allowedSortColumns = ['id', 'labName','created_at', 'updated_at'];
            $allowedSortOrder = ['asc', 'desc'];

            if (in_array($sortKey, $allowedSortColumns) && in_array(strtolower($sortOrder), $allowedSortOrder)) {
                $query->orderBy($sortKey, $sortOrder);
            }

            // Pagination
            $pageIndex = (int) $request->input('pageIndex', 1);
            $pageSize = (int) $request->input('pageSize', 10);

            $categories = $query->paginate($pageSize, ['*'], 'page', $pageIndex);


            return response()->json([
                'status' => true,
                'data' => $categories->items(),
                'total' => $categories->total()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch categories',
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
            'labName' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'department' => 'required|array|min:1',
            'department.*' => 'integer|distinct', // each department ID should be integer and unique
            'header' => 'nullable|integer',
            'footer' => 'nullable|integer',
            'amendmentNo' => 'nullable|string|max:50',
            'amendmentDate' => 'nullable|date',
            'approvedBy' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:100',
            'copyNo' => 'nullable|string|max:50',
            'documentName' => 'required|string|max:255',
            'documentNo' => 'nullable|string|max:50',
            'durationValue' => 'nullable|numeric',
            'durationUnit' => 'nullable|string|max:50',
            'effectiveDate' => 'nullable|date',
            'frequency' => 'nullable|string|max:50',
            'issueDate' => 'nullable|date',
            'issuedBy' => 'nullable|string|max:255',
            'issuedNo' => 'nullable|string|max:50',
            'preparedBy' => 'nullable|string|max:255',
            'preparedByDate' => 'nullable|date',
            'quantityPrepared' => 'nullable|integer|min:0',
            'status' => 'nullable|string|in:Controlled,Uncontrolled',
            'time' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $department = Document::create($request->all());
            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $department
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create department',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $department = Document::findOrFail($id);
            return response()->json([
                'success' => true,
                'data' => $department
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch department',
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
               'labName' => 'sometimes|string|max:255',
                'location' => 'sometimes|string|max:255',
                'department' => 'sometimes|array|min:1',
                'department.*' => 'integer|distinct',
                'header' => 'nullable|integer',
                'footer' => 'nullable|integer',
                'amendmentNo' => 'nullable|string|max:50',
                'amendmentDate' => 'nullable|date',
                'approvedBy' => 'nullable|string|max:255',
                'category' => 'nullable|string|max:100',
                'copyNo' => 'nullable|string|max:50',
                'documentName' => 'sometimes|string|max:255',
                'documentNo' => 'nullable|string|max:50',
                'durationValue' => 'nullable|numeric',
                'durationUnit' => 'nullable|string|max:50',
                'effectiveDate' => 'nullable|date',
                'frequency' => 'nullable|string|max:50',
                'issueDate' => 'nullable|date',
                'issuedBy' => 'nullable|string|max:255',
                'issuedNo' => 'nullable|string|max:50',
                'preparedBy' => 'nullable|string|max:255',
                'preparedByDate' => 'nullable|date',
                'quantityPrepared' => 'nullable|integer|min:0',
                'status' => 'nullable|string|in:Controlled,Uncontrolled',
                'time' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $department = Document::findOrFail($id);
            $department->update($request->all());
            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $department
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
                'message' => 'Failed to update department',
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
