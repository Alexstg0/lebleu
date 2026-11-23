-- Le Bleu (PHP + MySQL 8) · 002 · vistas del estado de cuenta
-- Misma lógica que la versión Postgres: el socio de mayor id recibe el RESTO, para que la
-- suma de las partes sea exactamente el total (ver docs/BUSINESS-RULES.md).

CREATE OR REPLACE SQL SECURITY INVOKER VIEW v_variable_por_socio AS
SELECT v.periodo_id,
       v.socio_id,
       COUNT(*)                 AS num_viajes,
       SUM(v.costo_combustible) AS combustible,
       SUM(v.costo_marinero)    AS marinero,
       SUM(v.costo_consumibles) AS consumibles,
       SUM(v.total)             AS total_variable
FROM viajes v
GROUP BY v.periodo_id, v.socio_id;

CREATE OR REPLACE SQL SECURITY INVOKER VIEW v_balance_operativo AS
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
  LEFT JOIN (SELECT periodo_id, SUM(monto_mxn) AS total_operativo
             FROM gastos_operativos GROUP BY periodo_id) op
         ON op.periodo_id = s.periodo_id
  LEFT JOIN (SELECT periodo_id, SUM(utilidad) AS total_utilidad
             FROM ingresos_renta GROUP BY periodo_id) renta
         ON renta.periodo_id = s.periodo_id
  LEFT JOIN v_variable_por_socio var
         ON var.periodo_id = s.periodo_id AND var.socio_id = s.socio_id
  LEFT JOIN (SELECT periodo_id, socio_id, SUM(monto) AS aportaciones
             FROM aportaciones GROUP BY periodo_id, socio_id) ap
         ON ap.periodo_id = s.periodo_id AND ap.socio_id = s.socio_id
) t;

CREATE OR REPLACE SQL SECURITY INVOKER VIEW v_extraordinarios_por_socio AS
SELECT tot.periodo_id,
       so.id AS socio_id,
       CASE WHEN so.id = (SELECT MAX(id) FROM socios)
            THEN tot.total - ROUND(tot.total * ((100 - so.porcentaje)/100), 2)
            ELSE ROUND(tot.total * (so.porcentaje/100), 2) END AS liquidacion_extraordinaria
FROM (SELECT periodo_id, SUM(monto_mxn) AS total FROM extraordinarios GROUP BY periodo_id) tot
CROSS JOIN socios so;
