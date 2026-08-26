-- ============================================================
--  Le Bleu — Seed de datos: Mayo 2026
--  Reproduce el Estado de Cuenta de Mayo 2026 al centavo.
--  Requiere: schema.sql ya ejecutado sobre una BD LIMPIA
--  (los IDs asumen secuencias SERIAL empezando en 1).
-- ============================================================
--  Validación esperada (vista v_balance_operativo):
--     Acosta  -> balance_operativo = 43,470.99
--     García  -> balance_operativo = 16,136.07
--     Extraordinarios por socio    = 217,386.24
-- ============================================================

BEGIN;

-- ---------- Catálogos ----------
INSERT INTO embarcaciones (id, nombre, razon_social) VALUES
    (1, 'Le Bleu', 'Arrendadora Acma S de RL de CV');

INSERT INTO socios (id, nombre, porcentaje) VALUES
    (1, 'Ramón Humberto Acosta Durán', 50.00),
    (2, 'León García Verduzco',        50.00);

INSERT INTO motores (id, embarcacion_id, etiqueta, horometro_actual) VALUES
    (1, 1, 'Motor B / Babor',    871.14),   -- última lectura del mes
    (2, 1, 'Motor E / Estribor', 836.59);

INSERT INTO clientes (id, nombre, socio_id) VALUES
    (1, 'Familia Acosta',           1),
    (2, 'Familia García Verduzco',  2);

-- ---------- Periodo ----------
INSERT INTO periodos (id, embarcacion_id, anio, mes, tipo_cambio, precio_litro, cargo_administracion, estado) VALUES
    (1, 1, 2026, 5, 17.5000, 25.0800, 6000.00, 'cerrado');

INSERT INTO saldos_socio (periodo_id, socio_id, saldo_inicio, saldo_fin) VALUES
    (1, 1, 27853.62, 43470.99),    -- Acosta
    (1, 2,  7351.10, 16136.07);    -- García

INSERT INTO aportaciones (periodo_id, socio_id, fecha, monto) VALUES
    (1, 1, '2026-05-01', 50000.00),   -- Acosta (fecha aproximada — ajusta si tienes la real)
    (1, 2, '2026-05-01', 50000.00);   -- García

-- ---------- Gastos operativos (50/50) — subtotal 57,891.87 ----------
-- Renglón 1: USD demostrando columna calculada (32.85 × 17.5 = 574.88).
INSERT INTO gastos_operativos
    (periodo_id, fecha, proveedor, concepto, categoria, moneda, monto_original, tipo_cambio, comprobante_folio) VALUES
    (1, '2026-05-01', 'Ebay',                    'Válvula para compresor',           'refaccion', 'USD',   32.85, 17.5, '13-14575-05716'),
    (1, '2026-05-01', 'Ebay',                    'Válvula para compresor',           'refaccion', 'MXN',  805.53,  1,   'NA'),
    (1, '2026-05-08', 'Gabriel Preciado',        'Nómina Sem. #19',                  'nomina',    'MXN', 8560.00,  1,   'Recibo de Nómina'),
    (1, '2026-05-11', 'Mecánico',                'Manivela Winche para el ancla',    'refaccion', 'MXN',  900.00,  1,   'NA'),
    (1, '2026-05-11', 'IGY Marina',              'Luz y Agua',                       'servicio',  'MXN',  792.00,  1,   'NA'),
    (1, '2026-05-14', 'Inter Clean Lavanderías', 'Limpieza de toallas',              'servicio',  'MXN',  320.00,  1,   '4199'),
    (1, '2026-05-15', 'Gabriel Preciado',        'Nómina Sem. #20',                  'nomina',    'MXN', 8560.00,  1,   'Recibo de Nómina'),
    (1, '2026-05-18', 'Sec. de Finanzas',        'Impuesto sobre la nómina',         'impuesto',  'MXN',  331.00,  1,   'ISN'),
    (1, '2026-05-18', 'IMSS',                    'Cuotas obrero-patronales',         'impuesto',  'MXN', 4399.47,  1,   'SIPARE'),
    (1, '2026-05-18', 'SAT',                     'Impuestos',                        'impuesto',  'MXN', 6980.00,  1,   'SAT'),
    (1, '2026-05-22', 'Telcel',                  'Internet',                         'servicio',  'MXN', 2548.99,  1,   'FA-000...'),
    (1, '2026-05-22', 'Gabriel Preciado',        'Nómina Sem. #21',                  'nomina',    'MXN', 8560.00,  1,   'Recibo de Nómina'),
    (1, '2026-05-29', 'Gabriel Preciado',        'Nómina Sem. #22',                  'nomina',    'MXN', 8560.00,  1,   'Recibo de Nómina'),
    (1, '2026-05-31', 'Cargo por Administración','Mayo 2026',                        'admin',     'MXN', 6000.00,  1,   'No Aplica');

