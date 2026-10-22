<?php

use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Cashier\CashierController;
use App\Http\Controllers\Cashier\CashierQueueController;
use App\Http\Controllers\Department\DepartmentClearanceController;
use App\Http\Controllers\Document\DocumentRequestController;
use App\Http\Controllers\Registrar\RegistrarController;
use App\Http\Controllers\Student\StudentClearanceController;
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
        Route::get('/payments', [StudentPortalController::class, 'payments']);
        Route::get('/queue', [CashierQueueController::class, 'studentStatus']);
        Route::post('/queue', [CashierQueueController::class, 'joinQueue']);
        Route::patch('/queue/presence', [CashierQueueController::class, 'updatePresence']);
        Route::delete('/queue', [CashierQueueController::class, 'cancelQueue']);
    });

    // Registrar (FR-5, FR-6, FR-7, FR-19)
    Route::middleware('role:registrar')->prefix('registrar')->group(function () {
        Route::get('/courses', [RegistrarController::class, 'courses']);
        Route::get('/students', [RegistrarController::class, 'students']);
        Route::get('/enrollments', [RegistrarController::class, 'enrollments']);
        Route::post('/enrollments', [RegistrarController::class, 'enroll']);
        Route::patch('/enrollments/{id}/grade', [RegistrarController::class, 'encodeGrade']);
        Route::get('/courses/{id}/roster', [RegistrarController::class, 'roster']);
        Route::get('/document-requests', [RegistrarController::class, 'documentRequests']);
    });

    // Cashier & Payments (FR-8, FR-10, FR-21, FR-22)
    Route::middleware('role:cashier')->prefix('cashier')->group(function () {
        Route::get('/students', [CashierController::class, 'students']);
        Route::get('/payments', [CashierController::class, 'index']);
        Route::post('/payments', [CashierController::class, 'recordPayment']);
        Route::get('/queue', [CashierQueueController::class, 'index']);
        Route::post('/queue/call-next', [CashierQueueController::class, 'callNext']);
        Route::patch('/queue/{id}/status', [CashierQueueController::class, 'updateStatus']);
    });
    Route::middleware('role:cashier,admin')->get('/cashier/students/{id}/payments', [CashierController::class, 'payments']);

    // Department Staff (FR-11)
    Route::middleware('role:department_staff')->prefix('department')->group(function () {
        Route::get('/clearances', [DepartmentClearanceController::class, 'index']);
        Route::patch('/clearances/{id}', [DepartmentClearanceController::class, 'update']);
    });

    // Cross-Department Clearances (FR-12)
    Route::get('/students/{id}/clearances', [StudentClearanceController::class, 'show']);

    // Document Requests (FR-18, FR-19, FR-20)
    Route::middleware('role:student')->get('/documents/requests', [DocumentRequestController::class, 'index']);
    Route::middleware('role:student')->post('/documents/requests', [DocumentRequestController::class, 'store']);
    Route::middleware('role:registrar')->patch('/documents/requests/{id}/status', [DocumentRequestController::class, 'updateStatus']);

    // Admin User Management, Password Reset & Audit Logs (FR-15, FR-16, FR-17)
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::post('/users', [AdminUserController::class, 'store']);
        Route::patch('/users/{id}', [AdminUserController::class, 'update']);
        Route::patch('/users/{id}/password', [AdminUserController::class, 'resetPassword']);
        Route::get('/departments', [AdminUserController::class, 'departments']);
        Route::get('/audit-logs', [AdminUserController::class, 'auditLogs']);
    });
});
