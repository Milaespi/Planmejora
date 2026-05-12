<?php
require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;

try {
    switch ($method) {

        case 'GET':
            if ($id) {
                $unidad = supabase('GET', 'unidades', ['id' => "eq.$id", 'select' => '*']);
                if (empty($unidad)) jsonError('Unidad no encontrada', 404);
                $unidad[0]['proyectos'] = supabase('GET', 'proyectos', [
                    'unidad_id' => "eq.$id",
                    'order'     => 'numero_apto.asc',
                    'select'    => '*',
                ]);
                jsonResponse($unidad[0]);
            }
            jsonResponse(supabase('GET', 'unidades', ['order' => 'nombre.asc', 'select' => '*']));

        case 'POST':
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            if (empty($body['nombre'])) jsonError('El nombre de la unidad es requerido');
            $nueva = supabase('POST', 'unidades', [], [
                'nombre'    => trim($body['nombre']),
                'direccion' => isset($body['direccion']) ? trim($body['direccion']) : null,
            ]);
            jsonResponse($nueva[0], 201);

        case 'PATCH':
            if (!$id) jsonError('Se requiere el ID de la unidad');
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            $datos = [];
            if (isset($body['nombre']))    $datos['nombre']    = trim($body['nombre']);
            if (isset($body['direccion'])) $datos['direccion'] = trim($body['direccion']);
            if (empty($datos)) jsonError('No hay campos para actualizar');
            $actualizada = supabase('PATCH', 'unidades', ['id' => "eq.$id"], $datos);
            jsonResponse($actualizada[0] ?? []);

        case 'DELETE':
            if (!$id) jsonError('Se requiere el ID de la unidad');
            supabase('DELETE', 'unidades', ['id' => "eq.$id"]);
            jsonResponse(['message' => 'Unidad eliminada']);

        default:
            jsonError('Método no permitido', 405);
    }
} catch (RuntimeException $e) {
    jsonError($e->getMessage(), $e->getCode() ?: 500);
}
