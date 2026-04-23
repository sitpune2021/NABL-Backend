<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Location;
use App\Models\Cluster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\Rule;

class LocationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $ctx = $this->labContext($request);
            $query = Location::with(['cluster', 'cluster.zone', 'departments.department'])->where('status', 'completed');

            if ($ctx['lab_id'] == 0) {
                $query->with('lab')->SuperAdmin();
            } else {
                $query->ForLab($ctx['lab_id']);
                if($ctx['location_id']){
                    $query->where('id', $ctx['location_id']);
                }
            }

            if ($request->cluster_id) {
                $query->where('cluster_id', $request->cluster_id);
            }

            // Search
            if ($request->filled('query')) {
                $search = strtolower($request->input('query'));

                $query->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(identifier) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(short_name) LIKE ?', ["%{$search}%"])
                        ->orWhereHas('cluster', function ($q2) use ($search) {
                            $q2->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
                        })
                        ->orWhereHas('cluster.zone', function ($q3) use ($search) {
                            $q3->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
                        });
                });
            }

            if ($request->filled('zones')) {
                $zones = $request->input('zones');

                $query->whereHas('cluster.zone', function ($q) use ($zones) {
                    $q->whereIn('id', $zones);
                });
            }

            if ($request->filled('clusters')) {
                $clusters = $request->input('clusters');

                $query->whereHas('cluster', function ($q) use ($clusters) {
                    $q->whereIn('id', $clusters);
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

            $locations = $query->paginate($pageSize, ['*'], 'page', $pageIndex);

            $data = collect($locations->items())->values()->map(function ($location, $index) use ($locations) {
                $serial = $locations->firstItem() + $index;

                $locationArray = $location->toArray();

                $locationArray['sr'] = str_pad($serial, 4, '0', STR_PAD_LEFT); // 👈 ADD THIS

                $locationArray['departments'] = collect($location->departments)->map(function ($ld) {

                    $deptArray = $ld->toArray();

                    // ❌ remove accesses
                    unset($deptArray['accesses']);

                    // ✅ move users INSIDE department
                    if ($ld->department) {

                        $deptArray['department']['users'] = $ld->accesses->map(function ($access) {

                            return [
                                'id' => $access->id,
                                'user' => optional($access->labUser)->user,
                                'role' => $access->role,
                            ];
                            
                        })
                        ->filter(fn($u) => $u['user'] !== null)
                        ->values();
                    }

                    return $deptArray;

                })->values();

                return $locationArray;
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'total' => $locations->total()
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch locations',
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
            'cluster_id' => ['required', 'exists:clusters,id'],
            'name' => ['required', 'string', 'max:255'],
            'identifier' => [
                'required',
                Rule::unique('locations')->where(
                    fn($q) =>
                    $q->where('cluster_id', $request->cluster_id)
                        ->where('owner_type', $ctx['owner_type'])
                        ->where('owner_id', $ctx['owner_id'])
                        ->whereNull('deleted_at')
                )
            ],
            'short_name' => ['required', 'string', 'max:255']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $location = Location::create([
                'cluster_id' => $request->cluster_id,
                'name' => $request->name,
                'identifier' => $request->identifier,
                'short_name' => $request->short_name,
                'owner_type' => $ctx['owner_type'],
                'owner_id' => $ctx['owner_id'],
                'status' => 'completed'
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $location
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create location'
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
            $location = Location::with(['cluster', 'cluster.zone'])
                ->accessible($ctx['lab_id'])
                ->findOrFail($id);
            return response()->json([
                'success' => true,
                'data' => $location
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Location not found'
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $location = Location::findOrFail($id);
        $validator = Validator::make($request->all(), [
            'cluster_id' => ['sometimes', 'exists:clusters,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'identifier' => [
                'sometimes',
                Rule::unique('locations')->ignore($id)
            ],
            'short_name' => ['sometimes', 'required']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $location->update(
            $request->only(['cluster_id', 'name', 'identifier', 'short_name']));
            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $location
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false
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
            Location::findOrFail($id)->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Location deleted successfully'
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false
            ], 500);
        }
    }
    /**
     * Lab Locations for append
     */
    public function labMasterLocations(Request $request)
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:labs,id'],
            'clusterId' => ['required', 'integer', 'exists:clusters,id'],
        ]);

        $locations = Location::query()
            ->with('appendedMaster')
            ->select([
                'id',
                'name',
                'identifier',
                'cluster_id',
                'owner_id',
                'owner_type',
                'parent_id',
                'created_at'
            ])
            ->where('owner_type', 'lab')
            ->where('owner_id', $validated['id'])
            ->where('cluster_id', $validated['clusterId'])
            ->whereNull('parent_id')
            ->whereDoesntHave('appendedMaster')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $locations
        ]);
    }

    /**
     * Append to master
     */
    public function appendLabLocationToMaster(Request $request)
    {
        $validated = $request->validate([
            'location_id' => ['required', 'exists:locations,id']
        ]);

        $labLocation = Location::where('id', $validated['location_id'])
            ->where('owner_type', 'lab')
            ->whereNull('parent_id')
            ->firstOrFail();

        $labCluster = Cluster::where('id', $labLocation->cluster_id)
            ->where('owner_type', 'lab')
            ->whereNull('parent_id')
            ->firstOrFail();

        $masterCluster = Cluster::where('owner_type', 'super_admin')
            ->where('parent_id', $labCluster->id)
            ->first();

        if (!$masterCluster) {

            return response()->json([
                'success' => false,
                    'needs_cluster_append' => true
                ], 409);
        }

        DB::beginTransaction();

        try {

            $masterLocation = Location::create([
                'name' => $labLocation->name,
                'identifier' => $labLocation->identifier,
                'short_name' => $labLocation->short_name,
                'cluster_id' => $masterCluster->id,
                'parent_id' => $labLocation->id,
                'owner_type' => 'super_admin',
                'owner_id' => $labLocation->owner_id,
                'status' => 'pending'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $masterLocation
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false
            ], 500);
        }
    }
    /**
     * Pending
     */
    public function pending()
    {
        $locations = Location::with(['cluster', 'cluster.zone', 'lab'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $locations
        ]);
    }

    /**
     * Approve
     */
    public function approve(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['exists:locations,id']
        ]);

        Location::whereIn('id', $validated['ids'])
            ->update(['status' => 'completed']);

        return response()->json([
            'success' => true
        ]);
    }
}
