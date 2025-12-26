<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class LocationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = Location::with('cluster', 'cluster.zone'); // Eager load cluster

            // Search
            if ($request->filled('query')) {
                $search = strtolower($request->input('query'));

                $query->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(identifier) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(short_name) LIKE ?', ["%{$search}%"])
                        ->orWhereHas('cluster', function ($q2) use ($search) {
                            $q2->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
                        });
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

            return response()->json([
                'status' => true,
                'data' => $locations->items(),
                'total' => $locations->total()
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
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
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:locations,name',
            'identifier' => 'required|string|max:255|unique:locations,identifier',
            'short_name' => 'required|string|max:255|unique:locations,short_name',
            'cluster_id' => 'required|exists:clusters,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $cluster = Location::create($request->only(['cluster_id', 'name', 'identifier', 'short_name']));
            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $cluster
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create cluster',
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
            $cluster = Location::with(['cluster', 'cluster.zone'])->findOrFail($id);
            $formattedCluster = [
                ...$cluster->toArray(),
                'zone_id'    => $cluster->cluster->zone->id ?? null,
            ];
            return response()->json([
                'success' => true,
                'data' => $formattedCluster
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Location not found'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch cluster',
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
            'name' => 'sometimes|required|string|max:255|unique:locations,name,' . $id,
            'identifier' => 'sometimes|required|string|max:255|unique:locations,identifier,' . $id,
            'short_name' => 'required|string|max:255|unique:locations,short_name,' . $id,
            'cluster_id' => 'sometimes|required|exists:clusters,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $cluster = Location::findOrFail($id);
            $cluster->update($request->only(['cluster_id', 'name', 'identifier', 'short_name']));
            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $cluster
            ], 200);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Location not found'
            ], 404);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update cluster',
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
            $cluster = Location::findOrFail($id);
            $cluster->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Location deleted successfully'
            ], 200);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Location not found'
            ], 404);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete cluster',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
