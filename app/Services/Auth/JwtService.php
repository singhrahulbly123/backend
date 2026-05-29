<?php

namespace App\Services\Auth;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;

class JwtService
{
    public function issue(User $user): string
    {
        $now = time();
        $ttl = (int) config('jwt.ttl', 1440) * 60;

        $payload = [
            'iss' => config('app.url'),
            'sub' => $user->id,
            'iat' => $now,
            'exp' => $now + $ttl,
            'jti' => (string) Str::uuid(),
            'role' => $user->role,
        ];

        return JWT::encode($payload, $this->secret(), 'HS256');
    }

    public function decode(string $token): object
    {
        return JWT::decode($token, new Key($this->secret(), 'HS256'));
    }

    private function secret(): string
    {
        return config('jwt.secret') ?: config('app.key');
    }
}
