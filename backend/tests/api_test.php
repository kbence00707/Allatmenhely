<?php
/**
 * Automatic API test. It sends real HTTP requests to the running backend
 * and checks the answers. Test data it creates is deleted at the end.
 *
 * Requirements: Apache + MySQL running in XAMPP, database imported.
 *
 * Run (from the Allatmenhely folder):
 *   E:\xampp\php\php.exe backend\tests\api_test.php
 *
 * Optional: a different API address as the first argument, e.g.
 *   E:\xampp\php\php.exe backend\tests\api_test.php http://localhost/Allatmenhely/backend/api
 */

if (PHP_SAPI !== 'cli') {
    exit('Only from the command line.');
}

$baseUrl = rtrim($argv[1] ?? 'http://localhost/Allatmenhely/backend/api', '/');
$staffCookies = tempnam(sys_get_temp_dir(), 'api_test_cookies');
$passed = 0;
$failed = 0;

/**
 * Sends a request. $asStaff = true uses the logged-in cookie jar.
 * Returns [status code, decoded JSON body].
 */
function request(string $method, string $path, $body = null, bool $asStaff = false, string $contentType = 'application/json'): array
{
    global $baseUrl, $staffCookies;

    $ch = curl_init($baseUrl . $path);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: $contentType"]);
    }
    if ($asStaff) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $staffCookies);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $staffCookies);
    }
    $response = curl_exec($ch);
    if ($response === false) {
        echo "Cannot reach $baseUrl - is Apache running in XAMPP?\n";
        exit(1);
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$status, json_decode($response, true)];
}

