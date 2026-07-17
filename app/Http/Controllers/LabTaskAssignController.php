<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Models\{Location, Assignment, TaskNotification, User};

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

                    $assignment = Assignment::create([
                        'document_id' => $request->document_id,
                        'clause_id'   => $request->clause_id,
                        'location_id' => $locationId,
                        'department_id' => null,
                        'user_id' => null,
                        'scope_type' => 'location',
                        'assigned_by' => $authUser->id,
                        'assigned_at' => now(),
                    ]);

                    $this->createNotificationsForAssignment($assignment);
                }

                foreach ($departments as $dept) {

                    $departmentId = $dept['id'];
                    $users = $dept['users'] ?? [];

                    // ✅ CASE 2: DEPARTMENT ONLY
                    if (empty($users)) {

                        $assignment = Assignment::create([
                            'document_id' => $request->document_id,
                            'clause_id'   => $request->clause_id,
                            'location_id' => $locationId,
                            'department_id' => $departmentId,
                            'user_id' => null,
                            'scope_type' => 'department',
                            'assigned_by' => $authUser->id,
                            'assigned_at' => now(),
                        ]);

                        $this->createNotificationsForAssignment($assignment);
                    }

                    // ✅ CASE 3: USERS
                    foreach ($users as $user) {

                        $assignment = Assignment::create([
                            'document_id' => $request->document_id,
                            'clause_id'   => $request->clause_id,
                            'location_id' => $locationId,
                            'department_id' => $departmentId,
                            'user_id' => $user['id'],
                            'scope_type' => 'user',
                            'assigned_by' => $authUser->id,
                            'assigned_at' => now(),
                        ]);

                        $this->createNotificationsForAssignment($assignment);
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

    private function createNotificationsForAssignment(Assignment $assignment)
    {
        $assignment->loadMissing([
            'assignedBy:id,name,email',
            'location:id,name,owner_type,owner_id',
            'department:id,name',
            'user:id,name,email',
        ]);

        $assignedBy = $assignment->assignedBy;
        $assignedByName = $assignedBy?->name ?? 'Someone';
        $title = 'New task assigned';
        $message = match ($assignment->scope_type) {
            'user' => "{$assignedByName} assigned a new task to you.",
            'department' => "{$assignedByName} assigned a new task to your department.",
            default => "{$assignedByName} assigned a new task to your location.",
        };

        $users = $this->resolveAssignmentRecipients($assignment);
        $notificationData = $this->assignmentNotificationData($assignment, $assignedBy);

        foreach ($users->reject(fn ($user) => $user->id === $assignment->assigned_by) as $user) {
            TaskNotification::create([
                'user_id' => $user->id,
                'type' => 'task_assigned',
                'title' => $title,
                'message' => $message,
                'data' => array_merge($notificationData, [
                    'notification_for' => 'assignee',
                ]),
            ]);
        }

        if ($assignment->assigned_by) {
            TaskNotification::create([
                'user_id' => $assignment->assigned_by,
                'type' => 'task_assignment_created',
                'title' => 'Task assigned by you',
                'message' => "You assigned a new task to {$this->assignmentTargetLabel($assignment)}.",
                'data' => array_merge($notificationData, [
                    'notification_for' => 'assigner',
                ]),
            ]);
        }
    }

    private function assignmentNotificationData(Assignment $assignment, ?User $assignedBy): array
    {
        return [
            'document_id' => $assignment->document_id,
            'clause_id' => $assignment->clause_id,
            'location_id' => $assignment->location_id,
            'location_name' => $assignment->location?->name,
            'department_id' => $assignment->department_id,
            'department_name' => $assignment->department?->name,
            'assigned_user_id' => $assignment->user_id,
            'assigned_user_name' => $assignment->user?->name,
            'assigned_user_email' => $assignment->user?->email,
            'scope_type' => $assignment->scope_type,
            'assigned_by' => $assignment->assigned_by,
            'assigned_by_name' => $assignedBy?->name,
            'assigned_by_email' => $assignedBy?->email,
            'assigned_at' => $assignment->assigned_at?->toDateTimeString(),
        ];
    }

    private function assignmentTargetLabel(Assignment $assignment): string
    {
        $location = $assignment->location?->name ?? "location #{$assignment->location_id}";
        $department = $assignment->department?->name ?? (
            $assignment->department_id ? "department #{$assignment->department_id}" : null
        );
        $user = $assignment->user?->name ?? (
            $assignment->user_id ? "user #{$assignment->user_id}" : null
        );

        return match ($assignment->scope_type) {
            'user' => $department
                ? "{$user} in {$department} department at {$location}"
                : "{$user} at {$location}",
            'department' => "{$department} department at {$location}",
            default => "{$location} location",
        };
    }

    private function resolveAssignmentRecipients(Assignment $assignment)
    {
        if ($assignment->user_id) {
            return User::where('id', $assignment->user_id)->get();
        }

        $assignment->loadMissing('location:id,owner_type,owner_id');
        $labId = $assignment->location?->owner_type === 'lab'
            ? $assignment->location->owner_id
            : null;

        return User::whereHas('labUsers', function ($labUserQuery) use ($assignment, $labId) {
            if ($labId) {
                $labUserQuery->where('lab_id', $labId);
            }

            $labUserQuery->whereHas('accesses', function ($accessQuery) use ($assignment) {
                $accessQuery
                    ->where('location_id', $assignment->location_id)
                    ->where('status', 'active')
                    ->where(function ($expiryQuery) {
                        $expiryQuery
                            ->whereNull('expires_at')
                            ->orWhere('expires_at', '>=', now());
                    });

                if ($assignment->department_id) {
                    $accessQuery->whereHas('department', function ($departmentQuery) use ($assignment) {
                        $departmentQuery->where('department_id', $assignment->department_id);
                    });
                }
            });
        })
            ->distinct()
            ->get();
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
