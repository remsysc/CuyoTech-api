<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'student_number_or_email' => 'required|string',
            'password' => 'required|string',
        ]);

        $identifier = $request->input('student_number_or_email');
        $password = $request->input('password');

        $user = User::where('email', $identifier)
            ->orWhereHas('student', function ($query) use ($identifier) {
                $query->where('student_number', $identifier);
            })
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return response()->json(['error' => 'INVALID_CREDENTIALS'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['error' => 'ACCOUNT_DEACTIVATED'], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        $redirects = [
            'student' => '/student/dashboard',
            'registrar' => '/registrar/dashboard',
            'cashier' => '/cashier/dashboard',
            'department_staff' => '/department/dashboard',
            'admin' => '/admin/dashboard',
        ];

        return response()->json([
            'token' => $token,
            'role' => $user->role,
            'redirect' => $redirects[$user->role],
        ], 200);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var PersonalAccessToken $token */
        $token = $request->user()->currentAccessToken();
        $token->delete();

        return response()->json(null, 204);
    }
}
