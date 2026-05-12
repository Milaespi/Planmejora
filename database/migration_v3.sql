-- Migración v3: Torre separada + tipo de contrato
ALTER TABLE proyectos ADD COLUMN IF NOT EXISTS torre          VARCHAR(100);
ALTER TABLE proyectos ADD COLUMN IF NOT EXISTS tipo_contrato  VARCHAR(20) NOT NULL DEFAULT 'todo_costo'
    CHECK (tipo_contrato IN ('fase1', 'fase2', 'todo_costo'));
