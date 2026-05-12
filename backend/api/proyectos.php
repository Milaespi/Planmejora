<?php
/**
 * proyectos.php — API REST para gestionar proyectos (apartamentos)
 *
 * En este sistema cada "proyecto" representa un apartamento en remodelación.
 * Contiene las fases (Obra Blanca / Amueblamiento) y las actividades de cada fase.
 *
 * Rutas soportadas:
 *   GET    /proyectos.php              → Lista todos los proyectos
 *   GET    /proyectos.php?id=X         → Detalle con fases y actividades
 *   GET    /proyectos.php?estado=activo → Lista filtrada por estado
 *   POST   /proyectos.php              → Crear proyecto + fases + actividades automáticamente
 *   PUT    /proyectos.php?id=X         → Editar información del proyecto
 *   PATCH  /proyectos.php?id=X         → Cambiar solo el estado del proyecto
 *   DELETE /proyectos.php?id=X         → Eliminar proyecto (y sus fases/actividades en cascada)
 */
require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;

try {
    switch ($method) {

        // ── Consultar proyectos ────────────────────────────────────────────────
        case 'GET':
            if ($id) {
                // Obtiene el proyecto por ID
                $proyecto = supabase('GET', 'proyectos', ['id' => "eq.$id", 'select' => '*']);
                if (empty($proyecto)) jsonError('Proyecto no encontrado', 404);

                // Carga las fases del proyecto, ordenadas por su número de orden
                $fases = supabase('GET', 'fases', [
                    'proyecto_id' => "eq.$id",
                    'order'       => 'orden.asc',
                    'select'      => '*',
                ]);

                // Para cada fase, carga sus actividades también ordenadas
                foreach ($fases as &$fase) {
                    $fase['actividades'] = supabase('GET', 'actividades', [
                        'fase_id' => 'eq.' . $fase['id'],
                        'order'   => 'orden.asc',
                        'select'  => '*',
                    ]);
                }

                // Adjunta las fases (con sus actividades) al objeto del proyecto
                $proyecto[0]['fases'] = $fases;
                jsonResponse($proyecto[0]);
            }

            // Sin ID: lista todos los proyectos, con filtro opcional por estado
            $params = ['order' => 'created_at.desc', 'select' => '*'];
            if (isset($_GET['estado'])) $params['estado'] = 'eq.' . $_GET['estado'];
            jsonResponse(supabase('GET', 'proyectos', $params));

        // ── Crear nuevo proyecto ───────────────────────────────────────────────
        // Al crear un proyecto se generan automáticamente las fases y actividades
        // según el tipo de contrato elegido.
        case 'POST':
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            validarProyecto($body); // Verifica que los campos obligatorios estén presentes

            // Determina el tipo de contrato; si no envían un valor válido, usa 'todo_costo'
            $tiposValidos = ['fase1', 'fase2', 'todo_costo'];
            $tipoContrato = in_array($body['tipo_contrato'] ?? '', $tiposValidos, true)
                ? $body['tipo_contrato']
                : 'todo_costo';

            // Construye el nombre del proyecto combinando torre y número de apartamento
            $datos = [
                'nombre'             => trim($body['nombre']),
                'direccion'          => trim($body['nombre']), // Se usa nombre como placeholder de dirección
                'cliente'            => trim($body['cliente']),
                'fecha_inicio'       => $body['fecha_inicio'],
                'fecha_fin_estimada' => $body['fecha_fin_estimada'],
                'estado'             => 'activo',              // Todo proyecto empieza activo
                'tipo_contrato'      => $tipoContrato,
            ];

            // Campos opcionales: unidad, número de apartamento y torre
            if (!empty($body['unidad_id']))   $datos['unidad_id']   = (int) $body['unidad_id'];
            if (!empty($body['numero_apto'])) $datos['numero_apto'] = trim($body['numero_apto']);
            if (!empty($body['torre']))       $datos['torre']       = trim($body['torre']);

            // Crea el registro del proyecto en la base de datos
            $nuevo = supabase('POST', 'proyectos', [], $datos);

            // Crea automáticamente las fases y actividades del proyecto
            crearFasesYActividades($nuevo[0]['id'], $body['fecha_inicio'], $body['fecha_fin_estimada'], $tipoContrato);
            jsonResponse($nuevo[0], 201);

        // ── Editar información de un proyecto ─────────────────────────────────
        // Usado desde el modal "Editar Apartamento" del dashboard
        case 'PUT':
            if (!$id) jsonError('Se requiere el ID del proyecto');
            $body = json_decode(file_get_contents('php://input'), true) ?? [];

            // Actualiza los campos editables del proyecto
            $actualizado = supabase('PATCH', 'proyectos', ['id' => "eq.$id"], [
                'nombre'             => trim($body['nombre'] ?? ''),
                'cliente'            => trim($body['cliente'] ?? ''),
                'fecha_inicio'       => $body['fecha_inicio'] ?? null,
                'fecha_fin_estimada' => $body['fecha_fin_estimada'] ?? null,
            ]);
            jsonResponse($actualizado[0] ?? []);

        // ── Cambiar estado del proyecto ────────────────────────────────────────
        // Solo modifica el campo "estado" (activo / pausado / completado)
        case 'PATCH':
            if (!$id) jsonError('Se requiere el ID del proyecto');
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            if (!isset($body['estado'])) jsonError('Se requiere el campo estado');

            $estadosValidos = ['activo', 'pausado', 'completado'];
            if (!in_array($body['estado'], $estadosValidos, true)) {
                jsonError('Estado inválido');
            }
            $actualizado = supabase('PATCH', 'proyectos', ['id' => "eq.$id"], ['estado' => $body['estado']]);
            jsonResponse($actualizado[0] ?? []);

        // ── Eliminar proyecto ──────────────────────────────────────────────────
        // Gracias al CASCADE en la base de datos, también elimina fases y actividades
        case 'DELETE':
            if (!$id) jsonError('Se requiere el ID del proyecto');
            supabase('DELETE', 'proyectos', ['id' => "eq.$id"]);
            jsonResponse(['message' => 'Proyecto eliminado']);

        default:
            jsonError('Método no permitido', 405);
    }
} catch (RuntimeException $e) {
    jsonError($e->getMessage(), $e->getCode() ?: 500);
}