function check(string $name, bool $ok, $extra = null): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "  [OK]   $name\n";
    } else {
        $failed++;
        echo "  [FAIL] $name\n";
        if ($extra !== null) {
            echo '         ' . json_encode($extra, JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
}

$tomorrow = date('Y-m-d', strtotime('+1 day'));
$yesterday = date('Y-m-d', strtotime('-1 day'));

echo "\nTesting API at $baseUrl\n";

// ------------------------------------------------------------------
echo "\n== General ==\n";
[$s, $b] = request('GET', '/');
check('GET / returns API info (200)', $s === 200 && ($b['status'] ?? '') === 'ok', $b);
[$s, $b] = request('GET', '/does-not-exist');
check('Unknown endpoint returns 404', $s === 404, $b);
[$s, $b] = request('DELETE', '/');
check('Wrong HTTP method returns 405', $s === 405, $b);

// ------------------------------------------------------------------
echo "\n== Animals (public) ==\n";
[$s, $b] = request('GET', '/animals');
$statuses = array_unique(array_column($b['data'] ?? [], 'status'));
check('List animals (200)', $s === 200 && count($b['data']) > 0, $b);
check('Public list hides adopted animals', !in_array('adopted', $statuses, true), $statuses);
[$s, $b] = request('GET', '/animals?species=cat');
check('Filter by species=cat', $s === 200 && array_unique(array_column($b['data'], 'species')) === ['cat'], $b);
[$s, $b] = request('GET', '/animals?search=bod');
check('Search by name "bod" finds Bodri', $s === 200 && in_array('Bodri', array_column($b['data'], 'name'), true), $b);
[$s, $b] = request('GET', '/animals?species=dragon');
check('Invalid species filter returns 422', $s === 422, $b);
[$s, $b] = request('GET', '/animals/1');
check('View one animal (200)', $s === 200 && ($b['data']['id'] ?? null) === 1, $b);
[$s, $b] = request('GET', '/animals/999999');
check('Nonexistent animal returns 404', $s === 404, $b);

// ------------------------------------------------------------------
echo "\n== Unauthorized (not logged in) ==\n";
[$s, $b] = request('POST', '/animals', ['name' => 'Hacker', 'species' => 'dog']);
check('Create animal without login returns 401', $s === 401, $b);
[$s, $b] = request('PUT', '/animals/1', ['name' => 'Hacked']);
check('Edit animal without login returns 401', $s === 401, $b);
[$s, $b] = request('DELETE', '/animals/1');
check('Delete animal without login returns 401', $s === 401, $b);
[$s, $b] = request('GET', '/appointments');
check('List appointments without login returns 401', $s === 401, $b);
[$s, $b] = request('PUT', '/appointments/1', ['status' => 'cancelled']);
check('Edit appointment without login returns 401', $s === 401, $b);
[$s, $b] = request('DELETE', '/appointments/1');
check('Delete appointment without login returns 401', $s === 401, $b);

// ------------------------------------------------------------------
echo "\n== Login ==\n";
[$s, $b] = request('POST', '/auth/login', ['email' => 'admin@allatmenhely.hu', 'password' => 'wrong-password'], true);
check('Wrong password returns 401', $s === 401, $b);
[$s, $b] = request('POST', '/auth/login', ['email' => ''], true);
check('Missing login data returns 422', $s === 422, $b);
[$s, $b] = request('GET', '/auth/me', null, true);
check('GET /auth/me before login returns 401', $s === 401, $b);
[$s, $b] = request('POST', '/auth/login', ['email' => 'admin@allatmenhely.hu', 'password' => 'Admin123!'], true);
check('Correct login (200)', $s === 200 && isset($b['data']['email']), $b);
check('Login response never contains the password hash', !isset($b['data']['password_hash']));
[$s, $b] = request('GET', '/auth/me', null, true);
check('GET /auth/me after login (200)', $s === 200 && $b['data']['email'] === 'admin@allatmenhely.hu', $b);

// ------------------------------------------------------------------
echo "\n== Animals (staff) ==\n";
[$s, $b] = request('POST', '/animals', ['name' => '', 'species' => 'dragon', 'age' => 100], true);
check('Create animal with invalid data returns 422 + field errors',
    $s === 422 && isset($b['details']['name'], $b['details']['species'], $b['details']['age']), $b);
[$s, $b] = request('POST', '/animals', 'name=Test', true, 'application/x-www-form-urlencoded');
check('Non-JSON request body returns 415', $s === 415, $b);
[$s, $b] = request('POST', '/animals', '{broken json', true);
check('Broken JSON returns 400', $s === 400, $b);
[$s, $b] = request('POST', '/animals', ['name' => 'Teszt Tesztelek', 'species' => 'dog', 'breed' => 'Teszt', 'age' => 4,
    'sex' => 'male', 'description' => 'Automatikus teszt állat', 'image_url' => 'javascript:alert(1)'], true);
check('Dangerous image_url is rejected (422)', $s === 422 && isset($b['details']['image_url']), $b);
[$s, $b] = request('POST', '/animals', ['name' => 'Teszt Tesztelek', 'species' => 'dog', 'breed' => 'Teszt', 'age' => 4,
    'sex' => 'male', 'description' => 'Automatikus teszt állat'], true);
$animalId = $b['data']['id'] ?? null;
check('Create animal (201)', $s === 201 && $animalId !== null && $b['data']['status'] === 'available', $b);
[$s, $b] = request('GET', '/animals', null, true);
check('Staff list also shows adopted animals', in_array('adopted', array_column($b['data'], 'status'), true));
[$s, $b] = request('PUT', "/animals/$animalId", ['age' => 5, 'status' => 'reserved'], true);
check('Edit animal (200), only sent fields change',
    $s === 200 && $b['data']['age'] === 5 && $b['data']['status'] === 'reserved' && $b['data']['name'] === 'Teszt Tesztelek', $b);
[$s, $b] = request('PUT', "/animals/$animalId", ['name' => ''], true);
check('Edit animal with empty name returns 422', $s === 422, $b);
[$s, $b] = request('PUT', '/animals/999999', ['age' => 1], true);
check('Edit nonexistent animal returns 404', $s === 404, $b);

// ------------------------------------------------------------------
echo "\n== Booking (public visitor) ==\n";
$booking = [
    'animal_id'        => $animalId,
    'visitor_name'     => 'Teszt Látogató',
    'visitor_email'    => 'teszt@example.com',
    'visitor_phone'    => '+36 30 111 2222',
    'appointment_date' => $tomorrow,
    'appointment_time' => '10:30',
    'note'             => 'Automatikus teszt',
    'status'           => 'confirmed', // visitors must NOT be able to set this
];
[$s, $b] = request('POST', '/appointments', $booking);
check('Booking a reserved animal returns 422', $s === 422 && isset($b['details']['animal_id']), $b);

request('PUT', "/animals/$animalId", ['status' => 'available'], true);
[$s, $b] = request('POST', '/appointments', $booking);
$appointmentId = $b['data']['id'] ?? null;
check('Book appointment (201)', $s === 201 && $appointmentId !== null, $b);
check('Visitor cannot set status (stays pending)', ($b['data']['status'] ?? '') === 'pending', $b);
check('Response contains the animal name', ($b['data']['animal_name'] ?? '') === 'Teszt Tesztelek', $b);
[$s, $b] = request('POST', '/appointments', $booking);
check('Double booking the same animal + time returns 409', $s === 409, $b);
[$s, $b] = request('POST', '/appointments', ['appointment_date' => $yesterday] + $booking);
check('Booking in the past returns 422', $s === 422 && isset($b['details']['appointment_date']), $b);
[$s, $b] = request('POST', '/appointments', ['appointment_time' => '20:00'] + $booking);
check('Booking outside opening hours returns 422', $s === 422 && isset($b['details']['appointment_time']), $b);
[$s, $b] = request('POST', '/appointments', ['visitor_email' => 'not-an-email', 'visitor_name' => ''] + $booking);
check('Invalid e-mail + missing name returns 422', $s === 422 && isset($b['details']['visitor_email'], $b['details']['visitor_name']), $b);
[$s, $b] = request('POST', '/appointments', ['appointment_date' => '2026-02-30'] + $booking);
check('Impossible date (Feb 30) returns 422', $s === 422, $b);
[$s, $b] = request('POST', '/appointments', ['animal_id' => 999999] + $booking);
check('Booking nonexistent animal returns 422', $s === 422 && isset($b['details']['animal_id']), $b);

// ------------------------------------------------------------------
echo "\n== Appointments (staff) ==\n";
[$s, $b] = request('GET', '/appointments', null, true);
$keys = array_map(fn($a) => $a['appointment_date'] . ' ' . $a['appointment_time'], $b['data'] ?? []);
$sorted = $keys;
sort($sorted);
check('List appointments (200)', $s === 200 && count($keys) > 0, $b);
check('Appointments are in chronological order', $keys === $sorted, $keys);
[$s, $b] = request('GET', '/appointments?upcoming=1', null, true);
check('upcoming=1 only returns today or later',
    $s === 200 && min(array_column($b['data'], 'appointment_date')) >= date('Y-m-d'), $b);
[$s, $b] = request('GET', '/appointments?status=pending', null, true);
check('Filter by status=pending', $s === 200 && array_unique(array_column($b['data'], 'status')) === ['pending'], $b);
[$s, $b] = request('GET', "/appointments/$appointmentId", null, true);
check('View one appointment (200)', $s === 200 && $b['data']['visitor_name'] === 'Teszt Látogató', $b);
[$s, $b] = request('GET', '/appointments/999999', null, true);
check('Nonexistent appointment returns 404', $s === 404, $b);
[$s, $b] = request('PUT', "/appointments/$appointmentId", ['status' => 'confirmed'], true);
check('Change status to confirmed (200)', $s === 200 && $b['data']['status'] === 'confirmed', $b);
[$s, $b] = request('PUT', "/appointments/$appointmentId", ['status' => 'flying'], true);
check('Invalid status returns 422', $s === 422, $b);
[$s, $b] = request('PUT', "/appointments/$appointmentId", ['appointment_time' => '11:15', 'note' => 'Módosítva'], true);
check('Edit appointment time + note (200)', $s === 200 && $b['data']['appointment_time'] === '11:15' && $b['data']['note'] === 'Módosítva', $b);
[$s, $b] = request('PUT', "/appointments/$appointmentId", ['status' => 'cancelled'], true);
check('Cancel appointment (status cancelled)', $s === 200 && $b['data']['status'] === 'cancelled', $b);

// ------------------------------------------------------------------
echo "\n== Deleting ==\n";
[$s, $b] = request('DELETE', "/animals/$animalId", null, true);
check('Deleting an animal that has appointments returns 409', $s === 409, $b);
[$s, $b] = request('DELETE', "/appointments/$appointmentId", null, true);
check('Delete appointment (200)', $s === 200, $b);
[$s, $b] = request('DELETE', "/appointments/$appointmentId", null, true);
check('Deleting it again returns 404', $s === 404, $b);
[$s, $b] = request('DELETE', "/animals/$animalId", null, true);
check('Delete animal (200)', $s === 200, $b);
[$s, $b] = request('GET', "/animals/$animalId", null, true);
check('Deleted animal returns 404', $s === 404, $b);

// ------------------------------------------------------------------
echo "\n== Logout ==\n";
[$s, $b] = request('POST', '/auth/logout', null, true);
check('Logout (200)', $s === 200, $b);
[$s, $b] = request('GET', '/appointments', null, true);
check('After logout, staff endpoints return 401 again', $s === 401, $b);

@unlink($staffCookies);

echo "\nResult: $passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
