<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NavigationItemController;
use App\Http\Controllers\RolePermissionsController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\SubCategoryController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ZoneController;
use App\Http\Controllers\ClusterController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\AuthController;

Route::post('/sign-in', [AuthController::class,'login']);
    Route::apiResource('navigation-items', NavigationItemController::class);


Route::middleware(['auth:api', 'throttle:api'])->group(function () {
    Route::post('/sign-out', [AuthController::class,'logout']);
    Route::get('/access-modules', [NavigationItemController::class, 'accessModules']);
    Route::apiResource('user', UserController::class);
    Route::apiResource('roles', RolePermissionsController::class);
    Route::apiResource('category', CategoryController::class);
    Route::apiResource('sub-category', SubCategoryController::class);
    Route::apiResource('department', DepartmentController::class);
    Route::apiResource('zone', ZoneController::class);
    Route::apiResource('cluster', ClusterController::class);
    Route::apiResource('location', LocationController::class);
});