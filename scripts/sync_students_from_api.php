<?php
/**
 * Sync students from remote API into local database.
 *
 * Rate limit aware:
 * - Tracks requests in the last hour
 * - Stops when request budget is reached
 *
 * Usage (CLI):
 *   C:\xampp\php\php.exe scripts/sync_students_from_api.php
 *   C:\xampp\php\php.exe scripts/sync_students_from_api.php --limit=20 --max-requests=15
 *   C:\xampp\php\php.exe scripts/sync_students_from_api.php --reset-offset=1
 */

date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/../config/database.php';

const SYNC_KEY = 'students_main';
const REMOTE_STUDENTS_API = 'https://nielitbhubaneswar.in/api/v1/students.php';
const DEFAULT_LIMIT = 25;
const DEFAULT_MAX_REQUESTS = 20;
const SAFE_HOURLY_BUDGET = 90; // Keep buffer under 100/hour hard limit.
const DEFAULT_REMOTE_API_KEY = 'b426f2591b862bd64bf55c37763af2540419da61f974061e59c0308b4ca66dd5';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Run this script from CLI only.\n";
    exit(1);
}

$options = parseCliOptions($argv);
$limit = max(1, min(100, (int) ($options['limit'] ?? DEFAULT_LIMIT)));
$maxRequests = max(1, min(50, (int) ($options['max-requests'] ?? DEFAULT_MAX_REQUESTS)));
$resetOffset = (int) ($options['reset-offset'] ?? 0) === 1;
$apiKey = getenv('NIELIT_REMOTE_API_KEY') ?: DEFAULT_REMOTE_API_KEY;

try {
    ensureSyncTables($pdo);

    if ($resetOffset) {
        setSyncOffset($pdo, SYNC_KEY, 0);
        echo "Offset reset to 0.\n";
    }

    $hourlyCount = getRequestsInLastHour($pdo);
    if ($hourlyCount >= SAFE_HOURLY_BUDGET) {
        echo "Stopped: request budget reached ({$hourlyCount}/" . SAFE_HOURLY_BUDGET . ").\n";
        exit(0);
    }

    $offset = getSyncOffset($pdo, SYNC_KEY);
    $totalSynced = 0;
    $requestsUsed = 0;

    while ($requestsUsed < $maxRequests) {
        $hourlyCount = getRequestsInLastHour($pdo);
        if ($hourlyCount >= SAFE_HOURLY_BUDGET) {
            echo "Stopped mid-run: request budget reached ({$hourlyCount}/" . SAFE_HOURLY_BUDGET . ").\n";
            break;
        }

        $response = fetchStudentsPage($offset, $limit, $apiKey);
        recordApiRequest($pdo);
        $requestsUsed++;

        if (!isset($response['status']) || (int) $response['status'] !== 200) {
            $message = $response['message'] ?? 'Unknown API error';
            echo "API error: {$message}\n";
            break;
        }

        $students = $response['data']['students'] ?? [];
        if (!is_array($students) || count($students) === 0) {
            echo "No more students returned. Sync complete.\n";
            setSyncOffset($pdo, SYNC_KEY, 0);
            break;
        }

        foreach ($students as $student) {
            upsertStudent($pdo, $student);
            $totalSynced++;
        }

        $pagination = $response['data']['pagination'] ?? [];
        $hasMore = (bool) ($pagination['has_more'] ?? false);

        if ($hasMore) {
            $offset += $limit;
            setSyncOffset($pdo, SYNC_KEY, $offset);
        } else {
            setSyncOffset($pdo, SYNC_KEY, 0);
            echo "Reached end of remote data. Offset reset to 0.\n";
            break;
        }
    }

    echo "Sync finished. Rows upserted: {$totalSynced}. Requests used: {$requestsUsed}.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Sync failed: " . $e->getMessage() . "\n");
    exit(1);
}

function parseCliOptions(array $argv)
{
    $options = [];

    foreach ($argv as $arg) {
        if (strpos($arg, '--') !== 0) {
            continue;
        }

        $pair = explode('=', substr($arg, 2), 2);
        $key = $pair[0];
        $value = $pair[1] ?? 1;
        $options[$key] = $value;
    }

    return $options;
}

function ensureSyncTables(PDO $pdo)
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS external_students (
            student_id VARCHAR(100) NOT NULL PRIMARY KEY,
            name VARCHAR(255) NULL,
            father_name VARCHAR(255) NULL,
            mother_name VARCHAR(255) NULL,
            email VARCHAR(255) NULL,
            mobile VARCHAR(50) NULL,
            course_id INT NULL,
            course_name VARCHAR(255) NULL,
            training_center VARCHAR(255) NULL,
            status VARCHAR(50) NULL,
            dob DATE NULL,
            gender VARCHAR(20) NULL,
            address TEXT NULL,
            city VARCHAR(100) NULL,
            state VARCHAR(100) NULL,
            pincode VARCHAR(20) NULL,
            source_created_at DATETIME NULL,
            source_payload LONGTEXT NULL,
            synced_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS api_sync_state (
            sync_key VARCHAR(100) NOT NULL PRIMARY KEY,
            sync_offset INT NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS api_sync_request_log (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            requested_at DATETIME NOT NULL
        )"
    );
}

