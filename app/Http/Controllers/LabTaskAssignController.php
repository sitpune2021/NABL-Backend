<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Models\{LabTaskAssign, Location};

class LabTaskAssignController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $ctx = $this->labContext($request);

        $tasks = LabTaskAssign::with(['user:id,name,email'])
            ->where('lab_id', $ctx['lab_id'])
            ->get([
                'id',
                'lab_id',
                'clause_id',
                'document_id',
                'user_id',
                'location_id',
                'department_id'
            ]);

        return response()->json([
            'status' => true,
            'message' => 'Assignments fetched successfully',
            'data' => $tasks
        ]);
    }

    public function store(Request $request)
    {
        $ctx = $this->labContext($request);

        $validator = Validator::make($request->all(), [
            'clause_id'     => 'required|exists:clauses,id',
            'document_id'   => 'required|exists:documents,id',
            'location_id'   => 'required|exists:locations,id',
            'department_id' => 'nullable|exists:departments,id',
            'user_id'       => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $location = Location::with([
                'departments.department.users'
            ])->findOrFail($request->location_id);

            $userData = collect();

            // 🔥 CASE 1: SINGLE USER
            if ($request->user_id) {
                $userData = collect([[
                    'user_id' => $request->user_id,
                    'department_id' => $request->department_id
                ]]);
            }

            // 🔥 CASE 2: DEPARTMENT USERS
            elseif ($request->department_id) {
                $department = collect($location->departments)
                    ->firstWhere('department_id', $request->department_id);

                $userData = collect($department?->department?->users ?? [])
                    ->map(fn($u) => [
                        'user_id' => $u->user_id,
                        'department_id' => $request->department_id
                    ]);
            }

            // 🔥 CASE 3: LOCATION USERS
            else {
                $userData = collect($location->departments)
                    ->flatMap(function ($d) {
                        return collect($d->department->users ?? [])
                            ->map(fn($u) => [
                                'user_id' => $u->user_id,
                                'department_id' => $d->department_id // ✅ KEY FIX
                            ]);
                    });
            }

            // ✅ unique users
            $userData = $userData->unique('user_id');

            if ($userData->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No users found'
                ], 400);
            }

            // 🔥 UPSERT
            $insertData = $userData->map(fn($u) => [
                'lab_id'        => $ctx['lab_id'],
                'clause_id'     => $request->clause_id,
                'document_id'   => $request->document_id,
                'location_id'   => $request->location_id,
                'department_id' => $u['department_id'], // ✅ no null issue
                'user_id'       => $u['user_id'],
                'assigned_by'   => auth()->id(),
                'created_at'    => now(),
                'updated_at'    => now(),
            ])->values()->toArray();

            LabTaskAssign::upsert(
                $insertData,
                ['lab_id', 'clause_id', 'document_id', 'user_id'],
                ['location_id', 'department_id', 'updated_at']
            );

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Task assigned successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong',
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
