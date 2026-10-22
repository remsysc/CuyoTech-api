<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\CashierQueue;
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

            $activeQueue = CashierQueue::query()
                ->where('student_id', $student->id)
                ->whereIn('status', ['waiting', 'serving'])
                ->orderBy('sequence_number')
                ->lockForUpdate()
                ->first();

            if ($activeQueue) {
                $activeQueue->update([
                    'status' => 'completed',
                    'is_present' => true,
                    'checked_in_at' => $activeQueue->checked_in_at ?? now(),
                    'called_at' => $activeQueue->called_at ?? now(),
                    'completed_at' => now(),
                    'called_by' => $cashier->id,
                    'or_number' => $orNumber,
                ]);
            }

            return response()->json([
                'or_number' => $orNumber,
                'receipt_url' => "/receipts/{$orNumber}",
            ], 201);
        });
    }

    /**
     * Retrieve student payment history newest-first (FR-10).
     * Auth: role in (cashier, admin)
     */
    public function payments(int|string $id): JsonResponse
    {
        $student = Student::whereKey((int) $id)->first();
        if (! $student) {
            return response()->json([
                'error' => 'STUDENT_NOT_FOUND',
            ], 404);
        }

        $payments = Payment::query()
            ->where('student_id', $student->id)
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get();

        $data = $payments->map(fn (Payment $p): array => [
            'or_number' => $p->or_number,
            'amount_centavos' => (int) $p->amount_centavos,
            'payment_type' => $p->payment_type,
            'paid_at' => $p->paid_at->toIso8601String(),
        ])->values();

        return response()->json($data);
    }

    /**
     * List students with balance for cashier counter.
     */
    public function students(): JsonResponse
    {
        $students = Student::with('user')->orderBy('student_number')->get();

        $data = $students->map(fn (Student $s): array => [
            'id' => $s->id,
            'student_number' => $s->student_number,
            'name' => $s->user->name,
            'email' => $s->user->email,
            'balance_centavos' => max(0, (int) $s->balance_centavos),
            'balance_formatted' => $s->balance_formatted,
        ]);

        return response()->json($data);
    }

    /**
     * List recent payments across all students for cashier dashboard.
     */
    public function index(): JsonResponse
    {
        $payments = Payment::with(['student.user', 'cashier'])
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $data = $payments->map(fn (Payment $p): array => [
            'id' => $p->id,
            'or_number' => $p->or_number,
            'student_id' => $p->student_id,
            'student_number' => $p->student->student_number,
            'student_name' => $p->student->user->name,
            'cashier_name' => $p->cashier->name,
            'amount_centavos' => (int) $p->amount_centavos,
            'amount_formatted' => $p->amount_formatted,
            'payment_type' => $p->payment_type,
            'paid_at' => $p->paid_at->toIso8601String(),
        ]);

        return response()->json($data);
    }
}
