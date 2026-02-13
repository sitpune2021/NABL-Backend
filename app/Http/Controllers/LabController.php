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
    LabLocation, 
    LabLocationDepartment, 
    LabUser, 
    Contact, 
    User, 
    UserLocationDepartmentRole, 
    LabClauseDocument, 
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
    UserAssignment
    };

class LabController extends Controller
{

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $authUser = auth()->user();

        $labIds = LabUser::where('user_id', $authUser->id)->pluck('lab_id');

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
    public function store(Request $request, NavigationAccessService $service)
    {
        DB::beginTransaction();
        
        try {
            // 1. Create Lab
            $lab = Lab::create([
                'name' => $request->name,
                'lab_type' => $request->lab_type,
                'lab_code' => $request->lab_code,
                'address' => $request->address ?? null,
                'loaction_count' => $request->loaction_count,
                'user_count' => $request->user_count
            ]);
            $modules = $service->getAccessModules(true, false);
            foreach ($modules as $module) {
                foreach ($module['accessor'] as $perm) {
                    Permission::firstOrCreate([
                        'name' => $module['key'] . '.' . $perm['value'],
                    ]);
                }
            }

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
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $masterRoles = Role::where('lab_id', 0)->get();
            foreach ($masterRoles as $masterRole) {
                    app(PermissionRegistrar::class)->setPermissionsTeamId($lab->id);
                    $labRole = Role::Create(
                        [
                            'name'       => $masterRole->name,
                            'lab_id'     => $lab->id,
                            'level'       => $masterRole->level,
                            'description' => $masterRole->description,
                        ]
                    );
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
                    $labRole->syncPermissions($permissions);

                    app(PermissionRegistrar::class)->forgetCachedPermissions();
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
                app(PermissionRegistrar::class)->setPermissionsTeamId($lab->id); // MASTER

                $labSuperAdminRole = Role::where('level', 1)
                    ->where('lab_id', $lab->id)
                    ->firstOrFail();

                $user->assignRole($labSuperAdminRole);
                $permissions = $labSuperAdminRole->permissions->pluck('name')->toArray();
                $user->syncPermissions($permissions);

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

                    app(PermissionRegistrar::class)->setPermissionsTeamId($lab->id); // MASTER

                    $labadminRole = Role::where('level', 2)
                        ->where('lab_id', $lab->id)
                        ->firstOrFail();

                    $admin->assignRole($labadminRole);
                    $adminpermissions = $labadminRole->permissions->pluck('name')->toArray();
                    $admin->syncPermissions($adminpermissions);
                    LabUser::firstOrCreate([
                        'lab_id' => $lab->id,
                        'user_id' => $admin->id,
                    ]);

                    $department = Department::SuperAdmin()->get();
                    foreach ($department as $dept) {
                        Department::create([
                            'parent_id'  => $dept->id,
                            'name'       => $dept->name,
                            'identifier' => $dept->identifier,
                            'owner_type' => 'lab',
                            'owner_id'   => $lab->id,
                        ]);
                    }

                    $units = Unit::SuperAdmin()->get();
                    foreach ($units as $unit) {
                        Unit::create([
                            'parent_id'  => $unit->id,
                            'name'       => $unit->name,
                            'owner_type' => 'lab',
                            'owner_id'   => $lab->id,
                        ]);
                    }

                    $departmentId = $loc['departments'][0]['name'] ?? null;
                    if ($departmentId) {
                        $departmentOg = Department::where([
                            ['parent_id', '=', $departmentId],
                            ['owner_type', '=', 'lab'],
                            ['owner_id', '=', $lab->id],
                        ])->first();
                        
                        $this->assignULDR(
                            $admin->id,
                            $loc['location_name'],
                            $departmentOg->id,
                            Role::where([
                                'level' => 2,
                                'lab_id' => $lab->id
                            ])->first()->id
                        );
                    }
                }
                // Departments
                foreach ($loc['departments'] ?? [] as $dept) {
                     $departmentOg = Department::where([
                            ['parent_id', '=', $dept['name']],
                            ['owner_type', '=', 'lab'],
                            ['owner_id', '=', $lab->id],
                        ])->first();
                    LabLocationDepartment::create([
                        'lab_location_id' => $labLocation->id,
                        'department_id' => $departmentOg->id ?? null,
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

        if (!empty($request->documents)) {
                foreach ($request->documents as $documentId) {
                    $originalDocument = Document::with([
                        'currentVersion.templates.template.currentVersion',
                        'departments'
                    ])->find($documentId);

                    if ($originalDocument) {
                        $this->cloneDocumentForLab($originalDocument, $lab, $user);
                    }
                }
            }

            DB::commit();
            return response()->json(['message' => 'Lab created successfully', 'lab_id' => $lab->id], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    protected function cloneDocumentForLab(Document $original, Lab $lab, $user)
    {
        return DB::transaction(function () use ($original, $lab ,$user ) {

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


            $baseNumber = preg_replace('/-\d+$/', '', $original->number); // remove trailing -number
            $suffix = 1;
            // 1. Clone document
            while (Document::where('number', "{$baseNumber}-{$suffix}")->exists()) {
                $suffix++;
            }


            $newDocument = $original->replicate();
            $newDocument->category_id = $category->id;
            $newDocument->owner_type  = 'lab';
            $newDocument->owner_id    = $lab->id;
            $newDocument->number      = "{$baseNumber}-{$suffix}";
            $newDocument->parent_id    = $original->id;
            $newDocument->save();

        // 2. Clone departments
            foreach ($original->departments as $dept) {
                 $departmentOg = Department::where([
                            ['parent_id', '=', $dept->id],
                            ['owner_type', '=', 'lab'],
                            ['owner_id', '=', $lab->id],
                        ])->first();
                DocumentDepartment::create([
                    'document_id'   => $newDocument->id,
                    'department_id' => $departmentOg->id,
                ]);
            }

        // 3. Clone versions (all fields)
            $version = $original->currentVersion;
            $newVersion = $version->replicate();
            $newVersion->document_id = $newDocument->id;
            $newVersion->major_version = 1;
            $newVersion->minor_version = 0;
            $newVersion->full_version = '1.0';
            $newVersion->version_status = 'active';
            $newVersion->workflow_state = $newDocument->mode == 'create' ? 'prepared' : 'issued';
            $newVersion->save();
            
            if($newDocument->mode == 'create'){
                // templates
                foreach ($version->templates as $template) {
                    $originalTemplate =  $template->template;

                    $newTemplate = $originalTemplate->replicate();
                    $newTemplate->parent_id = $originalTemplate->id;
                    $newTemplate->owner_type = 'lab';
                    $newTemplate->owner_id = $lab->id;
                    $newTemplate->save();

                    $originalTemplateVersion =  $template->template->currentVersion;

                    $newTemplateVersion = $originalTemplateVersion->replicate();

                    $newTemplateVersion->template_id = $newTemplate->id;
                    $newTemplateVersion->major =1;
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

                    DocumentVersionTemplate::create([
                        'document_version_id' => $newVersion->id,
                        'template_id'         => $newTemplate->id,
                        'template_version_id' => $newTemplateVersion->id,
                        'type'                => $newTemplate->type,
                    ]);
                }

                // workflow logs
                DocumentVersionWorkflowLog::create([
                    'document_version_id' => $newVersion->id,
                    'step_type' => 'prepared',
                    'step_status' => 'pending',
                    'performed_by' =>$user->id,
                    'comments' => 'Initial preparation',
                    'created_at' => now(),
                ]);
            }

            return $newDocument;
        });
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
                'location.locationRecord',
                'location.locationRecord.cluster',
                'labClauseDocuments',
                'users',
            ])->findOrFail($id);
            $ctx = $this->labContext($request);


            $labAdmin = $lab->users->map(fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_super_admin' => $user->is_super_admin,
            ]);

            $standardId = $lab->labClauseDocuments->first()->standard_id ?? null;
            $data = [
                'name'     => $lab->name,
                'lab_type'  => $lab->lab_type,
                'lab_code'  => $lab->lab_code,
                'address'  => $lab->address,
                'loaction_count' => $lab->loaction_count,
                'user_count' => $lab->user_count,

                // Lab Emails
                'emails' => $lab->contacts
                    ->where('type', 'email')
                    ->map(function ($c) use ($labAdmin) {
                        $admin = $labAdmin->firstWhere('email', $c->value);
                        return [
                        'id'         => $c->id,
                        'user_id' => $admin['id'] ?? null,
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
                    ->map(function ($c) use ($labAdmin) {
                        $admin = $labAdmin->firstWhere('phone', $c->value);
                        return [
                        'id'         => $c->id,
                        'user_id' => $admin['id'] ?? null,
                        'type'       => 'phone',
                        'value'      => $c->value,
                        'label'      => $c->label,
                        'is_primary' => (bool) $c->is_primary,
                        ];
                    })
                    ->values(),

                 // location
                'location' => $lab->location->map(function ($location) use ($labAdmin, $ctx) {
                    $primaryEmail = $location->contacts->where('type', 'email')->firstWhere('is_primary', true);
                    $primaryPhone = $location->contacts->where('type', 'phone')->firstWhere('is_primary', true);

                    return [
                        'id'            => $location->id,
                        'zone_name'     => $location->locationRecord->cluster->zone_id ?? null,
                        'cluster_name'  => $location->locationRecord->cluster->id ?? null,
                        'location_name' => $location->location_id,
                        'location_name_og' => $location->locationRecord->name,

                        'prefix'    => $location->prefix,
                        'shortName' => $location->shortName,
                        'address'   => $location->address,

                        // Location Emails
                        'emails' => $location->contacts
                            ->where('type', 'email')
                            ->map(function ($c) use ($labAdmin) {
                                $admin = $labAdmin->firstWhere('email', $c->value);
                                return [
                                    'id'         => $c->id,
                                    'type'       => 'email',
                                    'value'      => $c->value,
                                    'label'      => $c->label,
                                    'is_primary' => (bool) $c->is_primary,
                                    'user_id' => $admin['id'] ?? null
                                ];
                            })
                            ->values(),

                        // Location Phones
                        'phones' => $location->contacts
                            ->where('type', 'phone')
                            ->map(function ($c) use ($labAdmin) {
                                $admin = $labAdmin->firstWhere('phone', $c->value);
                                return [
                                    'id'         => $c->id,
                                    'type'       => 'phone',
                                    'value'      => $c->value,
                                    'label'      => $c->label,
                                    'is_primary' => (bool) $c->is_primary,
                                    'user_id' => $admin['id'] ?? null
                                ];
                            })
                            ->values(),

                    // Instruments
                    // 'instruments' => $location->instruments
                    //     ->pluck('id')
                    //     ->values(),

                        // Departments
                        'departments' => $location->departments->map(fn ($dept) => [
                            'id'   => $dept->id,
                            'name' => $ctx['owner_type'] !== 'lab' ? $dept->department->parent_id :$dept->department_id,
                        // 'instruments' => $dept->instruments
                        //     ->pluck('id')
                        //     ->values(),
                        ])->values(),
                    ];
                })->values(),
                'standard_id' => $standardId,
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
        dd($request);
        exit;
        DB::beginTransaction();

        try {
            $lab = Lab::findOrFail($id);

            // 1. Update Lab basic info
            $lab->update([
                'name' => $request->name,
                'lab_type' => $request->lab_type,
                'lab_code' => $request->lab_code,
                'address' => $request->address ?? null,
                'loaction_count' => $request->loaction_count,
                'user_count' => $request->user_count
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

            if (!empty($request->selectedClauses) && $request->standard_id) {
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
        $totalLocations = LabLocation::count();

        /* ===============================
        LOAD DATA
        =============================== */

        $labs = Lab::with('location.locationRecord')->get()->map(function ($lab) {
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
                'user_count'         => $totalUsers,

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
                if($validated['action'] === 'assign') {

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
}