function fetchStudentsPage($offset, $limit, $apiKey)
{
    $query = http_build_query([
        'action' => 'list',
        'limit' => $limit,
        'offset' => $offset,
    ]);

    $url = REMOTE_STUDENTS_API . '?' . $query;

    if (!function_exists('curl_init')) {
        throw new RuntimeException('cURL extension is required for sync script.');
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-Key: ' . $apiKey,
        'Accept: application/json',
    ]);

    $raw = curl_exec($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException('HTTP request failed: ' . $curlErr);
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Invalid JSON response from remote API.');
    }

    return $decoded;
}

function upsertStudent(PDO $pdo, array $student)
{
    $stmt = $pdo->prepare(
        "INSERT INTO external_students (
            student_id, name, father_name, mother_name, email, mobile,
            course_id, course_name, training_center, status, dob, gender,
            address, city, state, pincode, source_created_at, source_payload,
            synced_at, updated_at
        ) VALUES (
            :student_id, :name, :father_name, :mother_name, :email, :mobile,
            :course_id, :course_name, :training_center, :status, :dob, :gender,
            :address, :city, :state, :pincode, :source_created_at, :source_payload,
            NOW(), NOW()
        ) ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            father_name = VALUES(father_name),
            mother_name = VALUES(mother_name),
            email = VALUES(email),
            mobile = VALUES(mobile),
            course_id = VALUES(course_id),
            course_name = VALUES(course_name),
            training_center = VALUES(training_center),
            status = VALUES(status),
            dob = VALUES(dob),
            gender = VALUES(gender),
            address = VALUES(address),
            city = VALUES(city),
            state = VALUES(state),
            pincode = VALUES(pincode),
            source_created_at = VALUES(source_created_at),
            source_payload = VALUES(source_payload),
            synced_at = NOW(),
            updated_at = NOW()"
    );

    $sourceCreatedAt = null;
    if (!empty($student['created_at'])) {
        $timestamp = strtotime((string) $student['created_at']);
        if ($timestamp !== false) {
            $sourceCreatedAt = date('Y-m-d H:i:s', $timestamp);
        }
    }

    $dob = null;
    if (!empty($student['dob'])) {
        $dobTs = strtotime((string) $student['dob']);
        if ($dobTs !== false) {
            $dob = date('Y-m-d', $dobTs);
        }
    }

    $stmt->execute([
        ':student_id' => (string) ($student['student_id'] ?? ''),
        ':name' => nullableString($student['name'] ?? null),
        ':father_name' => nullableString($student['father_name'] ?? null),
        ':mother_name' => nullableString($student['mother_name'] ?? null),
        ':email' => nullableString($student['email'] ?? null),
        ':mobile' => nullableString($student['mobile'] ?? null),
        ':course_id' => isset($student['course_id']) ? (int) $student['course_id'] : null,
        ':course_name' => nullableString($student['course_name'] ?? null),
        ':training_center' => nullableString($student['training_center'] ?? null),
        ':status' => nullableString($student['status'] ?? null),
        ':dob' => $dob,
        ':gender' => nullableString($student['gender'] ?? null),
        ':address' => nullableString($student['address'] ?? null),
        ':city' => nullableString($student['city'] ?? null),
        ':state' => nullableString($student['state'] ?? null),
        ':pincode' => nullableString($student['pincode'] ?? null),
        ':source_created_at' => $sourceCreatedAt,
        ':source_payload' => json_encode($student, JSON_UNESCAPED_SLASHES),
    ]);
}

function nullableString($value)
{
    if ($value === null) {
        return null;
    }

    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

function getSyncOffset(PDO $pdo, $syncKey)
{
    $stmt = $pdo->prepare('SELECT sync_offset FROM api_sync_state WHERE sync_key = ? LIMIT 1');
    $stmt->execute([$syncKey]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? (int) $row['sync_offset'] : 0;
}

function setSyncOffset(PDO $pdo, $syncKey, $offset)
{
    $stmt = $pdo->prepare(
        "INSERT INTO api_sync_state (sync_key, sync_offset, updated_at)
         VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE
            sync_offset = VALUES(sync_offset),
            updated_at = NOW()"
    );
    $stmt->execute([$syncKey, (int) $offset]);
}

function recordApiRequest(PDO $pdo)
{
    $stmt = $pdo->prepare('INSERT INTO api_sync_request_log (requested_at) VALUES (NOW())');
    $stmt->execute();

    $pdo->exec("DELETE FROM api_sync_request_log WHERE requested_at < (NOW() - INTERVAL 2 DAY)");
}

function getRequestsInLastHour(PDO $pdo)
{
    $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM api_sync_request_log WHERE requested_at >= (NOW() - INTERVAL 1 HOUR)");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int) ($row['cnt'] ?? 0);
}
