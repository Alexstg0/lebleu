import { getDb } from "./db";
import { n } from "./format";

export type Periodo = {
  id: number;
  anio: number;
  mes: number;
  tipo_cambio: string;
  precio_litro: string;
  cargo_administracion: string;
  estado: string;
  embarcacion: string;
  razon_social: string | null;
};

export async function listPeriodos() {
  const db = await getDb();
  const r = await db.query<Periodo>(
    `select p.id, p.anio, p.mes, p.tipo_cambio, p.precio_litro,
            p.cargo_administracion, p.estado,
            e.nombre as embarcacion, e.razon_social
       from periodos p
       join embarcaciones e on e.id = p.embarcacion_id
      order by p.anio desc, p.mes desc`
  );
  return r.rows;
}

export async function getCatalogos() {
  const db = await getDb();
  const socios = (await db.query(`select id, nombre from socios where activo order by id`)).rows;
  const clientes = (await db.query(`select id, nombre, socio_id from clientes order by id`)).rows;
  const motores = (await db.query(`select id, etiqueta, horometro_actual from motores order by id`)).rows;
  const marineros = (await db.query(`select id, nombre from marineros where activo order by nombre`)).rows;
  return { socios, clientes, motores, marineros };
}

export async function getUsuarios() {
  const db = await getDb();
  const r = await db.query(
    `select u.id, u.nombre, u.email, u.rol, u.socio_id, u.activo, so.nombre as socio_nombre
       from usuarios u left join socios so on so.id = u.socio_id
      order by case u.rol when 'admin' then 0 when 'capitan' then 1 else 2 end, u.id`
  );
  return r.rows as any[];
}

export async function getViajesPorMes(anio: number, mes: number) {
  const db = await getDb();
  const r = await db.query(
    `select v.id, v.fecha, v.total, v.bandera, v.duracion_horas, v.num_personas,
            c.nombre as cliente, so.nombre as socio, so.id as socio_id
       from viajes v
       left join clientes c on c.id = v.cliente_id
       join socios so on so.id = v.socio_id
      where extract(year from v.fecha) = $1 and extract(month from v.fecha) = $2
      order by v.fecha, v.id`,
    [anio, mes]
  );
  return r.rows as any[];
}

export async function getCajaChica() {
  const db = await getDb();
  const r = await db.query(
    `select c.id, c.fecha, c.tipo, c.caja_numero, c.factura, c.proveedor, c.concepto, c.monto, c.abono, c.observaciones,
            coalesce(json_agg(json_build_object('id', a.id, 'nombre', a.nombre, 'generado', a.generado))
                     filter (where a.id is not null), '[]') as adjuntos
       from caja_chica c
       left join adjuntos a on a.caja_id = c.id
      group by c.id
      order by c.fecha, c.id`
  );
  // Balance corrido: + abono − monto.
  let bal = 0;
  const rows = (r.rows as any[]).map((m) => {
    bal += Number(m.abono) - Number(m.monto);
    return { ...m, balance: Math.round(bal * 100) / 100 };
  });
  return rows;
}

// Pagos a marineros de un periodo, agrupados por marinero con sus viajes.
export async function getMarineros(periodoId: number) {
  const db = await getDb();
  const rows = (await db.query(
    `select v.marinero, v.fecha, v.duracion_horas, v.num_personas, v.costo_marinero,
            coalesce(v.cliente_nombre, c.nombre, so.nombre, 'Renta') as cliente
       from viajes v
       left join clientes c on c.id = v.cliente_id
       left join socios so on so.id = v.socio_id
      where v.periodo_id = $1 and v.marinero is not null and btrim(v.marinero) <> ''
      order by lower(btrim(v.marinero)), v.fecha, v.id`,
    [periodoId]
  )).rows as any[];
  const map = new Map<string, any>();
  for (const r of rows) {
    const disp = String(r.marinero).trim();
    const key = disp.toLowerCase();
    if (!map.has(key)) map.set(key, { marinero: disp, viajes: [], total: 0, num: 0 });
    const g = map.get(key);
    g.viajes.push(r);
    g.total = Math.round((g.total + n(r.costo_marinero)) * 100) / 100;
    g.num++;
  }
  return [...map.values()];
}

// Lista ligera de viajes de un periodo, para el selector de insumos.
export async function getViajesDelPeriodo(periodoId: number) {
  const db = await getDb();
  const r = await db.query(
    `select v.id, v.fecha,
            coalesce(v.cliente_nombre, c.nombre, so.nombre, 'Renta') as cliente
       from viajes v
       left join clientes c on c.id = v.cliente_id
       left join socios so on so.id = v.socio_id
      where v.periodo_id = $1
      order by v.fecha, v.id`,
    [periodoId]
  );
  return r.rows as Array<{ id: number; fecha: string; cliente: string }>;
}

