<?php

// use App\Http\Controllers\Api\AuthController;
// use App\Http\Controllers\Api\StoreController;
// use App\Http\Controllers\Api\EmployeeController;
// use App\Http\Controllers\Api\StudentController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\MenuItemController;
use App\Http\Controllers\AdminAuthController;



Route::apiResource('categories', CategoryController::class);
Route::apiResource('menu-items', MenuItemController::class);

Route::post('/orders', [OrderController::class, 'store']);
Route::post('/orders/{id}/complete', [OrderController::class, 'markAsCompleted']);


Route::patch('/orders/{id}', [OrderController::class, 'update']);

Route::get('/dashboard-stats', [OrderController::class, 'dashboardStats']);
Route::get('/orders/{id}', [OrderController::class, 'show']);
Route::get('/orders', [OrderController::class, 'index']);




Route::post('/admin/login', [AdminAuthController::class, 'login']);
// Route::middleware('auth:sanctum')->group(function () {
//     Route::post('/menu', [MenuItemController::class, 'store']);
//     Route::delete('/menu/{id}', [MenuItemController::class, 'destroy']);
// });