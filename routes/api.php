<?php

use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Cashier\CashierController;
use App\Http\Controllers\Department\DepartmentClearanceController;
use App\Http\Controllers\Document\DocumentRequestController;
use App\Http\Controllers\Registrar\RegistrarController;
use App\Http\Controllers\Student\StudentPortalController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Student Portal
    Route::middleware('role:student')->prefix('student')->group(function () {
        Route::get('/profile', [StudentPortalController::class, 'profile']);
        Route::get('/subjects', [StudentPortalController::class, 'subjects']);
        Route::get('/grades', [StudentPortalController::class, 'grades']);
    });

    // Registrar
    Route::middleware('role:registrar')->prefix('registrar')->group(function () {
        Route::post('/enrollments', [RegistrarController::class, 'enroll']);
        Route::patch('/enrollments/{id}/grade', [RegistrarController::class, 'encodeGrade']);
    });

    // Cashier
    Route::middleware('role:cashier')->prefix('cashier')->group(function () {
        Route::post('/payments', [CashierController::class, 'recordPayment']);
    });

    // Department Staff
    Route::middleware('role:department_staff')->prefix('department')->group(function () {
        Route::get('/clearances', [DepartmentClearanceController::class, 'index']);
        Route::patch('/clearances/{id}', [DepartmentClearanceController::class, 'update']);
    });

    // Document Requests (FR-18, FR-19, FR-20)
    Route::middleware('role:student')->get('/documents/requests', [DocumentRequestController::class, 'index']);
    Route::middleware('role:student')->post('/documents/requests', [DocumentRequestController::class, 'store']);
    Route::middleware('role:registrar')->patch('/documents/requests/{id}/status', [DocumentRequestController::class, 'updateStatus']);

    // Admin User Management (FR-15)
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::post('/users', [AdminUserController::class, 'store']);
        Route::patch('/users/{id}', [AdminUserController::class, 'update']);
    });
});
