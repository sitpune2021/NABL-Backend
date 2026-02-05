<?php

namespace App\Http\Controllers;

use Exception;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Models\{Category, LabUser};

class CategoryController extends Controller
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
            $query = Category::query();

            if ($ctx['lab_id'] == null) {
                $query->SuperAdmin();
            } else {
                $query->ForLab($ctx['lab_id']);
            }
            if ($request->filled('query')) {
                $search = strtolower($request->input('query'));
                $query->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(identifier) LIKE ?', ["%{$search}%"]);
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
                'success' => true,
                'data' => $categories->items(),
                'total' => $categories->total()
            ], 200);
        } catch (Exception $e) {
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
        $ctx = $this->labContext();

        $validator = Validator::make($request->all(), [
            'name' => ['required'],
            'identifier' => ['required'],
            'parent_id' => [
                'nullable',
                'exists:categories,id',
                // only labs can override
                fn ($attr, $value, $fail) =>
                    $value && $ctx['owner_type'] !== 'lab'
                        ? $fail('Only lab users can override master categories')
                        : null
            ],
            'name' => [
                'required',
                Rule::unique('categories')->where(fn ($q) =>
                    $q->where('owner_type', $ctx['owner_type'])
                      ->where('owner_id', $ctx['owner_id'])
                      ->whereNull('deleted_at')
                ),
            ],
            'identifier' => [
                'required',
                Rule::unique('categories')->where(fn ($q) =>
                    $q->where('owner_type', $ctx['owner_type'])
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
            $category = Category::create([
                'parent_id'  => $request->parent_id,
                'name'       => $request->name,
                'identifier' => $request->identifier,
                'owner_type' => $ctx['owner_type'],
                'owner_id'   => $ctx['owner_id'],
            ]);

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
            $ctx = $this->labContext();

            $category = Category::accessible($ctx['lab_id'])->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $category
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found',
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
        $category = Category::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => [
                'sometimes',
                Rule::unique('categories')->ignore($id)->where(fn ($q) =>
                    $q->where('owner_type', $category->owner_type)
                      ->where('owner_id', $category->owner_id)
                      ->whereNull('deleted_at')
                ),
            ],
            'identifier' => [
                'sometimes',
                Rule::unique('categories')->ignore($id)->where(fn ($q) =>
                    $q->where('owner_type', $category->owner_type)
                      ->where('owner_id', $category->owner_id)
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
            $category->update($request->only(['name', 'identifier']));
            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $category
                ], 200);
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
            $category = Category::findOrFail($id);

            if ($category->is_master) {
                return response()->json([
                    'success' => false,
                    'message' => 'Master categories cannot be deleted',
                ], 403);
            }

            $category->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Category deleted successfully'
            ], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete category',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
