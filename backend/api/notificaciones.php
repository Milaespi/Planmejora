<?php
require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {

        case 'GET':
            // Retorna actividades retrasadas de proyectos activos
            $hoy = date('Y-m-d');

            $actividades = supabase('GET', 'actividades', [
                'select'          => 'id,nombre,estado,fecha_estimada,fase_id',
                'estado'          => 'in.(pendiente,en_progreso)',
                'fecha_estimada'  => "lt.$hoy",
                'order'           => 'fecha_estimada.asc',
            ]);

            // Para cada actividad retrasada, buscar el proyecto
            $resultado = [];
            foreach ($actividades as $act) {
                $fases = supabase('GET', 'fases', [
                    'id'     => 'eq.' . $act['fase_id'],
                    'select' => 'nombre,proyecto_id',
                ]);
                if (empty($fases)) continue;

                $proyectos = supabase('GET', 'proyectos', [
                    'id'     => 'eq.' . $fases[0]['proyecto_id'],
                    'select' => 'id,nombre,estado',
                    'estado' => 'eq.activo',
                ]);
                if (empty($proyectos)) continue;

                $diasRetraso = (int) round(
                    (strtotime($hoy) - strtotime($act['fecha_estimada'])) / 86400
                );

                $resultado[] = [
                    'actividad_id'   => $act['id'],
                    'actividad'      => $act['nombre'],
                    'fase'           => $fases[0]['nombre'],
                    'proyecto_id'    => $proyectos[0]['id'],
                    'proyecto'       => $proyectos[0]['nombre'],
                    'fecha_estimada' => $act['fecha_estimada'],
                    'dias_retraso'   => $diasRetraso,
                ];
            }

            jsonResponse($resultado);

        case 'POST':
            // Dispara el script Python de alertas SMS
            $scriptPath = __DIR__ . '/../../scripts/cron_alertas.py';
            $envPath    = __DIR__ . '/../../.env';

            if (!file_exists($scriptPath)) {
                jsonError('Script de alertas no encontrado', 500);
            }

            $output   = [];
            $exitCode = 0;
            exec("python \"$scriptPath\" 2>&1", $output, $exitCode);

            jsonResponse([
                'ejecutado' => true,
                'salida'    => implode("\n", $output),
                'codigo'    => $exitCode,
            ]);

        default:
            jsonError('Método no permitido', 405);
    }
} catch (RuntimeException $e) {
    jsonError($e->getMessage(), $e->getCode() ?: 500);
}
