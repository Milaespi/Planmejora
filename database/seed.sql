-- ============================================================
-- Datos de prueba — Sistema Planmejora
-- ============================================================

-- Usuario admin y supervisor
INSERT INTO usuarios (nombre, telefono, rol) VALUES
    ('Administrador Sistema', NULL, 'admin'),
    ('Ricardo Espinosa', '+573137126998', 'supervisor'),
    ('Trabajador Prueba', '+573100000001', 'trabajador');

-- Proyecto de prueba
INSERT INTO proyectos (nombre, direccion, cliente, fecha_inicio, fecha_fin_estimada, estado)
VALUES ('Apartamento Cra 5', 'Carrera 5 #12-34, Cali', 'Juan García', '2026-05-05', '2026-06-30', 'activo');

-- Fases del proyecto (id=1)
INSERT INTO fases (proyecto_id, nombre, tipo, orden) VALUES
    (1, 'Obra Blanca', 'obra_blanca', 1),
    (1, 'Amueblamiento', 'amueblamiento', 2);

-- Actividades Fase 1 — Obra Blanca (fase_id=1)
INSERT INTO actividades (fase_id, nombre, descripcion, estado, fecha_estimada, orden) VALUES
    (1, 'Regatas', 'Apertura de canales para cambio de puntos eléctricos', 'completada', '2026-05-07', 1),
    (1, 'Hidráulico', 'Tubería agua caliente baños, cocina, lavadero y monocontrol', 'completada', '2026-05-09', 2),
    (1, 'Tubería aire acondicionado', 'Instalación de cobre y drenaje para A/C', 'en_progreso', '2026-05-12', 3),
    (1, 'Panel yeso', 'Cielorrasos, descolgados y divisiones en drywall', 'pendiente', '2026-05-15', 4),
    (1, 'Estuco', 'Aplicación en paredes y techos, tapado de huecos y regatas', 'pendiente', '2026-05-19', 5),
    (1, 'Primera mano de pintura', 'Capa base en paredes y cielos', 'pendiente', '2026-05-22', 6),
    (1, 'Eléctrico', 'Instalación de luces y tomas eléctricas', 'pendiente', '2026-05-26', 7),
    (1, 'Mortero', 'Nivelación de piso y tapado de conexiones', 'pendiente', '2026-05-28', 8),
    (1, 'Enchape', 'Cerámica o porcelanato en pisos, baños y zona húmeda', 'pendiente', '2026-06-03', 9),
    (1, 'Retirar escombros', 'Limpieza y retiro de material sobrante', 'pendiente', '2026-06-04', 10),
    (1, 'Instalar sanitarios', 'Conexión de sanitarios con manguera', 'pendiente', '2026-06-05', 11),
    (1, 'Instalar rejillas', 'Rejillas en 2 baños y zona de lavado', 'pendiente', '2026-06-06', 12),
    (1, 'Aseo Fase 1', 'Limpieza general al finalizar obra blanca', 'pendiente', '2026-06-07', 13);

-- Actividades Fase 2 — Amueblamiento (fase_id=2)
INSERT INTO actividades (fase_id, nombre, descripcion, estado, fecha_estimada, orden) VALUES
    (2, 'Toma de medidas para madera', 'Medición precisa para fabricación de muebles', 'pendiente', '2026-06-09', 1),
    (2, 'Armar madera', 'Muebles cocina, escritorio, lavadero, baños, closet, vestier', 'pendiente', '2026-06-13', 2),
    (2, 'Toma de medidas para piedra', 'Medición para encimeras y mesones', 'pendiente', '2026-06-14', 3),
    (2, 'Instalación de piedra', 'Piedra en cocina y 2 baños', 'pendiente', '2026-06-16', 4),
    (2, 'Divisiones de baño', 'Instalación de 2 divisiones de baño', 'pendiente', '2026-06-17', 5),
    (2, 'Instalar lavamanos y grifería', 'Lavamanos, llaves cocina y duchas', 'pendiente', '2026-06-18', 6),
    (2, 'Accesorios de baño', 'Toalleros, ganchos, portarrollos', 'pendiente', '2026-06-19', 7),
    (2, 'Segunda mano de pintura', 'Capa final con acabado definitivo', 'pendiente', '2026-06-21', 8),
    (2, 'Instalar estufa y campana', 'Conexión e instalación de estufa y extractora', 'pendiente', '2026-06-22', 9),
    (2, 'Instalar guardaescobas', 'Guardaescobas en cocina y escritorio', 'pendiente', '2026-06-23', 10),
    (2, 'Instalación de espejos', 'Espejos en baños', 'pendiente', '2026-06-24', 11),
    (2, 'Fraguar apartamento', 'Fragua en enchapes y pisos', 'pendiente', '2026-06-25', 12),
    (2, 'Aseo final', 'Limpieza profunda final', 'pendiente', '2026-06-27', 13),
    (2, 'Detallar madera', 'Ajustes y detalles finales en carpintería', 'pendiente', '2026-06-28', 14);

-- Marcar actividad 1 como completada con fecha real
UPDATE actividades SET fecha_completada = '2026-05-07' WHERE id = 1;
UPDATE actividades SET fecha_completada = '2026-05-09' WHERE id = 2;
