<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\CashierQueue;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashierQueueController extends Controller
{
    /**
     * Format a queue ticket for JSON responses.
     *
     * @return array<string, mixed>
     */
    private function formatTicket(CashierQueue $q): array
    {
        return [
            'id' => $q->id,
            'queue_date' => $q->queue_date->toDateString(),
            'queue_number' => $q->queue_number,
            'sequence_number' => (int) $q->sequence_number,
            'student_id' => $q->student_id,
            'student_number' => $q->student->student_number,
            'student_name' => $q->student->user->name,
            'student_balance_centavos' => max(0, (int) $q->student->balance_centavos),
            'student_balance_formatted' => $q->student->balance_formatted,
            'amount_centavos' => (int) $q->amount_centavos,
            'amount_formatted' => $q->amount_formatted,
            'payment_type' => $q->payment_type,
            'status' => $q->status,
            'is_present' => (bool) $q->is_present,
            'checked_in_at' => $q->checked_in_at?->toIso8601String(),
            'called_at' => $q->called_at?->toIso8601String(),
            'completed_at' => $q->completed_at?->toIso8601String(),
            'caller_name' => $q->caller?->name,
            'or_number' => $q->or_number,
        ];
    }

    /**
     * Retrieve the authenticated student's queue ticket and live window summary.
     */
    public function studentStatus(Request $request): JsonResponse
    {
        $user = $request->user() ?? $request->user('sanctum');
        $student = $user?->student;

        if (! $student) {
            return response()->json([
                'error' => 'UNAUTHORIZED_ROLE',
            ], 403);
        }

        $today = now()->toDateString();

        $activeTicket = CashierQueue::with(['student.user', 'caller'])
            ->where('student_id', $student->id)
            ->whereDate('queue_date', $today)
            ->whereIn('status', ['waiting', 'serving'])
            ->orderByDesc('sequence_number')
            ->first();

        $latestTicket = CashierQueue::with(['student.user', 'caller'])
            ->where('student_id', $student->id)
            ->whereDate('queue_date', $today)
            ->orderByDesc('sequence_number')
            ->first();

        $servingTicket = CashierQueue::with(['student.user', 'caller'])
            ->whereDate('queue_date', $today)
            ->where('status', 'serving')
            ->orderByDesc('called_at')
            ->orderBy('sequence_number')
            ->first();

        $waitingTotal = CashierQueue::query()
            ->whereDate('queue_date', $today)
            ->where('status', 'waiting')
            ->count();

        $waitingPresent = CashierQueue::query()
            ->whereDate('queue_date', $today)
            ->where('status', 'waiting')
            ->where('is_present', true)
            ->count();

        $aheadCount = 0;
        $presentAheadCount = 0;

        if ($activeTicket && $activeTicket->status === 'waiting') {
            $aheadCount = CashierQueue::query()
                ->whereDate('queue_date', $today)
                ->where('status', 'waiting')
                ->where('sequence_number', '<', $activeTicket->sequence_number)
                ->count();

            $presentAheadCount = CashierQueue::query()
                ->whereDate('queue_date', $today)
                ->where('status', 'waiting')
                ->where('is_present', true)
                ->where('sequence_number', '<', $activeTicket->sequence_number)
                ->count();
        }

        return response()->json([
            'active_ticket' => $activeTicket ? $this->formatTicket($activeTicket) : null,
            'latest_ticket' => $latestTicket ? $this->formatTicket($latestTicket) : null,
            'summary' => [
                'now_serving_number' => $servingTicket?->queue_number,
                'waiting_total' => $waitingTotal,
                'waiting_present' => $waitingPresent,
                'ahead_count' => $aheadCount,
                'present_ahead_count' => $presentAheadCount,
            ],
        ]);
    }

    /**
     * Issue a consecutive cashier queue number for the authenticated student.
     */
    public function joinQueue(Request $request): JsonResponse
    {
        $user = $request->user() ?? $request->user('sanctum');
        $student = $user?->student;

        if (! $student) {
            return response()->json([
                'error' => 'UNAUTHORIZED_ROLE',
            ], 403);
        }

        $request->validate([
            'amount_centavos' => ['required', 'integer', 'min:1'],
            'payment_type' => ['required', 'string', 'in:tuition,misc_fee,document_fee'],
            'is_present' => ['sometimes', 'boolean'],
        ]);

        $amount = (int) $request->input('amount_centavos');
        $paymentType = (string) $request->input('payment_type');
        $isPresent = $request->boolean('is_present', false);
        $today = now()->toDateString();

        return DB::transaction(function () use ($student, $amount, $paymentType, $isPresent, $today): JsonResponse {
            $existing = CashierQueue::with(['student.user', 'caller'])
                ->where('student_id', $student->id)
                ->whereDate('queue_date', $today)
                ->whereIn('status', ['waiting', 'serving'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return response()->json([
                    'error' => 'ACTIVE_QUEUE_EXISTS',
                    'message' => "You already have an active queue ticket ({$existing->queue_number}).",
                    'ticket' => $this->formatTicket($existing),
                ], 409);
            }

            $maxSeq = CashierQueue::query()
                ->whereDate('queue_date', $today)
                ->lockForUpdate()
                ->max('sequence_number');

            $nextSeq = ((int) $maxSeq) + 1;
            $queueNumber = 'C-'.str_pad((string) $nextSeq, 3, '0', STR_PAD_LEFT);

            $ticket = CashierQueue::create([
                'queue_date' => $today,
                'queue_number' => $queueNumber,
                'sequence_number' => $nextSeq,
                'student_id' => $student->id,
                'amount_centavos' => $amount,
                'payment_type' => $paymentType,
                'status' => 'waiting',
                'is_present' => $isPresent,
                'checked_in_at' => $isPresent ? now() : null,
            ]);

            $ticket->load(['student.user', 'caller']);

            return response()->json($this->formatTicket($ticket), 201);
        });
    }

    /**
     * Toggle whether the student is physically present and waiting at the cashier window.
     */
    public function updatePresence(Request $request): JsonResponse
    {
        $user = $request->user() ?? $request->user('sanctum');
        $student = $user?->student;

        if (! $student) {
            return response()->json([
                'error' => 'UNAUTHORIZED_ROLE',
            ], 403);
        }

        $request->validate([
            'is_present' => ['required', 'boolean'],
        ]);

        $today = now()->toDateString();
        $ticket = CashierQueue::with(['student.user', 'caller'])
            ->where('student_id', $student->id)
            ->whereDate('queue_date', $today)
            ->whereIn('status', ['waiting', 'serving'])
            ->orderByDesc('sequence_number')
            ->first();

        if (! $ticket) {
            return response()->json([
                'error' => 'QUEUE_TICKET_NOT_FOUND',
            ], 404);
        }

        $isPresent = $request->boolean('is_present');

        $ticket->update([
            'is_present' => $isPresent,
            'checked_in_at' => $isPresent ? ($ticket->checked_in_at ?? now()) : null,
        ]);

        return response()->json($this->formatTicket($ticket->fresh(['student.user', 'caller']) ?? $ticket));
    }

    /**
     * Cancel the authenticated student's active waiting ticket.
     */
    public function cancelQueue(Request $request): JsonResponse
    {
        $user = $request->user() ?? $request->user('sanctum');
        $student = $user?->student;

        if (! $student) {
            return response()->json([
                'error' => 'UNAUTHORIZED_ROLE',
            ], 403);
        }

        $today = now()->toDateString();
        $ticket = CashierQueue::with(['student.user', 'caller'])
            ->where('student_id', $student->id)
            ->whereDate('queue_date', $today)
            ->whereIn('status', ['waiting', 'serving'])
            ->orderByDesc('sequence_number')
            ->first();

        if (! $ticket) {
            return response()->json([
                'error' => 'QUEUE_TICKET_NOT_FOUND',
            ], 404);
        }

        $ticket->update([
            'status' => 'cancelled',
            'is_present' => false,
        ]);

        return response()->json($this->formatTicket($ticket->fresh(['student.user', 'caller']) ?? $ticket));
    }

    /**
     * Retrieve today's cashier queue and presence counts for the Cashier portal.
     */
    public function index(): JsonResponse
    {
        $today = now()->toDateString();

        $tickets = CashierQueue::with(['student.user', 'caller'])
            ->whereDate('queue_date', $today)
            ->orderBy('sequence_number')
            ->get();

        $serving = $tickets
            ->where('status', 'serving')
            ->sortByDesc('called_at')
            ->first();

        $waitingTickets = $tickets->where('status', 'waiting');
        $waitingPresent = $waitingTickets->where('is_present', true)->count();
        $waitingTotal = $waitingTickets->count();
        $completedToday = $tickets->where('status', 'completed')->count();

        return response()->json([
            'summary' => [
                'now_serving' => $serving ? $this->formatTicket($serving) : null,
                'waiting_total' => $waitingTotal,
                'waiting_present' => $waitingPresent,
                'waiting_en_route' => $waitingTotal - $waitingPresent,
                'completed_today' => $completedToday,
            ],
            'tickets' => $tickets->map(fn (CashierQueue $q): array => $this->formatTicket($q))->values(),
        ]);
    }

    /**
     * Call the next student in the queue, prioritizing students who are physically present at the window.
     */
    public function callNext(Request $request): JsonResponse
    {
        $cashier = $request->user() ?? $request->user('sanctum');
        if (! $cashier instanceof User) {
            return response()->json([
                'error' => 'UNAUTHENTICATED',
            ], 401);
        }

        $includeAbsent = $request->boolean('include_absent', false);
        $today = now()->toDateString();

        return DB::transaction(function () use ($cashier, $includeAbsent, $today): JsonResponse {
            $next = CashierQueue::with(['student.user', 'caller'])
                ->whereDate('queue_date', $today)
                ->where('status', 'waiting')
                ->where('is_present', true)
                ->orderBy('sequence_number')
                ->lockForUpdate()
                ->first();

            if (! $next && $includeAbsent) {
                $next = CashierQueue::with(['student.user', 'caller'])
                    ->whereDate('queue_date', $today)
                    ->where('status', 'waiting')
                    ->orderBy('sequence_number')
                    ->lockForUpdate()
                    ->first();
            }

            if (! $next) {
                return response()->json([
                    'error' => 'NO_WAITING_STUDENT',
                    'message' => 'No present students are currently waiting in the queue.',
                ], 404);
            }

            $next->update([
                'status' => 'serving',
                'is_present' => true,
                'checked_in_at' => $next->checked_in_at ?? now(),
                'called_at' => now(),
                'called_by' => $cashier->id,
            ]);

            return response()->json($this->formatTicket($next->fresh(['student.user', 'caller']) ?? $next));
        });
    }

    /**
     * Update a specific queue ticket's status or physical presence from the Cashier desk.
     */
    public function updateStatus(Request $request, int|string $id): JsonResponse
    {
        $cashier = $request->user() ?? $request->user('sanctum');
        if (! $cashier instanceof User) {
            return response()->json([
                'error' => 'UNAUTHENTICATED',
            ], 401);
        }

        $request->validate([
            'status' => ['sometimes', 'string', 'in:waiting,serving,completed,skipped,cancelled'],
            'is_present' => ['sometimes', 'boolean'],
        ]);

        $ticket = CashierQueue::with(['student.user', 'caller'])->whereKey((int) $id)->first();
        if (! $ticket) {
            return response()->json([
                'error' => 'QUEUE_TICKET_NOT_FOUND',
            ], 404);
        }

        $updates = [];

        if ($request->has('is_present')) {
            $isPresent = $request->boolean('is_present');
            $updates['is_present'] = $isPresent;
            $updates['checked_in_at'] = $isPresent ? ($ticket->checked_in_at ?? now()) : null;
        }

        if ($request->filled('status')) {
            $status = (string) $request->input('status');
            $updates['status'] = $status;

            if ($status === 'serving') {
                $updates['is_present'] = true;
                $updates['checked_in_at'] = $ticket->checked_in_at ?? now();
                $updates['called_at'] = now();
                $updates['called_by'] = $cashier->id;
            } elseif ($status === 'completed') {
                $updates['completed_at'] = now();
                $updates['called_by'] = $ticket->called_by ?? $cashier->id;
            }
        }

        if ($updates !== []) {
            $ticket->update($updates);
        }

        return response()->json($this->formatTicket($ticket->fresh(['student.user', 'caller']) ?? $ticket));
    }
}
