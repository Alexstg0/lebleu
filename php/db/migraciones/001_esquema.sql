-- Le Bleu (PHP + MySQL 8) · 001 · esquema base consolidado
-- Equivale al estado final de db/schema.sql + auth.sql + reservas.sql + migraciones.sql de la
-- versión Next.js. Portable: corre igual en MySQL 8 (>= 8.0.16, por los CHECK) y MariaDB.
-- Todas las fechas con hora se guardan en UTC (la conexión fija time_zone = '+00:00').

CREATE TABLE IF NOT EXISTS migracion (
  nombre     VARCHAR(120) NOT NULL PRIMARY KEY,
  aplicada   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 1. Catálogos
CREATE TABLE IF NOT EXISTS embarcaciones (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  nombre        VARCHAR(160) NOT NULL,
  razon_social  VARCHAR(200),
  matricula     VARCHAR(80),
  activo        TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS socios (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  nombre        VARCHAR(160)  NOT NULL,
  rfc           VARCHAR(20),
  porcentaje    DECIMAL(5,2)  NOT NULL DEFAULT 50.00,
  activo        TINYINT(1)    NOT NULL DEFAULT 1,
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS motores (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  embarcacion_id   INT           NOT NULL,
  etiqueta         VARCHAR(80)   NOT NULL,
  horometro_actual DECIMAL(10,2) NOT NULL DEFAULT 0,
  created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_motores_embarcacion (embarcacion_id),
  CONSTRAINT fk_motores_embarcacion FOREIGN KEY (embarcacion_id) REFERENCES embarcaciones(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS clientes (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nombre      VARCHAR(160) NOT NULL,
  socio_id    INT,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_clientes_socio FOREIGN KEY (socio_id) REFERENCES socios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS marineros (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  nombre     VARCHAR(160) NOT NULL,
  activo     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_marineros_nombre (nombre)
) ENGINE=InnoDB;

-- 2. Periodo (ancla del estado de cuenta mensual)
CREATE TABLE IF NOT EXISTS periodos (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  embarcacion_id       INT           NOT NULL,
  anio                 INT           NOT NULL,
  mes                  INT           NOT NULL,
  tipo_cambio          DECIMAL(8,4)  NOT NULL DEFAULT 1,
  precio_litro         DECIMAL(8,4)  NOT NULL DEFAULT 0,
  cargo_administracion DECIMAL(14,2) NOT NULL DEFAULT 0,
  estado               VARCHAR(10)   NOT NULL DEFAULT 'abierto',
  created_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_periodo (embarcacion_id, anio, mes),
  CONSTRAINT ck_periodos_mes    CHECK (mes BETWEEN 1 AND 12),
  CONSTRAINT ck_periodos_estado CHECK (estado IN ('abierto','cerrado')),
  CONSTRAINT fk_periodos_embarcacion FOREIGN KEY (embarcacion_id) REFERENCES embarcaciones(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS saldos_socio (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  periodo_id   INT           NOT NULL,
  socio_id     INT           NOT NULL,
  saldo_inicio DECIMAL(14,2) NOT NULL DEFAULT 0,
  saldo_fin    DECIMAL(14,2),
  UNIQUE KEY uk_saldo (periodo_id, socio_id),
  CONSTRAINT fk_saldos_periodo FOREIGN KEY (periodo_id) REFERENCES periodos(id) ON DELETE CASCADE,
  CONSTRAINT fk_saldos_socio   FOREIGN KEY (socio_id)   REFERENCES socios(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS aportaciones (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  periodo_id  INT           NOT NULL,
  socio_id    INT           NOT NULL,
  fecha       DATE          NOT NULL,
  monto       DECIMAL(14,2) NOT NULL,
  metodo      VARCHAR(80),
  referencia  VARCHAR(160),
  created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_aportaciones_periodo (periodo_id),
  CONSTRAINT fk_aportaciones_periodo FOREIGN KEY (periodo_id) REFERENCES periodos(id) ON DELETE CASCADE,
  CONSTRAINT fk_aportaciones_socio   FOREIGN KEY (socio_id)   REFERENCES socios(id)
) ENGINE=InnoDB;

-- 3. Movimientos
CREATE TABLE IF NOT EXISTS gastos_operativos (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  periodo_id        INT           NOT NULL,
  fecha             DATE          NOT NULL,
  proveedor         VARCHAR(200)  NOT NULL,
  concepto          VARCHAR(400)  NOT NULL,
  categoria         VARCHAR(20)   NOT NULL DEFAULT 'otro',
  moneda            CHAR(3)       NOT NULL DEFAULT 'MXN',
  monto_original    DECIMAL(14,2) NOT NULL,
  tipo_cambio       DECIMAL(8,4)  NOT NULL DEFAULT 1,
  monto_mxn         DECIMAL(14,2) GENERATED ALWAYS AS (monto_original * tipo_cambio) STORED,
  comprobante_folio VARCHAR(120),
  archivo_url       VARCHAR(500),
  clave_recurrente  VARCHAR(80),
  created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_operativos_periodo (periodo_id),
  KEY idx_operativos_clave (clave_recurrente),
  CONSTRAINT ck_operativos_categoria CHECK (categoria IN ('nomina','impuesto','servicio','refaccion','consumible','admin','otro')),
  CONSTRAINT ck_operativos_moneda    CHECK (moneda IN ('MXN','USD')),
  CONSTRAINT fk_operativos_periodo FOREIGN KEY (periodo_id) REFERENCES periodos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS extraordinarios (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  periodo_id        INT           NOT NULL,
  fecha             DATE          NOT NULL,
  proveedor         VARCHAR(200)  NOT NULL,
  concepto          VARCHAR(400)  NOT NULL,
  categoria         VARCHAR(20)   NOT NULL DEFAULT 'otro',
  moneda            CHAR(3)       NOT NULL DEFAULT 'MXN',
  monto_original    DECIMAL(14,2) NOT NULL,
  tipo_cambio       DECIMAL(8,4)  NOT NULL DEFAULT 1,
  monto_mxn         DECIMAL(14,2) GENERATED ALWAYS AS (monto_original * tipo_cambio) STORED,
  comprobante_folio VARCHAR(120),
  archivo_url       VARCHAR(500),
  liquidado         TINYINT(1)    NOT NULL DEFAULT 0,
  fecha_liquidacion DATE,
  created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_extraordinarios_periodo (periodo_id),
  CONSTRAINT ck_extra_categoria CHECK (categoria IN ('muelle','refaccion','varada','otro')),
  CONSTRAINT ck_extra_moneda    CHECK (moneda IN ('MXN','USD')),
  CONSTRAINT fk_extra_periodo FOREIGN KEY (periodo_id) REFERENCES periodos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS viajes (
  id                      INT AUTO_INCREMENT PRIMARY KEY,
  periodo_id              INT           NOT NULL,
  embarcacion_id          INT           NOT NULL,
  socio_id                INT,
  cliente_id              INT,
  cliente_nombre          VARCHAR(160),
  marinero                VARCHAR(160),
  fecha                   DATE          NOT NULL,
  duracion_horas          DECIMAL(5,2),
  num_personas            INT,
  combustible_inicio      DECIMAL(10,2),
  combustible_fin         DECIMAL(10,2),
  litros                  DECIMAL(10,2) NOT NULL DEFAULT 0,
  precio_litro            DECIMAL(8,4)  NOT NULL DEFAULT 0,
  costo_combustible       DECIMAL(14,2) NOT NULL DEFAULT 0,
  costo_marinero          DECIMAL(14,2) NOT NULL DEFAULT 0,
  costo_consumibles       DECIMAL(14,2) NOT NULL DEFAULT 0,
  consumibles_comprobante VARCHAR(120),
  total                   DECIMAL(14,2) GENERATED ALWAYS AS (costo_combustible + costo_marinero + costo_consumibles) STORED,
  es_renta                TINYINT(1)    NOT NULL DEFAULT 0,
  bandera                 TINYINT(1)    NOT NULL DEFAULT 0,
  notas                   TEXT,
  created_at              DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_viajes_periodo (periodo_id),
  KEY idx_viajes_socio (socio_id),
  CONSTRAINT fk_viajes_periodo     FOREIGN KEY (periodo_id)     REFERENCES periodos(id) ON DELETE CASCADE,
  CONSTRAINT fk_viajes_embarcacion FOREIGN KEY (embarcacion_id) REFERENCES embarcaciones(id),
  CONSTRAINT fk_viajes_socio       FOREIGN KEY (socio_id)       REFERENCES socios(id),
  CONSTRAINT fk_viajes_cliente     FOREIGN KEY (cliente_id)     REFERENCES clientes(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS viaje_horometros (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  viaje_id        INT           NOT NULL,
  motor_id        INT           NOT NULL,
  lectura_inicio  DECIMAL(10,2) NOT NULL,
  lectura_fin     DECIMAL(10,2) NOT NULL,
  horas           DECIMAL(10,2) GENERATED ALWAYS AS (lectura_fin - lectura_inicio) STORED,
  UNIQUE KEY uk_viaje_motor (viaje_id, motor_id),
  CONSTRAINT ck_horometros_lectura CHECK (lectura_fin >= lectura_inicio),
  CONSTRAINT fk_horometros_viaje FOREIGN KEY (viaje_id) REFERENCES viajes(id) ON DELETE CASCADE,
  CONSTRAINT fk_horometros_motor FOREIGN KEY (motor_id) REFERENCES motores(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ingresos_renta (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  periodo_id       INT           NOT NULL,
  cliente          VARCHAR(160)  NOT NULL,
  fecha            DATE          NOT NULL,
  monto            DECIMAL(14,2) NOT NULL,
  costos_asociados DECIMAL(14,2) NOT NULL DEFAULT 0,
  utilidad         DECIMAL(14,2) GENERATED ALWAYS AS (monto - costos_asociados) STORED,
  viaje_id         INT,
  created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ingresos_periodo (periodo_id),
  CONSTRAINT fk_ingresos_periodo FOREIGN KEY (periodo_id) REFERENCES periodos(id) ON DELETE CASCADE,
  CONSTRAINT fk_ingresos_viaje   FOREIGN KEY (viaje_id)   REFERENCES viajes(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS mantenimientos (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  motor_id            INT           NOT NULL,
  tipo                VARCHAR(120)  NOT NULL,
  horometro_objetivo  DECIMAL(10,2),
  horometro_realizado DECIMAL(10,2),
  fecha_realizado     DATE,
  costo               DECIMAL(14,2),
  notas               TEXT,
  created_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_mantenimientos_motor (motor_id),
  CONSTRAINT fk_mantenimientos_motor FOREIGN KEY (motor_id) REFERENCES motores(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS caja_chica (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  fecha         DATE          NOT NULL,
  tipo          VARCHAR(10)   NOT NULL DEFAULT 'gasto',
  caja_numero   INT,
  factura       VARCHAR(120),
  proveedor     VARCHAR(200),
  concepto      VARCHAR(400),
  monto         DECIMAL(14,2) NOT NULL DEFAULT 0,
  abono         DECIMAL(14,2) NOT NULL DEFAULT 0,
  observaciones TEXT,
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_caja_chica_fecha (fecha),
  CONSTRAINT ck_caja_tipo CHECK (tipo IN ('gasto','abono'))
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS insumos (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  periodo_id        INT,
  viaje_id          INT,
  fecha             DATE          NOT NULL,
  categoria         VARCHAR(40),
  proveedor         VARCHAR(200),
  concepto          VARCHAR(400),
  moneda            CHAR(3)       NOT NULL DEFAULT 'MXN',
  monto_original    DECIMAL(14,2) NOT NULL DEFAULT 0,
  tipo_cambio       DECIMAL(8,4)  NOT NULL DEFAULT 1,
  monto_mxn         DECIMAL(14,2) GENERATED ALWAYS AS (monto_original * tipo_cambio) STORED,
  comprobante_folio VARCHAR(120),
  created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_insumos_periodo (periodo_id),
  KEY idx_insumos_viaje (viaje_id),
  CONSTRAINT ck_insumos_moneda CHECK (moneda IN ('MXN','USD')),
  CONSTRAINT fk_insumos_periodo FOREIGN KEY (periodo_id) REFERENCES periodos(id) ON DELETE CASCADE,
  CONSTRAINT fk_insumos_viaje   FOREIGN KEY (viaje_id)   REFERENCES viajes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS abonos_extraordinarios (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  periodo_id INT           NOT NULL,
  socio_id   INT           NOT NULL,
  fecha      DATE          NOT NULL,
  monto      DECIMAL(14,2) NOT NULL DEFAULT 0,
  metodo     VARCHAR(80),
  referencia VARCHAR(160),
  created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_abonos_extra_periodo (periodo_id),
  KEY idx_abonos_extra_socio (socio_id),
  CONSTRAINT fk_abonos_periodo FOREIGN KEY (periodo_id) REFERENCES periodos(id) ON DELETE CASCADE,
  CONSTRAINT fk_abonos_socio   FOREIGN KEY (socio_id)   REFERENCES socios(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS adjuntos (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  gasto_id          INT,
  insumo_id         INT,
  periodo_id        INT,
  extraordinario_id INT,
  caja_id           INT,
  nombre            VARCHAR(255) NOT NULL,
  mime              VARCHAR(100) NOT NULL DEFAULT 'application/pdf',
  datos             LONGTEXT     NOT NULL,
  generado          TINYINT(1)   NOT NULL DEFAULT 0,
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_adjuntos_gasto (gasto_id),
  KEY idx_adjuntos_insumo (insumo_id),
  KEY idx_adjuntos_periodo (periodo_id),
  KEY idx_adjuntos_extra (extraordinario_id),
  KEY idx_adjuntos_caja (caja_id),
  CONSTRAINT fk_adjuntos_gasto   FOREIGN KEY (gasto_id)          REFERENCES gastos_operativos(id) ON DELETE CASCADE,
  CONSTRAINT fk_adjuntos_insumo  FOREIGN KEY (insumo_id)         REFERENCES insumos(id) ON DELETE CASCADE,
  CONSTRAINT fk_adjuntos_periodo FOREIGN KEY (periodo_id)        REFERENCES periodos(id) ON DELETE CASCADE,
  CONSTRAINT fk_adjuntos_extra   FOREIGN KEY (extraordinario_id) REFERENCES extraordinarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_adjuntos_caja    FOREIGN KEY (caja_id)           REFERENCES caja_chica(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reservas (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  fecha          DATE         NOT NULL,
  hora           TIME,
  cliente        VARCHAR(160),
  socio_id       INT,
  num_personas   INT,
  duracion_horas DECIMAL(5,2),
  notas          TEXT,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_reservas_fecha (fecha),
  CONSTRAINT fk_reservas_socio FOREIGN KEY (socio_id) REFERENCES socios(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS auditoria (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id     INT,
  usuario_nombre VARCHAR(160),
  accion         VARCHAR(40)  NOT NULL,
  tabla          VARCHAR(60)  NOT NULL,
  registro_id    INT,
  detalle        TEXT,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_auditoria_fecha (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS manuales (
  rol            VARCHAR(10)  NOT NULL PRIMARY KEY,
  nombre         VARCHAR(200) NOT NULL,
  datos          LONGTEXT     NOT NULL,
  actualizado_en DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT ck_manuales_rol CHECK (rol IN ('admin','capitan','socio'))
) ENGINE=InnoDB;

-- 4. Usuarios y sesiones
CREATE TABLE IF NOT EXISTS usuarios (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  nombre        VARCHAR(160) NOT NULL,
  email         VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  rol           VARCHAR(10)  NOT NULL,
  socio_id      INT,
  activo        TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_usuarios_email (email),
  CONSTRAINT ck_usuarios_rol CHECK (rol IN ('admin','capitan','socio')),
  CONSTRAINT fk_usuarios_socio FOREIGN KEY (socio_id) REFERENCES socios(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sesiones (
  token       CHAR(64)  NOT NULL PRIMARY KEY,
  usuario_id  INT       NOT NULL,
  expires_at  DATETIME  NOT NULL,
  created_at  DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sesiones_expira (expires_at),
  CONSTRAINT fk_sesiones_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_intentos (
  clave      VARCHAR(255) NOT NULL PRIMARY KEY,
  intentos   INT          NOT NULL DEFAULT 0,
  updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
