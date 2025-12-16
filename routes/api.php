<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\MenuItemController;
use App\Http\Controllers\AdminAuthController;

// Public endpoints (with rate limiting to prevent abuse)
Route::middleware('throttle:60,1')->group(function () {
    Route::post('/admin/login', [AdminAuthController::class, 'login']);
    
    // Menu browsing
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{category}', [CategoryController::class, 'show']);
    Route::get('/menu-items', [MenuItemController::class, 'index']);
    Route::get('/menu-items/{menu_item}', [MenuItemController::class, 'show']);
    
    // Create orders
    Route::post('/orders', [OrderController::class, 'store']);
    
    // Order view and status update
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::patch('/orders/{id}', [OrderController::class, 'update']);
    Route::post('/orders/{id}/complete', [OrderController::class, 'markAsCompleted']);
    
    // Public dashboard stats (for customer-facing dashboard)
    Route::get('/dashboard-stats', [OrderController::class, 'dashboardStats']);
});

// Protected admin endpoints (require authentication)
Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
    Route::post('/admin/logout', [AdminAuthController::class, 'logout']);

    // Category management (create, update, delete)
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::match(['post', 'put', 'patch'], '/categories/{category}', [CategoryController::class, 'update']);
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
    
    // Menu item management (create, update, delete)
    Route::post('/menu-items', [MenuItemController::class, 'store']);
    Route::match(['post', 'put', 'patch'], '/menu-items/{menu_item}', [MenuItemController::class, 'update']);
    Route::delete('/menu-items/{menu_item}', [MenuItemController::class, 'destroy']);
    
    // Admin-only stats
    Route::get('/admin/stats', [OrderController::class, 'adminStats']);
});