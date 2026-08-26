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

-- Caja chica: movimientos (gastos y abonos) por número de caja.
CREATE TABLE IF NOT EXISTS caja_chica (
  id            SERIAL PRIMARY KEY,
  fecha         DATE          NOT NULL,
  tipo          TEXT          NOT NULL DEFAULT 'gasto' CHECK (tipo IN ('gasto','abono')),
  caja_numero   INT,
  factura       TEXT,
  proveedor     TEXT,
  concepto      TEXT,
  monto         NUMERIC(14,2) NOT NULL DEFAULT 0,
  abono         NUMERIC(14,2) NOT NULL DEFAULT 0,
  observaciones TEXT,
  created_at    TIMESTAMPTZ   NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_caja_chica_fecha ON caja_chica(fecha);

-- Insumos: gastos de consumibles capturados como partida y asignados a un viaje.
-- El total por viaje se sincroniza en viajes.costo_consumibles para el estado de cuenta.
CREATE TABLE IF NOT EXISTS insumos (
  id                SERIAL PRIMARY KEY,
  periodo_id        INT           REFERENCES periodos(id) ON DELETE CASCADE,
  viaje_id          INT           REFERENCES viajes(id) ON DELETE CASCADE,
  fecha             DATE          NOT NULL,
  categoria         TEXT,
  proveedor         TEXT,
  concepto          TEXT,
  moneda            TEXT          NOT NULL DEFAULT 'MXN' CHECK (moneda IN ('MXN','USD')),
  monto_original    NUMERIC(14,2) NOT NULL DEFAULT 0,
  tipo_cambio       NUMERIC(8,4)  NOT NULL DEFAULT 1,
  monto_mxn         NUMERIC(14,2) GENERATED ALWAYS AS (monto_original * tipo_cambio) STORED,
  comprobante_folio TEXT,
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_insumos_periodo ON insumos(periodo_id);
CREATE INDEX IF NOT EXISTS idx_insumos_viaje   ON insumos(viaje_id);

-- Cargos recurrentes (nómina semanal, administrativo mensual): clave única para no duplicar.
ALTER TABLE gastos_operativos ADD COLUMN IF NOT EXISTS clave_recurrente TEXT;
CREATE UNIQUE INDEX IF NOT EXISTS idx_operativos_clave_recurrente
  ON gastos_operativos(clave_recurrente) WHERE clave_recurrente IS NOT NULL;

-- Adjuntos (PDF de respaldo) por gasto operativo. Se guardan en la app (base64).
CREATE TABLE IF NOT EXISTS adjuntos (
  id         SERIAL PRIMARY KEY,
  gasto_id   INT           REFERENCES gastos_operativos(id) ON DELETE CASCADE,
  nombre     TEXT          NOT NULL,
  mime       TEXT          NOT NULL DEFAULT 'application/pdf',
  datos      TEXT          NOT NULL,               -- contenido en base64
  generado   BOOLEAN       NOT NULL DEFAULT FALSE, -- true si es un recibo generado por el sistema
  created_at TIMESTAMPTZ   NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_adjuntos_gasto ON adjuntos(gasto_id);

-- Los adjuntos también pueden pertenecer a un insumo de viaje.
ALTER TABLE adjuntos ADD COLUMN IF NOT EXISTS insumo_id INT REFERENCES insumos(id) ON DELETE CASCADE;
CREATE INDEX IF NOT EXISTS idx_adjuntos_insumo ON adjuntos(insumo_id);

-- Abonos de cada socio a la cuenta de gastos extraordinarios (se liquidan por separado).
CREATE TABLE IF NOT EXISTS abonos_extraordinarios (
  id         SERIAL PRIMARY KEY,
  periodo_id INT           NOT NULL REFERENCES periodos(id) ON DELETE CASCADE,
  socio_id   INT           NOT NULL REFERENCES socios(id),
  fecha      DATE          NOT NULL,
  monto      NUMERIC(14,2) NOT NULL DEFAULT 0,
  metodo     TEXT,
  referencia TEXT,
  created_at TIMESTAMPTZ   NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_abonos_extra_periodo ON abonos_extraordinarios(periodo_id);
CREATE INDEX IF NOT EXISTS idx_abonos_extra_socio   ON abonos_extraordinarios(socio_id);

-- Catálogo de marineros (los viajes guardan el nombre en texto; esto alimenta el menú).
CREATE TABLE IF NOT EXISTS marineros (
  id         SERIAL PRIMARY KEY,
  nombre     TEXT        NOT NULL,
  activo     BOOLEAN     NOT NULL DEFAULT TRUE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_marineros_nombre ON marineros (lower(btrim(nombre)));
-- Normaliza el dato histórico y siembra el catálogo desde los viajes existentes.
UPDATE viajes SET marinero = 'Ulices Quiroz' WHERE lower(btrim(marinero)) = 'ulices quiroz';
INSERT INTO marineros (nombre)
  SELECT DISTINCT btrim(marinero) FROM viajes
   WHERE marinero IS NOT NULL AND btrim(marinero) <> ''
ON CONFLICT DO NOTHING;

-- Los adjuntos pueden pertenecer a un periodo (estado de cuenta archivado al cerrar).
ALTER TABLE adjuntos ADD COLUMN IF NOT EXISTS periodo_id INT REFERENCES periodos(id) ON DELETE CASCADE;
CREATE INDEX IF NOT EXISTS idx_adjuntos_periodo ON adjuntos(periodo_id);

-- Los adjuntos también pueden pertenecer a un gasto extraordinario.
ALTER TABLE adjuntos ADD COLUMN IF NOT EXISTS extraordinario_id INT REFERENCES extraordinarios(id) ON DELETE CASCADE;
CREATE INDEX IF NOT EXISTS idx_adjuntos_extra ON adjuntos(extraordinario_id);

-- Los adjuntos también pueden pertenecer a un movimiento de caja chica.
ALTER TABLE adjuntos ADD COLUMN IF NOT EXISTS caja_id INT REFERENCES caja_chica(id) ON DELETE CASCADE;
CREATE INDEX IF NOT EXISTS idx_adjuntos_caja ON adjuntos(caja_id);

-- Manual de uso por rol (PDF en base64); cada usuario descarga el suyo desde su cuenta.
CREATE TABLE IF NOT EXISTS manuales (
  rol            TEXT PRIMARY KEY CHECK (rol IN ('admin','capitan','socio')),
  nombre         TEXT NOT NULL,
  datos          TEXT NOT NULL,               -- PDF en base64
  actualizado_en TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Reparto sin centavo fantasma: el socio con id mayor absorbe el resto del redondeo
-- para que la suma de las partes siempre sea igual al total.
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

-- Auditoría: quién hizo qué y cuándo.
CREATE TABLE IF NOT EXISTS auditoria (
  id             SERIAL PRIMARY KEY,
  usuario_id     INT,
  usuario_nombre TEXT,
  accion         TEXT NOT NULL,           -- crear | editar | borrar | cerrar | reabrir | subir | generar
  tabla          TEXT NOT NULL,
  registro_id    INT,
  detalle        TEXT,
  created_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_auditoria_fecha ON auditoria(created_at desc);

-- Rate limiting de login respaldado en BD (compartido entre instancias serverless).
CREATE TABLE IF NOT EXISTS login_intentos (
  clave      TEXT PRIMARY KEY,
  intentos   INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