// Datos agregados para la pestaña de Análisis (gráficas).
export async function getAnalisis() {
  const db = await getDb();
  const porMes = (await db.query(
    `select p.id, p.anio, p.mes,
            coalesce((select sum(monto_mxn) from gastos_operativos g where g.periodo_id = p.id), 0) as operativos,
            coalesce((select sum(total) from viajes v where v.periodo_id = p.id), 0) as variables,
            coalesce((select sum(monto_mxn) from extraordinarios e where e.periodo_id = p.id), 0) as extraordinarios,
            coalesce((select count(*) from viajes v where v.periodo_id = p.id), 0)::int as num_viajes,
            coalesce((select sum(litros) from viajes v where v.periodo_id = p.id), 0) as litros
       from periodos p
      order by p.anio, p.mes`
  )).rows as any[];
  const viajes = (await db.query(
    `select to_char(v.fecha, 'YYYY-MM-DD') as fecha, v.litros,
            coalesce(v.cliente_nombre, c.nombre, so.nombre, 'Renta') as cliente
       from viajes v
       left join clientes c on c.id = v.cliente_id
       left join socios so on so.id = v.socio_id
      order by v.fecha, v.id`
  )).rows as any[];
  return { porMes, viajes };
}

export async function getMesesViajes() {
  const db = await getDb();
  const r = await db.query(
    `select distinct extract(year from fecha)::int as anio, extract(month from fecha)::int as mes
       from viajes order by anio desc, mes desc`
  );
  return r.rows as Array<{ anio: number; mes: number }>;
}

export async function getBitacora(anio: number, mes: number) {
  const db = await getDb();
  const viajes = (await db.query(
    `select v.id, v.fecha, v.num_personas, v.duracion_horas, v.litros,
            v.combustible_inicio, v.combustible_fin, v.marinero, v.bandera, v.es_renta,
            v.costo_consumibles, v.consumibles_comprobante,
            v.socio_id, v.cliente_id, v.precio_litro, v.costo_combustible, v.costo_marinero,
            coalesce(v.cliente_nombre, c.nombre, so.nombre, 'Renta') as cliente
       from viajes v
       left join clientes c on c.id = v.cliente_id
       left join socios so on so.id = v.socio_id
      where extract(year from v.fecha) = $1 and extract(month from v.fecha) = $2
      order by v.fecha, v.id`,
    [anio, mes]
  )).rows as any[];

  const horos = (await db.query(
    `select h.viaje_id, h.motor_id, h.lectura_inicio, h.lectura_fin, m.etiqueta
       from viaje_horometros h
       join motores m on m.id = h.motor_id
       join viajes v on v.id = h.viaje_id
      where extract(year from v.fecha) = $1 and extract(month from v.fecha) = $2
      order by h.motor_id`,
    [anio, mes]
  )).rows as any[];

  return viajes.map((v) => ({ ...v, horometros: horos.filter((h) => h.viaje_id === v.id) }));
}

export async function getReservasPorMes(anio: number, mes: number) {
  const db = await getDb();
  const r = await db.query(
    `select r.id, r.fecha, r.hora, r.cliente, r.socio_id, r.num_personas,
            r.duracion_horas, r.notas, so.nombre as socio_nombre
       from reservas r
       left join socios so on so.id = r.socio_id
      where extract(year from r.fecha) = $1 and extract(month from r.fecha) = $2
      order by r.fecha, r.hora`,
    [anio, mes]
  );
  return r.rows as any[];
}

