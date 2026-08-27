-- Migraciones idempotentes (se ejecutan en cada arranque).

-- Lecturas del tanque de combustible (en litros) para el reporte de viajes.
ALTER TABLE viajes ADD COLUMN IF NOT EXISTS combustible_inicio NUMERIC(10,2);
ALTER TABLE viajes ADD COLUMN IF NOT EXISTS combustible_fin    NUMERIC(10,2);

-- Nombre del marinero (lo captura el capitán).
ALTER TABLE viajes ADD COLUMN IF NOT EXISTS marinero TEXT;

-- Nombre de cliente libre (para rentas a externos, sin socio).
ALTER TABLE viajes ADD COLUMN IF NOT EXISTS cliente_nombre TEXT;

-- Las rentas no tienen socio: socio_id pasa a ser opcional.
ALTER TABLE viajes ALTER COLUMN socio_id DROP NOT NULL;

-- Ajuste de datos: la Sra. Gardenia pertenece a la familia García (socio 2).
INSERT INTO clientes (nombre, socio_id)
  SELECT 'Sra. Gardenia', 2
  WHERE NOT EXISTS (SELECT 1 FROM clientes WHERE lower(nombre) LIKE '%gardenia%');
UPDATE reservas SET socio_id = 2
  WHERE lower(cliente) LIKE '%gardenia%' AND socio_id IS DISTINCT FROM 2;

-- Normaliza el dato histórico de marineros y siembra el catálogo.
UPDATE viajes SET marinero = 'Ulices Quiroz' WHERE lower(btrim(marinero)) = 'ulices quiroz';
INSERT INTO marineros (nombre)
  SELECT DISTINCT btrim(marinero) FROM viajes
   WHERE marinero IS NOT NULL AND btrim(marinero) <> ''
ON CONFLICT DO NOTHING;

-- Tabla de manuales de uso por rol.
CREATE TABLE IF NOT EXISTS manuales (
  rol            TEXT PRIMARY KEY CHECK (rol IN ('admin','capitan','socio')),
  nombre         TEXT NOT NULL,
  datos          TEXT NOT NULL,
  actualizado_en TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Recrear vistas con la lógica de reparto final (resto al socio de mayor id).
CREATE OR REPLACE VIEW v_balance_operativo AS
WITH op AS (
    SELECT periodo_id, SUM(monto_mxn) AS total_operativo
    FROM gastos_operativos GROUP BY periodo_id
), renta AS (
    SELECT periodo_id, SUM(utilidad) AS total_utilidad
    FROM ingresos_renta GROUP BY periodo_id
)
SELECT t.periodo_id,
       t.socio_id,
       t.saldo_inicio,
       t.aportaciones,
       t.gastos_generales,
       t.gastos_variables,
       t.utilidad_renta,
       t.saldo_inicio + t.aportaciones - t.gastos_generales - t.gastos_variables + t.utilidad_renta AS balance_operativo
FROM (
  SELECT s.periodo_id,
         s.socio_id,
         s.saldo_inicio,
         COALESCE(ap.aportaciones, 0) AS aportaciones,
         CASE WHEN s.socio_id = (SELECT MAX(id) FROM socios)
              THEN COALESCE(op.total_operativo, 0) - ROUND(COALESCE(op.total_operativo, 0) * ((100 - so.porcentaje)/100), 2)
              ELSE ROUND(COALESCE(op.total_operativo, 0) * (so.porcentaje/100), 2) END AS gastos_generales,
         COALESCE(var.total_variable, 0) AS gastos_variables,
         CASE WHEN s.socio_id = (SELECT MAX(id) FROM socios)
              THEN COALESCE(renta.total_utilidad, 0) - ROUND(COALESCE(renta.total_utilidad, 0) * ((100 - so.porcentaje)/100), 2)
              ELSE ROUND(COALESCE(renta.total_utilidad, 0) * (so.porcentaje/100), 2) END AS utilidad_renta
  FROM saldos_socio s
  JOIN socios so ON so.id = s.socio_id
  LEFT JOIN op    ON op.periodo_id = s.periodo_id
  LEFT JOIN renta ON renta.periodo_id = s.periodo_id
  LEFT JOIN v_variable_por_socio var
         ON var.periodo_id = s.periodo_id AND var.socio_id = s.socio_id
  LEFT JOIN (SELECT periodo_id, socio_id, SUM(monto) AS aportaciones
             FROM aportaciones GROUP BY periodo_id, socio_id) ap
         ON ap.periodo_id = s.periodo_id AND ap.socio_id = s.socio_id
) t;

CREATE OR REPLACE VIEW v_extraordinarios_por_socio AS
WITH tot AS (
    SELECT periodo_id, SUM(monto_mxn) AS total FROM extraordinarios GROUP BY periodo_id
)
SELECT tot.periodo_id,
       so.id AS socio_id,
       CASE WHEN so.id = (SELECT MAX(id) FROM socios)
            THEN tot.total - ROUND(tot.total * ((100 - so.porcentaje)/100), 2)
            ELSE ROUND(tot.total * (so.porcentaje/100), 2) END AS liquidacion_extraordinaria
FROM tot
CROSS JOIN socios so;
