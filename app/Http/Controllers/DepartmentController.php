<?php

namespace App\Http\Controllers;

use Exception;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Models\{LabUser, Department};

class DepartmentController extends Controller
{

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $ctx = $this->labContext($request);
            $query = Department::query()->where('status', 'completed');

            if ($ctx['lab_id'] == 0) {
                 $query->with('lab')->SuperAdmin();
            } else {
                $query->ForLab($ctx['lab_id']);
                if($ctx['department_id']){
                    $query->where('id', $ctx['department_id']);
                }
            }

            // Search
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

            $departments = $query->paginate($pageSize, ['*'], 'page', $pageIndex);
            $data = collect($departments->items())->addSerial($departments->firstItem());

            return response()->json([
                'status' => true,
                'data' => $data,
                'total' => $departments->total()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch departments',
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
                'exists:departments,id',
                fn($attr, $value, $fail) =>
                $value && $ctx['owner_type'] !== 'lab'
                    ? $fail('Only lab users can override master departments')
                    : null
            ],
            'name' => [
                'required',
                Rule::unique('departments')->where(
                    fn($q) =>
                    $q->where('owner_type', $ctx['owner_type'])
                        ->where('owner_id', $ctx['owner_id'])
                        ->whereNull('deleted_at')
                ),
            ],
            'identifier' => [
                'required',
                Rule::unique('departments')->where(
                    fn($q) =>
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
            $department = Department::create([
                'parent_id'  => $request->parent_id,
                'name'       => $request->name,
                'identifier' => $request->identifier,
                'owner_type' => $ctx['owner_type'],
                'owner_id'   => $ctx['owner_id'],
                'status'     => 'completed',
            ]);
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
            $ctx = $this->labContext(request());
            $department = Department::accessible($ctx['lab_id'])->findOrFail($id);
            return response()->json([
                'success' => true,
                'data' => $department
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found'
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
        $department = Department::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => [
                'sometimes',
                Rule::unique('departments')->ignore($id)->where(
                    fn($q) =>
                    $q->where('owner_type', $department->owner_type)
                        ->where('owner_id', $department->owner_id)
                        ->whereNull('deleted_at')
                ),
            ],
            'identifier' => [
                'sometimes',
                Rule::unique('departments')->ignore($id)->where(
                    fn($q) =>
                    $q->where('owner_type', $department->owner_type)
                        ->where('owner_id', $department->owner_id)
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
            $department->update($request->only(['name', 'identifier']));
            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $department
            ], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Department not found'
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
            $department = Department::findOrFail($id);
            if ($department->is_master) {
                return response()->json([
                    'success' => false,
                    'message' => 'Master department cannot be deleted',
                ], 403);
            }

            $department->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Department deleted successfully'
            ], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Department not found'
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

    public function labMasterDepartments(Request $request)
            {
            $validated = $request->validate([
                'id'  => ['required', 'integer', 'exists:labs,id'],
                'start_date' => ['nullable', 'date'],
                'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
                'key' => ['nullable', 'string', 'in:all,master']
            ]);

            $labId = $validated['id'];
            $key   = $validated['key'] ?? null;

            $query = Department::query()
                ->with('appendedMaster')
                ->select([
                    'id',
                    'name',
                    'identifier',
                    'owner_id',
                    'owner_type',
                    'parent_id',
                    'created_at'
                ])
                ->where('owner_type', 'lab')
                ->where('owner_id', $labId)
                ->whereNull('parent_id');

            if (!empty($validated['start_date']) && !empty($validated['end_date'])) {
                $query->whereBetween('created_at', [
                    $validated['start_date'] . ' 00:00:00',
                    $validated['end_date'] . ' 23:59:59'
                ]);
            }

            if ($key !== 'all') {
                $query->whereDoesntHave('appendedMaster');
            }

            $departments = $query
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $departments
        ]);
    }

    public function appendLabDepartmentToMaster(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lab_department_id' => ['required', 'exists:departments,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $labDepartment = Department::where('id', $request->lab_department_id)
            ->where('owner_type', 'lab')
            ->whereNull('parent_id')
            ->firstOrFail();

        $alreadyExists = Department::where('owner_type', 'super_admin')
            ->where('parent_id', $labDepartment->id)
            ->exists();

        if ($alreadyExists) {
            return response()->json([
                'success' => false,
                'message' => 'Department already synced',
            ], 409);
        }

        DB::beginTransaction();
        try {
            $masterDepartment = Department::create([
                'parent_id'      => $labDepartment->id,
                'name'           => $labDepartment->name,
                'identifier'     => $labDepartment->identifier,
                'owner_type'     => 'super_admin',
                'owner_id'       => 0,
                'status'         => 'pending',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $masterDepartment,
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to append department',
            ], 500);
        }
    }
    public function pendingDepartments()
    {
        $departments = Department::with('lab')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $departments
        ]);
    }
    public function approveDepartments(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['exists:departments,id']
        ]);

        Department::whereIn('id', $validated['ids'])
            ->update(['status' => 'completed']);

        return response()->json([
            'success' => true,
        ]);
    }
}