-- ---------- Gastos extraordinarios (50/50, liquidación separada) — total 434,772.48 ----------
-- Refacciones Mercury: se guarda el MXN autoritativo (la columna MXN del estado cuadra
-- exacto; el USD×17.5 tiene redondeos menores). El USD se conserva como referencia en el concepto.
INSERT INTO extraordinarios
    (periodo_id, fecha, proveedor, concepto, categoria, moneda, monto_original, tipo_cambio, comprobante_folio) VALUES
    (1, '2026-05-11', 'IGY Marina',                  'Pago Anual del Muelle 2026 (cubre ejercicio 2026)',                       'muelle',    'MXN', 211010.94, 1, 'NA'),
    (1, '2026-05-10', 'eBay · naplesmarineparts',    'Mercury 8M0174307 Catalyst-Exhaust ×2 · ref. USD 4,743.40',               'refaccion', 'MXN',  82909.50, 1, '18-14606-98216'),
    (1, '2026-05-10', 'PPT Performance Technologies', 'Mercury 8M0174307 Catalyst-Exhaust ×2 · ref. USD 5,259.69',              'refaccion', 'MXN',  92044.58, 1, '#129736'),
    (1, '2026-05-13', 'MercruiserParts.com',         'Sensores y refacciones Mercury (Crank, Assy, Shim, O-Ring) · ref. USD 645.82', 'refaccion', 'MXN', 11301.85, 1, '#71769'),
    (1, '2026-05-20', 'First Watch Marine',          'Refacciones Mercury: Sensor-O2, Gasket, Spark Plugs ×16, Cable ×2 · ref. USD 2,143.21', 'refaccion', 'MXN', 37505.61, 1, '#3361');

-- ---------- Viajes (gastos variables, 100% al socio) ----------
-- ids 1..7 en orden cronológico.
INSERT INTO viajes
    (id, periodo_id, embarcacion_id, socio_id, cliente_id, fecha, duracion_horas, num_personas,
     litros, precio_litro, costo_combustible, costo_marinero, costo_consumibles, consumibles_comprobante, es_renta, bandera) VALUES
    (1, 1, 1, 1, 1, '2026-05-10', 4, NULL, 11.73,  25.08,  294.19, 800.00,   0.00, NULL,             FALSE, FALSE),
    (2, 1, 1, 1, 1, '2026-05-12', 3, NULL, 47.32,  25.08, 1187.74, 800.00, 526.00, 'Chedraui SM-91363', FALSE, FALSE),
    (3, 1, 1, 2, 2, '2026-05-15', 4, 10,   46.94,  25.08, 1177.26, 800.00, 798.50, NULL,             FALSE, FALSE),
    (4, 1, 1, 2, 2, '2026-05-17', 6, 6,    68.52,  25.08, 1718.49, 800.00, 576.74, NULL,             FALSE, FALSE),
    (5, 1, 1, 2, 2, '2026-05-21', 5, NULL, 108.64, 25.08, 2724.69, 800.00, 389.50, NULL,             FALSE, TRUE),   -- ⚠ consumo alto
    (6, 1, 1, 2, 2, '2026-05-24', 4, 8,    42.78,  25.08, 1072.91, 800.00, 611.00, NULL,             FALSE, FALSE),
    (7, 1, 1, 1, 1, '2026-05-30', 4, NULL, 18.93,  25.08,  474.76, 800.00, 554.00, NULL,             FALSE, FALSE);

-- ---------- Horómetros por viaje (Motor B=1, Motor E=2) ----------
INSERT INTO viaje_horometros (viaje_id, motor_id, lectura_inicio, lectura_fin) VALUES
    (1, 1, 858.17, 859.18), (1, 2, 823.24, 824.20),
    (2, 1, 859.18, 861.10), (2, 2, 824.20, 826.12),
    (3, 1, 861.10, 863.16), (3, 2, 826.12, 828.17),
    (4, 1, 863.16, 865.30), (4, 2, 828.17, 830.32),
    (5, 1, 865.30, 868.25), (5, 2, 830.32, 833.27),
    (6, 1, 868.25, 870.14), (6, 2, 833.27, 835.56),
    (7, 1, 870.14, 871.14), (7, 2, 835.56, 836.59);

-- ---------- Ingresos por renta ----------
-- Mayo 2026: SIN renta (en Abril fueron $18,167). No se inserta ningún renglón.

-- Sincroniza las secuencias con los IDs insertados manualmente.
SELECT setval('embarcaciones_id_seq', 1, true);
SELECT setval('socios_id_seq',        2, true);
SELECT setval('motores_id_seq',       2, true);
SELECT setval('clientes_id_seq',      2, true);
SELECT setval('periodos_id_seq',      1, true);
SELECT setval('viajes_id_seq',        7, true);

COMMIT;

-- ============================================================
--  VALIDACIÓN — corre esto después del seed:
-- ============================================================
-- SELECT so.nombre, b.saldo_inicio, b.aportaciones, b.gastos_generales,
--        b.gastos_variables, b.balance_operativo
--   FROM v_balance_operativo b JOIN socios so ON so.id = b.socio_id
--  WHERE b.periodo_id = 1;
--   -> Acosta 43,470.99 · García 16,136.07
--
-- SELECT so.nombre, e.liquidacion_extraordinaria
--   FROM v_extraordinarios_por_socio e JOIN socios so ON so.id = e.socio_id
--  WHERE e.periodo_id = 1;
--   -> 217,386.24 cada uno
