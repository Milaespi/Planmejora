<?php
/**
 * unidades.php — API REST para gestionar unidades residenciales (torres/edificios)
 *
 * Una "unidad" es un edificio o conjunto que agrupa apartamentos.
 * Cada apartamento es un "proyecto" en la base de datos, con unidad_id como vínculo.
 *
 * Rutas soportadas:
 *   GET    /unidades.php          → Lista todas las unidades
 *   GET    /unidades.php?id=X     → Detalle de una unidad con sus proyectos
 *   POST   /unidades.php          → Crear nueva unidad
 *   PATCH  /unidades.php?id=X     → Editar nombre/dirección de una unidad
 *   DELETE /unidades.php?id=X     → Eliminar unidad (y sus apartamentos en cascada)
 */
require_once __DIR__ . '/../config/database.php';

// Lee el método HTTP de la petición (GET, POST, PATCH, DELETE)
$method = $_SERVER['REQUEST_METHOD'];
// Lee el parámetro ?id=X de la URL, si existe, y lo convierte a entero para seguridad
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;

try {
    switch ($method) {

        // ── Consultar unidades ─────────────────────────────────────────────────
        case 'GET':
            if ($id) {
                // Obtiene los datos de la unidad por su ID
                $unidad = supabase('GET', 'unidades', ['id' => "eq.$id", 'select' => '*']);
                if (empty($unidad)) jsonError('Unidad no encontrada', 404);

                // Adjunta los proyectos (apartamentos) que pertenecen a esta unidad,
                // ordenados por número de apartamento
                $unidad[0]['proyectos'] = supabase('GET', 'proyectos', [
                    'unidad_id' => "eq.$id",
                    'order'     => 'numero_apto.asc',
                    'select'    => '*',
                ]);
                jsonResponse($unidad[0]);
            }
            // Sin ID: devuelve todas las unidades ordenadas alfabéticamente por nombre
            jsonResponse(supabase('GET', 'unidades', ['order' => 'nombre.asc', 'select' => '*']));

        // ── Crear nueva unidad ─────────────────────────────────────────────────
        case 'POST':
            // Lee el JSON enviado desde el frontend
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            // El nombre es el único campo obligatorio
            if (empty($body['nombre'])) jsonError('El nombre de la unidad es requerido');

            // Inserta la nueva unidad en la base de datos
            // La dirección es opcional; si no la envían, queda NULL
            $nueva = supabase('POST', 'unidades', [], [
                'nombre'    => trim($body['nombre']),
                'direccion' => isset($body['direccion']) ? trim($body['direccion']) : null,
            ]);
            // Devuelve la unidad creada con código 201 (creado exitosamente)
            jsonResponse($nueva[0], 201);

        // ── Editar una unidad ──────────────────────────────────────────────────
        case 'PATCH':
            if (!$id) jsonError('Se requiere el ID de la unidad');
            $body = json_decode(file_get_contents('php://input'), true) ?? [];

            // Solo actualiza los campos que realmente se enviaron
            $datos = [];
            if (isset($body['nombre']))    $datos['nombre']    = trim($body['nombre']);
            if (isset($body['direccion'])) $datos['direccion'] = trim($body['direccion']);

            // Si no mandaron ningún campo, no hay nada que cambiar
            if (empty($datos)) jsonError('No hay campos para actualizar');

            $actualizada = supabase('PATCH', 'unidades', ['id' => "eq.$id"], $datos);
            jsonResponse($actualizada[0] ?? []);

        // ── Eliminar una unidad ────────────────────────────────────────────────
        // NOTA: la base de datos tiene CASCADE configurado, así que al borrar la
        // unidad también se borran automáticamente todos sus proyectos (apartamentos),
        // y a su vez las fases y actividades de cada proyecto.
        case 'DELETE':
            if (!$id) jsonError('Se requiere el ID de la unidad');
            supabase('DELETE', 'unidades', ['id' => "eq.$id"]);
            jsonResponse(['message' => 'Unidad eliminada']);

        default:
            jsonError('Método no permitido', 405);
    }
} catch (RuntimeException $e) {
    // Si Supabase devuelve un error (ej: clave foránea violada), lo capturamos
    // y lo enviamos como respuesta de error al frontend
    jsonError($e->getMessage(), $e->getCode() ?: 500);
}
