<?php

namespace App\Http\Controllers;

use Exception;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Models\{Unit, LabUser};
use Illuminate\Validation\Rule;

class UnitController extends Controller
{

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $ctx = $this->labContext($request);
            $query = Unit::query()->where('status', 'completed');

            if ($ctx['lab_id'] == 0) {
                $query->with('lab')->SuperAdmin();
            } else {
                $query->ForLab($ctx['lab_id']);
            }

            // Search
            if ($request->filled('query')) {
                $search = strtolower($request->input('query'));

                $query->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
                });
            }

            // Sorting
            $sortKey = $request->input('sort.key', 'id'); // default sort by id
            $sortOrder = $request->input('sort.order', 'asc'); // default ascending

            $allowedSortColumns = ['id', 'name', 'created_at', 'updated_at'];
            $allowedSortOrder = ['asc', 'desc'];

            if (in_array($sortKey, $allowedSortColumns) && in_array(strtolower($sortOrder), $allowedSortOrder)) {
                $query->orderBy($sortKey, $sortOrder);
            }

            // Pagination
            $pageIndex = (int) $request->input('pageIndex', 1);
            $pageSize = (int) $request->input('pageSize', 10);

            $units = $query->paginate($pageSize, ['*'], 'page', $pageIndex);

            return response()->json([
                'status' => true,
                'data' => $units->items(),
                'total' => $units->total()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch units',
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
            'name' => 'required',
            'parent_id' => [
                'nullable',
                'exists:units,id',
                // only labs can override
                fn ($attr, $value, $fail) =>
                    $value && $ctx['owner_type'] !== 'lab'
                        ? $fail('Only lab users can override master units')
                        : null
            ],
            'name' => [
                'required',
                Rule::unique('units')->where(fn ($q) =>
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
            $unit = Unit::create([
                'parent_id'  => $request->parent_id,
                'name'       => $request->name,
                'owner_type' => $ctx['owner_type'],
                'owner_id'   => $ctx['owner_id'],
                'status'     => 'completed',

            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $unit
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create unit',
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
            $unit = Unit::accessible($ctx['lab_id'])->findOrFail($id);
            return response()->json([
                'success' => true,
                'data' => $unit
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unit not found'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch unit',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $unit = Unit::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => [
                'sometimes',
                Rule::unique('units')->ignore($id)->where(fn ($q) =>
                    $q->where('owner_type', $unit->owner_type)
                      ->where('owner_id', $unit->owner_id)
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
            $unit->update($request->only(['name']));
            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $unit
            ], 200);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Unit not found'
            ], 404);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update unit',
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
            $unit = Unit::findOrFail($id);
            if ($unit->is_master) {
                return response()->json([
                    'success' => false,
                    'message' => 'Master unit cannot be deleted',
                ], 403);
            }

            $unit->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Unit deleted successfully'
            ], 200);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Unit not found'
            ], 404);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete unit',
                'error' => $e->getMessage()
            ], 500);
        }
    }

   public function labMasterUnits(Request $request)
{
    $validated = $request->validate([
        'id'  => ['required', 'integer', 'exists:labs,id'],
        'start_date' => ['nullable', 'date'],
        'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        'key' => ['nullable', 'string', 'in:all,master']
    ]);

    $labId = $validated['id'];
    $key   = $validated['key'] ?? null;

    $query = Unit::query()
        ->with('appendedMaster')
        ->select([
            'id',
            'name',
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

    $units = $query->orderBy('name')->get();

    return response()->json([
        'success' => true,
        'data'    => $units
    ]);
}

    public function appendLabUnitToMaster(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lab_unit_id' => ['required', 'exists:units,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $labUnit = Unit::where('id', $request->lab_unit_id)
            ->where('owner_type', 'lab')
            ->whereNull('parent_id')
            ->firstOrFail();

        $alreadyExists = Unit::where('owner_type', 'super_admin')
            ->where('parent_id', $labUnit->id)
            ->exists();

        if ($alreadyExists) {
            return response()->json([
                'success' => false,
                'message' => 'Unit already synced',
            ], 409);
        }

        DB::beginTransaction();
        try {
            $masterUnit = Unit::create([
                'parent_id'              => $labUnit->id,
                'name'                   => $labUnit->name,
                'identifier'             => $labUnit->identifier,
                'owner_type'             => 'super_admin',
                'owner_id'               => null,
                'status'     => 'pending',

            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $masterUnit,
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to append unit',
            ], 500);
        }
    }
    public function pendingUnits()
    {
        $units =Unit::with('lab')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $units
        ]);
    }
    public function approveUnits(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['exists:units,id']
        ]);

       Unit::whereIn('id', $validated['ids'])
            ->update(['status' => 'completed']);

        return response()->json([
            'success' => true,
        ]);
    }
}