export async function getMovimientos(periodoId: number) {
  const db = await getDb();

  const operativos = (await db.query(
    `select id, fecha, proveedor, concepto, categoria, moneda, monto_original,
            tipo_cambio, monto_mxn, comprobante_folio, clave_recurrente
       from gastos_operativos where periodo_id=$1 order by fecha, id`, [periodoId])).rows as any[];

  const adjuntos = (await db.query(
    `select a.id, a.gasto_id, a.nombre, a.generado
       from adjuntos a join gastos_operativos g on g.id = a.gasto_id
      where g.periodo_id=$1 order by a.id`, [periodoId])).rows as any[];

  const adjInsumos = (await db.query(
    `select a.id, a.insumo_id, a.nombre, a.generado
       from adjuntos a join insumos i on i.id = a.insumo_id
      where i.periodo_id=$1 order by a.id`, [periodoId])).rows as any[];

  const adjExtra = (await db.query(
    `select a.id, a.extraordinario_id, a.nombre, a.generado
       from adjuntos a join extraordinarios e on e.id = a.extraordinario_id
      where e.periodo_id=$1 order by a.id`, [periodoId])).rows as any[];

  const extraordinarios = (await db.query(
    `select id, fecha, proveedor, concepto, categoria, moneda, monto_original,
            tipo_cambio, monto_mxn, comprobante_folio
       from extraordinarios where periodo_id=$1 order by fecha, id`, [periodoId])).rows as any[];

  const viajes = (await db.query(
    `select v.*, coalesce(v.cliente_nombre, c.nombre, so.nombre, 'Renta') as cliente_disp,
            so.nombre as socio_nombre
       from viajes v left join clientes c on c.id=v.cliente_id
       left join socios so on so.id=v.socio_id
      where v.periodo_id=$1 order by v.fecha, v.id`, [periodoId])).rows as any[];

  const horos = (await db.query(
    `select h.viaje_id, h.motor_id, h.lectura_inicio, h.lectura_fin
       from viaje_horometros h join viajes v on v.id=h.viaje_id
      where v.periodo_id=$1`, [periodoId])).rows as any[];

  const aportaciones = (await db.query(
    `select a.id, a.fecha, a.socio_id, so.nombre as socio_nombre, a.monto, a.metodo, a.referencia
       from aportaciones a join socios so on so.id=a.socio_id
      where a.periodo_id=$1 order by a.fecha, a.id`, [periodoId])).rows as any[];

  const renta = (await db.query(
    `select id, fecha, cliente, monto, costos_asociados, utilidad
       from ingresos_renta where periodo_id=$1 order by fecha, id`, [periodoId])).rows as any[];

  const abonosExtra = (await db.query(
    `select a.id, a.fecha, a.socio_id, so.nombre as socio_nombre, a.monto, a.metodo, a.referencia
       from abonos_extraordinarios a join socios so on so.id=a.socio_id
      where a.periodo_id=$1 order by a.fecha, a.id`, [periodoId])).rows as any[];

  const insumos = (await db.query(
    `select i.id, i.fecha, i.viaje_id, i.categoria, i.proveedor, i.concepto, i.moneda,
            i.monto_original, i.tipo_cambio, i.monto_mxn, i.comprobante_folio,
            coalesce(v.cliente_nombre, c.nombre, so.nombre, 'Renta') as viaje_cliente, v.fecha as viaje_fecha
       from insumos i
       left join viajes v on v.id = i.viaje_id
       left join clientes c on c.id = v.cliente_id
       left join socios so on so.id = v.socio_id
      where i.periodo_id=$1 order by i.fecha, i.id`, [periodoId])).rows as any[];

  return {
    operativos: operativos.map((o) => ({ ...o, adjuntos: adjuntos.filter((a) => a.gasto_id === o.id) })),
    extraordinarios: extraordinarios.map((e) => ({ ...e, adjuntos: adjExtra.filter((a) => a.extraordinario_id === e.id) })),
    viajes: viajes.map((v) => ({ ...v, horometros: horos.filter((h) => h.viaje_id === v.id) })),
    aportaciones,
    renta,
    abonosExtra,
    insumos: insumos.map((i) => ({ ...i, adjuntos: adjInsumos.filter((a) => a.insumo_id === i.id) })),
  };
}

