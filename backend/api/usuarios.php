<?php
require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;

try {
    switch ($method) {

        case 'GET':
            if ($id) {
                $result = supabase('GET', 'usuarios', ['id' => "eq.$id", 'select' => '*']);
                if (empty($result)) jsonError('Usuario no encontrado', 404);
                jsonResponse($result[0]);
            }
            $params = ['order' => 'nombre.asc', 'select' => '*'];
            if (isset($_GET['rol'])) $params['rol'] = 'eq.' . $_GET['rol'];
            jsonResponse(supabase('GET', 'usuarios', $params));

        case 'POST':
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            if (empty($body['nombre']) || empty($body['rol'])) {
                jsonError('Los campos nombre y rol son requeridos');
            }
            $rolesValidos = ['admin', 'supervisor', 'trabajador'];
            if (!in_array($body['rol'], $rolesValidos, true)) {
                jsonError('Rol inválido. Valores permitidos: ' . implode(', ', $rolesValidos));
            }

            $nuevo = supabase('POST', 'usuarios', [], [
                'nombre'   => trim($body['nombre']),
                'telefono' => $body['telefono'] ?? null,
                'rol'      => $body['rol'],
            ]);
            jsonResponse($nuevo[0], 201);

        case 'PUT':
            if (!$id) jsonError('Se requiere el ID del usuario');
            $body = json_decode(file_get_contents('php://input'), true) ?? [];

            $actualizado = supabase('PATCH', 'usuarios', ['id' => "eq.$id"], [
                'nombre'   => trim($body['nombre'] ?? ''),
                'telefono' => $body['telefono'] ?? null,
                'rol'      => $body['rol'] ?? 'trabajador',
            ]);
            jsonResponse($actualizado[0] ?? []);

        case 'DELETE':
            if (!$id) jsonError('Se requiere el ID del usuario');
            supabase('DELETE', 'usuarios', ['id' => "eq.$id"]);
            jsonResponse(['message' => 'Usuario eliminado']);

        default:
            jsonError('Método no permitido', 405);
    }
} catch (RuntimeException $e) {
    jsonError($e->getMessage(), $e->getCode() ?: 500);
}
