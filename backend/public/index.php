<?php
/**
 * FRONT CONTROLLER - every API request arrives here.
 *
 * The backend/.htaccess file sends every URL that starts with /backend/api/
 * to this file. Here we:
 *   1. load all classes and the configuration
 *   2. handle CORS
 *   3. connect to the database and start the session
 *   4. let the router call the right controller function
 *   5. turn any error into a JSON error response
 */

declare(strict_types=1);

$root = dirname(__DIR__);

require $root . '/helpers/HttpException.php';
require $root . '/helpers/Response.php';
require $root . '/helpers/Request.php';
require $root . '/helpers/Validator.php';
require $root . '/helpers/Router.php';
require $root . '/config/Database.php';
require $root . '/middleware/Auth.php';
require $root . '/middleware/Cors.php';
require $root . '/models/Animal.php';
require $root . '/models/Appointment.php';
require $root . '/models/User.php';
require $root . '/controllers/AnimalController.php';
require $root . '/controllers/AppointmentController.php';
require $root . '/controllers/AuthController.php';

$config = require $root . '/config/config.php';

date_default_timezone_set($config['timezone']);
// Never print PHP errors into the JSON output (they could reveal internal details).
ini_set('display_errors', '0');

Cors::handle($config['cors_origins']);

try {
    try {
        Database::connect($config['db']);
    } catch (PDOException $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        throw new HttpException(500, 'Nem sikerült csatlakozni az adatbázishoz. Fut a MySQL, és importálva van az adatbázis?');
    }
    $db = Database::get();

    Auth::startSession();

    $router = new Router();
    require $root . '/routes/api.php';

    $router->dispatch($_SERVER['REQUEST_METHOD'], apiPath());
} catch (HttpException $e) {
    Response::error($e->status, $e->getMessage(), $e->details);
} catch (Throwable $e) {
    // Unexpected error: log the details on the server, send a safe message to the browser.
    error_log((string) $e);
    Response::error(500, $config['debug'] ? $e->getMessage() : 'Váratlan szerverhiba történt.');
}

/**
 * Turns the full URL path into the part after "/api".
 * Example: /Allatmenhely/backend/api/animals/3  ->  /animals/3
 */
function apiPath(): string
{
    $path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
    // SCRIPT_NAME is /Allatmenhely/backend/public/index.php -> base is /Allatmenhely/backend/api
    $base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'], 2)) . '/api';

    if (stripos($path, $base) === 0) {
        $path = substr($path, strlen($base));
    }
    $path = '/' . trim($path, '/');
    return $path;
}
