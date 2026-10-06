<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

class ReceiptController extends Controller
{
    /**
     * Display a printable HTML receipt for a payment (FR-9).
     */
    public function show(Request $request, string $or_number): Response|JsonResponse
    {
        $token = $request->bearerToken() ?: $request->query('token');

        if (! $token || trim((string) $token) === '') {
            return response()->json([
                'error' => 'UNAUTHENTICATED',
            ], 401);
        }

        $accessToken = PersonalAccessToken::findToken((string) $token);
        if (! $accessToken) {
            return response()->json([
                'error' => 'UNAUTHENTICATED',
            ], 401);
        }

        $tokenable = $accessToken->tokenable;
        $user = $tokenable instanceof User ? $tokenable->fresh() : null;
        if (! $user instanceof User) {
            return response()->json([
                'error' => 'UNAUTHENTICATED',
            ], 401);
        }

        if (! $user->is_active) {
            return response()->json([
                'error' => 'ACCOUNT_DEACTIVATED',
            ], 403);
        }

        $trimmedOr = trim($or_number);
        if ($trimmedOr === '') {
            return response()->json([
                'error' => 'NOT_FOUND',
            ], 404);
        }

        $payment = Payment::with(['student.user'])->where('or_number', $trimmedOr)->first();
        if (! $payment) {
            return response()->json([
                'error' => 'NOT_FOUND',
            ], 404);
        }

        if ($user->role === 'student') {
            if (! $user->student || $payment->student_id !== $user->student->id) {
                return response()->json([
                    'error' => 'UNAUTHORIZED_ROLE',
                ], 403);
            }
        } elseif (! in_array($user->role, ['cashier', 'admin'], true)) {
            return response()->json([
                'error' => 'UNAUTHORIZED_ROLE',
            ], 403);
        }

        $student = $payment->student;
        $studentUser = $student->user;
        $studentName = htmlspecialchars($studentUser->name, ENT_QUOTES, 'UTF-8');
        $studentNumber = htmlspecialchars($student->student_number, ENT_QUOTES, 'UTF-8');
        $orNumber = htmlspecialchars($payment->or_number, ENT_QUOTES, 'UTF-8');
        $paymentType = htmlspecialchars($payment->payment_type, ENT_QUOTES, 'UTF-8');
        $amount = htmlspecialchars($payment->amount_formatted, ENT_QUOTES, 'UTF-8');
        $paidAt = htmlspecialchars($payment->paid_at->format('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Official Receipt - {$orNumber}</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            margin: 40px;
            color: #222;
            background-color: #f9f9f9;
        }
        .receipt-card {
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 32px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .header h1 {
            margin: 0 0 6px 0;
            font-size: 22px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px dashed #eee;
        }
        .label {
            font-weight: bold;
            color: #555;
        }
        .value {
            text-align: right;
            font-weight: 500;
        }
        .footer {
            margin-top: 32px;
            text-align: center;
            font-size: 12px;
            color: #888;
        }
    </style>
</head>
<body>
    <div class="receipt-card">
        <div class="header">
            <h1>CuyoTech University</h1>
            <div>Official Payment Receipt</div>
        </div>
        <div class="row">
            <span class="label">OR Number:</span>
            <span class="value">{$orNumber}</span>
        </div>
        <div class="row">
            <span class="label">Student Name:</span>
            <span class="value">{$studentName}</span>
        </div>
        <div class="row">
            <span class="label">Student Number:</span>
            <span class="value">{$studentNumber}</span>
        </div>
        <div class="row">
            <span class="label">Payment Type:</span>
            <span class="value">{$paymentType}</span>
        </div>
        <div class="row">
            <span class="label">Amount Paid:</span>
            <span class="value">{$amount}</span>
        </div>
        <div class="row">
            <span class="label">Payment Date:</span>
            <span class="value">{$paidAt}</span>
        </div>
        <div class="footer">
            This document serves as an official proof of payment.
        </div>
    </div>
</body>
</html>
HTML;

        return response($html, 200)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
