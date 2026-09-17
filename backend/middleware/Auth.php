<?php
/**
 * Staff authentication using PHP sessions.
 *
 * After a successful login PHP stores the user's id in $_SESSION on the server
 * and gives the browser a random session cookie. The browser sends that cookie
 * with every later request, so we know the request comes from a logged-in employee.
 */
class Auth
{
    public static function startSession(): void
    {
        session_set_cookie_params([
            'lifetime' => 0,                  // cookie is deleted when the browser closes
            'path'     => '/',
            'httponly' => true,               // JavaScript cannot read the cookie (XSS protection)
            'samesite' => 'Lax',              // other websites cannot use the cookie (CSRF protection)
            'secure'   => !empty($_SERVER['HTTPS']),
        ]);
        session_name('ALLATMENHELY_SESSION');
        session_start();
    }

    /** Returns the logged-in user's id, or null. */
    public static function userId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function isStaff(): bool
    {
        return self::userId() !== null;
    }

    /** Stops the request with HTTP 401 if nobody is logged in. */
    public static function requireStaff(): void
    {
        if (!self::isStaff()) {
            throw new HttpException(401, 'Ehhez a művelethez dolgozói bejelentkezés szükséges.');
        }
    }

    public static function login(int $userId): void
    {
        // New session id after login prevents "session fixation" attacks.
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 3600,
            'path'     => $params['path'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_destroy();
    }
}
