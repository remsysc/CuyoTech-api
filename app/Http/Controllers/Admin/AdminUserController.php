<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    /**
     * Create a new user account.
     * Auth: role=admin
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Email uniqueness check -> 409 EMAIL_TAKEN (FR-15.2 / SPEC §5)
        $email = $request->input('email');
        if (User::where('email', $email)->exists()) {
            abort(409, 'EMAIL_TAKEN');
        }

        // 2. Validate request payload
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', Rule::in(['admin', 'student', 'registrar', 'cashier', 'department_staff'])],
            'password' => ['required', 'string', 'min:8'],
            'department_id' => [
                Rule::requiredIf(fn () => $request->input('role') === 'department_staff'),
                'nullable',
                'exists:departments,id',
            ],
        ]);

        // 3. Create user
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'password' => Hash::make($validated['password']),
            'department_id' => $validated['role'] === 'department_staff' ? $validated['department_id'] : null,
            'is_active' => true,
        ]);

        // 4. Append audit_logs row (FR-15.5)
        AuditLog::create([
            'actor_id' => $request->user()->id,
            'action' => 'user.created',
            'target_type' => 'user',
            'target_id' => $user->id,
            'changes' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'department_id' => $user->department_id,
                'is_active' => $user->is_active,
            ],
        ]);

        return response()->json([
            'id' => $user->id,
            'role' => $user->role,
        ], 201);
    }

    /**
     * Update user details (role, department_id, is_active).
     * Auth: role=admin
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);
        if (! $user) {
            abort(404, 'USER_NOT_FOUND');
        }

        $validated = $request->validate([
            'role' => ['nullable', Rule::in(['admin', 'student', 'registrar', 'cashier', 'department_staff'])],
            'department_id' => ['nullable', 'exists:departments,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $targetRole = $validated['role'] ?? $user->role;
        if ($targetRole === 'department_staff' && ! array_key_exists('department_id', $validated) && $user->department_id === null) {
            abort(422, 'VALIDATION_FAILED');
        }

        $before = [
            'role' => $user->role,
            'department_id' => $user->department_id,
            'is_active' => (bool) $user->is_active,
        ];

        if (array_key_exists('role', $validated)) {
            $user->role = $validated['role'];
            if ($user->role !== 'department_staff' && ! array_key_exists('department_id', $validated)) {
                $user->department_id = null;
            }
        }

        if (array_key_exists('department_id', $validated)) {
            $user->department_id = $validated['department_id'];
        }

        if (array_key_exists('is_active', $validated)) {
            $user->is_active = (bool) $validated['is_active'];
        }

        $user->save();

        $after = [
            'role' => $user->role,
            'department_id' => $user->department_id,
            'is_active' => (bool) $user->is_active,
        ];

        // 4. Append audit_logs row (FR-15.5)
        AuditLog::create([
            'actor_id' => $request->user()->id,
            'action' => 'user.updated',
            'target_type' => 'user',
            'target_id' => $user->id,
            'changes' => [
                'before' => $before,
                'after' => $after,
            ],
        ]);

        return response()->json([
            'id' => $user->id,
            'role' => $user->role,
            'is_active' => (bool) $user->is_active,
        ]);
    }

    /**
     * Admin password reset and immediate token revocation (FR-16 / A-5).
     * Auth: role=admin
     */
    public function resetPassword(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);
        if (! $user) {
            abort(404, 'USER_NOT_FOUND');
        }

        $validated = $request->validate([
            'new_password' => ['required', 'string', 'min:8'],
        ]);

        $user->password = Hash::make($validated['new_password']);
        $user->save();

        // Revoke all existing tokens for that user immediately (A-5)
        $user->tokens()->delete();

        // Append audit log
        AuditLog::create([
            'actor_id' => $request->user()->id,
            'action' => 'user.password_reset',
            'target_type' => 'user',
            'target_id' => $user->id,
            'changes' => [
                'action' => 'tokens_revoked',
            ],
        ]);

        return response()->json(null, 204);
    }

    /**
     * Retrieve audit logs with optional filters (FR-17).
     * Auth: role=admin
     */
    public function auditLogs(Request $request): JsonResponse
    {
        $query = AuditLog::query()->orderByDesc('created_at')->orderByDesc('id');

        if ($request->filled('actor_id')) {
            $query->where('actor_id', (int) $request->query('actor_id'));
        }

        if ($request->filled('target_type')) {
            $query->where('target_type', (string) $request->query('target_type'));
        }

        $perPage = 15;
        $paginated = $query->paginate($perPage);

        $items = collect($paginated->items())->map(fn (AuditLog $log): array => [
            'actor_id' => (int) $log->actor_id,
            'action' => $log->action,
            'target_type' => $log->target_type,
            'target_id' => (int) $log->target_id,
            'changes' => $log->changes,
            'created_at' => $log->created_at?->toIso8601String(),
        ])->values();

        return response()->json($items);
    }
}
