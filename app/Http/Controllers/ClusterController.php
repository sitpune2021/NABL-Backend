<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Cluster;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\Rule;

class ClusterController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $ctx = $this->labContext($request);
            $query = Cluster::with('zone')->where('status','completed');

            if ($ctx['lab_id'] == 0) {
                $query->with('lab')->superAdmin();
            } else {
                $query->forLab($ctx['lab_id']);
            }

            if ($request->zone_id) {
                $query->where('zone_id', $request->zone_id);
            }

            // Search
            if ($request->filled('query')) {
                $search = strtolower($request->input('query'));
                $query->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(identifier) LIKE ?', ["%{$search}%"])
                        ->orWhereHas('zone', function ($q2) use ($search) {
                            $q2->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
                        });
                });
            }

            if ($request->filled('zones')) {
                $zones = $request->input('zones'); // [4, 6, 7]

                $query->whereHas('zone', function ($q) use ($zones) {
                    $q->whereIn('id', $zones);
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

            $clusters = $query->paginate($pageSize, ['*'], 'page', $pageIndex);

            return response()->json([
                'status' => true,
                'data' => $clusters->items(),
                'total' => $clusters->total()
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch clusters',
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
        $validator = Validator::make($request->all(),[
            'zone_id'=>['required','exists:zones,id'],
            'name'=>['required','string','max:255'],
            'identifier'=>[
                'required',
                Rule::unique('clusters')->where(fn($q)=>
                    $q->where('zone_id',$request->zone_id)
                      ->where('owner_type',$ctx['owner_type'])
                      ->where('owner_id',$ctx['owner_id'])
                      ->whereNull('deleted_at')
                )
            ]
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $cluster = Cluster::create([
                'zone_id'=>$request->zone_id,
                'name'=>$request->name,
                'identifier'=>$request->identifier,
                'owner_type'=>$ctx['owner_type'],
                'owner_id'=>$ctx['owner_id'],
                'status'=>'completed'
            ]);
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
            $ctx = $this->labContext(request());
            $cluster = Cluster::with('zone')
                ->accessible($ctx['lab_id'])
                ->findOrFail($id);
            return response()->json([
                'success' => true,
                'data' => $cluster
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cluster not found'
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
        $cluster = Cluster::findOrFail($id);
        $validator = Validator::make($request->all(),[
            'zone_id'=>['sometimes','exists:zones,id'],
            'name'=>['sometimes','required','string','max:255'],
            'identifier'=>[ 'sometimes',
                Rule::unique('clusters')
                    ->ignore($id)
            ]
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $cluster->update($request->only(['zone_id', 'name', 'identifier']));
            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $cluster
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update cluster',
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
            Cluster::findOrFail($id)->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cluster deleted successfully'
            ], 200);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Cluster not found'
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
    /**
     * Lab clusters list for append
     */
    public function labMasterClusters(Request $request)
    {
        $validated = $request->validate([
            'id'=>['required','integer','exists:labs,id'],
            'zoneId'=>['required','integer','exists:zones,id'],
            'start_date'=>['nullable','date'],
            'end_date'=>['nullable','date','after_or_equal:start_date'],
            'key'=>['nullable','string','in:all,master'],
        ]);

        $labId  = $validated['id'];
        $zoneId = $validated['zoneId'];
        $key    = $validated['key'] ?? null;

        $query = Cluster::query()
            ->with('appendedMaster')
            ->select([
                'id',
                'name',
                'identifier',
                'zone_id',
                'owner_id',
                'owner_type',
                'parent_id',
                'created_at'
            ])
            ->where('owner_type','lab')
            ->where('owner_id',$labId)
            ->where('zone_id',$zoneId)
            ->whereNull('parent_id');

        if(!empty($validated['start_date']) && !empty($validated['end_date'])){
            $query->whereBetween('created_at',[
                $validated['start_date'].' 00:00:00',
                $validated['end_date'].' 23:59:59'
            ]);
        }

        if($key !== 'all'){
            $query->whereDoesntHave('appendedMaster');
        }

        $clusters = $query->orderBy('name')->get();

        return response()->json([
            'success'=>true,
            'data'=>$clusters
        ]);
    }

    /**
     * Append cluster to master
     */
    public function appendLabClusterToMaster(Request $request)
    {
        $validated = $request->validate([
            'lab_cluster_id'=>['required','exists:clusters,id'],
            'force_append_zone'=>['nullable','boolean']
        ]);

        $force = $validated['force_append_zone'] ?? false;

        $labCluster = Cluster::where('id',$validated['lab_cluster_id'])
            ->where('owner_type','lab')
            ->whereNull('parent_id')
            ->firstOrFail();

        $labZone = Zone::where('id',$labCluster->zone_id)
            ->where('owner_type','lab')
            ->whereNull('parent_id')
            ->firstOrFail();

        $masterZone = Zone::where('owner_type','super_admin')
            ->where('parent_id',$labZone->id)
            ->first();

        if(!$masterZone && !$force){
            return response()->json([
                'success'=>false,
                'needs_zone_append'=>true,
                'message'=>'Parent zone not appended'
            ],409);
        }

        DB::beginTransaction();

        try {

            if(!$masterZone){
                $masterZone = Zone::create([
                    'parent_id'=>$labZone->id,
                    'name'=>$labZone->name,
                    'identifier'=>$labZone->identifier,
                    'owner_type'=>'super_admin',
                    'owner_id'=>0
                ]);
            }

            $alreadyExists = Cluster::where('owner_type','super_admin')
                ->where('parent_id',$labCluster->id)
                ->exists();

            if($alreadyExists){
                DB::rollBack();

                return response()->json([
                    'success'=>false,
                    'message'=>'Cluster already appended'
                ],409);
            }

            $masterCluster = Cluster::create([
                'name'=>$labCluster->name,
                'identifier'=>$labCluster->identifier,
                'zone_id'=>$masterZone->id,
                'parent_id'=>$labCluster->id,
                'owner_type'=>'super_admin',
                'owner_id'=>$labCluster->owner_id,
                'status'=>'pending'
            ]);

            DB::commit();

            return response()->json([
                'success'=>true,
                'data'=>$masterCluster
            ],201);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'success'=>false,
                'message'=>'Failed to append cluster'
            ],500);
        }
    }

    /**
     * Pending clusters
     */
    public function pendingClusters()
    {
        $clusters = Cluster::with(['zone','lab'])
            ->where('status','pending')
            ->orderBy('created_at','desc')
            ->get();

        return response()->json([
            'success'=>true,
            'data'=>$clusters
        ]);
    }

    /**
     * Approve clusters
     */
    public function approveClusters(Request $request)
    {
        $validated = $request->validate([
            'ids'=>['required','array'],
            'ids.*'=>['exists:clusters,id']
        ]);

        Cluster::whereIn('id',$validated['ids'])
            ->update([
                'status'=>'completed'
            ]);

        return response()->json([
            'success'=>true
        ]);
    }
}
