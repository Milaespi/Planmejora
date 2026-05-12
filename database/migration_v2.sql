-- Migración v2: Unidades, apartamentos y contraseñas
CREATE TABLE IF NOT EXISTS unidades (
    id         BIGSERIAL PRIMARY KEY,
    nombre     VARCHAR(255) NOT NULL UNIQUE,
    direccion  VARCHAR(255),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE proyectos ADD COLUMN IF NOT EXISTS unidad_id   BIGINT REFERENCES unidades(id) ON DELETE SET NULL;
ALTER TABLE proyectos ADD COLUMN IF NOT EXISTS numero_apto VARCHAR(50);
ALTER TABLE usuarios  ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NOT NULL DEFAULT '';

CREATE INDEX IF NOT EXISTS idx_proyectos_unidad ON proyectos(unidad_id);

-- Contraseñas: admin2026 / supervisor2026 / trabajador2026
UPDATE usuarios SET password_hash = '$2y$10$iIvc1kcVy1MeXOTkcUVxC.hCnpq/GnN35dcPFP4uzr21nkOJEs942' WHERE id = 1;
UPDATE usuarios SET password_hash = '$2y$10$4H4l7cmBmeJJ80mPtzcMTe0vIpTYKPiiHtyBoIa4/vzpR1nT135o2' WHERE id = 2;
UPDATE usuarios SET password_hash = '$2y$10$4E5kjLCa8GMGnqQVF12HRO4IUenHFu4qZ82R.GwNZNMYWWUKXMvZK' WHERE id = 3;

-- Unidades de prueba
INSERT INTO unidades (nombre, direccion) VALUES
    ('Amaranto', 'Cali, Valle del Cauca'),
    ('Camelia',  'Cali, Valle del Cauca'),
    ('Praia',    'Cali, Valle del Cauca')
ON CONFLICT (nombre) DO NOTHING;
