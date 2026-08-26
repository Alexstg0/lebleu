// Lógica compartida de periodos: crear (con arrastre de saldos), cerrar/reabrir con archivo.
import { generarEstadoCuentaPDF } from "./reporte-estado";

export type NuevoPeriodo = {
  embarcacion_id?: number;
  anio: number;
  mes: number;
  tipo_cambio?: number;
  precio_litro?: number;
  cargo_administracion?: number;
};

// Crea un periodo, arrastra el saldo de cada socio y cierra el periodo anterior.
export async function crearPeriodo(db: any, b: NuevoPeriodo): Promise<number> {
  const emb = Number(b.embarcacion_id) || 1;
  const anio = Number(b.anio);
  const mes = Number(b.mes);

  const prev = (
    await db.query(
      `select id from periodos
        where embarcacion_id = $1 and (anio * 12 + mes) < ($2 * 12 + $3)
        order by anio desc, mes desc limit 1`,
      [emb, anio, mes]
    )
  ).rows[0] as { id: number } | undefined;

  const r = await db.query(
    `insert into periodos
      (embarcacion_id, anio, mes, tipo_cambio, precio_litro, cargo_administracion, estado)
     values ($1,$2,$3,$4,$5,$6,'abierto')
     returning id`,
    [emb, anio, mes, b.tipo_cambio || 1, b.precio_litro || 0, b.cargo_administracion || 0]
  );
  const periodoId = r.rows[0].id as number;

  const socios = (await db.query(`select id from socios where activo order by id`)).rows as Array<{ id: number }>;
  for (const s of socios) {
    let saldoInicio = 0;
    if (prev) {
      const pf = (
        await db.query(`select saldo_fin from saldos_socio where periodo_id = $1 and socio_id = $2`, [prev.id, s.id])
      ).rows[0] as { saldo_fin: string | null } | undefined;
      let fin = pf?.saldo_fin ?? null;
      if (fin === null) {
        const bal = (
          await db.query(
            `select balance_operativo from v_balance_operativo where periodo_id = $1 and socio_id = $2`,
            [prev.id, s.id]
          )
        ).rows[0] as { balance_operativo: string } | undefined;
        fin = bal?.balance_operativo ?? "0";
        await db.query(`update saldos_socio set saldo_fin = $1 where periodo_id = $2 and socio_id = $3`, [fin, prev.id, s.id]);
      }
      saldoInicio = Number(fin);
    }
    await db.query(`insert into saldos_socio (periodo_id, socio_id, saldo_inicio) values ($1,$2,$3)`, [periodoId, s.id, saldoInicio]);
  }

  if (prev) await cerrarPeriodo(db, prev.id);
  return periodoId;
}

// Crea el periodo del mes en curso si aún no existe (para el cron diario).
// Copia T/C, precio de litro y cargo administrativo del periodo más reciente.
export async function ensurePeriodoActual(db: any, hoyISO: string): Promise<number | null> {
  const [anio, mes] = hoyISO.split("-").map(Number);
  const emb = 1;
  const existe = (
    await db.query(`select id from periodos where embarcacion_id=$1 and anio=$2 and mes=$3`, [emb, anio, mes])
  ).rows[0];
  if (existe) return null;
  const ult = (
    await db.query(
      `select tipo_cambio, precio_litro, cargo_administracion from periodos
        where embarcacion_id=$1 order by anio desc, mes desc limit 1`, [emb]
    )
  ).rows[0] as any;
  return await crearPeriodo(db, {
    embarcacion_id: emb, anio, mes,
    tipo_cambio: Number(ult?.tipo_cambio || 1),
    precio_litro: Number(ult?.precio_litro || 0),
    cargo_administracion: Number(ult?.cargo_administracion || 0),
  });
}

// Cierra un periodo y archiva el estado de cuenta en PDF (reemplaza el archivo anterior).
export async function cerrarPeriodo(db: any, periodoId: number): Promise<boolean> {
  await db.query(`update periodos set estado='cerrado' where id=$1`, [periodoId]);
  try {
    const bytes = await generarEstadoCuentaPDF(db, periodoId);
    if (bytes) {
      const p = (await db.query(`select anio, mes from periodos where id=$1`, [periodoId])).rows[0];
      const nombre = `Estado de cuenta ${p.anio}-${String(p.mes).padStart(2, "0")}.pdf`;
      await db.query(`delete from adjuntos where periodo_id=$1 and generado=true`, [periodoId]);
      await db.query(
        `insert into adjuntos (periodo_id, nombre, mime, datos, generado) values ($1,$2,'application/pdf',$3,true)`,
        [periodoId, nombre, Buffer.from(bytes).toString("base64")]
      );
    }
    return true;
  } catch (e: any) {
    console.error("cerrarPeriodo snapshot error:", e?.message || e);
    return false;
  }
}

export async function reabrirPeriodo(db: any, periodoId: number) {
  await db.query(`update periodos set estado='abierto' where id=$1`, [periodoId]);
}
