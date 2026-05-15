<?php
/**
 * database.php — Configuración central de la conexión con Supabase
 *
 * Este archivo es incluido por todos los archivos de la API.
 * Define funciones reutilizables para hablar con la base de datos
 * y para enviar respuestas JSON al frontend.
 */

// ── Carga el archivo .env ──────────────────────────────────────────────────────
// Lee el archivo .env (que guarda datos sensibles como contraseñas y claves)
// y los mete en la variable global $_ENV para usarlos en el código.
function loadEnv(string $path): void {
    if (!file_exists($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        // Ignora las líneas que empiezan con # (son comentarios en .env)
        if (str_starts_with(trim($line), '#')) continue;
        // Separa cada línea en CLAVE=VALOR y la guarda en $_ENV
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

// Carga el .env que está dos carpetas arriba de este archivo
loadEnv(__DIR__ . '/../../.env');

// Las variables de Railway (getenv) tienen prioridad sobre el .env local
define('SUPABASE_URL', rtrim(getenv('SUPABASE_URL') ?: ($_ENV['SUPABASE_URL'] ?? ''), '/'));
define('SUPABASE_KEY', getenv('SUPABASE_KEY') ?: ($_ENV['SUPABASE_KEY'] ?? ''));

// ── Función principal para hablar con Supabase ─────────────────────────────────
// supabase() envía peticiones HTTP a la API REST de Supabase.
// Parámetros:
//   $method  → el verbo HTTP: GET, POST, PATCH, DELETE
//   $table   → nombre de la tabla en la base de datos (ej: 'proyectos')
//   $params  → filtros y opciones que van en la URL (ej: ['id' => 'eq.5'])
//   $body    → datos que se envían en el cuerpo (solo para POST y PATCH)
// Retorna un arreglo con los datos devueltos por Supabase.
function supabase(string $method, string $table, array $params = [], ?array $body = null): array {
    // Construye la URL base apuntando a la tabla indicada
    $url = SUPABASE_URL . '/rest/v1/' . $table;

    // Si hay parámetros de filtro, los agrega a la URL como query string
    // Ejemplo: ?id=eq.5&order=nombre.asc
    if ($params) {
        $url .= '?' . http_build_query($params);
    }

    // Cabeceras de autenticación que Supabase exige en cada petición
    $headers = [
        'apikey: ' . SUPABASE_KEY,              // Clave de API para identificar la app
        'Authorization: Bearer ' . SUPABASE_KEY, // Token de acceso con permisos completos
        'Content-Type: application/json',         // Le dice a Supabase que enviamos JSON
        'Prefer: return=representation',          // Le pide a Supabase que devuelva los datos guardados
    ];

    // Inicializa una petición HTTP con curl (herramienta de PHP para hacer requests)
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,          // Que la respuesta se guarde en variable, no que se imprima
        CURLOPT_HTTPHEADER     => $headers,       // Agrega las cabeceras de arriba
        CURLOPT_CUSTOMREQUEST  => strtoupper($method), // El verbo HTTP (GET, POST, etc.)
        // SSL: desactivado en local (XAMPP no tiene CA bundle), activado en producción
        CURLOPT_SSL_VERIFYPEER => getenv('APP_ENV') === 'production',
        CURLOPT_SSL_VERIFYHOST => getenv('APP_ENV') === 'production' ? 2 : 0,
    ]);

    // Si hay datos para enviar (POST/PATCH), los convierte a JSON y los adjunta
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    // Ejecuta la petición y recoge la respuesta
    $response = curl_exec($ch);
    // Lee el código de estado HTTP devuelto (200=OK, 404=no encontrado, 400=error, etc.)
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Convierte la respuesta JSON en arreglo PHP
    $data = json_decode($response, true);

    // Si el código HTTP indica error (400 o mayor), lanza una excepción con el mensaje
    if ($status >= 400) {
        $msg = $data['message'] ?? $data['error'] ?? 'Error en Supabase';
        throw new RuntimeException($msg, $status);
    }

    return $data ?? [];
}

// ── Enviar respuesta exitosa ───────────────────────────────────────────────────
// Formatea la respuesta en JSON con el campo "success: true" y los datos,
// luego termina la ejecución del script.
function jsonResponse(mixed $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $status < 400, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Enviar respuesta de error ──────────────────────────────────────────────────
// Formatea la respuesta en JSON con "success: false" y un mensaje de error.
function jsonError(string $message, int $status = 400): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── CORS — permite que el frontend hable con esta API ─────────────────────────
// CORS (Cross-Origin Resource Sharing) es un mecanismo de seguridad del navegador.
// Como el frontend está en localhost/Planmejora/frontend y la API en .../backend,
// el navegador bloquearía las peticiones sin estos encabezados.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Los navegadores envían primero una petición OPTIONS (preflight) para preguntar
// si se permite el CORS. Respondemos 204 (sin contenido) y terminamos.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
