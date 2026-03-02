<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\SubCategory;
use App\Models\Category;
use App\Models\LabUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\Rule;

class SubCategoryController extends Controller
{

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $ctx = $this->labContext($request);

            $query = SubCategory::with('category');

            if ($ctx['lab_id'] == 0) {
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

            $subCategories = $query->paginate($pageSize, ['*'], 'page', $pageIndex);

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
        $ctx = $this->labContext($request);

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
            $ctx = $this->labContext(request());

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

    public function labMasterSubCategories(Request $request)
    {
        $validated = $request->validate([
            'id'     => ['required', 'integer', 'exists:labs,id'],
            'catId'  => ['required', 'integer', 'exists:categories,id'],
            'key'    => ['nullable', 'string', 'in:all,master'],
        ]);

        $labId      = $validated['id'];
        $categoryId = $validated['catId'];
        $key        = $validated['key'] ?? null;

        $subCategories = SubCategory::query()
            ->with('appendedMaster') // 🔥 prevent N+1
            ->select([
                'id',
                'name',
                'identifier',
                'cat_id',
                'owner_id',
                'owner_type',
                'parent_id'
            ])
            ->where('owner_type', 'lab')
            ->where('owner_id', $labId)
            ->where('cat_id', $categoryId)
            ->whereNull('parent_id')
            ->when($key !== 'all', function ($query) {
                $query->whereDoesntHave('appendedMaster');
            })
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $subCategories,
        ]);
    }

    public function appendLabSubCategoryToMaster(Request $request)
    {
        $validated = $request->validate([
            'lab_subcategory_id'   => ['required', 'exists:sub_categories,id'],
            'force_append_category'=> ['nullable', 'boolean'],
        ]);

        $force = $validated['force_append_category'] ?? false;

        $labSub = SubCategory::where('id', $validated['lab_subcategory_id'])
            ->where('owner_type', 'lab')
            ->whereNull('parent_id')
            ->firstOrFail();

        $labCategory = Category::where('id', $labSub->cat_id)
            ->where('owner_type', 'lab')
            ->whereNull('parent_id')
            ->firstOrFail();

        // 🔎 Check if category already appended
        $masterCategory = Category::where('owner_type', 'super_admin')
            ->where('parent_id', $labCategory->id)
            ->first();

        if (!$masterCategory && !$force) {
            return response()->json([
                'success' => false,
                'needs_category_append' => true,
                'message' => 'Parent category not appended. Do you want to append both?',
            ], 409);
        }

        DB::beginTransaction();

        try {

            // 🔥 Append category first if needed
            if (!$masterCategory) {
                $masterCategory = Category::create([
                    'parent_id'  => $labCategory->id,
                    'name'       => $labCategory->name,
                    'identifier' => $labCategory->identifier,
                    'owner_type' => 'super_admin',
                    'owner_id'   => null,
                ]);
            }

            // 🔎 Prevent duplicate subcategory
            $alreadyExists = SubCategory::where('owner_type', 'super_admin')
                ->where('parent_id', $labSub->id)
                ->exists();

            if ($alreadyExists) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'SubCategory already appended',
                ], 409);
            }

            // 🔥 Append subcategory under MASTER category
            $masterSub = SubCategory::create([
                'name'       => $labSub->name,
                'identifier' => $labSub->identifier,
                'cat_id'     => $masterCategory->id, // VERY IMPORTANT
                'parent_id'  => $labSub->id,
                'owner_type' => 'super_admin',
                'owner_id'   => null,
                'appended_from_lab_id' => $labSub->owner_id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data'    => $masterSub,
            ], 201);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to append subcategory',
            ], 500);
        }
    }
    
}
