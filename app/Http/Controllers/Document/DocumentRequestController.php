<?php

namespace App\Http\Controllers\Document;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequest;
use App\Models\Student;
use App\Services\BalanceGuard;
use App\Services\ClearanceGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DocumentRequestController extends Controller
{
    public function __construct(
        protected BalanceGuard $balanceGuard,
        protected ClearanceGuard $clearanceGuard,
    ) {}

    /**
     * Store a newly created document request.
     * Auth: role=student
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['tor', 'cor', 'certification'])],
            'purpose' => ['required', 'string', 'filled'],
        ]);

        // Explicit whitespace-only check for purpose (Edge Case 7)
        if (trim($validated['purpose']) === '') {
            abort(422, 'VALIDATION_FAILED');
        }

        $user = $request->user();
        $student = Student::where('user_id', $user->id)->firstOrFail();

        // 1. Check duplicate open request of the same type (Edge Case 3)
        $hasOpenRequest = DocumentRequest::query()
            ->where('student_id', $student->id)
            ->where('type', $validated['type'])
            ->whereIn('status', ['pending', 'processing', 'ready'])
            ->exists();

        if ($hasOpenRequest) {
            abort(409, 'DUPLICATE_REQUEST');
        }

        // 2. Check balance guard (FR-14)
        $this->balanceGuard->ensureZeroBalance($student);

        // 3. Check clearance guard (FR-13)
        $this->clearanceGuard->ensureClearancesApproved($student);

        // 4. Create document request
        $documentRequest = DocumentRequest::create([
            'student_id' => $student->id,
            'type' => $validated['type'],
            'purpose' => trim($validated['purpose']),
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        return response()->json([
            'id' => $documentRequest->id,
            'status' => $documentRequest->status,
        ], 201);
    }

    /**
     * List authenticated student's document requests (FR-20 polling).
     * Auth: role=student
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $student = Student::where('user_id', $user->id)->firstOrFail();

        $requests = DocumentRequest::query()
            ->where('student_id', $student->id)
            ->orderByDesc('requested_at')
            ->get(['id', 'type', 'purpose', 'status', 'requested_at', 'released_at']);

        return response()->json($requests);
    }

    /**
     * Update the status of a document request.
     * Auth: role=registrar
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['pending', 'processing', 'ready', 'released', 'rejected'])],
        ]);

        $newStatus = $validated['status'];

        $documentRequest = DocumentRequest::with('student')->find($id);
        if (! $documentRequest) {
            abort(404, 'NOT_FOUND');
        }

        $currentStatus = $documentRequest->status;

        // Document request state machine validation (FR-19 & SPEC §6):
        // Allowed transitions:
        // pending -> processing | rejected
        // processing -> ready | rejected
        // ready -> released
        // (released, rejected are terminal)
        $allowedTransitions = [
            'pending' => ['processing', 'rejected'],
            'processing' => ['ready', 'rejected'],
            'ready' => ['released'],
            'released' => [],
            'rejected' => [],
        ];

        if (! isset($allowedTransitions[$currentStatus]) || ! in_array($newStatus, $allowedTransitions[$currentStatus], true)) {
            abort(409, 'INVALID_TRANSITION');
        }

        // Re-check ClearanceGuard + BalanceGuard only on transition into "released" (FR-19.2 / SPEC §5)
        if ($newStatus === 'released') {
            $student = $documentRequest->student;
            $this->balanceGuard->ensureZeroBalance($student);
            $this->clearanceGuard->ensureClearancesApproved($student);

            $documentRequest->released_at = now();
        }

        $documentRequest->status = $newStatus;
        $documentRequest->processed_by = $request->user()->id;
        $documentRequest->save();

        return response()->json([
            'id' => $documentRequest->id,
            'status' => $documentRequest->status,
        ]);
    }
}
