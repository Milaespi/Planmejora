<?php
// Carga variables de entorno desde .env
function loadEnv(string $path): void {
    if (!file_exists($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

loadEnv(__DIR__ . '/../../.env');

define('SUPABASE_URL', rtrim($_ENV['SUPABASE_URL'] ?? '', '/'));
define('SUPABASE_KEY', $_ENV['SUPABASE_KEY'] ?? '');

// Ejecuta una petición HTTP a la API REST de Supabase
function supabase(string $method, string $table, array $params = [], ?array $body = null): array {
    $url = SUPABASE_URL . '/rest/v1/' . $table;

    if ($params) {
        $url .= '?' . http_build_query($params);
    }

    $headers = [
        'apikey: ' . SUPABASE_KEY,
        'Authorization: Bearer ' . SUPABASE_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation',
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);

    if ($status >= 400) {
        $msg = $data['message'] ?? $data['error'] ?? 'Error en Supabase';
        throw new RuntimeException($msg, $status);
    }

    return $data ?? [];
}

// Envía respuesta JSON y termina la ejecución
function jsonResponse(mixed $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $status < 400, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

// Envía respuesta de error JSON
function jsonError(string $message, int $status = 400): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

// Habilita CORS para el frontend
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
