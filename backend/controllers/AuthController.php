<?php
/**
 * Handles staff login / logout: /api/auth/...
 */
class AuthController
{
    public function __construct(private User $users)
    {
    }

    /** POST /api/auth/login  {"email": "...", "password": "..."} */
    public function login(): void
    {
        $input = Request::json();
        $email = $input['email'] ?? null;
        $password = $input['password'] ?? null;

        if (!is_string($email) || trim($email) === '' || !is_string($password) || $password === '') {
            throw new HttpException(422, 'Az e-mail cím és a jelszó megadása kötelező.');
        }

        $user = $this->users->findByEmail(trim($email));

        // Same message for "no such user" and "wrong password", so attackers
        // cannot find out which e-mail addresses exist.
        if (!$user || !password_verify($password, $user['password_hash'])) {
            throw new HttpException(401, 'Hibás e-mail cím vagy jelszó.');
        }

        Auth::login((int) $user['id']);
        Response::json([
            'message' => 'Sikeres bejelentkezés.',
            'data'    => $this->users->findPublic((int) $user['id']),
        ]);
    }

    /** POST /api/auth/logout */
    public function logout(): void
    {
        Auth::logout();
        Response::json(['message' => 'Sikeres kijelentkezés.']);
    }

    /** GET /api/auth/me - who is logged in? (401 if nobody) */
    public function me(): void
    {
        Auth::requireStaff();

        $user = $this->users->findPublic(Auth::userId());
        if (!$user) {
            // The account was deleted while logged in
            Auth::logout();
            throw new HttpException(401, 'Ehhez a művelethez dolgozói bejelentkezés szükséges.');
        }
        Response::json(['data' => $user]);
    }
}
