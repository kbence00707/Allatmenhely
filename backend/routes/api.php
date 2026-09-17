<?php
/**
 * All API endpoints in one place.
 *
 * Format: $router->add(HTTP METHOD, URL, controller function, staff only?)
 * The URLs are relative to /backend/api, e.g. '/animals' = /backend/api/animals
 *
 * Variables available here (created in public/index.php): $router, $db, $config
 */

$animalController      = new AnimalController(new Animal($db));
$appointmentController = new AppointmentController(new Appointment($db), new Animal($db), $config);
$authController        = new AuthController(new User($db));

const STAFF_ONLY = true;

// --- API info (quick check that the backend works) ---
$router->add('GET', '/', function () {
    Response::json([
        'name'    => 'Állatmenhely API',
        'status'  => 'ok',
        'version' => '1.0',
    ]);
});

// --- Animals ---
$router->add('GET',    '/animals',      [$animalController, 'index']);
$router->add('GET',    '/animals/{id}', [$animalController, 'show']);
$router->add('POST',   '/animals',      [$animalController, 'store'],   STAFF_ONLY);
$router->add('PUT',    '/animals/{id}', [$animalController, 'update'],  STAFF_ONLY);
$router->add('PATCH',  '/animals/{id}', [$animalController, 'update'],  STAFF_ONLY);
$router->add('DELETE', '/animals/{id}', [$animalController, 'destroy'], STAFF_ONLY);

// --- Appointments ---
$router->add('GET',    '/appointments',      [$appointmentController, 'index'],   STAFF_ONLY);
$router->add('GET',    '/appointments/{id}', [$appointmentController, 'show'],    STAFF_ONLY);
$router->add('POST',   '/appointments',      [$appointmentController, 'store']);  // public booking
$router->add('PUT',    '/appointments/{id}', [$appointmentController, 'update'],  STAFF_ONLY);
$router->add('PATCH',  '/appointments/{id}', [$appointmentController, 'update'],  STAFF_ONLY);
$router->add('DELETE', '/appointments/{id}', [$appointmentController, 'destroy'], STAFF_ONLY);

// --- Staff authentication ---
$router->add('POST', '/auth/login',  [$authController, 'login']);
$router->add('POST', '/auth/logout', [$authController, 'logout']);
$router->add('GET',  '/auth/me',     [$authController, 'me']);
