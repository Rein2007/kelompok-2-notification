<?php
/**
 * Notification Service - Kelompok 2 - Mini LMS Terdistribusi
 * Port: 8106
 * Jalankan: php -S 127.0.0.1:8106 index.php
 */

header('Content-Type: application/json; charset=utf-8');

const DATA_FILE = __DIR__ . '/data/notifications.json';

// ---------- Konfigurasi ----------
function env_value(string $key, string $default = ''): string {
    $v = getenv($key);
    if ($v !== false && $v !== '') return $v;
    $file = __DIR__ . '/.env';
    if (is_file($file)) {
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if ($line[0] === '#' || strpos($line, '=') === false) continue;
            [$k, $val] = explode('=', $line, 2);
            if (trim($k) === $key) return trim($val);
        }
    }
    return $default;
}

function auth_base_url(): string {
    $cfg = json_decode(@file_get_contents(__DIR__ . '/services.json'), true);
    return rtrim($cfg['auth'] ?? 'http://127.0.0.1:8102', '/');
}

// ---------- Helper respons (sesuai kontrak bagian 8) ----------
$REQUEST_ID = $_SERVER['HTTP_X_REQUEST_ID'] ?? ('req-' . bin2hex(random_bytes(4)));
header('X-Request-ID: ' . $REQUEST_ID);

function ok($data, int $status = 200): void {
    global $REQUEST_ID;
    http_response_code($status);
    echo json_encode(['success' => true, 'data' => $data, 'meta' => ['request_id' => $REQUEST_ID]], JSON_UNESCAPED_UNICODE);
    exit;
}

function fail(int $status, string $code, string $message): void {
    global $REQUEST_ID;
    http_response_code($status);
    echo json_encode([
        'success' => false,
        'error' => ['code' => $code, 'message' => $message],
        'meta' => ['request_id' => $REQUEST_ID],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- Autentikasi: validasi token ke service Authentication ----------
function require_auth(): array {
    if (strtolower(env_value('AUTH_BYPASS', 'false')) === 'true') {
        return ['id' => 'bypass', 'bypass' => true];
    }

    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
        fail(401, 'unauthorized', 'Token tidak ada atau format Authorization salah');
    }

    global $REQUEST_ID;
    $method  = strtoupper(env_value('AUTH_VALIDATE_METHOD', 'GET'));
    $path    = env_value('AUTH_VALIDATE_PATH', '/api/auth/me');
    $timeout = (int) env_value('AUTH_TIMEOUT', '3');

    $ch = curl_init(auth_base_url() . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $m[1],
            'X-Request-ID: ' . $REQUEST_ID,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);
    if ($method === 'POST') curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['token' => $m[1]]));

    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errno = curl_errno($ch);
    curl_close($ch);

    if ($errno === CURLE_OPERATION_TIMEDOUT) fail(504, 'auth_timeout', 'Service Authentication melewati batas waktu');
    if ($errno !== 0 || $code === 0)        fail(503, 'auth_unavailable', 'Service Authentication tidak dapat dihubungi');
    if ($code === 401 || $code === 403)     fail(401, 'unauthorized', 'Token tidak valid');
    if ($code < 200 || $code >= 300)        fail(502, 'auth_bad_response', 'Respons tidak valid dari Authentication');

    $json = json_decode((string) $body, true);
    return is_array($json) ? ($json['data'] ?? $json) : [];
}

// ---------- Penyimpanan (file JSON dengan kunci agar aman dari tulis bersamaan) ----------
function with_store(callable $fn, bool $write = false) {
    if (!is_dir(dirname(DATA_FILE))) mkdir(dirname(DATA_FILE), 0775, true);
    $fh = fopen(DATA_FILE, 'c+');
    if (!$fh) fail(500, 'storage_error', 'Penyimpanan tidak dapat dibuka');
    flock($fh, $write ? LOCK_EX : LOCK_SH);
    $raw = stream_get_contents($fh);
    $store = json_decode($raw ?: '', true);
    if (!is_array($store) || !isset($store['items'])) $store = ['seq' => 0, 'items' => []];

    $result = $fn($store);

    if ($write) {
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fflush($fh);
    }
    flock($fh, LOCK_UN);
    fclose($fh);
    return $result;
}

