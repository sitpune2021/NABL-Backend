<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Models\{LabTaskAssign};

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
        ->get(['id', 'lab_id', 'clause_id', 'document_id', 'user_id']);

        return response()->json([
            'status' => true,
            'message' => 'Assignments fetched successfully',
            'data' => $tasks
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $authUser = auth()->user();
        $ctx = $this->labContext($request);


        // ✅ Validation
        $validator = Validator::make($request->all(), [
            'clause_id'   => 'required|exists:clauses,id',
            'document_id' => 'required|exists:documents,id',
            'user_id'     => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // 🔥 Prevent duplicate OR update existing
            $task = LabTaskAssign::updateOrCreate(
                [
                    'lab_id'      => $ctx['lab_id'],
                    'clause_id'   => $request->clause_id,
                    'document_id' => $request->document_id,
                ],
                [
                    'user_id' => $request->user_id,
                ]
            );

            return response()->json([
                'status'  => true,
                'message' => 'Task assigned successfully',
                'data'    => $task
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong',
                'error'   => $e->getMessage()
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
