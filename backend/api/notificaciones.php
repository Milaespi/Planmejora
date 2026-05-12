<?php
/**
 * notificaciones.php — API para alertas de actividades retrasadas
 *
 * Rutas soportadas:
 *   GET  /notificaciones.php → Lista actividades retrasadas en proyectos activos
 *   POST /notificaciones.php → Ejecuta el script Python que envía SMS por Twilio
 *
 * Una actividad se considera "retrasada" si:
 *   - Su estado es "pendiente" o "en_progreso" (no completada), Y
 *   - Su fecha_estimada ya pasó (es anterior a hoy)
 */
require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {

        // ── Obtener actividades retrasadas ─────────────────────────────────────
        case 'GET':
            $hoy = date('Y-m-d'); // Fecha de hoy en formato YYYY-MM-DD

            // Busca actividades pendientes o en progreso cuya fecha ya venció
            // "lt.$hoy" significa "less than hoy" = fecha anterior a hoy
            $actividades = supabase('GET', 'actividades', [
                'select'          => 'id,nombre,estado,fecha_estimada,fase_id',
                'estado'          => 'in.(pendiente,en_progreso)',
                'fecha_estimada'  => "lt.$hoy",
                'order'           => 'fecha_estimada.asc', // Las más antiguas primero
            ]);

            // Para cada actividad retrasada, buscamos a qué proyecto pertenece
            // (actividad → fase → proyecto)
            $resultado = [];
            foreach ($actividades as $act) {
                // Paso 1: buscar la fase a la que pertenece esta actividad
                $fases = supabase('GET', 'fases', [
                    'id'     => 'eq.' . $act['fase_id'],
                    'select' => 'nombre,proyecto_id',
                ]);
                if (empty($fases)) continue; // Si no hay fase, saltamos esta actividad

                // Paso 2: buscar el proyecto (solo si está "activo")
                // Los proyectos pausados o completados no generan alertas
                $proyectos = supabase('GET', 'proyectos', [
                    'id'     => 'eq.' . $fases[0]['proyecto_id'],
                    'select' => 'id,nombre,estado',
                    'estado' => 'eq.activo', // Solo proyectos activos
                ]);
                if (empty($proyectos)) continue;

                // Calcula cuántos días lleva de retraso
                $diasRetraso = (int) round(
                    (strtotime($hoy) - strtotime($act['fecha_estimada'])) / 86400
                );

                // Agrega al resultado con toda la información necesaria para la UI
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

        // ── Enviar alertas SMS ─────────────────────────────────────────────────
        // Ejecuta el script Python que usa Twilio para enviar mensajes de texto
        // a los responsables cuando hay actividades retrasadas.
        case 'POST':
            $scriptPath = __DIR__ . '/../../scripts/cron_alertas.py';
            $envPath    = __DIR__ . '/../../.env';

            // Verifica que el script Python exista antes de intentar ejecutarlo
            if (!file_exists($scriptPath)) {
                jsonError('Script de alertas no encontrado', 500);
            }

            // exec() ejecuta un comando del sistema operativo desde PHP
            // "2>&1" redirige los errores al mismo canal que la salida normal
            $output   = [];
            $exitCode = 0;
            exec("python \"$scriptPath\" 2>&1", $output, $exitCode);

            // Devuelve si se ejecutó, lo que imprimió el script y el código de salida
            // (código 0 = sin errores, cualquier otro = hubo un problema)
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
