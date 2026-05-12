-- ============================================================
-- Sistema de Monitoreo de Proyectos
-- R.E Amueblamiento de Espacios S.A.S.
-- ============================================================

-- Tabla: proyectos
CREATE TABLE IF NOT EXISTS proyectos (
    id               BIGSERIAL PRIMARY KEY,
    nombre           VARCHAR(255) NOT NULL,
    direccion        VARCHAR(255) NOT NULL,
    cliente          VARCHAR(255) NOT NULL,
    fecha_inicio     DATE NOT NULL,
    fecha_fin_estimada DATE NOT NULL,
    estado           VARCHAR(20) NOT NULL DEFAULT 'activo'
                     CHECK (estado IN ('activo', 'pausado', 'completado')),
    created_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT fecha_valida CHECK (fecha_fin_estimada > fecha_inicio)
);

-- Tabla: fases
CREATE TABLE IF NOT EXISTS fases (
    id           BIGSERIAL PRIMARY KEY,
    proyecto_id  BIGINT NOT NULL REFERENCES proyectos(id) ON DELETE CASCADE,
    nombre       VARCHAR(255) NOT NULL,
    tipo         VARCHAR(30) NOT NULL
                 CHECK (tipo IN ('obra_blanca', 'amueblamiento')),
    orden        INT NOT NULL CHECK (orden IN (1, 2)),
    created_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (proyecto_id, orden)
);

-- Tabla: actividades
CREATE TABLE IF NOT EXISTS actividades (
    id               BIGSERIAL PRIMARY KEY,
    fase_id          BIGINT NOT NULL REFERENCES fases(id) ON DELETE CASCADE,
    nombre           VARCHAR(255) NOT NULL,
    descripcion      TEXT,
    estado           VARCHAR(20) NOT NULL DEFAULT 'pendiente'
                     CHECK (estado IN ('pendiente', 'en_progreso', 'completada', 'retrasada')),
    fecha_estimada   DATE,
    fecha_completada DATE,
    orden            INT NOT NULL,
    created_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (fase_id, orden)
);

-- Tabla: usuarios
CREATE TABLE IF NOT EXISTS usuarios (
    id         BIGSERIAL PRIMARY KEY,
    nombre     VARCHAR(255) NOT NULL,
    telefono   VARCHAR(20),
    rol        VARCHAR(20) NOT NULL
               CHECK (rol IN ('admin', 'supervisor', 'trabajador')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Tabla: alertas
CREATE TABLE IF NOT EXISTS alertas (
    id           BIGSERIAL PRIMARY KEY,
    actividad_id BIGINT NOT NULL REFERENCES actividades(id) ON DELETE CASCADE,
    mensaje      TEXT NOT NULL,
    enviada      BOOLEAN NOT NULL DEFAULT false,
    fecha_envio  TIMESTAMPTZ,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Índices para consultas frecuentes
CREATE INDEX IF NOT EXISTS idx_fases_proyecto ON fases(proyecto_id);
CREATE INDEX IF NOT EXISTS idx_actividades_fase ON actividades(fase_id);
CREATE INDEX IF NOT EXISTS idx_actividades_estado ON actividades(estado);
CREATE INDEX IF NOT EXISTS idx_actividades_fecha ON actividades(fecha_estimada);
CREATE INDEX IF NOT EXISTS idx_alertas_actividad ON alertas(actividad_id);
CREATE INDEX IF NOT EXISTS idx_alertas_fecha ON alertas(fecha_envio);
