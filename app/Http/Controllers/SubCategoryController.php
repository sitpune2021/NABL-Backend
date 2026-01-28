<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\SubCategory;
use App\Models\LabUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\Rule;

class SubCategoryController extends Controller
{
    private function labContext(): array
    {
        $user = auth()->user();
        $labUser = LabUser::where('user_id', $user->id)->first();

        return [
            'lab_id'     => $labUser?->lab_id,
            'owner_type' => $labUser ? 'lab' : 'super_admin',
            'owner_id'   => $labUser?->lab_id,
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $ctx = $this->labContext();

            $query = SubCategory::with('category');

            if ($ctx['lab_id'] == null) {
                $query->SuperAdmin();
            } else {
                $query->ForLab($ctx['lab_id']);
            }

            // Search
            if ($request->filled('query')) {
                $search = strtolower($request->input('query'));

                $query->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                      ->orWhereRaw('LOWER(identifier) LIKE ?', ["%{$search}%"])
                      ->orWhereHas('category', function ($q2) use ($search) {
                          $q2->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
                          $q2->whereRaw('LOWER(identifier) LIKE ?', ["%{$search}%"]);
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
            $sortKey   = $request->input('sort.key', 'id');
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
                'success' => true,
                'data' => $subCategories->items(),
                'total' => $subCategories->total()
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sub categories',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $ctx = $this->labContext();

        $validator = Validator::make($request->all(), [
            'cat_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'identifier' => [
                'required',
                Rule::unique('sub_categories')->where(fn ($q) =>
                    $q->where('cat_id', $request->cat_id)
                      ->where('owner_type', $ctx['owner_type'])
                      ->where('owner_id', $ctx['owner_id'])
                      ->whereNull('deleted_at')
                ),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $subCategory = SubCategory::create([
                'cat_id'     => $request->cat_id,
                'name'       => $request->name,
                'identifier' => $request->identifier,
                'owner_type' => $ctx['owner_type'],
                'owner_id'   => $ctx['owner_id'],
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $subCategory
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create sub category',
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
            $ctx = $this->labContext();

            $subCategory = SubCategory::with('category')
                ->accessible($ctx['lab_id'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $subCategory
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Sub Category not found'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sub category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource.
     */
    public function update(Request $request, string $id)
    {
        $subCategory = SubCategory::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'cat_id' => ['sometimes', 'exists:categories,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'identifier' => [
                'sometimes',
                Rule::unique('sub_categories')->ignore($id)->where(fn ($q) =>
                    $q->where('cat_id', $request->cat_id ?? $subCategory->cat_id)
                      ->where('owner_type', $subCategory->owner_type)
                      ->where('owner_id', $subCategory->owner_id)
                      ->whereNull('deleted_at')
                ),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $subCategory->update(
                $request->only(['cat_id', 'name', 'identifier'])
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $subCategory,
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update sub category',
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
            SubCategory::findOrFail($id)->delete();

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
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete sub category',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
