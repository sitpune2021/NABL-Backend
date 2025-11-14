<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NavigationItemController;
use App\Http\Controllers\RolePermissionsController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\SubCategoryController;
use App\Http\Controllers\DepartmentController;


    Route::apiResource('navigation-items', NavigationItemController::class);
    Route::get('/access-modules', [NavigationItemController::class, 'accessModules']);

    Route::apiResource('roles', RolePermissionsController::class);
    Route::apiResource('category', CategoryController::class);
    Route::apiResource('sub-category', SubCategoryController::class);
    Route::apiResource('department', DepartmentController::class);

