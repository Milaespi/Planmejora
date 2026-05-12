<?php
/**
 * actividades.php — API REST para gestionar actividades de obra
 *
 * Las actividades son los pasos individuales dentro de cada fase
 * (ej: "Regatas", "Hidráulico", "Enchape"...).
 *
 * Rutas soportadas:
 *   GET    /actividades.php?id=X       → Detalle de una actividad
 *   GET    /actividades.php?fase_id=X  → Todas las actividades de una fase
 *   POST   /actividades.php            → Crear actividad manualmente
 *   PATCH  /actividades.php?id=X       → Cambiar estado (y fecha de completado)
 *   PUT    /actividades.php?id=X       → Editar nombre, descripción o fecha
 *   DELETE /actividades.php?id=X       → Eliminar actividad
 */
require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;

try {
    switch ($method) {

        // ── Consultar actividades ──────────────────────────────────────────────
        case 'GET':
            if ($id) {
                // Busca una actividad específica por su ID
                $result = supabase('GET', 'actividades', ['id' => "eq.$id", 'select' => '*']);
                if (empty($result)) jsonError('Actividad no encontrada', 404);
                jsonResponse($result[0]);
            }

            // Sin ?id, se exige ?fase_id para devolver todas las actividades de esa fase
            if (!isset($_GET['fase_id'])) jsonError('Se requiere fase_id');
            $faseId = (int) $_GET['fase_id'];

            // Devuelve las actividades ordenadas según el campo "orden" (1, 2, 3...)
            jsonResponse(supabase('GET', 'actividades', [
                'fase_id' => "eq.$faseId",
                'order'   => 'orden.asc',
                'select'  => '*',
            ]));

        // ── Crear actividad manualmente ────────────────────────────────────────
        // Normalmente las actividades se crean automáticamente al crear un proyecto.
        // Este endpoint existe por si se necesita agregar una actividad extra.
        case 'POST':
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            if (empty($body['fase_id']) || empty($body['nombre']) || empty($body['orden'])) {
                jsonError('Los campos fase_id, nombre y orden son requeridos');
            }

            $nueva = supabase('POST', 'actividades', [], [
                'fase_id'        => (int) $body['fase_id'],
                'nombre'         => trim($body['nombre']),
                'descripcion'    => $body['descripcion'] ?? null,
                'estado'         => 'pendiente',              // Estado inicial siempre es pendiente
                'fecha_estimada' => $body['fecha_estimada'] ?? null,
                'orden'          => (int) $body['orden'],
            ]);
            jsonResponse($nueva[0], 201);

        // ── Cambiar estado de una actividad ────────────────────────────────────
        // Este es el endpoint más usado: se llama desde el modal de "Cambiar estado"
        // en proyecto.html cuando el trabajador marca una actividad como completada, etc.
        case 'PATCH':
            if (!$id) jsonError('Se requiere el ID de la actividad');
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            if (!isset($body['estado'])) jsonError('Se requiere el campo estado');

            // Verifica que el estado sea uno de los cuatro valores permitidos
            $estadosValidos = ['pendiente', 'en_progreso', 'completada', 'retrasada'];
            if (!in_array($body['estado'], $estadosValidos, true)) {
                jsonError('Estado inválido. Valores permitidos: ' . implode(', ', $estadosValidos));
            }

            $datos = ['estado' => $body['estado']];

            // Si la actividad se marca como completada, se guarda la fecha de hoy
            // automáticamente en el campo fecha_completada
            if ($body['estado'] === 'completada') {
                $datos['fecha_completada'] = date('Y-m-d');
            }

            $actualizada = supabase('PATCH', 'actividades', ['id' => "eq.$id"], $datos);
            jsonResponse($actualizada[0] ?? []);

        // ── Editar nombre, descripción o fecha ────────────────────────────────
        case 'PUT':
            if (!$id) jsonError('Se requiere el ID de la actividad');
            $body = json_decode(file_get_contents('php://input'), true) ?? [];

            $actualizada = supabase('PATCH', 'actividades', ['id' => "eq.$id"], [
                'nombre'         => trim($body['nombre'] ?? ''),
                'descripcion'    => $body['descripcion'] ?? null,
                'fecha_estimada' => $body['fecha_estimada'] ?? null,
            ]);
            jsonResponse($actualizada[0] ?? []);

        // ── Eliminar actividad ─────────────────────────────────────────────────
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
