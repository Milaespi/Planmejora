<?php
require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;

try {
    switch ($method) {

        case 'GET':
            if ($id) {
                $result = supabase('GET', 'actividades', ['id' => "eq.$id", 'select' => '*']);
                if (empty($result)) jsonError('Actividad no encontrada', 404);
                jsonResponse($result[0]);
            }

            if (!isset($_GET['fase_id'])) jsonError('Se requiere fase_id');
            $faseId = (int) $_GET['fase_id'];

            jsonResponse(supabase('GET', 'actividades', [
                'fase_id' => "eq.$faseId",
                'order'   => 'orden.asc',
                'select'  => '*',
            ]));

        case 'POST':
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            if (empty($body['fase_id']) || empty($body['nombre']) || empty($body['orden'])) {
                jsonError('Los campos fase_id, nombre y orden son requeridos');
            }

            $nueva = supabase('POST', 'actividades', [], [
                'fase_id'        => (int) $body['fase_id'],
                'nombre'         => trim($body['nombre']),
                'descripcion'    => $body['descripcion'] ?? null,
                'estado'         => 'pendiente',
                'fecha_estimada' => $body['fecha_estimada'] ?? null,
                'orden'          => (int) $body['orden'],
            ]);
            jsonResponse($nueva[0], 201);

        case 'PATCH':
            // Cambiar estado de una actividad
            if (!$id) jsonError('Se requiere el ID de la actividad');
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            if (!isset($body['estado'])) jsonError('Se requiere el campo estado');

            $estadosValidos = ['pendiente', 'en_progreso', 'completada', 'retrasada'];
            if (!in_array($body['estado'], $estadosValidos, true)) {
                jsonError('Estado inválido. Valores permitidos: ' . implode(', ', $estadosValidos));
            }

            $datos = ['estado' => $body['estado']];

            if ($body['estado'] === 'completada') {
                $datos['fecha_completada'] = date('Y-m-d');
            }

            $actualizada = supabase('PATCH', 'actividades', ['id' => "eq.$id"], $datos);
            jsonResponse($actualizada[0] ?? []);

        case 'PUT':
            if (!$id) jsonError('Se requiere el ID de la actividad');
            $body = json_decode(file_get_contents('php://input'), true) ?? [];

            $actualizada = supabase('PATCH', 'actividades', ['id' => "eq.$id"], [
                'nombre'         => trim($body['nombre'] ?? ''),
                'descripcion'    => $body['descripcion'] ?? null,
                'fecha_estimada' => $body['fecha_estimada'] ?? null,
            ]);
            jsonResponse($actualizada[0] ?? []);

        case 'DELETE':
            if (!$id) jsonError('Se requiere el ID de la actividad');
            supabase('DELETE', 'actividades', ['id' => "eq.$id"]);
            jsonResponse(['message' => 'Actividad eliminada']);

        default:
            jsonError('Método no permitido', 405);
    }
} catch (RuntimeException $e) {
    jsonError($e->getMessage(), $e->getCode() ?: 500);
}
