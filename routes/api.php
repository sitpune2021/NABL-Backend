<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    AuthController,
    NavigationItemController,
    RolePermissionsController,
    CategoryController,
    SubCategoryController,
    DepartmentController,
    UnitController,
    TemplateController,
    InstrumentController,
    UserController,
    ZoneController,
    ClusterController,
    LocationController,
    DocumentController,
    LabController,
    StandardController,
    ClauseDocumentLinkController,
    AuthProfileController
};

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Public Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('throttle:5,1')->post('auth/login', [AuthController::class, 'login']);

    Route::get('navigation-items', [NavigationItemController::class, 'index']);


    /*
    |--------------------------------------------------------------------------
    | Protected Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware(['auth:api', 'throttle:api'])->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Authentication
        |--------------------------------------------------------------------------
        */
        Route::post('auth/logout', [AuthController::class, 'logout']);

        /*
        |--------------------------------------------------------------------------
        | Profile
        |--------------------------------------------------------------------------
        */
        Route::prefix('profile')->group(function () {
            Route::get('me', [AuthProfileController::class, 'show']);
            Route::get('/', [AuthProfileController::class, 'profile']);
            Route::put('/', [AuthProfileController::class, 'update']);
        });

        /*
        |--------------------------------------------------------------------------
        | Access & Roles
        |--------------------------------------------------------------------------
        */
        Route::get('access-modules', [NavigationItemController::class, 'accessModules']);
        Route::get('roles/levels', [RolePermissionsController::class, 'levels']);
        Route::apiResource('roles', RolePermissionsController::class);

        /*
        |--------------------------------------------------------------------------
        | Users
        |--------------------------------------------------------------------------
        */
        Route::apiResource('users', UserController::class);

        /*
        |--------------------------------------------------------------------------
        | Documents & Workflow
        |--------------------------------------------------------------------------
        */
        Route::prefix('documents')->group(function () {

            Route::get('generate-number', [DocumentController::class, 'generateDocumentNumber']);
            Route::post('workflow-action', [DocumentController::class, 'workflowAction']);

            Route::prefix('data-entry')->group(function () {
                Route::post('/', [DocumentController::class, 'dataEntry']);
                Route::get('/', [DocumentController::class, 'dataEntryTask']);
                Route::get('{id}', [DocumentController::class, 'getDataEntriesByDocument']);
            });
        });

        Route::apiResource('documents', DocumentController::class);

        /*
        |--------------------------------------------------------------------------
        | Master Sync Pattern (Reusable Structure)
        |--------------------------------------------------------------------------
        */

        Route::prefix('categories')->group(function () {
            Route::get('sync-master', [CategoryController::class, 'labMasterCategories']);
            Route::get('lab-all', [CategoryController::class, 'labAllCategories']);
            Route::post('append-to-master', [CategoryController::class, 'appendLabCategoryToMaster']);
            Route::post('append-to-lab', [CategoryController::class, 'appendMasterCategoryToLab']);
        });
        Route::apiResource('categories', CategoryController::class);

        Route::prefix('sub-categories')->group(function () {
            Route::get('sync-master', [SubCategoryController::class, 'labMasterSubCategories']);
            Route::post('append-to-master', [SubCategoryController::class, 'appendLabSubCategoryToMaster']);
        });
        Route::apiResource('sub-categories', SubCategoryController::class);

        Route::prefix('departments')->group(function () {
            Route::get('lab-master', [DepartmentController::class, 'labMasterDepartments']);
            Route::post('append-to-master', [DepartmentController::class, 'appendLabDepartmentToMaster']);
        });
        Route::apiResource('departments', DepartmentController::class);

        Route::prefix('units')->group(function () {
            Route::get('lab-master', [UnitController::class, 'labMasterUnits']);
            Route::post('append-to-master', [UnitController::class, 'appendLabUnitToMaster']);
        });
        Route::apiResource('units', UnitController::class);

        /*
        |--------------------------------------------------------------------------
        | Templates
        |--------------------------------------------------------------------------
        */
        Route::prefix('templates')->group(function () {
            Route::get('lab-master', [TemplateController::class, 'labMasterTemplates']);
            Route::post('append-to-master', [TemplateController::class, 'appendLabTemplateToMaster']);

            Route::get('versions/{templateId}', [TemplateController::class, 'versions']);
            Route::get('{templateId}/versions/{versionId}', [TemplateController::class, 'showVersion']);
            Route::put('{templateId}/change-current-version', [TemplateController::class, 'changeCurrentVersion']);
        });
        Route::apiResource('templates', TemplateController::class);

        /*
        |--------------------------------------------------------------------------
        | Infrastructure
        |--------------------------------------------------------------------------
        */
        Route::apiResource('zones', ZoneController::class);
        Route::apiResource('clusters', ClusterController::class);
        Route::apiResource('locations', LocationController::class);
        Route::apiResource('labs', LabController::class);

        // Route::prefix('labs')->group(function () {
            Route::get('lab-assignments', [LabController::class, 'labAssignments']);
            Route::post('lab-assignments', [LabController::class, 'assignmentUserRole']);
        // });

        /*
        |--------------------------------------------------------------------------
        | Standards & Clauses
        |--------------------------------------------------------------------------
        */
        Route::get('standards/current', [StandardController::class, 'currentStandards']);
        Route::apiResource('standards', StandardController::class);
        Route::apiResource('clauses', ClauseDocumentLinkController::class);

        /*
        |--------------------------------------------------------------------------
        | Instruments
        |--------------------------------------------------------------------------
        */
        Route::apiResource('instruments', InstrumentController::class);
    });
});