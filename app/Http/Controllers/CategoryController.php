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

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $ctx = $this->labContext($request);
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
        $ctx = $this->labContext($request);

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
            $ctx = $this->labContext(request());

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

    public function labMasterCategories(Request $request)
    {
        $labId = $request->query('lab_id');

        if (!$labId) {
            return response()->json([
                'success' => false,
                'message' => 'lab_id is required'
            ], 422);
        }

        $categories = Category::query()
            ->where('owner_type', 'lab')
            ->where('owner_id', $labId)
            ->whereNull('parent_id')
             ->whereDoesntHave('overrides', function ($q) {
                $q->where('owner_type', 'super_admin');
            })
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }


    public function appendLabCategoryToMaster(Request $request)
{
    $validator = Validator::make($request->all(), [
        'lab_category_id' => ['required', 'exists:categories,id'],
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors(),
        ], 422);
    }

    $labCategory = Category::where('id', $request->lab_category_id)
        ->where('owner_type', 'lab')
        ->whereNull('parent_id')
        ->firstOrFail();

    $alreadyExists = Category::where('owner_type', 'super_admin')
        ->where('parent_id', $labCategory->id)
        ->exists();

    if ($alreadyExists) {
        return response()->json([
            'success' => false,
            'message' => 'Category already appended to master',
        ], 409);
    }

    DB::beginTransaction();
    try {
        $masterCategory = Category::create([
            'parent_id'  => $labCategory->id,
            'name'       => $labCategory->name,
            'identifier' => $labCategory->identifier,
            'owner_type' => 'super_admin',
            'owner_id'   => null,
            'appended_from_lab_id' => $labCategory->owner_id,
        ]);

        DB::commit();

        return response()->json([
            'success' => true,
            'data' => $masterCategory,
        ], 201);
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Failed to append category',
        ], 500);
    }
}
}
