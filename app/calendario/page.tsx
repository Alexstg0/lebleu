import type { Metadata } from "next";
import { listPeriodos, getReservasPorMes, getCatalogos } from "@/lib/queries";
import { mesNombre, fechaISO } from "@/lib/format";
import Topbar from "../Topbar";
import CalendarClient from "./CalendarClient";
import PrintButton from "../PrintButton";
import { requireUser } from "@/lib/auth";

export const dynamic = "force-dynamic";

export async function generateMetadata({
  searchParams,
}: {
  searchParams: Promise<{ y?: string; m?: string }>;
}): Promise<Metadata> {
  const sp = await searchParams;
  const anio = Number(sp.y) || new Date().getFullYear();
  const mes = Number(sp.m) || new Date().getMonth() + 1;
  return { title: `Le Bleu — Calendario ${mesNombre(mes)} ${anio}` };
}

export default async function Calendario({
  searchParams,
}: {
  searchParams: Promise<{ y?: string; m?: string }>;
}) {
  const user = await requireUser(["admin", "capitan", "socio"]);
  const sp = await searchParams;
  const periodos = await listPeriodos();
  const latest = periodos[0];

  const anio = Number(sp.y) || latest?.anio || new Date().getFullYear();
  const mes = Number(sp.m) || latest?.mes || new Date().getMonth() + 1;

  const { socios } = await getCatalogos();
  const reservasRaw = await getReservasPorMes(anio, mes);
  const reservas = reservasRaw.map((r) => ({
    id: r.id,
    fecha: fechaISO(r.fecha),
    hora: r.hora ? String(r.hora).slice(0, 5) : "",
    cliente: r.cliente ?? "",
    socio_id: r.socio_id,
    socio_nombre: r.socio_nombre ?? "",
    num_personas: r.num_personas,
    duracion_horas: r.duracion_horas != null ? Number(r.duracion_horas) : null,
    notas: r.notas ?? "",
  }));

  const prevMes = mes === 1 ? 12 : mes - 1;
  const prevAnio = mes === 1 ? anio - 1 : anio;
  const nextMes = mes === 12 ? 1 : mes + 1;
  const nextAnio = mes === 12 ? anio + 1 : anio;
  const hoy = new Date();

  return (
    <>
      <Topbar periodos={periodos} periodoId={latest?.id ?? 0} active="calendario" rol={user.rol} nombre={user.nombre} />
      <PrintButton />
      <div className="wrap cal-page">
        <div className="cal-print-head">
          <img src="/logo-lebleu.png" alt="Le Bleu" />
          <div className="cal-print-info">
            <h2>Calendario de reservaciones — {mesNombre(mes)} {anio}</h2>
            <div>Embarcación {latest?.embarcacion ?? "Le Bleu"}{latest?.razon_social ? ` · ${latest.razon_social}` : ""}</div>
          </div>
        </div>
        <div className="cal-toolbar">
          <div className="cal-title">
            <h1>{mesNombre(mes)}</h1>
            <span className="yr">{anio}</span>
          </div>
          <div className="cal-nav">
            <span className="cal-count">{reservas.length} reserva{reservas.length === 1 ? "" : "s"}</span>
            <a className="ico" href={`/calendario?y=${prevAnio}&m=${prevMes}`} aria-label="Mes anterior">
              <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M15 18l-6-6 6-6" /></svg>
            </a>
            <a href={`/calendario?y=${hoy.getFullYear()}&m=${hoy.getMonth() + 1}`}>Hoy</a>
            <a className="ico" href={`/calendario?y=${nextAnio}&m=${nextMes}`} aria-label="Mes siguiente">
              <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M9 18l6-6-6-6" /></svg>
            </a>
          </div>
        </div>
        <CalendarClient anio={anio} mes={mes} reservas={reservas} socios={socios as any} />
      </div>
    </>
  );
}
