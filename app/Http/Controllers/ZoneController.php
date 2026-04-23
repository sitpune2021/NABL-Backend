<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\{Zone, LabUser};

class ZoneController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $ctx = $this->labContext($request);
            $query = Zone::query()->where('status', 'completed');

            if ($ctx['lab_id'] == 0) {
                $query->with('lab')->SuperAdmin();
            } else {
                $query->ForLab($ctx['lab_id']);
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

            $zones = $query->paginate($pageSize, ['*'], 'page', $pageIndex);
                        $data = collect($zones->items())->addSerial($zones->firstItem());


            return response()->json([
                'success' => true,
                'data' => $data,
                'total' => $zones->total()
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch zones',
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
                'exists:zones,id',
                fn($attr, $value, $fail) =>
                $value && $ctx['owner_type'] !== 'lab'
                    ? $fail('Only lab users can override master zones')
                    : null
            ],
            'name' => [
                'required',
                Rule::unique('zones')->where(
                    fn($q) =>
                    $q->where('owner_type', $ctx['owner_type'])
                        ->where('owner_id', $ctx['owner_id'])
                        ->whereNull('deleted_at')
                ),
            ],
            'identifier' => [
                'required',
                Rule::unique('zones')->where(
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
            $zone = Zone::create([
                'name'       => $request->name,
                'identifier' => $request->identifier,
                'owner_type' => $ctx['owner_type'],
                'owner_id'   => $ctx['owner_id'],
                'status'     => 'completed',
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $zone
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create zone',
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
            $zone = Zone::accessible($ctx['lab_id'])->findOrFail($id);
            return response()->json([
                'success' => true,
                'data' => $zone
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Zone not found'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch zone',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $zone = Zone::findOrFail($id);
        $validator = Validator::make($request->all(), [
            'name' => [
                'sometimes',
                Rule::unique('zones')->ignore($id)->where(
                    fn($q) =>
                    $q->where('owner_type', $zone->owner_type)
                        ->where('owner_id', $zone->owner_id)
                        ->whereNull('deleted_at')
                ),
            ],
            'identifier' => [
                'sometimes',
                Rule::unique('zones')->ignore($id)->where(
                    fn($q) =>
                    $q->where('owner_type', $zone->owner_type)
                        ->where('owner_id', $zone->owner_id)
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
            $zone->update($request->only(['name', 'identifier']));
            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $zone
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update zone',
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
            $zone = Zone::findOrFail($id);

            if ($zone->is_master) {
                return response()->json([
                    'success' => false,
                    'message' => 'Master zones cannot be deleted',
                ], 403);
            }

            $zone->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Zone deleted successfully'
            ], 200);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Zone not found'
            ], 404);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete zone',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function labMasterZones(Request $request)
    {
        $validated = $request->validate([
            'id'  => ['required', 'integer', 'exists:labs,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'key' => ['nullable', 'string', 'in:all,master']
        ]);

        $labId = $validated['id'];
        $key   = $validated['key'] ?? null;

        $query = Zone::query()
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

        $zones = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $zones
        ]);
    }

    public function appendLabZoneToMaster(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lab_zone_id' => ['required', 'exists:zones,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $labZone = Zone::where('id', $request->lab_zone_id)
            ->where('owner_type', 'lab')
            ->whereNull('parent_id')
            ->firstOrFail();

        if ($labZone->appendedMaster()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Zone already appended to master',
            ], 409);
        }

        DB::beginTransaction();

        try {

            $masterZone = Zone::create([
                'parent_id'  => $labZone->id,
                'name'       => $labZone->name,
                'identifier' => $labZone->identifier,
                'owner_type' => 'super_admin',
                'owner_id'   => 0,
                'status'     => 'pending',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $masterZone,
            ], 201);
        } catch (Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to append zone',
            ], 500);
        }
    }

    public function pendingZones()
    {
        $zones = Zone::with('lab')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $zones
        ]);
    }

    public function approveZones(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['exists:zones,id']
        ]);

        Zone::whereIn('id', $validated['ids'])
            ->update(['status' => 'completed']);

        return response()->json([
            'success' => true,
        ]);
    }
}
