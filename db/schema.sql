-- ============================================================
--  Le Bleu — Control de Embarcación
--  Esquema de base de datos (PostgreSQL 14+)
--  Basado en el Estado de Cuenta de Mayo 2026
-- ============================================================
--  Dominios:
--    Catálogos      -> embarcaciones, socios, motores, clientes
--    Periodo        -> periodos, saldos_socio, aportaciones
--    Movimientos    -> gastos_operativos, extraordinarios,
--                      viajes, viaje_horometros, ingresos_renta
--  Bonus opcional   -> mantenimientos (control por horómetro)
-- ============================================================

BEGIN;

-- ------------------------------------------------------------
-- 1. CATÁLOGOS
-- ------------------------------------------------------------

CREATE TABLE embarcaciones (
    id            SERIAL PRIMARY KEY,
    nombre        TEXT        NOT NULL,
    razon_social  TEXT,                       -- ej. "Arrendadora Acma S de RL de CV"
    matricula     TEXT,
    activo        BOOLEAN     NOT NULL DEFAULT TRUE,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE socios (
    id            SERIAL PRIMARY KEY,
    nombre        TEXT          NOT NULL,
    rfc           TEXT,
    porcentaje    NUMERIC(5,2)  NOT NULL DEFAULT 50.00,   -- participación
    activo        BOOLEAN       NOT NULL DEFAULT TRUE,
    created_at    TIMESTAMPTZ   NOT NULL DEFAULT now()
);

CREATE TABLE motores (
    id               SERIAL PRIMARY KEY,
    embarcacion_id   INT     NOT NULL REFERENCES embarcaciones(id) ON DELETE CASCADE,
    etiqueta         TEXT    NOT NULL,                    -- "Motor B / Babor", "Motor E / Estribor"
    horometro_actual NUMERIC(10,2) NOT NULL DEFAULT 0,
    created_at       TIMESTAMPTZ   NOT NULL DEFAULT now()
);

CREATE TABLE clientes (
    id          SERIAL PRIMARY KEY,
    nombre      TEXT    NOT NULL,                         -- "Familia Acosta", "Familia García", o renta externa
    socio_id    INT     REFERENCES socios(id) ON DELETE SET NULL,  -- NULL = tercero (renta)
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ------------------------------------------------------------
-- 2. PERIODO (ancla del estado de cuenta mensual)
-- ------------------------------------------------------------

CREATE TABLE periodos (
    id                   SERIAL PRIMARY KEY,
    embarcacion_id       INT          NOT NULL REFERENCES embarcaciones(id) ON DELETE CASCADE,
    anio                 INT          NOT NULL,
    mes                  INT          NOT NULL CHECK (mes BETWEEN 1 AND 12),
    tipo_cambio          NUMERIC(8,4) NOT NULL DEFAULT 1,    -- T/C de referencia del mes (ej. 17.50)
    precio_litro         NUMERIC(8,4) NOT NULL DEFAULT 0,    -- precio combustible MXN/Lt (ej. 25.08)
    cargo_administracion NUMERIC(14,2) NOT NULL DEFAULT 0,
    estado               TEXT         NOT NULL DEFAULT 'abierto'
                                      CHECK (estado IN ('abierto','cerrado')),
    created_at           TIMESTAMPTZ  NOT NULL DEFAULT now(),
    UNIQUE (embarcacion_id, anio, mes)
);

CREATE TABLE saldos_socio (
    id           SERIAL PRIMARY KEY,
    periodo_id   INT           NOT NULL REFERENCES periodos(id) ON DELETE CASCADE,
    socio_id     INT           NOT NULL REFERENCES socios(id),
    saldo_inicio NUMERIC(14,2) NOT NULL DEFAULT 0,         -- = saldo_fin del mes anterior (snapshot)
    saldo_fin    NUMERIC(14,2),                            -- se congela al cerrar el periodo
    UNIQUE (periodo_id, socio_id)
);

CREATE TABLE aportaciones (
    id          SERIAL PRIMARY KEY,
    periodo_id  INT           NOT NULL REFERENCES periodos(id) ON DELETE CASCADE,
    socio_id    INT           NOT NULL REFERENCES socios(id),
    fecha       DATE          NOT NULL,
    monto       NUMERIC(14,2) NOT NULL,
    metodo      TEXT,                                       -- transferencia, efectivo, etc.
    referencia  TEXT,
    created_at  TIMESTAMPTZ   NOT NULL DEFAULT now()
);

-- ------------------------------------------------------------
-- 3. MOVIMIENTOS
-- ------------------------------------------------------------

-- Gastos operativos: se reparten 50/50 entre socios.
CREATE TABLE gastos_operativos (
    id                SERIAL PRIMARY KEY,
    periodo_id        INT           NOT NULL REFERENCES periodos(id) ON DELETE CASCADE,
    fecha             DATE          NOT NULL,
    proveedor         TEXT          NOT NULL,
    concepto          TEXT          NOT NULL,
    categoria         TEXT          NOT NULL DEFAULT 'otro'
                                    CHECK (categoria IN
                                      ('nomina','impuesto','servicio','refaccion','consumible','admin','otro')),
    moneda            TEXT          NOT NULL DEFAULT 'MXN' CHECK (moneda IN ('MXN','USD')),
    monto_original    NUMERIC(14,2) NOT NULL,
    tipo_cambio       NUMERIC(8,4)  NOT NULL DEFAULT 1,     -- 1 cuando moneda = MXN
    monto_mxn         NUMERIC(14,2) GENERATED ALWAYS AS (monto_original * tipo_cambio) STORED,
    comprobante_folio TEXT,                                 -- factura/UUID, "NA", "SIPARE", etc.
    archivo_url       TEXT,
    created_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);

-- Gastos extraordinarios: 50/50 pero LIQUIDACIÓN SEPARADA (fuera del balance operativo).
CREATE TABLE extraordinarios (
    id                SERIAL PRIMARY KEY,
    periodo_id        INT           NOT NULL REFERENCES periodos(id) ON DELETE CASCADE,
    fecha             DATE          NOT NULL,
    proveedor         TEXT          NOT NULL,
    concepto          TEXT          NOT NULL,
    categoria         TEXT          NOT NULL DEFAULT 'otro'
                                    CHECK (categoria IN ('muelle','refaccion','varada','otro')),
    moneda            TEXT          NOT NULL DEFAULT 'MXN' CHECK (moneda IN ('MXN','USD')),
    monto_original    NUMERIC(14,2) NOT NULL,
    tipo_cambio       NUMERIC(8,4)  NOT NULL DEFAULT 1,
    monto_mxn         NUMERIC(14,2) GENERATED ALWAYS AS (monto_original * tipo_cambio) STORED,
    comprobante_folio TEXT,
    archivo_url       TEXT,
    liquidado         BOOLEAN       NOT NULL DEFAULT FALSE,
    fecha_liquidacion DATE,
    created_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);

-- Viajes: los costos del viaje SON los gastos variables (100% al socio responsable).
CREATE TABLE viajes (
    id                      SERIAL PRIMARY KEY,
    periodo_id              INT           NOT NULL REFERENCES periodos(id) ON DELETE CASCADE,
    embarcacion_id          INT           NOT NULL REFERENCES embarcaciones(id),
    socio_id                INT           REFERENCES socios(id),   -- quién absorbe el costo (NULL en rentas)
    cliente_id              INT           REFERENCES clientes(id),
    cliente_nombre          TEXT,                        -- nombre libre (rentas a externos)
    marinero                TEXT,                        -- nombre del marinero
    fecha                   DATE          NOT NULL,
    duracion_horas          NUMERIC(5,2),
    num_personas            INT,
    combustible_inicio      NUMERIC(10,2),               -- lectura del tanque al inicio (litros)
    combustible_fin         NUMERIC(10,2),               -- lectura del tanque al final (litros)
    litros                  NUMERIC(10,2) NOT NULL DEFAULT 0,   -- consumo = inicio - fin
    precio_litro            NUMERIC(8,4)  NOT NULL DEFAULT 0,
    costo_combustible       NUMERIC(14,2) NOT NULL DEFAULT 0,
    costo_marinero          NUMERIC(14,2) NOT NULL DEFAULT 0,
    costo_consumibles       NUMERIC(14,2) NOT NULL DEFAULT 0,
    consumibles_comprobante TEXT,                            -- ej. "Chedraui SM-91363"
    total                   NUMERIC(14,2) GENERATED ALWAYS AS
                              (costo_combustible + costo_marinero + costo_consumibles) STORED,
    es_renta                BOOLEAN       NOT NULL DEFAULT FALSE,
    bandera                 BOOLEAN       NOT NULL DEFAULT FALSE,   -- marca viajes anómalos (⚠)
    notas                   TEXT,
    created_at              TIMESTAMPTZ   NOT NULL DEFAULT now()
);

-- Lecturas de horómetro por motor en cada viaje.
CREATE TABLE viaje_horometros (
    id              SERIAL PRIMARY KEY,
    viaje_id        INT           NOT NULL REFERENCES viajes(id) ON DELETE CASCADE,
    motor_id        INT           NOT NULL REFERENCES motores(id),
    lectura_inicio  NUMERIC(10,2) NOT NULL,
    lectura_fin     NUMERIC(10,2) NOT NULL,
    horas           NUMERIC(10,2) GENERATED ALWAYS AS (lectura_fin - lectura_inicio) STORED,
    UNIQUE (viaje_id, motor_id),
    CHECK (lectura_fin >= lectura_inicio)
);

-- Ingresos por renta a terceros.
CREATE TABLE ingresos_renta (
    id               SERIAL PRIMARY KEY,
    periodo_id       INT           NOT NULL REFERENCES periodos(id) ON DELETE CASCADE,
    cliente          TEXT          NOT NULL,
    fecha            DATE          NOT NULL,
    monto            NUMERIC(14,2) NOT NULL,
    costos_asociados NUMERIC(14,2) NOT NULL DEFAULT 0,
    utilidad         NUMERIC(14,2) GENERATED ALWAYS AS (monto - costos_asociados) STORED,
    viaje_id         INT           REFERENCES viajes(id) ON DELETE SET NULL,
    created_at       TIMESTAMPTZ   NOT NULL DEFAULT now()
);

-- ------------------------------------------------------------
-- 4. BONUS (recomendado): mantenimiento por horas de motor
-- ------------------------------------------------------------
CREATE TABLE mantenimientos (
    id                  SERIAL PRIMARY KEY,
    motor_id            INT           NOT NULL REFERENCES motores(id) ON DELETE CASCADE,
    tipo                TEXT          NOT NULL,             -- "Cambio de aceite", "Servicio Mercury", etc.
    horometro_objetivo  NUMERIC(10,2),                      -- a qué hora toca
    horometro_realizado NUMERIC(10,2),                      -- NULL = pendiente
    fecha_realizado     DATE,
    costo               NUMERIC(14,2),
    notas               TEXT,
    created_at          TIMESTAMPTZ   NOT NULL DEFAULT now()
);

-- ------------------------------------------------------------
-- 5. ÍNDICES
-- ------------------------------------------------------------
CREATE INDEX idx_motores_embarcacion       ON motores(embarcacion_id);
CREATE INDEX idx_periodos_embarcacion      ON periodos(embarcacion_id);
CREATE INDEX idx_saldos_periodo            ON saldos_socio(periodo_id);
CREATE INDEX idx_aportaciones_periodo      ON aportaciones(periodo_id);
CREATE INDEX idx_operativos_periodo        ON gastos_operativos(periodo_id);
CREATE INDEX idx_extraordinarios_periodo   ON extraordinarios(periodo_id);
CREATE INDEX idx_viajes_periodo            ON viajes(periodo_id);
CREATE INDEX idx_viajes_socio              ON viajes(socio_id);
CREATE INDEX idx_horometros_viaje          ON viaje_horometros(viaje_id);
CREATE INDEX idx_ingresos_periodo          ON ingresos_renta(periodo_id);
CREATE INDEX idx_mantenimientos_motor      ON mantenimientos(motor_id);

-- ------------------------------------------------------------
-- 6. VISTAS — reproducen el estado de cuenta
-- ------------------------------------------------------------

-- Gasto variable acumulado por socio en el periodo (Resumen de Gastos Variables).
CREATE VIEW v_variable_por_socio AS
SELECT v.periodo_id,
       v.socio_id,
       COUNT(*)                       AS num_viajes,
       SUM(v.costo_combustible)       AS combustible,
       SUM(v.costo_marinero)          AS marinero,
       SUM(v.costo_consumibles)       AS consumibles,
       SUM(v.total)                   AS total_variable
FROM viajes v
GROUP BY v.periodo_id, v.socio_id;

-- Balance operativo por socio (valida $43,470.99 Acosta / $16,136.07 García en mayo 2026).
-- El reparto usa "resto al último socio": el socio con id mayor absorbe el centavo
-- de diferencia cuando el total no es divisible exacto, para que la suma de las
-- partes SIEMPRE sea igual al total (sin el centavo fantasma del redondeo).
CREATE VIEW v_balance_operativo AS
WITH op AS (   -- total operativo del periodo (se reparte 50/50)
    SELECT periodo_id, SUM(monto_mxn) AS total_operativo
    FROM gastos_operativos GROUP BY periodo_id
), renta AS (  -- utilidad de renta del periodo (se reparte 50/50)
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

-- Liquidación de extraordinarios por socio (pago separado, mismo criterio de resto).
CREATE VIEW v_extraordinarios_por_socio AS
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

-- Insumos: gastos de consumibles asignados a un viaje (se suman a viajes.costo_consumibles).
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

-- Cargos recurrentes: clave única para no duplicar nómina/administrativo.
ALTER TABLE gastos_operativos ADD COLUMN IF NOT EXISTS clave_recurrente TEXT;
CREATE UNIQUE INDEX IF NOT EXISTS idx_operativos_clave_recurrente
  ON gastos_operativos(clave_recurrente) WHERE clave_recurrente IS NOT NULL;

-- Adjuntos (PDF de respaldo) por gasto operativo.
CREATE TABLE IF NOT EXISTS adjuntos (
  id         SERIAL PRIMARY KEY,
  gasto_id   INT           REFERENCES gastos_operativos(id) ON DELETE CASCADE,
  insumo_id  INT           REFERENCES insumos(id) ON DELETE CASCADE,
  nombre     TEXT          NOT NULL,
  mime       TEXT          NOT NULL DEFAULT 'application/pdf',
  datos      TEXT          NOT NULL,
  generado   BOOLEAN       NOT NULL DEFAULT FALSE,
  created_at TIMESTAMPTZ   NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_adjuntos_gasto ON adjuntos(gasto_id);
CREATE INDEX IF NOT EXISTS idx_adjuntos_insumo ON adjuntos(insumo_id);

-- Abonos de cada socio a la cuenta de extraordinarios.
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

-- Catálogo de marineros.
CREATE TABLE IF NOT EXISTS marineros (
  id         SERIAL PRIMARY KEY,
  nombre     TEXT        NOT NULL,
  activo     BOOLEAN     NOT NULL DEFAULT TRUE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_marineros_nombre ON marineros (lower(btrim(nombre)));

-- Los adjuntos pueden pertenecer a un periodo (estado de cuenta archivado).
ALTER TABLE adjuntos ADD COLUMN IF NOT EXISTS periodo_id INT REFERENCES periodos(id) ON DELETE CASCADE;
CREATE INDEX IF NOT EXISTS idx_adjuntos_periodo ON adjuntos(periodo_id);
ALTER TABLE adjuntos ADD COLUMN IF NOT EXISTS extraordinario_id INT REFERENCES extraordinarios(id) ON DELETE CASCADE;
CREATE INDEX IF NOT EXISTS idx_adjuntos_extra ON adjuntos(extraordinario_id);
ALTER TABLE adjuntos ADD COLUMN IF NOT EXISTS caja_id INT REFERENCES caja_chica(id) ON DELETE CASCADE;
CREATE INDEX IF NOT EXISTS idx_adjuntos_caja ON adjuntos(caja_id);

-- Auditoría: quién hizo qué y cuándo.
CREATE TABLE IF NOT EXISTS auditoria (
  id             SERIAL PRIMARY KEY,
  usuario_id     INT,
  usuario_nombre TEXT,
  accion         TEXT NOT NULL,
  tabla          TEXT NOT NULL,
  registro_id    INT,
  detalle        TEXT,
  created_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_auditoria_fecha ON auditoria(created_at desc);

COMMIT;
