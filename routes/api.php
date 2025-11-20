<!-- <?php

// use App\Http\Controllers\Api\AuthController;
// use App\Http\Controllers\Api\StoreController;
// use App\Http\Controllers\Api\EmployeeController;
// use App\Http\Controllers\Api\StudentController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderController;



Route::post('/orders', [OrderController::class, 'store']);
Route::get('/orders', [OrderController::class, 'index']);


// Auth
// Route::post('/register', [AuthController::class, 'register']);
// Route::post('/login', [AuthController::class, 'login']);

// Protected routes
// Route::middleware('auth:sanctum')->group(function () {
//     Route::post('/logout', [AuthController::class, 'logout']);
//     Route::get('/profile', [AuthController::class, 'profile']);
    
    // Customers (GET only)
    // Route::apiResource('stores', StoreController::class)->only(['index', 'show']);
    // Route::apiResource('employees', EmployeeController::class)->only(['index', 'show']);
    // Route::apiResource('students', StudentController::class)->only(['index', 'show']);

    // Admins (Full CRUD)
    // Route::middleware('role:admin')->group(function () {
    //     Route::apiResource('stores', StoreController::class)->except(['index', 'show']);
    //     Route::apiResource('employees', EmployeeController::class)->except(['index', 'show']);
    //     Route::apiResource('students', StudentController::class)->except(['index', 'show']);
    // });
// })