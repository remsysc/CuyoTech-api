<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashierController extends Controller
{
    /**
     * Record a student payment (FR-8).
     */
    public function recordPayment(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => ['required', 'integer'],
            'amount_centavos' => ['required', 'integer'],
            'payment_type' => ['required', 'string', 'in:tuition,misc_fee,document_fee'],
        ]);

        $amount = (int) $request->input('amount_centavos');

        if ($amount <= 0) {
            return response()->json([
                'error' => 'INVALID_AMOUNT',
            ], 400);
        }

        $student = Student::whereKey((int) $request->input('student_id'))->first();
        if (! $student) {
            return response()->json([
                'error' => 'STUDENT_NOT_FOUND',
            ], 404);
        }

        $cashier = $request->user() ?? $request->user('sanctum');
        if (! $cashier instanceof User) {
            return response()->json([
                'error' => 'UNAUTHENTICATED',
            ], 401);
        }

        $paymentType = (string) $request->input('payment_type');

        return DB::transaction(function () use ($student, $cashier, $amount, $paymentType) {
            do {
                $orNumber = 'OR-'.now()->format('Y').'-'.str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT);
            } while (Payment::where('or_number', $orNumber)->exists());

            Payment::create([
                'student_id' => $student->id,
                'cashier_id' => $cashier->id,
                'amount_centavos' => $amount,
                'or_number' => $orNumber,
                'payment_type' => $paymentType,
                'paid_at' => now(),
            ]);

            $lockedStudent = Student::whereKey($student->id)->lockForUpdate()->first();
            if ($lockedStudent) {
                $lockedStudent->decrement('balance_centavos', $amount);
            }

            return response()->json([
                'or_number' => $orNumber,
                'receipt_url' => "/receipts/{$orNumber}",
            ], 201);
        });
    }
}
