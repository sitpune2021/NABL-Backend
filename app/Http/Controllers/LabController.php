<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{Lab, LabLocation, LabLocationDepartment, LabUser, Contact, User, UserLocationDepartmentRole};
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class LabController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $labs = Lab::with(['contacts', 'locations', 'locations.contacts', 'users'])->get();

        return response()->json([
            'list' => $labs,
            'total' => $labs->count()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
         DB::beginTransaction();

        try {
            // 1. Create Lab
            $lab = Lab::create([
                'name' => $request->name,
                'labType' => $request->labType,
                'labCode' => $request->labCode,
                'address' => $request->address ?? null,
            ]);

            // 2. Lab Contacts
            foreach ($request->emails ?? [] as $email) {
                $lab->contacts()->create([
                    'type' => 'email',
                    'value' => $email['value'],
                    'label' => $email['label'] ?? null,
                    'is_primary' => $email['is_primary'] ?? false,
                ]);
            }
            foreach ($request->phones ?? [] as $phone) {
                $lab->contacts()->create([
                    'type' => 'phone',
                    'value' => $phone['value'],
                    'label' => $phone['label'] ?? null,
                    'is_primary' => $phone['is_primary'] ?? false,
                ]);
            }

            $primaryEmail = collect($request->emails ?? [])->firstWhere('is_primary', true);
            $primaryPhone = collect($request->phones ?? [])->firstWhere('is_primary', true);

            if ($primaryEmail || $primaryPhone) {
                $user = User::firstOrCreate(
                    ['email' => $primaryEmail['value'] ?? null],
                    [
                        'name' => 'Lab Admin', // Or generate a name
                        'username' => Str::lower(Str::random(10)),
                        'dial_code' => $primaryPhone['value'] ? '+91' : null, // example
                        'phone' => $primaryPhone['value'] ?? null,
                        'address' => $request->address ?? null,
                        'password' => Hash::make('superadmin1234'),
                        'is_super_admin' => true,
                    ]
                );

                LabUser::firstOrCreate([
                    'lab_id' => $lab->id,
                    'user_id' => $user->id,
                ]);
            }
            // 3. Lab Locations
            foreach ($request->location ?? [] as $loc) {
                $labLocation = LabLocation::create([
                    'lab_id' => $lab->id,
                    'location_id' => $loc['location_name'],
                    'prefix' => $loc['prefix'],
                    'shortName' => $loc['shortName'] ?? null,
                    'address' => $loc['address'] ?? null,
                ]);

                // Location Contacts
                foreach ($loc['emails'] ?? [] as $email) {
                    $labLocation->contacts()->create([
                        'type' => 'email',
                        'value' => $email['value'],
                        'label' => $email['label'] ?? null,
                        'is_primary' => $email['is_primary'] ?? false,
                    ]);
                }
                foreach ($loc['phones'] ?? [] as $phone) {
                    $labLocation->contacts()->create([
                        'type' => 'phone',
                        'value' => $phone['value'],
                        'label' => $phone['label'] ?? null,
                        'is_primary' => $phone['is_primary'] ?? false,
                    ]);
                }

                $primaryEmail = collect($loc['emails'] ?? [])->firstWhere('is_primary', true);
                $primaryPhone = collect($loc['phones'] ?? [])->firstWhere('is_primary', true);

                if ($primaryEmail || $primaryPhone) {
                    $admin = User::firstOrCreate(
                        ['email' => $primaryEmail['value'] ?? 'admin@example.com'],
                        [
                            'name' => 'Admin User',
                            'username' => 'labadminuser'.Str::random(4),
                            'dial_code' => $primaryPhone['value'] ? '+91' : null,
                            'phone' => $primaryPhone['value'] ?? '8888888888',
                            'address' => $loc['address'] ?? null,
                            'password' => Hash::make('admin123'),
                            'is_super_admin' => false,
                        ]
                    );

                    // Assign role
                    $admin->assignRole('Admin');

                    // Assign ULDR (example: first department in location)
                    $departmentId = $loc['departments'][0]['name'] ?? null;
                    if ($departmentId) {
                        $this->assignULDR(
                            $admin->id,
                            $labLocation->id,
                            $departmentId,
                            Role::where('name', 'Admin')->first()->id
                        );
                    }
                }


                // Departments
                foreach ($loc['departments'] ?? [] as $dept) {
                    LabLocationDepartment::create([
                        'lab_location_id' => $labLocation->id,
                        'department_id' => $dept['name'] ?? null, // Ensure department exists
                    ]);
                }
            }

            DB::commit();
            return response()->json(['message' => 'Lab created successfully', 'lab_id' => $lab->id], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified lab.
     */
    public function show(string $id)
    {
        try {
            // Eager load relationships: contacts, locations (with their contacts), and users
            $lab = Lab::with(['contacts', 'locations', 'locations.contacts','locations.locationRecord', 'users'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $lab
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lab not found'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch lab',
                'error' => $e->getMessage()
            ], 500);
        }
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

    
    private function assignULDR($userId, $locationId, $departmentId, $roleId)
    {
        $uldr = UserLocationDepartmentRole::create([
            'user_id'       => $userId,
            'location_id'   => $locationId,
            'department_id' => $departmentId,
            'role_id'       => $roleId,
            'status'        => 'active',
            'position_type' => 'permanent',
        ]);

        return $uldr;
    }

}
