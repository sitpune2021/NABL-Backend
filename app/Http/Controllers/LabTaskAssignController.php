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
                'locations' => 'required|array',
                'locations.*.id' => 'required|integer',
                'locations.*.departments' => 'nullable|array',
                'locations.*.departments.*.id' => 'required|integer',
                'locations.*.departments.*.users' => 'nullable|array',
            ]);

            // 🔥 DELETE OLD (for this document + clause)
            Assignment::where([
                'document_id' => $request->document_id,
                'clause_id' => $request->clause_id,
            ])->delete();

            foreach ($request->locations as $location) {

                $locationId = $location['id'];
                $departments = $location['departments'] ?? [];

                // ✅ CASE 1: ONLY LOCATION
                if (empty($departments)) {

                    Assignment::create([
                        'document_id' => $request->document_id,
                        'clause_id'   => $request->clause_id,
                        'location_id' => $locationId,
                        'department_id' => null,
                        'user_id' => null,
                        'scope_type' => 'location',
                        'assigned_by' => $authUser->id,
                        'assigned_at' => now(),
                    ]);
                }

                foreach ($departments as $dept) {

                    $departmentId = $dept['id'];
                    $users = $dept['users'] ?? [];

                    // ✅ CASE 2: DEPARTMENT ONLY
                    if (empty($users)) {

                        Assignment::create([
                            'document_id' => $request->document_id,
                            'clause_id'   => $request->clause_id,
                            'location_id' => $locationId,
                            'department_id' => $departmentId,
                            'user_id' => null,
                            'scope_type' => 'department',
                            'assigned_by' => $authUser->id,
                            'assigned_at' => now(),
                        ]);
                    }

                    // ✅ CASE 3: USERS
                    foreach ($users as $user) {

                        Assignment::create([
                            'document_id' => $request->document_id,
                            'clause_id'   => $request->clause_id,
                            'location_id' => $locationId,
                            'department_id' => $departmentId,
                            'user_id' => $user['id'],
                            'scope_type' => 'user',
                            'assigned_by' => $authUser->id,
                            'assigned_at' => now(),
                        ]);
                    }
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
