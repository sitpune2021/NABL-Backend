<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{Lab, LabLocation, LabLocationDepartment, LabUser, Contact, User, UserLocationDepartmentRole, LabClauseDocument};
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
        $authUser = auth()->user();

        $labIds = \App\Models\LabUser::where('user_id', $authUser->id)
                    ->pluck('lab_id');

        $query = Lab::with([
            'contacts',
            'location',
            'location.contacts',
            'users'
        ]);

        // If user is a lab user → restrict labs
        if ($labIds->isNotEmpty()) {
            $query->whereIn('id', $labIds);
        }

        // Otherwise → show all labs
        $labs = $query->get();

        return response()->json([
            'data'  => $labs,
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
                        'name' => 'Lab Admin',
                        'username' => Str::lower(Str::random(10)),
                        'dial_code' => $primaryPhone['value'] ? '+91' : null,
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

            // 3. Lab location
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

                    $admin->assignRole('Admin');

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
                        'department_id' => $dept['name'] ?? null,
                    ]);
                }
            }

            // 4. Save Lab Clause Documents (NEW)
            if (!empty($request->selectedClauses)) {
                $records = [];
                $standardId = $request->standard_id; // Ensure standard_id is sent from frontend

                foreach ($request->selectedClauses as $item) {
                    if (str_starts_with($item, 'clause-')) {
                        $clauseId = intval(substr($item, 7));
                        $records[] = [
                            'lab_id' => $lab->id,
                            'standard_id' => $standardId,
                            'clause_id' => $clauseId,
                            'document_id' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    } elseif (str_starts_with($item, 'doc-')) {
                        // format: doc-{clauseId}-{docId}
                        [$prefix, $clauseId, $docId] = explode('-', $item);
                        $records[] = [
                            'lab_id' => $lab->id,
                            'standard_id' => $standardId,
                            'clause_id' => intval($clauseId),
                            'document_id' => intval($docId),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                LabClauseDocument::insert($records);
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
            $lab = Lab::with([
                'contacts',
                'location.contacts',
                'location.departments',
                'location.locationRecord',
                'location.locationRecord.cluster',
                'labClauseDocuments',
                'users',
            ])->findOrFail($id);

            $labAdmin = $lab->users->map(fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_super_admin' => $user->is_super_admin,
            ]);

            $data = [
                'name'     => $lab->name,
                'labType'  => $lab->labType,
                'labCode'  => $lab->labCode,
                'address'  => $lab->address,

                // Lab Emails
                'emails' => $lab->contacts
                    ->where('type', 'email')
                    ->map(fn ($c) => [
                        'id'         => $c->id,
                        'type'       => 'email',
                        'value'      => $c->value,
                        'label'      => $c->label,
                        'is_primary' => (bool) $c->is_primary,
                    ])
                    ->values(),

                // Lab Phones
                'phones' => $lab->contacts
                    ->where('type', 'phone')
                    ->map(fn ($c) => [
                        'id'         => $c->id,
                        'type'       => 'phone',
                        'value'      => $c->value,
                        'label'      => $c->label,
                        'is_primary' => (bool) $c->is_primary,
                    ])
                    ->values(),

                'users' => $labAdmin,
                
                 // location
                'location' => $lab->location->map(function ($location) {
                    $primaryEmail = $location->contacts->where('type', 'email')->firstWhere('is_primary', true);
                    $primaryPhone = $location->contacts->where('type', 'phone')->firstWhere('is_primary', true);

                    return [
                        'id'            => $location->id,
                        'zone_name'     => $location->locationRecord->cluster->zone_id ?? null,
                        'cluster_name'  => $location->locationRecord->cluster->id ?? null,
                        'location_name' => $location->location_id,

                        'prefix'    => $location->prefix,
                        'shortName' => $location->shortName,
                        'address'   => $location->address,

                        // Location Emails
                        'emails' => $location->contacts
                            ->where('type', 'email')
                            ->map(fn ($c) => [
                                'id'         => $c->id,
                                'type'       => 'email',
                                'value'      => $c->value,
                                'label'      => $c->label,
                                'is_primary' => (bool) $c->is_primary,
                            ])
                            ->values(),

                        // Location Phones
                        'phones' => $location->contacts
                            ->where('type', 'phone')
                            ->map(fn ($c) => [
                                'id'         => $c->id,
                                'type'       => 'phone',
                                'value'      => $c->value,
                                'label'      => $c->label,
                                'is_primary' => (bool) $c->is_primary,
                            ])
                            ->values(),

                    // Instruments
                    // 'instruments' => $location->instruments
                    //     ->pluck('id')
                    //     ->values(),

                        // Departments
                        'departments' => $location->departments->map(fn ($dept) => [
                            'id'   => $dept->id,
                            'name' => $dept->department_id,
                        // 'instruments' => $dept->instruments
                        //     ->pluck('id')
                        //     ->values(),
                        ])->values(),
                    ];
                })->values(),

                'selectedClauses' => $lab->labClauseDocuments->flatMap(function($doc) {
                    if ($doc->document_id) {
                        return ["doc-{$doc->clause_id}-{$doc->document_id}"];
                    } else {
                        return ["clause-{$doc->clause_id}"];
                    }
                })->values(),
            ];

            return response()->json([
                'success' => true,
                'data'    => $data,
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lab not found',
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch lab',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }



    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        DB::beginTransaction();

        try {
            $lab = Lab::findOrFail($id);

            // 1. Update Lab basic info
            $lab->update([
                'name' => $request->name,
                'labType' => $request->labType,
                'labCode' => $request->labCode,
                'address' => $request->address ?? null,
            ]);

            // 2. Update Lab contacts (emails + phones)
            $lab->contacts()->delete(); // remove old contacts

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

            // 3. Update Lab User (super admin)
            $primaryEmail = collect($request->emails ?? [])->firstWhere('is_primary', true);
            $primaryPhone = collect($request->phones ?? [])->firstWhere('is_primary', true);

            if ($primaryEmail || $primaryPhone) {
                $user = User::updateOrCreate(
                    ['email' => $primaryEmail['value'] ?? null],
                    [
                        'name' => 'Lab Admin',
                        'username' => Str::lower(Str::random(10)),
                        'dial_code' => $primaryPhone['value'] ? '+91' : null,
                        'phone' => $primaryPhone['value'] ?? null,
                        'address' => $request->address ?? null,
                        'password' => Hash::make('superadmin1234'),
                        'is_super_admin' => true,
                    ]
                );

                LabUser::updateOrCreate(
                    ['lab_id' => $lab->id, 'user_id' => $user->id]
                );
            }

            // 4. Update Lab Locations
            // Remove old locations + departments + contacts
            foreach ($lab->location as $location) {
                $location->contacts()->delete();
                $location->departments()->delete();
            }
            $lab->location()->delete();

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

                // Admin per location
                $primaryEmail = collect($loc['emails'] ?? [])->firstWhere('is_primary', true);
                $primaryPhone = collect($loc['phones'] ?? [])->firstWhere('is_primary', true);

                if ($primaryEmail || $primaryPhone) {
                    $admin = User::updateOrCreate(
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

                    $admin->assignRole('Admin');

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
                        'department_id' => $dept['name'] ?? null,
                    ]);
                }
            }

            // 5. Update LabClauseDocuments
            $lab->labClauseDocuments()->delete(); // remove old clause documents

            if (!empty($request->selectedClauses)) {
                $records = [];
                $standardId = $request->standard_id;

                foreach ($request->selectedClauses as $item) {
                    if (str_starts_with($item, 'clause-')) {
                        $clauseId = intval(substr($item, 7));
                        $records[] = [
                            'lab_id' => $lab->id,
                            'standard_id' => $standardId,
                            'clause_id' => $clauseId,
                            'document_id' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    } elseif (str_starts_with($item, 'doc-')) {
                        [$prefix, $clauseId, $docId] = explode('-', $item);
                        $records[] = [
                            'lab_id' => $lab->id,
                            'standard_id' => $standardId,
                            'clause_id' => intval($clauseId),
                            'document_id' => intval($docId),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                LabClauseDocument::insert($records);
            }

            DB::commit();

            return response()->json(['message' => 'Lab updated successfully', 'lab_id' => $lab->id], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
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
