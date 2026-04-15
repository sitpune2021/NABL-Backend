<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Hash};

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

use App\Services\NavigationAccessService;

use App\Models\{Lab, 
    Location, 
    LabLocationDepartment, 
    LabUser, 
    Contact, 
    User, 
    Document,
    DocumentDepartment,
    DocumentVersion,
    DocumentVersionTemplate,
    DocumentVersionWorkflowLog,
    LabDocumentsEntryData,
    Template,
    Category,
    SubCategory,
    Department,
    Unit,
    TemplateChangeHistory,
    UserAssignment,
    Zone,
    Cluster,
    LabUserAccess,
    Instrument,
    ClauseDocumentLink,
    LabInstrumentAssignment
    };
use Illuminate\Database\Eloquent\ModelNotFoundException;

class LabController extends Controller
{

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $authUser = auth()->user();

        $query = Lab::with([
            'contacts',
            'location.contacts', // ✅ fixed plural
            'users'
        ]);

        // If user is a lab user → restrict labs
        $query->when(
            LabUser::where('user_id', $authUser->id)->exists(),
            function ($q) use ($authUser) {
                $q->whereHas('users', function ($sub) use ($authUser) {
                    $sub->where('user_id', $authUser->id);
                });
            }
        );

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
    public function store(Request $request, NavigationAccessService $service)
    {
        DB::beginTransaction();

        try {
            // 1. Create Lab
            $lab = Lab::create([
                'name' => $request->name,
                'lab_type' => $request->lab_type,
                'lab_code' => $request->lab_code,
                'location_limit' => $request->location_limit,
                'user_limit' => $request->user_limit,
                'created_by' => auth()->id(),
                'standard_id' => $request->standard['standard_id'],
            ]);
            $this->initializeLabMasters($lab);
            $modules = $service->getAccessModules(true, false);
            foreach ($modules as $module) {
                foreach ($module['accessor'] as $perm) {
                    Permission::firstOrCreate([
                        'name' => $module['key'] . '.' . $perm['value'],
                    ]);
                }
            }

            // 2. Lab Contacts
            $this->saveContacts($lab, $request->emails, $request->phones);
            foreach ([1,2] as $level) {
                $this->createRoleWithPermissions($lab, $modules, $level);
            }

            $primaryEmail = collect($request->emails ?? [])->firstWhere('is_primary', true);
            $primaryPhone = collect($request->phones ?? [])->firstWhere('is_primary', true);

            if ($primaryEmail || $primaryPhone) {
                $superAdmin = $this->createLabUser($primaryEmail['value'] ?? null, $primaryPhone['value'] ?? null, true);
                $this->assignRoleToUser($superAdmin, $lab, 1);
            }

            // 3. Lab location
            foreach ($request->location ?? [] as $loc) {
                $zone = $this->createIfNotExists(Zone::class, $loc['zone_id'], $lab, $loc);
                $cluster = $this->createIfNotExists(Cluster::class, $loc['cluster_id'], $lab,$loc, ['zone_id' => $zone->id]);
                $labLocation = $this->createIfNotExists(Location::class, $loc['location_id'], $lab, $loc,['cluster_id' => $cluster->id,'short_name' => $loc['shortName']]);

                // Location Contacts
                $this->saveContacts($labLocation, $loc['emails'], $loc['phones']);

                $primaryEmail = collect($loc['emails'] ?? [])->firstWhere('is_primary', true);
                $primaryPhone = collect($loc['phones'] ?? [])->firstWhere('is_primary', true);

                if ($primaryEmail || $primaryPhone) {
                    $admin = $this->createLabUser($primaryEmail['value'] ?? null, $primaryPhone['value'] ?? null, false);
                    $this->assignRoleToUser($admin, $lab, 2,true, $labLocation->id);
                }

                 // Instruments
                foreach ($loc['instruments'] ?? [] as $locInstrument) {
                   $this->assignInstrument($locInstrument, $lab, $labLocation->id);
                }
                
                // Departments
                foreach ($loc['departments'] ?? [] as $dept) {
                    $departmentOg = Department::where([
                        ['parent_id', '=', $dept['name']],
                        ['owner_type', '=', 'lab'],
                        ['owner_id', '=', $lab->id],
                    ])->first();

                    $LabLocationDepartment =  LabLocationDepartment::create([
                        'location_id' => $labLocation->id,
                        'department_id' => $departmentOg->id ?? null,
                    ]);

                    foreach ($dept['instruments'] ?? [] as $deptInstrument) {
                        $this->assignInstrument($deptInstrument, $lab, $labLocation->id, $LabLocationDepartment->id);
                    }
                }
            }
            
            if (!empty($request->documents)) {
                foreach ($request->documents as $documentId) {
                    $originalDocument = Document::with([
                        'currentVersion.templates.template.currentVersion',
                        'departments'
                    ])->find($documentId);

                    if ($originalDocument) {
                        $this->cloneDocumentForLab($originalDocument, $lab, $superAdmin);
                    }
                }
            }

            // 4. Save Lab Clause Documents (NEW)
            if (!empty($request->standard['clause_documents_link'])) {
                $linkIds = $request->standard['clause_documents_link'];

                // ✅ Fetch all links in one query
                $links = ClauseDocumentLink::whereIn('id', $linkIds)->get();

                // ✅ Get all parent document IDs
                $parentDocIds = $links->pluck('document_id')->unique();

                // ✅ Fetch all lab documents in ONE query
                $labDocuments = Document::whereIn('parent_id', $parentDocIds)
                    ->where('owner_type', 'lab')
                    ->where('owner_id', $lab->id)
                    ->with('currentVersion')
                    ->get()
                    ->keyBy('parent_id'); // 🔥 important

                foreach ($links as $link) {

                    $labDoc = $labDocuments[$link->document_id] ?? null;

                    // ❌ Skip if no mapped lab document
                    if (!$labDoc || !$labDoc->currentVersion) {
                        continue;
                    }

                    // ❌ Prevent duplicate override
                    $exists = ClauseDocumentLink::where([
                        'parent_id'  => $link->id,
                        'owner_type' => 'lab',
                        'owner_id'   => $lab->id,
                    ])->exists();

                    if ($exists) {
                        continue;
                    }

                    // ✅ Create override (clean, not replicate)
                    ClauseDocumentLink::create([
                        'parent_id'            => $link->id,
                        'standard_id'          => $link->standard_id,
                        'clause_id'            => $link->clause_id,
                        'document_id'          => $labDoc->id,
                        'document_version_id'  => $labDoc->currentVersion->id,
                        'owner_type'           => 'lab',
                        'owner_id'             => $lab->id,
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
    public function show(Request $request, string $id)
    {
        try {
            $lab = Lab::with([
                'contacts',
                'location.contacts',
                'location.departments.department',
                'location',
                'location.cluster',
                'location.cluster.zone',
                'location.instruments.instrument',
                'location.departments.instruments.instrument',
                'users.user',
            ])->findOrFail($id);
            $ctx = $this->labContext($request);

            $labusers = $lab->users->map(fn($user) => [
                'id' => $user->user->id,
                'name' => $user->user->name,
                'email' => $user->user->email,
                'phone' => $user->user->phone,
                'is_super_admin' => $user->user->is_super_admin,
            ]);

            $data = [
                'name'     => $lab->name,
                'lab_type'  => $lab->lab_type,
                'lab_code'  => $lab->lab_code,
                'location_limit' => $lab->location_limit,
                'user_limit' => $lab->user_limit,

                // Lab Emails
                'emails' => $lab->contacts
                    ->where('type', 'email')
                    ->map(function ($c) use ($labusers) {
                        $user = $labusers->firstWhere('email', $c->value);
                        return [
                        'id'         => $c->id,
                        'user_id' => $user['id'] ?? null,
                        'type'       => 'email',
                        'value'      => $c->value,
                        'label'      => $c->label,
                        'is_primary' => (bool) $c->is_primary,
                        ];
                    })
                    ->values(),

                // Lab Phones
                'phones' => $lab->contacts
                    ->where('type', 'phone')
                    ->map(function ($c) use ($labusers) {
                        $user = $labusers->firstWhere('phone', $c->value);
                        return [
                        'id'         => $c->id,
                        'user_id' => $user['id'] ?? null,
                        'type'       => 'phone',
                        'value'      => $c->value,
                        'label'      => $c->label,
                        'is_primary' => (bool) $c->is_primary,
                        ];
                    })
                    ->values(),

                 // location
                'location' => $lab->location->map(function ($location) use ($labusers, $ctx) {
                    $primaryEmail = $location->contacts->where('type', 'email')->firstWhere('is_primary', true);
                    $primaryPhone = $location->contacts->where('type', 'phone')->firstWhere('is_primary', true);

                    return [
                        'zone_id' => $ctx['owner_type'] == 'super_admin' ? $location->cluster->zone->parent_id : $location->cluster->zone->id,
                        'cluster_id' => $ctx['owner_type'] == 'super_admin' ? $location->cluster->parent_id : $location->cluster->id,
                        'location_id' => $ctx['owner_type'] == 'super_admin' ? $location->parent_id : $location->id,
                        'location_name_og' => $location->name,
                        'prefix'    => $location->identifier,
                        'shortName' => $location->short_name,
                        // Location Emails
                        'emails' => $location->contacts
                            ->where('type', 'email')
                            ->map(function ($c) use ($labusers) {
                                $user = $labusers->firstWhere('email', $c->value);
                                return [
                                    'id'         => $c->id,
                                    'user_id' => $user['id'] ?? null,
                                    'type'       => 'email',
                                    'value'      => $c->value,
                                    'label'      => $c->label,
                                    'is_primary' => (bool) $c->is_primary,
                                ];
                            })
                            ->values(),

                        // Location Phones
                        'phones' => $location->contacts
                            ->where('type', 'phone')
                            ->map(function ($c) use ($labusers) {
                                $user = $labusers->firstWhere('phone', $c->value);
                                return [
                                    'id'         => $c->id,
                                    'user_id' => $user['id'] ?? null,
                                    'type'       => 'phone',
                                    'value'      => $c->value,
                                    'label'      => $c->label,
                                    'is_primary' => (bool) $c->is_primary,
                                ];
                            })
                            ->values(),

                        // Instruments
                        'instruments' => $location->instruments
                            ->map->instrument
                            ->pluck($ctx['owner_type'] !== 'lab' ? 'parent_id' : 'id')
                            ->filter()
                            ->values(),
                        // Departments
                        'departments' => $location->departments->map(fn ($dept) => [
                            'id'   => $dept->id,
                            'name' => $ctx['owner_type'] !== 'lab' ? $dept->department->parent_id :$dept->department_id,
                        'instruments' => $dept->instruments->map->instrument
                            ->pluck($ctx['owner_type'] !== 'lab' ? 'parent_id' : 'id')
                            ->filter()
                            ->values(),
                        ])->values(),
                    ];
                })->values(),
               'standard' => [
                    'standard_id' => $lab->standard_id,
                    'clause_documents_link' => $lab->labClauseDocuments
                        ->map(function ($doc) {
                            return $doc->owner_type === 'super_admin'
                                ? $doc->id                 // master
                                : ($doc->parent_id ?? $doc->id); // lab → parent or self
                        })
                        ->values(),
                ],
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
            $lab->update([
                'name' => $request->name,
                'lab_type' => $request->lab_type,
                'lab_code' => $request->lab_code,
                'location_limit' => $request->location_limit,
                'user_limit' => $request->user_limit,
                'created_by' => auth()->id(),
                'standard_id' => $request->standard['standard_id'],
            ]);

            // 2. Update Lab contacts (emails + phones)
            $incomingIds = collect($request->emails ?? [])
                ->pluck('id')
                ->filter()
                ->toArray();

            $incomingPhoneIds = collect($request->phones ?? [])
                ->pluck('id')
                ->filter()
                ->toArray();

            $lab->contacts()
                ->where('type', 'email')
                ->whereNotIn('id', $incomingIds)
                ->delete();

            $lab->contacts()
                ->where('type', 'phone')
                ->whereNotIn('id', $incomingPhoneIds)
                ->delete();

            foreach ($request->emails ?? [] as $email) {
                $lab->contacts()->updateOrCreate(
                    ['id' => $email['id'] ?? null], // match by id
                    [
                        'type'       => 'email',
                        'value'      => $email['value'],
                        'label'      => $email['label'] ?? null,
                        'is_primary' => $email['is_primary'] ?? false,
                    ]
                );
            }

            foreach ($request->phones ?? [] as $phone) {
                $lab->contacts()->updateOrCreate(
                    ['id' => $phone['id'] ?? null],
                    [
                        'type'       => 'phone',
                        'value'      => $phone['value'],
                        'label'      => $phone['label'] ?? null,
                        'is_primary' => $phone['is_primary'] ?? false,
                    ]
                );
            }

            // 3. Update Lab User (super admin)
            $primaryEmail = collect($request->emails ?? [])->firstWhere('is_primary', true);
            $emailUserId = collect($request->emails ?? [])->firstWhere('user_id');
            $primaryPhone = collect($request->phones ?? [])->firstWhere('is_primary', true);
            $phoneUserId = collect($request->phones ?? [])->firstWhere('user_id');

            if ($primaryEmail || $primaryPhone) {
                if($emailUserId['user_id'] == $phoneUserId['user_id']){
                  $superAdmin = user::find($emailUserId['user_id']);
                  $superAdmin->update([
                        'email' => $primaryEmail['value'] ?? null,
                        'dial_code' => $primaryPhone['value'] ? '+91' : null,
                        'phone' => $primaryPhone['value'] ?? null,
                    ]);
                }
            }

            // 4. Update Lab Locations
            // Remove old locations + departments + contacts
            $existingLocations = $lab->location()->get()->keyBy('parent_id');

            $requestLocationIds = collect($request->location ?? [])
                ->pluck('location_id')
                ->toArray();

            foreach ($request->location ?? [] as $loc) {
                $zone = $this->createIfNotExists(Zone::class, $loc['zone_id'], $lab, $loc);
                $cluster = $this->createIfNotExists(Cluster::class,$loc['cluster_id'],$lab,$loc,['zone_id' => $zone->id]);
                $existing = $existingLocations[$loc['location_id']] ?? null;
                if ($existing) {
                    $existing->update([
                        'short_name' => $loc['shortName'] ?? null,
                    ]);
                    $labLocation = $existing;

                    // 2. Update Lab contacts (emails + phones)
                    $labIncomingIds = collect($loc['emails'] ?? [])
                        ->pluck('id')
                        ->filter()
                        ->toArray();

                    $labIncomingPhoneIds = collect($loc['phones'] ?? [])
                        ->pluck('id')
                        ->filter()
                        ->toArray();

                    $labLocation->contacts()
                        ->where('type', 'email')
                        ->whereNotIn('id', $labIncomingIds)
                        ->delete();

                    $labLocation->contacts()
                        ->where('type', 'phone')
                        ->whereNotIn('id', $labIncomingPhoneIds)
                        ->delete();

                    foreach ($loc['emails'] ?? [] as $labEmail) {
                        $labLocation->contacts()->updateOrCreate(
                            ['id' => $labEmail['id'] ?? null], // match by id
                            [
                                'type'       => 'email',
                                'value'      => $labEmail['value'],
                                'label'      => $labEmail['label'] ?? null,
                                'is_primary' => $labEmail['is_primary'] ?? false,
                            ]
                        );
                    }

                    foreach ($loc['phones'] ?? [] as $labPhone) {
                        $labLocation->contacts()->updateOrCreate(
                            ['id' => $labPhone['id'] ?? null],
                            [
                                'type'       => 'phone',
                                'value'      => $labPhone['value'],
                                'label'      => $labPhone['label'] ?? null,
                                'is_primary' => $labPhone['is_primary'] ?? false,
                            ]
                        );
                    }

                    // 3. Update Lab User (super admin)
                    $labPrimaryEmail = collect($loc['emails'] ?? [])->firstWhere('is_primary', true);
                    $labEmailUserId = collect($loc['emails'] ?? [])->firstWhere('user_id');
                    $labPrimaryPhone = collect($loc['phones'] ?? [])->firstWhere('is_primary', true);
                    $lanPhoneUserId = collect($loc['phones'] ?? [])->firstWhere('user_id');

                    if ($labPrimaryEmail || $labPrimaryPhone) {
                        if($labEmailUserId['user_id'] == $lanPhoneUserId['user_id']){
                            $userId = $labEmailUserId['user_id'] ?? $lanPhoneUserId['user_id'];
                            $userAdmin = User::find($userId);
                            $userAdmin->update([
                                'email' => $labPrimaryEmail['value'] ?? null,
                                'dial_code' => $labPrimaryPhone['value'] ? '+91' : null,
                                'phone' => $labPrimaryPhone['value'] ?? null,
                            ]);
                        }
                        
                    }

                    $labIncomingInstrumentIds = collect($loc['instruments'] ?? [])
                        ->pluck('id')
                        ->filter()
                        ->toArray();

                    $labLocation->instruments()->whereNotIn('instrument_id', $labIncomingInstrumentIds)->delete();

                    // Instruments
                    foreach ($loc['instruments'] ?? [] as $locInstrument) {
                        $this->assignInstrument($locInstrument, $lab, $labLocation->id);
                    }
                    
                    // Departments
                    foreach ($loc['departments'] ?? [] as $dept) {
                        $departmentOg = Department::where([
                            ['parent_id', '=', $dept['name']],
                            ['owner_type', '=', 'lab'],
                            ['owner_id', '=', $lab->id],
                        ])->first();

                    $LabLocationDepartment = LabLocationDepartment::updateOrCreate(
                        [
                            'location_id'   => $labLocation->id,
                            'department_id' => $departmentOg->id ?? null,
                        ],
                        [
                            // any future fields (safe for update)
                            'location_id'   => $labLocation->id,
                            'department_id' => $departmentOg->id ?? null,
                        ]
                    );
                    $deptIncomingInstrumentIds = collect($dept['instruments'] ?? [])
                        ->pluck('id')
                        ->filter()
                        ->toArray();

                    $LabLocationDepartment->instruments()->whereNotIn('instrument_id', $deptIncomingInstrumentIds)->delete();

                    foreach ($dept['instruments'] ?? [] as $deptInstrument) {
                        $this->assignInstrument($deptInstrument, $lab, $labLocation->id, $LabLocationDepartment->id);
                    }
                }

                } else {
                    $labLocation = $this->createIfNotExists(Location::class, $loc['location_id'], $lab, $loc,['cluster_id' => $cluster->id,'short_name' => $loc['shortName']]);
                    $this->saveContacts($labLocation, $loc['emails'], $loc['phones']);

                    $primaryEmail = collect($loc['emails'] ?? [])->firstWhere('is_primary', true);
                    $primaryPhone = collect($loc['phones'] ?? [])->firstWhere('is_primary', true);

                    if ($primaryEmail || $primaryPhone) {
                        $admin = $this->createLabUser($primaryEmail['value'] ?? null, $primaryPhone['value'] ?? null, false);
                        $this->assignRoleToUser($admin, $lab, 2,true, $labLocation->id);
                    }

                    // Instruments
                    foreach ($loc['instruments'] ?? [] as $locInstrument) {
                        $this->assignInstrument($locInstrument, $lab, $labLocation->id);
                    }
                    
                    // Departments
                    foreach ($loc['departments'] ?? [] as $dept) {
                        $departmentOg = Department::where([
                            ['parent_id', '=', $dept['name']],
                            ['owner_type', '=', 'lab'],
                            ['owner_id', '=', $lab->id],
                        ])->first();

                        $LabLocationDepartment =  LabLocationDepartment::create([
                            'location_id' => $labLocation->id,
                            'department_id' => $departmentOg->id ?? null,
                        ]);

                        foreach ($dept['instruments'] ?? [] as $deptInstrument) {
                            $this->assignInstrument($deptInstrument, $lab, $labLocation->id, $LabLocationDepartment->id);
                        }
                    }
                }
            }

            if (!empty($request->documents)) {
                foreach ($request->documents as $documentId) {
                    $originalDocument = Document::with([
                        'currentVersion.templates.template.currentVersion',
                        'departments'
                    ])->find($documentId);

                    if ($originalDocument) {
                        $this->cloneDocumentForLab($originalDocument, $lab, $superAdmin);
                    }
                }
            }

            if (!empty($request->standard['clause_documents_link'])) {

                $linkIds = $request->standard['clause_documents_link'];

                // ✅ Fetch master links
                $links = ClauseDocumentLink::whereIn('id', $linkIds)->get();

                // ✅ Parent document IDs
                $parentDocIds = $links->pluck('document_id')->unique();

                // ✅ Lab documents
                $labDocuments = Document::whereIn('parent_id', $parentDocIds)
                    ->where('owner_type', 'lab')
                    ->where('owner_id', $lab->id)
                    ->with('currentVersion')
                    ->get()
                    ->keyBy('parent_id');

                // ✅ Existing overrides (IMPORTANT)
                $existingOverrides = ClauseDocumentLink::where([
                    'owner_type' => 'lab',
                    'owner_id'   => $lab->id,
                ])->get()->keyBy('parent_id');

                $processedIds = [];

                foreach ($links as $link) {

                    $labDoc = $labDocuments[$link->document_id] ?? null;

                    if (!$labDoc || !$labDoc->currentVersion) {
                        continue;
                    }

                    $processedIds[] = $link->id;

                    // 🔁 UPDATE or CREATE
                    ClauseDocumentLink::updateOrCreate(
                        [
                            'parent_id'  => $link->id,
                            'owner_type' => 'lab',
                            'owner_id'   => $lab->id,
                        ],
                        [
                            'standard_id'         => $link->standard_id,
                            'clause_id'           => $link->clause_id,
                            'document_id'         => $labDoc->id,
                            'document_version_id' => $labDoc->currentVersion->id,
                        ]
                    );
                }

                // ❌ DELETE removed links (VERY IMPORTANT)
                ClauseDocumentLink::where([
                    'owner_type' => 'lab',
                    'owner_id'   => $lab->id,
                ])
                ->whereNotIn('parent_id', $processedIds)
                ->delete();
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

    public function labAssignments()
    {
        /* ===============================
        USER FILTERING
        =============================== */

        $currentUser = auth()->user();
        $labUser = LabUser::where('user_id', $currentUser->id)->first();

        $userQuery = User::where('is_super_admin', false);

        if (!$labUser) {
            $userQuery->whereNotIn('id', function ($q) {
                $q->select('user_id')->from('lab_users');
            });
        }

        $users     = $userQuery->get();
        $userIds  = $users->pluck('id');
        $totalUsers = $users->count();

        /* ===============================
        BASE TOTALS
        =============================== */

        $totalLabs      = Lab::count();
        $totalLocations = Location::where('owner_type', 'lab')->count();

        /* ===============================
        LOAD DATA
        =============================== */

        $labs = Lab::with('location')->get()->map(function ($lab) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($lab->id);

            $lab->roles = Role::where('lab_id', $lab->id)
                ->orderBy('level')
                ->get();

            return $lab;
        });

        $assignments = UserAssignment::select(
                'user_id',
                'lab_id',
                'location_id'
            )->get();

        $assingn= UserAssignment::get();

        /* ===============================
        LOCATION ASSIGNMENT
        =============================== */

        $assignedLocations = $assignments
            ->whereNotNull('location_id')
            ->pluck('location_id')
            ->unique()
            ->count();

        $pendingLocations = $totalLocations - $assignedLocations;

        /* ===============================
        USER ASSIGNMENT
        =============================== */

        $assignedUsers = $assignments
            ->pluck('user_id')
            ->unique()
            ->count();

        $pendingUsers = $totalUsers - $assignedUsers;

        /* ===============================
        LAB ASSIGNMENT (IMPORTANT PART)
        =============================== */

        $assignedLabs = 0;
        $pendingLabs  = 0;

        foreach ($labs as $lab) {

            $totalLabLocations = $lab->location->count();

            // If a lab has no locations → pending
            if ($totalLabLocations === 0) {
                $pendingLabs++;
                continue;
            }

            // Locations that have at least one assignment
            $assignedLabLocations = $assignments
                ->where('lab_id', $lab->id)
                ->whereNotNull('location_id')
                ->pluck('location_id')
                ->unique()
                ->count();

            if ($assignedLabLocations === $totalLabLocations) {
                $assignedLabs++;
            } else {
                $pendingLabs++;
            }
        }

        /* ===============================
        RESPONSE
        =============================== */

        return response()->json([
            'success' => true,
            'data' => [
                'lab_count'          => $totalLabs,
                'lab_location_count' => $totalLocations,
                'user_limit'         => $totalUsers,

                'users'   => $users,
                'labs'    => $labs,
                'assingn' => $assignments,
                'assingn' => $assingn,


                'lab_assignment' => [
                    'assigned' => $assignedLabs,
                    'pending'  => $pendingLabs,
                ],

                'location_assignment' => [
                    'assigned' => $assignedLocations,
                    'pending'  => $pendingLocations,
                ],

                'user_assignment' => [
                    'assigned' => $assignedUsers,
                    'pending'  => $pendingUsers,
                ],
            ],
        ], 200);
    }

    public function assignmentUserRole(Request $request)
    {
        $validated = $request->validate([
            'user_id'     => ['required', 'exists:users,id'],
            'lab_id'      => ['required', 'exists:labs,id'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'role_id'     => ['required', 'exists:roles,id'],
            'action'      => ['required', 'in:assign,remove'],
        ]);

        return DB::transaction(function () use ($validated) {

            $user = User::findOrFail($validated['user_id']);

            app(PermissionRegistrar::class)
                ->setPermissionsTeamId($validated['lab_id']);

            $role = Role::where('lab_id', $validated['lab_id'])
                ->findOrFail($validated['role_id']);

        /* =====================================
            LEVEL VALIDATION (NEW - IMPORTANT)
        ===================================== */

        // Lab level → only level 1
        if (is_null($validated['location_id']) && $role->level != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Only Level 1 roles allowed at Lab level'
            ], 422);
        }

        // Location level → no level 1
        if (!is_null($validated['location_id']) && $role->level == 1) {
            return response()->json([
                'success' => false,
                'message' => 'Level 1 roles not allowed at Location level'
            ], 422);
        }

        /* =====================================
           PREVENT DUPLICATE (NEW FIX)
        ===================================== */

        $exists = UserAssignment::where([
            'user_id'     => $user->id,
            'lab_id'      => $validated['lab_id'],
            'location_id' => $validated['location_id'],
            'role_id'     => $role->id,
        ])->exists();

        /* =====================================
           ASSIGN
        ===================================== */

        if ($validated['action'] === 'assign') {

            if (!$exists) {

                    $user->assignRole($role);
                    $user->syncPermissions(
                        $role->permissions->pluck('name')->toArray()
                    );

                    UserAssignment::updateOrCreate(
                        [
                            'user_id'     => $user->id,
                            'lab_id'      => $validated['lab_id'],
                            'location_id' => $validated['location_id'],
                            'role_id'     => $role->id,
                        ],
                        [] // no extra fields yet
                    );
                }
                }
                else{
                    $user->removeRole($role);
                    $user->syncPermissions(
                        $user->getRoleNames()->flatMap(function($r) use ($validated) {
                            return Role::where('lab_id', $validated['lab_id'])->findByName($r)->permissions->pluck('name')->toArray();
                        })->unique()->toArray()
                    );

                    UserAssignment::where([
                        'user_id'     => $user->id,
                        'lab_id'      => $validated['lab_id'],
                        'location_id' => $validated['location_id'],
                        'role_id'     => $role->id,
                    ])->delete();
                }

            return response()->json([
                'success' => true,
                'message' => "User role {$validated['action']} successfully",
            ], 200);

        });
    }
    
    protected function saveContacts($model, $emails = [], $phones = [])
    {
        foreach ($emails as $email) {
            $model->contacts()->create([
                'type' => 'email',
                'value' => $email['value'] ?? null,
                'label' => $email['label'] ?? null,
                'is_primary' => $email['is_primary'] ?? false,
            ]);
        }

        foreach ($phones as $phone) {
            $model->contacts()->create([
                'type' => 'phone',
                'value' => $phone['value'] ?? null,
                'label' => $phone['label'] ?? null,
                'is_primary' => $phone['is_primary'] ?? false,
            ]);
        }
    }

    protected function createRoleWithPermissions($lab, $modules, $level)
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($lab->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::create([
            'name' => $level == 1 ? 'Super Admin' : 'Admin',
            'lab_id' => $lab->id,
            'level' => $level,
            'description' => $level == 1 ? 'Super Admin Role' : 'Admin Role'
        ]);

        $moduleMap = [];
        foreach ($modules as $module) {
            $moduleMap[$module['key']] = $module['key'];
        }

        $permissions = [];
        foreach ($modules as $moduleId => $module) {
            foreach ($module['accessor'] as $action) {
                $permissions[] = $module['key'] . '.' . $action['value'];
            }
        }

        $role->syncPermissions($permissions);

        app(PermissionRegistrar::class)->setPermissionsTeamId($lab->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role;
    }

    protected function createLabUser($email, $phone, $isSuperAdmin = false)
    {
        return User::firstOrCreate(
            ['email' => $email ?? 'admin@example.com'],
            [
                'name' => $isSuperAdmin ? 'Lab Admin' : 'Admin User',
                'username' => Str::lower(Str::random(10)),
                'dial_code' => $phone ? '+91' : null,
                'phone' => $phone ?? null,
                'password' => Hash::make($isSuperAdmin ? 'superadmin1234' : 'admin123'),
                'is_super_admin' => $isSuperAdmin,
            ]
        );
    }
    
    protected function assignRoleToUser($user, $lab, $level,$labAccess = false, $labLocationId = null)
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($lab->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $role = Role::where('level', $level)->where('lab_id', $lab->id)->firstOrFail();
        $user->assignRole($role);
        $user->syncPermissions($role->permissions->pluck('name')->toArray());

        $labUser = LabUser::firstOrCreate([
            'lab_id' => $lab->id,
            'user_id' => $user->id,
        ]);
        if($labAccess){
            LabUserAccess::firstOrCreate([
                'lab_user_id' => $labUser->id,
                'location_id' => $labLocationId,
                'role_id'     => $role->id,
            ], [
                'status' => 'active',
                'granted_at' => now(),
            ]);
        }
    }

    protected function initializeLabMasters($lab)
    {
        foreach (Department::SuperAdmin()->get() as $dept) {
            Department::firstOrCreate([
                'parent_id'  => $dept->id,
                'owner_type' => 'lab',
                'owner_id'   => $lab->id,
            ], [
                'name'       => $dept->name,
                'identifier' => $dept->identifier,
            ]);
        }

        foreach (Unit::SuperAdmin()->get() as $unit) {
            Unit::firstOrCreate([
                'parent_id'  => $unit->id,
                'owner_type' => 'lab',
                'owner_id'   => $lab->id,
            ], [
                'name' => $unit->name,
            ]);
        }
    }

    protected function assignInstrument($instrumentId, $lab, $locationId, $deptId = null)
    {
        $instrument = $this->createOrGetInstrument($instrumentId, $lab);
        if (!$instrument) return;

        LabInstrumentAssignment::firstOrCreate([
            'instrument_id' => $instrument->id,
            'lab_id'        => $lab->id,
            'location_id'   => $locationId,
            'lab_location_department_id' => $deptId,
        ], [
            'is_active' => true,
        ]);
    }

    protected function createIfNotExists($model, $parentId, $lab, $loc, $extra = [])
    {
        $record = $model::where('parent_id', $parentId)
            ->where('owner_type', 'lab')
            ->where('owner_id', $lab->id)
            ->first();

        $parent = $model::findOrFail($parentId);

        // ✅ ONLY for Location model → increment identifier
        if ($model === Location::class && $record) {
            $identifier = $this->generateNextIdentifier($model, $parent->identifier);
        } else {
            $identifier = $parent->identifier;
        }

        // If already exists and NOT location → return existing
        if ($record && $model !== Location::class) {
            return $record;
        }

        return $model::create(array_merge([
            'parent_id'  => $parent->id,
            'name'       => $parent->name,
            'identifier' => $identifier,
            'owner_type' => 'lab',
            'owner_id'   => $lab->id,
            'status'     => 'completed',
        ], $extra));
    }

    protected function generateNextIdentifier($model, $baseIdentifier)
    {
        $existing = $model::where('identifier', 'LIKE', $baseIdentifier . '%')
            ->pluck('identifier')
            ->toArray();

        if (!in_array($baseIdentifier, $existing)) {
            return $baseIdentifier;
        }

        $max = 0;

        foreach ($existing as $id) {
            if (preg_match('/-(\d+)$/', $id, $matches)) {
                $num = (int) $matches[1];
                $max = max($max, $num);
            }
        }

        return $baseIdentifier . '-' . str_pad($max + 1, 2, '0', STR_PAD_LEFT);
    }

    protected function createOrGetInstrument($instrumentId, $lab)
    {
        $original = Instrument::find($instrumentId);

        if (!$original) return null;

        return Instrument::firstOrCreate([
            'parent_id'  => $original->id,
            'owner_type' => 'lab',
            'owner_id'   => $lab->id,
        ], [
            'name'        => $original->name,
            'identifier'  => $original->identifier,
            'short_name'  => $original->short_name,
            'manufacturer'=> $original->manufacturer,
            'serial_no'   => $original->serial_no,
            'status'      => $original->status,
        ]);
    }

    protected function cloneDocumentForLab(Document $original, Lab $lab, $user)
    {
        return DB::transaction(function () use ($original, $lab, $user) {

            // ✅ 1. CHECK if already cloned
            $existingDoc = Document::where([
                'parent_id'  => $original->id,
                'owner_type' => 'lab',
                'owner_id'   => $lab->id,
            ])->first();

            if ($existingDoc) {
                return $existingDoc; // ✅ already exists → skip cloning
            }

            // ✅ 2. CATEGORY (same logic)
            $masterCategory = $original->category;

            $category = Category::where([
                'name'     => $masterCategory->name,
                'owner_id' => $lab->id,
            ])->first();

            if (! $category) {
                $category = $masterCategory->replicate([
                    'id', 'created_at', 'updated_at', 'owner_id', 'owner_type'
                ]);

                $category->parent_id  = $masterCategory->id;
                $category->owner_type = 'lab';
                $category->owner_id   = $lab->id;

                $category->save();
            }

            // ✅ 3. SUB CATEGORIES
            foreach ($masterCategory->subCategories as $masterSub) {
                $labSub = SubCategory::where([
                    'identifier' => $masterSub->identifier,
                    'cat_id'     => $category->id,
                    'owner_id'   => $lab->id,
                ])->first();

                if (! $labSub) {
                    $labSub = $masterSub->replicate([
                        'id', 'created_at', 'updated_at', 'cat_id'
                    ]);

                    $labSub->parent_id  = $masterSub->id;
                    $labSub->cat_id     = $category->id;
                    $labSub->owner_type = 'lab';
                    $labSub->owner_id   = $lab->id;

                    $labSub->save();
                }
            }

            // ✅ 4. DOCUMENT NUMBER SAFE
            $baseNumber = preg_replace('/-\d+$/', '', $original->number);
            $suffix = 1;

            while (Document::where('number', "{$baseNumber}-{$suffix}")->exists()) {
                $suffix++;
            }

            // ✅ 5. CREATE DOCUMENT
            $newDocument = $original->replicate();
            $newDocument->category_id = $category->id;
            $newDocument->owner_type  = 'lab';
            $newDocument->owner_id    = $lab->id;
            $newDocument->number      = "{$baseNumber}-{$suffix}";
            $newDocument->parent_id   = $original->id;
            $newDocument->save();

            // ✅ 6. DEPARTMENTS (SAFE)
            foreach ($original->departments as $dept) {
                $departmentOg = Department::where([
                    ['parent_id', '=', $dept->id],
                    ['owner_type', '=', 'lab'],
                    ['owner_id', '=', $lab->id],
                ])->first();

                if (!$departmentOg) continue;

                DocumentDepartment::firstOrCreate([
                    'document_id'   => $newDocument->id,
                    'department_id' => $departmentOg->id,
                ]);
            }

            // ✅ 7. VERSION (SAFE)
            $version = $original->currentVersion;
            $newVersion = $version->replicate();
            $newVersion->document_id = $newDocument->id;
            $newVersion->major_version = 1;
            $newVersion->minor_version = 0;
            $newVersion->full_version = '1.0';
            $newVersion->version_status = 'active';
            $newVersion->workflow_state = $newDocument->mode == 'create' ? 'prepared' : 'issued';
            $newVersion->save();

            // ✅ 8. TEMPLATE ONLY IF NOT EXISTS
            if($newDocument->mode == 'create'){

                foreach ($version->templates as $template) {
                    $originalTemplate = $template->template;

                    $existingTemplate = Template::where([
                        'parent_id' => $originalTemplate->id,
                        'owner_id'  => $lab->id,
                    ])->first();

                    if ($existingTemplate) continue;

                    $newTemplate = $originalTemplate->replicate();
                    $newTemplate->parent_id = $originalTemplate->id;
                    $newTemplate->owner_type = 'lab';
                    $newTemplate->owner_id = $lab->id;
                    $newTemplate->save();

                    $originalTemplateVersion = $originalTemplate->currentVersion;

                    $newTemplateVersion = $originalTemplateVersion->replicate();

                    $newTemplateVersion->template_id = $newTemplate->id;
                    $newTemplateVersion->major = 1;
                    $newTemplateVersion->minor = 0;
                    $newTemplateVersion->change_type = 'major';
                    $newTemplateVersion->message = 'Initial version';
                    $newTemplateVersion->changed_by = $user->id;
                    $newTemplateVersion->save();

                    TemplateChangeHistory::create([
                        'template_id'=>$newTemplate->id,
                        'template_version_id'=>$newTemplateVersion->id,
                        'field_name'=>null,
                        'old_value'=>null,
                        'new_value'=>json_encode([
                            'name'=>$newTemplate->name,
                            'type'=>$newTemplate->type,
                            'css'=>$newTemplateVersion->css,
                            'html'=>$newTemplateVersion->html,
                            'json'=>$newTemplateVersion->json_data
                        ]),
                        'change_context'=>'create',
                        'changed_by'=> $user->id,
                        'message'=> 'Template created'
                    ]);

                    DocumentVersionTemplate::firstOrCreate([
                        'document_version_id' => $newVersion->id,
                        'template_id'         => $newTemplate->id,
                        'template_version_id' => $newTemplateVersion->id,
                        'type'                => $newTemplate->type,
                    ]);
                }

                DocumentVersionWorkflowLog::firstOrCreate([
                    'document_version_id' => $newVersion->id,
                    'step_type' => 'prepared',
                ], [
                    'step_status' => 'pending',
                    'performed_by' => $user->id,
                    'comments' => 'Initial preparation',
                ]);
            }

            return $newDocument;
        });
    }
}