export async function getEstadoCuenta(periodoId: number) {
  const db = await getDb();

  const periodo = (
    await db.query<Periodo>(
      `select p.id, p.anio, p.mes, p.tipo_cambio, p.precio_litro,
              p.cargo_administracion, p.estado,
              e.nombre as embarcacion, e.razon_social
         from periodos p join embarcaciones e on e.id = p.embarcacion_id
        where p.id = $1`,
      [periodoId]
    )
  ).rows[0];
  if (!periodo) return null;

  const balances = (
    await db.query(
      `select b.socio_id, so.nombre, b.saldo_inicio, b.aportaciones,
              b.gastos_generales, b.gastos_variables, b.utilidad_renta,
              b.balance_operativo
         from v_balance_operativo b
         join socios so on so.id = b.socio_id
        where b.periodo_id = $1
        order by b.socio_id`,
      [periodoId]
    )
  ).rows as any[];

  const variable = (
    await db.query(
      `select v.socio_id, v.num_viajes, v.combustible, v.marinero,
              v.consumibles, v.total_variable
         from v_variable_por_socio v
        where v.periodo_id = $1`,
      [periodoId]
    )
  ).rows as any[];

  const extraSocio = (
    await db.query(
      `select e.socio_id, so.nombre, e.liquidacion_extraordinaria
         from v_extraordinarios_por_socio e
         join socios so on so.id = e.socio_id
        where e.periodo_id = $1
        order by e.socio_id`,
      [periodoId]
    )
  ).rows as any[];

  const operativos = (
    await db.query(
      `select id, fecha, proveedor, concepto, categoria, moneda,
              monto_original, tipo_cambio, monto_mxn, comprobante_folio
         from gastos_operativos where periodo_id = $1 order by fecha, id`,
      [periodoId]
    )
  ).rows as any[];

  const extraordinarios = (
    await db.query(
      `select id, fecha, proveedor, concepto, categoria, moneda,
              monto_original, tipo_cambio, monto_mxn, comprobante_folio, liquidado
         from extraordinarios where periodo_id = $1 order by fecha, id`,
      [periodoId]
    )
  ).rows as any[];

  const insumos = (
    await db.query(
      `select i.id, i.fecha, i.viaje_id, i.categoria, i.proveedor, i.concepto, i.moneda,
              i.monto_original, i.tipo_cambio, i.monto_mxn, i.comprobante_folio,
              coalesce(v.cliente_nombre, c.nombre, so.nombre, 'Renta') as viaje_cliente,
              v.fecha as viaje_fecha
         from insumos i
         left join viajes v on v.id = i.viaje_id
         left join clientes c on c.id = v.cliente_id
         left join socios so on so.id = v.socio_id
        where i.periodo_id = $1 order by i.fecha, i.id`,
      [periodoId]
    )
  ).rows as any[];

  // Un PDF por gasto/insumo (se prefiere el recibo generado) para abrirlo desde el dashboard.
  const adjO = (await db.query(
    `select distinct on (a.gasto_id) a.gasto_id, a.id
       from adjuntos a join gastos_operativos g on g.id = a.gasto_id
      where g.periodo_id = $1
      order by a.gasto_id, a.generado desc, a.id`, [periodoId])).rows as any[];
  const adjI = (await db.query(
    `select distinct on (a.insumo_id) a.insumo_id, a.id
       from adjuntos a join insumos i on i.id = a.insumo_id
      where i.periodo_id = $1
      order by a.insumo_id, a.generado desc, a.id`, [periodoId])).rows as any[];
  const adjE = (await db.query(
    `select distinct on (a.extraordinario_id) a.extraordinario_id, a.id
       from adjuntos a join extraordinarios e on e.id = a.extraordinario_id
      where e.periodo_id = $1
      order by a.extraordinario_id, a.generado desc, a.id`, [periodoId])).rows as any[];

  // Extraordinarios del mes inmediatamente anterior (misma embarcación): total y pendiente.
  const extraPrev = (await db.query(
    `select p.anio, p.mes,
            coalesce(sum(e.monto_mxn), 0) as total,
            coalesce(sum(e.monto_mxn) filter (where not e.liquidado), 0) as pendiente
       from periodos p
       left join extraordinarios e on e.periodo_id = p.id
      where p.embarcacion_id = (select embarcacion_id from periodos where id = $1)
        and (p.anio * 12 + p.mes) = ($2 * 12 + $3) - 1
      group by p.anio, p.mes`,
    [periodoId, periodo.anio, periodo.mes]
  )).rows[0] as { anio: number; mes: number; total: string; pendiente: string } | undefined;

  // Cuenta de extraordinarios por socio (cargo 50% acumulado − abonos acumulados).
  const embId = (await db.query(`select embarcacion_id from periodos where id = $1`, [periodoId])).rows[0]?.embarcacion_id;
  const currKey = periodo.anio * 12 + periodo.mes;
  const extraTotalPrevAcum = n((await db.query(
    `select coalesce(sum(e.monto_mxn), 0) t
       from extraordinarios e join periodos p on p.id = e.periodo_id
      where p.embarcacion_id = $1 and (p.anio * 12 + p.mes) < $2`, [embId, currKey])).rows[0].t);
  const sociosPct = (await db.query(`select id, nombre, porcentaje from socios where activo order by id`)).rows as any[];
  const abonosExtraRows = (await db.query(
    `select a.socio_id, a.monto, (p.anio * 12 + p.mes) as key, a.periodo_id
       from abonos_extraordinarios a join periodos p on p.id = a.periodo_id
      where p.embarcacion_id = $1`, [embId])).rows as any[];

  const viajes = (
    await db.query(
      `select v.*, c.nombre as cliente, so.nombre as socio
         from viajes v
         left join clientes c on c.id = v.cliente_id
         join socios so on so.id = v.socio_id
        where v.periodo_id = $1
        order by v.fecha, v.id`,
      [periodoId]
    )
  ).rows as any[];

  const horos = (
    await db.query(
      `select h.viaje_id, h.lectura_inicio, h.lectura_fin, h.horas, m.etiqueta
         from viaje_horometros h
         join motores m on m.id = h.motor_id
         join viajes v on v.id = h.viaje_id
        where v.periodo_id = $1
        order by h.motor_id`,
      [periodoId]
    )
  ).rows as Array<{ viaje_id: number; etiqueta: string; lectura_inicio: string; lectura_fin: string; horas: string }>;

  const renta = (
    await db.query(
      `select coalesce(sum(monto),0) as ingresos, coalesce(sum(utilidad),0) as utilidad
         from ingresos_renta where periodo_id = $1`,
      [periodoId]
    )
  ).rows[0] as any;

  // KPIs derivados de los viajes
  const opTotal = operativos.reduce((s, r: any) => s + n(r.monto_mxn), 0);
  const extraTotal = extraordinarios.reduce((s, r: any) => s + n(r.monto_mxn), 0);
  const varTotal = viajes.reduce((s, r: any) => s + n(r.total), 0);
  const combTotal = viajes.reduce((s, r: any) => s + n(r.costo_combustible), 0);
  const marTotal = viajes.reduce((s, r: any) => s + n(r.costo_marinero), 0);
  const consTotal = viajes.reduce((s, r: any) => s + n(r.costo_consumibles), 0);
  const litrosTotal = viajes.reduce((s, r: any) => s + n(r.litros), 0);
  const insumosTotal = insumos.reduce((s, r: any) => s + n(r.monto_mxn), 0);

  const r2 = (x: number) => Math.round(x * 100) / 100;
  // El socio con id mayor absorbe el resto del redondeo, para que la suma de las
  // partes siempre sea igual al total (mismo criterio que las vistas SQL).
  const maxSocioId = Math.max(...sociosPct.map((s: any) => Number(s.id)));
  const parte = (total: number, pct: number, esUltimo: boolean) =>
    esUltimo ? r2(total - r2(total * (1 - pct))) : r2(total * pct);
  const extraCuenta = sociosPct.map((s: any) => {
    const pct = n(s.porcentaje) / 100;
    const esUltimo = Number(s.id) === maxSocioId;
    const cargoMes = parte(extraTotal, pct, esUltimo);
    const cargoPrev = parte(extraTotalPrevAcum, pct, esUltimo);
    const abonosMes = r2(abonosExtraRows.filter((a) => a.socio_id === s.id && a.periodo_id === periodoId).reduce((x, a) => x + n(a.monto), 0));
    const abonosPrev = r2(abonosExtraRows.filter((a) => a.socio_id === s.id && a.key < currKey).reduce((x, a) => x + n(a.monto), 0));
    const saldoAnterior = r2(cargoPrev - abonosPrev);
    const saldoPendiente = r2(saldoAnterior + cargoMes - abonosMes);
    return { socio_id: s.id, nombre: s.nombre, saldoAnterior, cargoMes, abonosMes, saldoPendiente };
  });

  return {
    periodo,
    balances,
    variable,
    extraSocio,
    operativos: operativos.map((o: any) => ({ ...o, adjunto_id: adjO.find((a) => a.gasto_id === o.id)?.id ?? null })),
    insumos: insumos.map((i: any) => ({ ...i, adjunto_id: adjI.find((a) => a.insumo_id === i.id)?.id ?? null })),
    extraordinarios: extraordinarios.map((e: any) => ({ ...e, adjunto_id: adjE.find((a) => a.extraordinario_id === e.id)?.id ?? null })),
    extraPrev: extraPrev ? { anio: extraPrev.anio, mes: extraPrev.mes, total: n(extraPrev.total), pendiente: n(extraPrev.pendiente) } : null,
    extraCuenta,
    viajes: viajes.map((v: any) => ({
      ...v,
      horometros: horos.filter((h) => h.viaje_id === v.id),
    })),
    renta,
    kpis: {
      opTotal,
      opMitad: Math.round((opTotal / 2) * 100) / 100,
      extraTotal,
      extraMitad: Math.round((extraTotal / 2) * 100) / 100,
      varTotal,
      combTotal,
      marTotal,
      consTotal,
      litrosTotal,
      numViajes: viajes.length,
      ingresosRenta: n(renta?.ingresos),
      insumosTotal,
    },
  };
}
