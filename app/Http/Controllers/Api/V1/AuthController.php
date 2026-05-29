<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\JwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function __construct(private readonly JwtService $jwt) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $user->update(['last_login_at' => now()]);

        $token = $user->createToken('admin')->plainTextToken;

        return response()->json([
            'user' => $user->load('authorProfile'),
            'token' => $token,
            'jwt' => $this->jwt->issue($user),
            'token_type' => 'Bearer',
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $signupRoles = [
            UserRole::Journalist->value,
            UserRole::Editor->value,
            UserRole::SeoManager->value,
            UserRole::AiReviewer->value,
        ];

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'role' => ['nullable', Rule::in($signupRoles)],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'] ?? UserRole::Journalist->value,
            'password' => Hash::make($data['password']),
        ]);

        $token = $user->createToken('admin')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'jwt' => $this->jwt->issue($user),
            'roles_available' => collect($signupRoles)->map(fn ($r) => [
                'value' => $r,
                'label' => UserRole::from($r)->label(),
            ]),
        ], 201);
    }

    public function roles(): JsonResponse
    {
        return response()->json([
            'roles' => collect(UserRole::cases())->map(fn (UserRole $role) => [
                'value' => $role->value,
                'label' => $role->label(),
                'can_register' => $role !== UserRole::SuperAdmin,
            ]),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()->load('authorProfile')]);
    }
}
