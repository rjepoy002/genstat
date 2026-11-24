<?php
declare(strict_types=1);

// COOP HTTPS API 1.1. GET is the authenticated connection health check.
// POST account_lookup accepts AcctCode. POST monthly_consumption accepts
// ReportMonth (YYYYMM) and up to 100 AcctCode strings in accounts.
// Item statuses: found, ok, account_not_found, no_active_account,
// multiple_active_account_matches, no_billing_record, incomplete_billing_data,
// retrieval_failure. A confirmed zero is "0.00". Unavailable KWH is null.

ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const COOP_API_MAX_BYTES = 32768;
const COOP_API_MAX_ACCOUNTS = 100;

function coopApiRespond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    exit;
}

function coopApiError(int $status, string $message): void
{
    coopApiRespond($status, ['success' => false, 'message' => $message]);
}

function coopApiConfig(): array
{
    $configPath = dirname(__DIR__, 2) . '/genstat-private/config.php';
    if (!is_readable($configPath)) {
        throw new RuntimeException('Missing API configuration.');
    }
    $config = require $configPath;
    if (!is_array($config)) {
        throw new RuntimeException('Invalid API configuration.');
    }
    foreach (['db_host', 'db_name', 'db_user', 'api_key'] as $field) {
        if (!is_string($config[$field] ?? null) || $config[$field] === '') {
            throw new RuntimeException('Invalid API configuration.');
        }
    }
    if (!is_string($config['db_password'] ?? null)
        || !filter_var($config['db_port'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 65535],
        ])) {
        throw new RuntimeException('Invalid API configuration.');
    }
    return $config;
}

function coopApiReadRequest(): array
{
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > COOP_API_MAX_BYTES) {
        coopApiError(413, 'Request body is too large.');
    }
    $body = file_get_contents('php://input', false, null, 0, COOP_API_MAX_BYTES + 1);
    if ($body === false) {
        coopApiError(400, 'Unable to read the request body.');
    }
    if (strlen($body) > COOP_API_MAX_BYTES) {
        coopApiError(413, 'Request body is too large.');
    }
    try {
        $input = json_decode($body, false, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        coopApiError(400, 'Request body must be valid JSON.');
    }
    if (!$input instanceof stdClass) {
        coopApiError(400, 'Request body must be a JSON object.');
    }
    if (($input->action ?? null) === 'account_lookup') {
        return ['account_lookup', null, [coopApiCode($input->AcctCode ?? null)]];
    }
    if (($input->action ?? null) !== 'monthly_consumption') {
        coopApiError(400, 'Unsupported action.');
    }
    $month = $input->ReportMonth ?? null;
    if (!is_string($month) || !preg_match('/\A(?!0000)[0-9]{4}(0[1-9]|1[0-2])\z/', $month)) {
        coopApiError(400, 'ReportMonth must be a valid YYYYMM string.');
    }
    $codes = $input->accounts ?? null;
    if (!is_array($codes) || !$codes || count($codes) > COOP_API_MAX_ACCOUNTS) {
        coopApiError(400, 'accounts must be a JSON array of 1 to 100 account codes.');
    }
    return ['monthly_consumption', $month, array_map('coopApiCode', $codes)];
}

function coopApiCode(mixed $value): string
{
    if (!is_string($value) || trim($value) === '' || strlen($value) > 128
        || preg_match('/[\x00-\x1f\x7f]/', $value)) {
        coopApiError(400, 'Invalid account code.');
    }
    return $value;
}