// ── Validación de campos obligatorios ─────────────────────────────────────────
// Se llama al crear un proyecto para asegurarse de que los datos sean correctos
// antes de intentar guardar nada en la base de datos.
function validarProyecto(array $body): void {
    foreach (['nombre', 'cliente', 'fecha_inicio', 'fecha_fin_estimada'] as $campo) {
        if (empty($body[$campo])) jsonError("El campo '$campo' es requerido");
    }
    if (strlen(trim($body['cliente'])) < 3) jsonError('El nombre del cliente debe tener al menos 3 caracteres');
    if ($body['fecha_fin_estimada'] <= $body['fecha_inicio']) {
        jsonError('La fecha de entrega debe ser posterior a la fecha de inicio');
    }
}

// ── Crear fases y actividades automáticamente ─────────────────────────────────
// Al crear un proyecto se llama esta función para poblar la base de datos con
// todas las actividades de obra predefinidas, distribuidas proporcionalmente
// entre la fecha de inicio y la fecha de entrega estimada.
//
// Tipos de contrato:
//   fase1      → Solo Obra Blanca (actividades 1-13)
//   fase2      → Solo Amueblamiento (actividades 1-14)
//   todo_costo → Ambas fases (27 actividades en total)
function crearFasesYActividades(int $proyectoId, string $fechaInicio, string $fechaFin, string $tipo = 'todo_costo'): void {

    // Lista de actividades de Obra Blanca con: nombre, descripción, y días desde el inicio
    // El "días desde el inicio" es relativo a un proyecto de 38 días (se escala después)
    $actividadesF1 = [
        ['Regatas',                    'Apertura de canales para cambio de puntos eléctricos',      7],
        ['Hidráulico',                 'Tubería agua caliente baños, cocina, lavadero y monocontrol', 9],
        ['Tubería aire acondicionado', 'Instalación de cobre y drenaje para A/C',                  12],
        ['Panel yeso',                 'Cielorrasos, descolgados y divisiones en drywall',          15],
        ['Estuco',                     'Aplicación en paredes y techos, tapado de huecos y regatas',19],
        ['Primera mano de pintura',    'Capa base en paredes y cielos',                             22],
        ['Eléctrico',                  'Instalación de luces y tomas eléctricas',                   26],
        ['Mortero',                    'Nivelación de piso y tapado de conexiones',                 28],
        ['Enchape',                    'Cerámica o porcelanato en pisos, baños y zona húmeda',      34],
        ['Retirar escombros',          'Limpieza y retiro de material sobrante de obra',            35],
        ['Instalar sanitarios',        'Conexión de sanitarios con manguera',                       36],
        ['Instalar rejillas',          'Rejillas en 2 baños y zona de lavado',                      37],
        ['Aseo Fase 1',                'Limpieza general al finalizar obra blanca',                 38],
    ];

    // Lista de actividades de Amueblamiento (días escalados sobre 59 días de proyecto base)
    $actividadesF2 = [
        ['Toma de medidas para madera',  'Medición precisa para fabricación de muebles',              40],
        ['Armar madera',                 'Muebles cocina, escritorio, lavadero, baños, closet, vestier',44],
        ['Toma de medidas para piedra',  'Medición para encimeras y mesones',                         45],
        ['Instalación de piedra',        'Piedra en cocina y 2 baños',                                47],
        ['Divisiones de baño',           'Instalación de 2 divisiones de baño',                       48],
        ['Instalar lavamanos y grifería','Lavamanos, llaves cocina y duchas',                          49],
        ['Accesorios de baño',           'Toalleros, ganchos, portarrollos',                           50],
        ['Segunda mano de pintura',      'Capa final con acabado definitivo',                          52],
        ['Instalar estufa y campana',    'Conexión e instalación de estufa y extractora',              53],
        ['Instalar guardaescobas',       'Guardaescobas en cocina y escritorio',                       54],
        ['Instalación de espejos',       'Espejos en baños',                                           55],
        ['Fraguar apartamento',          'Fragua en enchapes y pisos',                                 56],
        ['Aseo final',                   'Limpieza profunda final',                                    58],
        ['Detallar madera',              'Ajustes y detalles finales en carpintería',                  59],
    ];

    $inicio = new DateTime($fechaInicio);

    // Selecciona qué fases crear según el tipo de contrato
    $fases = match($tipo) {
        'fase1'  => [['Obra Blanca',   'obra_blanca',   1, $actividadesF1]],
        'fase2'  => [['Amueblamiento', 'amueblamiento', 2, $actividadesF2]],
        default  => [['Obra Blanca',   'obra_blanca',   1, $actividadesF1],
                     ['Amueblamiento', 'amueblamiento', 2, $actividadesF2]],
    };

    // El offset máximo es el número de días del último paso en el cronograma base.
    // Se usa para calcular el factor de escala.
    $maxOffset = match($tipo) {
        'fase1'  => 38,  // El último paso de Obra Blanca está en el día 38
        'fase2'  => 59,  // El último paso de Amueblamiento está en el día 59
        default  => 59,  // Con las dos fases, el máximo sigue siendo 59
    };

    // Duración real del proyecto (diferencia entre inicio y entrega)
    $duracionDias = (int) $inicio->diff(new DateTime($fechaFin))->days;

    // Factor de escala: convierte los días del cronograma base a la duración real.
    // Ejemplo: si el proyecto dura 90 días y el máximo base es 59,
    //          una actividad en el día 30 queda en el día round(30 × 90/59) = día 46
    $factor = $duracionDias > 0 ? $duracionDias / $maxOffset : 1;

    foreach ($fases as [$nombreFase, $tipoFase, $orden, $actividades]) {
        // Crea la fase en la base de datos
        $fase   = supabase('POST', 'fases', [], [
            'proyecto_id' => $proyectoId,
            'nombre'      => $nombreFase,
            'tipo'        => $tipoFase,
            'orden'       => $orden,
        ]);
        $faseId = $fase[0]['id'];

        // Crea cada actividad de la fase con su fecha calculada proporcionalmente
        foreach ($actividades as $i => [$nombre, $desc, $diasOffset]) {
            // Aplica el factor de escala y redondea al entero más cercano
            $diasEscalados = (int) round($diasOffset * $factor);
            // Calcula la fecha sumando los días escalados a la fecha de inicio
            $fecha = (clone $inicio)->modify("+$diasEscalados days")->format('Y-m-d');

            supabase('POST', 'actividades', [], [
                'fase_id'        => $faseId,
                'nombre'         => $nombre,
                'descripcion'    => $desc,
                'estado'         => 'pendiente',  // Todas empiezan en pendiente
                'fecha_estimada' => $fecha,
                'orden'          => $i + 1,        // Orden secuencial: 1, 2, 3...
            ]);
        }
    }
}
