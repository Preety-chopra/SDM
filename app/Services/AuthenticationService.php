<?php

namespace App\Services;

use App\Contracts\Repositories\UserRepositoryInterface;
use Closure;
use Illuminate\Support\Facades\Auth;

final class AuthenticationService
{
    public function __construct(private UserRepositoryInterface $users) {}

    public function attempt(string $identifier, #[\SensitiveParameter] string $password, Closure $canLogin): bool
    {
        $user = $this->users->findByLoginIdentifier($identifier);

        return Auth::attemptWhen([
            'id' => $user?->getKey(),
            'password' => $password,
            'is_enabled' => true,
        ], $canLogin);
    }
}
