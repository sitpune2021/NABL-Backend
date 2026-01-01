<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\SubCategory;
use App\Models\LabUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SubCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $user = auth()->user();

            $labUser = LabUser::where('user_id', $user->id)->first();

            $query = SubCategory::with('category'); // Eager load category

            if ($labUser) {
                // Only include subcategories whose categories are linked to documents of this lab
                $query->whereHas('category', function ($q) use ($labUser) {
                    $q->whereIn('id', function ($subQuery) use ($labUser) {
                        $subQuery->select('category_id')
                            ->from('documents')
                            ->whereIn('id', function ($q2) use ($labUser) {
                                $q2->select('document_id')
                                    ->from('lab_clause_documents')
                                    ->where('lab_id', $labUser->lab_id);
                            });
                    });
                });
            }

            // Search
            if ($request->filled('query')) {
                $search = strtolower($request->input('query'));

                $query->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(identifier) LIKE ?', ["%{$search}%"])
                        ->orWhereHas('category', function ($q2) use ($search) {
                            $q2->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
                        });
                });
            }

            if ($request->filled('categories')) {
                $categories = $request->input('categories'); // [4, 6, 7]

                $query->whereHas('category', function ($q) use ($categories) {
                    $q->whereIn('id', $categories);
                });
            }

            // Sorting
            $sortKey = $request->input('sort.key', 'id'); // default sort by id
            $sortOrder = $request->input('sort.order', 'asc'); // default ascending

            $allowedSortColumns = ['id', 'name', 'identifier', 'created_at', 'updated_at'];
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

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
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
            'name' => 'required|string|max:255|unique:sub_categories,name',
            'identifier' => 'required|string|max:255|unique:sub_categories,identifier',
            'cat_id' => 'required|exists:categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $category = SubCategory::create($request->only(['cat_id', 'name', 'identifier']));
            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $category
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create category',
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
            $category = SubCategory::findOrFail($id);
            return response()->json([
                'success' => true,
                'data' => $category
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Sub Category not found'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch category',
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
            'name' => 'sometimes|required|string|max:255|unique:sub_categories,name,' . $id,
            'identifier' => 'sometimes|required|string|max:255|unique:sub_categories,identifier,' . $id,
            'cat_id' => 'sometimes|required|exists:categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $category = SubCategory::findOrFail($id);
            $category->update($request->only(['cat_id', 'name', 'identifier']));
            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $category
            ], 200);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Sub Category not found'
            ], 404);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update category',
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
            $category = SubCategory::findOrFail($id);
            $category->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sub Category deleted successfully'
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Sub Category not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete category',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
