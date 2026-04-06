<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Models\{Location, Assignment};

class LabTaskAssignController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $ctx = $this->labContext($request);

        $tasks = Assignment::with([
            'user:id,name,email',
            'location:id,name',
            'department:id,name'
        ])
        // ->where('lab_id', $ctx['lab_id'])
        ->get();

        return response()->json([
            'status' => true,
            'message' => 'Assignments fetched successfully',
            'data' => $tasks
        ]);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $authUser = auth()->user();
            $ctx = $this->labContext($request);

            // ✅ VALIDATION
            $request->validate([
                'document_id' => 'required|integer',
                'clause_id' => 'required|integer',
                'location_id' => 'required|integer',
                'department_id' => 'nullable|integer',
                'user_ids' => 'nullable|array',
            ]);

            $locationId   = $request->location_id;
            $departmentId = $request->department_id;
            $userIds      = $request->user_ids ?? [];

            // 🔥 CLEAN OLD ASSIGNMENTS (IMPORTANT)
            Assignment::where([
                // 'lab_id' => $ctx['lab_id'],
                'document_id' => $request->document_id,
                'clause_id' => $request->clause_id,
                'location_id' => $locationId,
            ])->delete();

            // 🔥 CASE 1: LOCATION LEVEL
            if ($locationId && !$departmentId && empty($userIds)) {

                Assignment::updateOrCreate([
                    // 'lab_id' => $ctx['lab_id'],
                    'document_id' => $request->document_id,
                    'clause_id'   => $request->clause_id,
                    'location_id' => $locationId,
                    'department_id' => null,
                    'user_id' => null,
                ], [
                    'scope_type' => 'location',
                    'assigned_by' => $authUser->id,
                    'assigned_at' => now(),
                ]);
            }

            // 🔥 CASE 2: DEPARTMENT LEVEL
            elseif ($locationId && $departmentId && empty($userIds)) {

                Assignment::updateOrCreate([
                    // 'lab_id' => $ctx['lab_id'],
                    'document_id' => $request->document_id,
                    'clause_id'   => $request->clause_id,
                    'location_id' => $locationId,
                    'department_id' => $departmentId,
                    'user_id' => null,
                ], [
                    'scope_type' => 'department',
                    'assigned_by' => $authUser->id,
                    'assigned_at' => now(),
                ]);
            }

            // 🔥 CASE 3: USER LEVEL
            else {

                foreach ($userIds as $userId) {

                    Assignment::updateOrCreate([
                        // 'lab_id' => $ctx['lab_id'],
                        'document_id' => $request->document_id,
                        'clause_id'   => $request->clause_id,
                        'location_id' => $locationId,
                        'department_id' => $departmentId,
                        'user_id' => $userId,
                    ], [
                        'scope_type' => 'user',
                        'assigned_by' => $authUser->id,
                        'assigned_at' => now(),
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Assignment saved successfully'
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
