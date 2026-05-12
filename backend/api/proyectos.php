<?php
require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;

try {
    switch ($method) {

        case 'GET':
            if ($id) {
                $proyecto = supabase('GET', 'proyectos', ['id' => "eq.$id", 'select' => '*']);
                if (empty($proyecto)) jsonError('Proyecto no encontrado', 404);

                $fases = supabase('GET', 'fases', [
                    'proyecto_id' => "eq.$id",
                    'order'       => 'orden.asc',
                    'select'      => '*',
                ]);
                foreach ($fases as &$fase) {
                    $fase['actividades'] = supabase('GET', 'actividades', [
                        'fase_id' => 'eq.' . $fase['id'],
                        'order'   => 'orden.asc',
                        'select'  => '*',
                    ]);
                }
                $proyecto[0]['fases'] = $fases;
                jsonResponse($proyecto[0]);
            }

            $params = ['order' => 'created_at.desc', 'select' => '*'];
            if (isset($_GET['estado'])) $params['estado'] = 'eq.' . $_GET['estado'];
            jsonResponse(supabase('GET', 'proyectos', $params));

        case 'POST':
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            validarProyecto($body);

            $tiposValidos = ['fase1', 'fase2', 'todo_costo'];
            $tipoContrato = in_array($body['tipo_contrato'] ?? '', $tiposValidos, true)
                ? $body['tipo_contrato']
                : 'todo_costo';

            $datos = [
                'nombre'             => trim($body['nombre']),
                'direccion'          => trim($body['nombre']),
                'cliente'            => trim($body['cliente']),
                'fecha_inicio'       => $body['fecha_inicio'],
                'fecha_fin_estimada' => $body['fecha_fin_estimada'],
                'estado'             => 'activo',
                'tipo_contrato'      => $tipoContrato,
            ];
            if (!empty($body['unidad_id']))   $datos['unidad_id']   = (int) $body['unidad_id'];
            if (!empty($body['numero_apto'])) $datos['numero_apto'] = trim($body['numero_apto']);
            if (!empty($body['torre']))       $datos['torre']       = trim($body['torre']);

            $nuevo = supabase('POST', 'proyectos', [], $datos);
            crearFasesYActividades($nuevo[0]['id'], $body['fecha_inicio'], $tipoContrato);
            jsonResponse($nuevo[0], 201);

        case 'PUT':
            if (!$id) jsonError('Se requiere el ID del proyecto');
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            $actualizado = supabase('PATCH', 'proyectos', ['id' => "eq.$id"], [
                'nombre'             => trim($body['nombre'] ?? ''),
                'cliente'            => trim($body['cliente'] ?? ''),
                'fecha_inicio'       => $body['fecha_inicio'] ?? null,
                'fecha_fin_estimada' => $body['fecha_fin_estimada'] ?? null,
            ]);
            jsonResponse($actualizado[0] ?? []);

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

// ─── Validación ───────────────────────────────────────────────────────────────

function validarProyecto(array $body): void {
    foreach (['nombre', 'cliente', 'fecha_inicio', 'fecha_fin_estimada'] as $campo) {
        if (empty($body[$campo])) jsonError("El campo '$campo' es requerido");
    }
    if (strlen(trim($body['cliente'])) < 3) jsonError('El nombre del cliente debe tener al menos 3 caracteres');
    if ($body['fecha_fin_estimada'] <= $body['fecha_inicio']) {
        jsonError('La fecha de entrega debe ser posterior a la fecha de inicio');
    }
}

// ─── Crear fases y actividades según tipo de contrato ────────────────────────

function crearFasesYActividades(int $proyectoId, string $fechaInicio, string $tipo = 'todo_costo'): void {
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

    $fases = match($tipo) {
        'fase1'      => [['Obra Blanca',   'obra_blanca',   1, $actividadesF1]],
        'fase2'      => [['Amueblamiento', 'amueblamiento', 2, $actividadesF2]],
        default      => [['Obra Blanca',   'obra_blanca',   1, $actividadesF1],
                         ['Amueblamiento', 'amueblamiento', 2, $actividadesF2]],
    };

    foreach ($fases as [$nombreFase, $tipoFase, $orden, $actividades]) {
        $fase   = supabase('POST', 'fases', [], [
            'proyecto_id' => $proyectoId,
            'nombre'      => $nombreFase,
            'tipo'        => $tipoFase,
            'orden'       => $orden,
        ]);
        $faseId = $fase[0]['id'];

        foreach ($actividades as $i => [$nombre, $desc, $diasOffset]) {
            $fecha = (clone $inicio)->modify("+$diasOffset days")->format('Y-m-d');
            supabase('POST', 'actividades', [], [
                'fase_id'        => $faseId,
                'nombre'         => $nombre,
                'descripcion'    => $desc,
                'estado'         => 'pendiente',
                'fecha_estimada' => $fecha,
                'orden'          => $i + 1,
            ]);
        }
    }
}