function read_json_body(): array {
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') === false) {
        fail(415, 'unsupported_media_type', 'Content-Type harus application/json');
    }
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
        fail(400, 'invalid_json', 'Isi JSON tidak valid');
    }
    return $data;
}

// ---------- Routing ----------
$method = $_SERVER['REQUEST_METHOD'];
$path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: '/';

// GET /health (tanpa token)
if ($path === '/health') {
    if ($method !== 'GET') fail(405, 'method_not_allowed', 'Gunakan GET');
    ok(['service' => 'notification', 'status' => 'healthy']);
}

// GET & POST /api/notifications
if ($path === '/api/notifications') {
    require_auth();

    if ($method === 'GET') {
        $userId = trim($_GET['user_id'] ?? '');
        if ($userId === '') fail(400, 'validation_error', 'Parameter user_id wajib diisi');

        $isRead = $_GET['is_read'] ?? null;
        if ($isRead !== null && !in_array($isRead, ['true', 'false'], true)) {
            fail(400, 'validation_error', 'is_read harus true atau false');
        }

        $items = with_store(function ($s) use ($userId, $isRead) {
            $out = array_filter($s['items'], function ($n) use ($userId, $isRead) {
                if ($n['user_id'] !== $userId) return false;
                if ($isRead !== null && $n['is_read'] !== ($isRead === 'true')) return false;
                return true;
            });
            return array_values(array_reverse($out)); // terbaru dulu
        });
        ok(['notifications' => $items, 'total' => count($items)]);
    }

    if ($method === 'POST') {
        $in = read_json_body();
        $userId  = trim((string) ($in['user_id'] ?? ''));
        $message = trim((string) ($in['message'] ?? ''));
        $type    = trim((string) ($in['type'] ?? 'info'));

        $errors = [];
        if ($userId === '')  $errors[] = 'user_id wajib diisi';
        if ($message === '') $errors[] = 'message wajib diisi';
        if (mb_strlen($message) > 500) $errors[] = 'message maksimal 500 karakter';
        if ($errors) fail(400, 'validation_error', implode('; ', $errors));

        $notif = with_store(function (&$s) use ($userId, $message, $type, $in) {
            $s['seq']++;
            $n = [
                'id'            => sprintf('notif-%03d', $s['seq']),
                'user_id'       => $userId,
                'message'       => $message,
                'type'          => $type,
                'course_id'     => $in['course_id'] ?? null,
                'assignment_id' => $in['assignment_id'] ?? null,
                'submission_id' => $in['submission_id'] ?? null,
                'is_read'       => false,
                'created_at'    => gmdate('c'),
                'read_at'       => null,
            ];
            $s['items'][] = $n;
            return $n;
        }, true);
        ok($notif, 201);
    }

    fail(405, 'method_not_allowed', 'Gunakan GET atau POST');
}

// PATCH /api/notifications/{id}/read
if (preg_match('#^/api/notifications/([A-Za-z0-9_-]+)/read$#', $path, $m)) {
    require_auth();
    if ($method !== 'PATCH') fail(405, 'method_not_allowed', 'Gunakan PATCH');

    $found = with_store(function (&$s) use ($m) {
        foreach ($s['items'] as &$n) {
            if ($n['id'] === $m[1]) {
                if (!$n['is_read']) {
                    $n['is_read'] = true;
                    $n['read_at'] = gmdate('c');
                }
                return $n;
            }
        }
        return null;
    }, true);

    if ($found === null) fail(404, 'notification_not_found', 'Notifikasi tidak ditemukan');
    ok($found);
}

fail(404, 'not_found', 'Endpoint tidak ditemukan');
