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

// Public routes
Route::post('/sign-in', [AuthController::class,'login'])->middleware('throttle:5,1');
Route::apiResource('navigation-items', NavigationItemController::class)->only(['index']);

// Protected routes
Route::middleware(['auth:api', 'throttle:api'])->group(function () {
    Route::post('sign-out', [AuthController::class,'logout']);
    Route::get('access-modules', [NavigationItemController::class, 'accessModules']);
    Route::get('role-levels', [RolePermissionsController::class, 'levels']); // Get role
    Route::apiResource('documents', DocumentController::class);
    Route::post('data-entry', [DocumentController::class, 'dataEntry']);
    Route::get('data-entry/{id}', [DocumentController::class, 'getDataEntriesByDocument']);
    Route::get('data-entry', [DocumentController::class, 'dataEntryTask']);

    Route::post('documents/workflow-action', [DocumentController::class, 'workflowAction']);
    Route::get('generate-document-number', [DocumentController::class, 'generateDocumentNumber']);

    Route::apiResource('users', UserController::class);
    Route::apiResource('roles', RolePermissionsController::class);
    Route::get('/categories/lab-master', [CategoryController::class, 'labMasterCategories']);
    Route::post('categories/append-to-master', [CategoryController::class, 'appendLabCategoryToMaster']);
    Route::get('/categories/lab-all', [CategoryController::class, 'labAllCategories']);
    Route::apiResource('categories', CategoryController::class);
    Route::get('sub-categories/lab-master', [SubCategoryController::class, 'labMasterSubCategories']);
    Route::post('sub-categories/append-to-master', [SubCategoryController::class, 'appendLabSubCategoryToMaster']);
    Route::apiResource('sub-categories', SubCategoryController::class);
    Route::get('/departments/lab-master', [DepartmentController::class, 'labMasterDepartments']);
    Route::post('departments/append-to-master', [DepartmentController::class, 'appendLabDepartmentToMaster']);
    Route::apiResource('departments', DepartmentController::class);
    Route::get('/units/lab-master', [UnitController::class, 'labMasterUnits']);
    Route::post('units/append-to-master', [UnitController::class, 'appendLabUnitToMaster']);
    Route::apiResource('units', UnitController::class);
    Route::apiResource('instruments', InstrumentController::class);
    Route::get('templates/lab-master', [TemplateController::class, 'labMasterTemplates']);
    Route::post('templates/append-to-master', [TemplateController::class, 'appendLabTemplateToMaster']);
    Route::apiResource('templates', TemplateController::class);
    Route::get('templates/versions/{templateId}',[TemplateController::class, 'versions'] );
    Route::get('templates/{templateId}/versions/{versionId}', [TemplateController::class, 'showVersion']);
    Route::put('templates/{templateId}/change-current-version',[TemplateController::class, 'changeCurrentVersion']);
    Route::apiResource('zones', ZoneController::class);
    Route::apiResource('clusters', ClusterController::class);
    Route::apiResource('locations', LocationController::class);
    Route::apiResource('labs', LabController::class);
    Route::get('lab-assignments', [LabController::class, 'labAssignments']);
    Route::post('lab-assignments', [LabController::class, 'assignmentUserRole']);
    Route::apiResource('standards', StandardController::class);
    Route::get('standards-current', [StandardController::class, 'currentStandards']);
    Route::apiResource('clauses', ClauseDocumentLinkController::class);
    Route::get('profile/me', [AuthProfileController::class, 'show']);
    Route::get('profile', [AuthProfileController::class,'profile']);
    Route::put('profile/update', [AuthProfileController::class, 'update']);
});
