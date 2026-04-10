<?php

declare(strict_types=1);

class AuthMiddleware
{
    public function isLoggedIn(): void
    {
        if (!isset($_SESSION['user'])) {
            header('Location: /login');
            exit;
        }
    }

    public function isLoggedOut(): void
    {
        if (isset($_SESSION['user'])) {
            header('Location: /');
            exit;
        }
    }

    public function requireRole(array $rolesPermitidos): void
    {
        $this->isLoggedIn();
        if (!in_array($_SESSION['user']['rol'] ?? null, $rolesPermitidos, true)) {
            http_response_code(403);
            header('Location: /error');
            exit;
        }
    }

    public function currentRole(): ?string
    {
        return $_SESSION['user']['rol'] ?? null;
    }

    public function currentUserId(): ?int
    {
        return isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : null;
    }
}