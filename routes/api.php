<?php

// use App\Http\Controllers\Api\AuthController;
// use App\Http\Controllers\Api\StoreController;
// use App\Http\Controllers\Api\EmployeeController;
// use App\Http\Controllers\Api\StudentController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\MenuItemController;



Route::apiResource('categories', CategoryController::class);
Route::apiResource('menu-items', MenuItemController::class);

Route::post('/orders', [OrderController::class, 'store']);
Route::get('/orders', [OrderController::class, 'index']);