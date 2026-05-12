<?php
require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {

        case 'GET':
            $params = ['order' => 'created_at.desc', 'select' => '*'];
            if (isset($_GET['actividad_id'])) {
                $params['actividad_id'] = 'eq.' . (int) $_GET['actividad_id'];
            }
            if (isset($_GET['enviada'])) {
                $params['enviada'] = 'eq.' . $_GET['enviada'];
            }
            // Últimas N alertas (default 50)
            $params['limit'] = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
            jsonResponse(supabase('GET', 'alertas', $params));

        case 'POST':
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            if (empty($body['actividad_id']) || empty($body['mensaje'])) {
                jsonError('Los campos actividad_id y mensaje son requeridos');
            }

            $nueva = supabase('POST', 'alertas', [], [
                'actividad_id' => (int) $body['actividad_id'],
                'mensaje'      => trim($body['mensaje']),
                'enviada'      => $body['enviada'] ?? false,
                'fecha_envio'  => $body['enviada'] ? date('c') : null,
            ]);
            jsonResponse($nueva[0], 201);

        default:
            jsonError('Método no permitido', 405);
    }
} catch (RuntimeException $e) {
    jsonError($e->getMessage(), $e->getCode() ?: 500);
}