function coopApiLookup(PDO $db, array $codes): array
{
    $slots = implode(',', array_fill(0, count($codes), '?'));
    $exact = "AcctCode IN ($slots) AND BINARY AcctCode IN ($slots)";
    // Aggregate active matches per exact code so ambiguous source rows stay bounded.
    $query = $db->prepare("SELECT BINARY AcctCode AS code, COUNT(*) AS matches,
        MIN(AcctNo) AS number, MIN(Name) AS accountName
        FROM master WHERE $exact AND Status = 'A' GROUP BY BINARY AcctCode");
    $query->execute([...$codes, ...$codes]);
    $results = [];
    foreach ($query as $row) {
        $number = $row['number'];
        $status = match (true) {
            (int) $row['matches'] !== 1 => 'multiple_active_account_matches',
            $number === null || trim($number) === '' || strlen($number) > 128 => 'no_active_account',
            default => 'found',
        };
        $results[$row['code']] = [
            'AcctCode' => $row['code'],
            'AcctNo' => $status === 'found' ? $number : null,
            'AccountName' => $status === 'found' ? $row['accountName'] : null,
            'status' => $status,
        ];
    }
    $query->closeCursor();
    $missing = array_values(array_filter($codes, static fn(string $code): bool => !isset($results[$code])));
    if ($missing) {
        $slots = implode(',', array_fill(0, count($missing), '?'));
        $query = $db->prepare("SELECT DISTINCT BINARY AcctCode AS code
            FROM master WHERE AcctCode IN ($slots) AND BINARY AcctCode IN ($slots)");
        $query->execute([...$missing, ...$missing]);
        foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $code) {
            $results[$code] = coopApiUnresolved($code, 'no_active_account');
        }
        $query->closeCursor();
        foreach ($missing as $code) {
            $results[$code] ??= coopApiUnresolved($code, 'account_not_found');
        }
    }
    return $results;
}

function coopApiUnresolved(string $code, string $status): array
{
    return ['AcctCode' => $code, 'AcctNo' => null, 'AccountName' => null, 'status' => $status];
}

function coopApiBilling(PDO $db, array $numbers, string $month): array
{
    if (!$numbers) {
        return [];
    }
    $slots = implode(',', array_fill(0, count($numbers), '?'));
    $query = $db->prepare("SELECT BINARY b.AcctNo AS identity, COUNT(*) AS records,
        COUNT(b.KWH) AS validRecords, SUM(b.KWH) AS kwh
        FROM billhistory b WHERE b.BillMonth = ? AND b.AcctNo IN ($slots)
        AND BINARY b.AcctNo IN ($slots) AND EXISTS (
            SELECT 1 FROM master m WHERE m.AcctNo = b.AcctNo
            AND BINARY m.AcctNo = BINARY b.AcctNo AND m.Status = 'A'
        ) GROUP BY BINARY b.AcctNo");
    $query->execute([$month, ...$numbers, ...$numbers]);
    if (!in_array($query->getColumnMeta(3)['native_type'] ?? '', ['DECIMAL', 'NEWDECIMAL'], true)) {
        throw new RuntimeException('Billing KWH is not an exact decimal.');
    }
    $bills = [];
    foreach ($query as $bill) {
        $bills[$bill['identity']] = $bill;
    }
    return $bills;
}

function coopApiAmount(?array $bill): array
{
    if ($bill === null) {
        return [null, 'no_billing_record'];
    }
    $value = $bill['kwh'];
    if ((int) $bill['records'] !== 1 || (int) $bill['validRecords'] !== 1
        || !is_string($value) || !preg_match('/\A-?[0-9]+(?:\.[0-9]{1,60})?\z/', $value)) {
        return [null, 'incomplete_billing_data'];
    }
    // Test the sign and zero as strings to avoid rounding or requiring BCMath.
    if (preg_match('/\A-?0+(?:\.0+)?\z/', $value)) {
        return ['0.00', 'ok'];
    }
    return $value[0] === '-' ? [null, 'incomplete_billing_data'] : [$value, 'ok'];
}

try {
    if (!in_array(strtolower($_SERVER['HTTPS'] ?? ''), ['on', '1'], true)) {
        coopApiError(403, 'HTTPS required.');
    }
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($method, ['GET', 'POST'], true)) {
        header('Allow: GET, POST');
        coopApiError(405, 'Unsupported HTTP method.');
    }
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if ($apiKey === '') {
        coopApiError(401, 'Unauthorized.');
    }
    $config = coopApiConfig();
    if (!hash_equals($config['api_key'], $apiKey)) {
        coopApiError(401, 'Unauthorized.');
    }
    if ($method === 'GET' && (isset($_GET['action']) || isset($_GET['operation']))) {
        coopApiError(400, 'Data operations require POST.');
    }
    [$action, $month, $codes] = $method === 'POST' ? coopApiReadRequest() : ['health', null, []];
    $accounts = [];
    try {
        $db = new PDO(
            'mysql:host=' . $config['db_host'] . ';port=' . $config['db_port']
                . ';dbname=' . $config['db_name'] . ';charset=utf8mb4',
            $config['db_user'],
            $config['db_password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 3,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => true,
                PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
            ]
        );
        if ($action === 'health') {
            $db->prepare('SELECT 1')->execute();
            coopApiRespond(200, [
                'success' => true, 'message' => 'Connection successful.',
                'api_version' => '1.1', 'database_connected' => true,
            ]);
        }
        $accounts = coopApiLookup($db, array_values(array_unique($codes, SORT_STRING)));
        if ($action === 'account_lookup') {
            coopApiRespond(200, ['success' => true, 'data' => $accounts[$codes[0]]]);
        }
        $numbers = array_values(array_unique(array_column(
            array_filter($accounts, static fn(array $account): bool => $account['status'] === 'found'),
            'AcctNo'
        ), SORT_STRING));
        $bills = coopApiBilling($db, $numbers, $month);
        $data = [];
        foreach ($codes as $code) {
            $item = $accounts[$code];
            $item['KWH'] = null;
            if ($item['status'] === 'found') {
                [$item['KWH'], $item['status']] = coopApiAmount($bills[$item['AcctNo']] ?? null);
            }
            $data[] = $item;
        }
        coopApiRespond(200, ['success' => true, 'ReportMonth' => $month, 'data' => $data]);
    } catch (RuntimeException) {
        $response = ['success' => false, 'message' => 'Database retrieval is unavailable.'];
        if ($action === 'health') {
            $response['api_version'] = '1.1';
            $response['database_connected'] = false;
        } elseif ($action === 'account_lookup') {
            $response['data'] = coopApiUnresolved($codes[0], 'retrieval_failure');
        } else {
            $response['ReportMonth'] = $month;
            $response['data'] = [];
            foreach ($codes as $code) {
                $item = $accounts[$code] ?? coopApiUnresolved($code, 'retrieval_failure');
                if ($item['status'] === 'found') {
                    $item['status'] = 'retrieval_failure';
                }
                $item['KWH'] = null;
                $response['data'][] = $item;
            }
        }
        coopApiRespond(503, $response);
    }
} catch (Throwable) {
    coopApiError(500, 'Internal API error.');
}
