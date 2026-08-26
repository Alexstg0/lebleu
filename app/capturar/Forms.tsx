"use client";

import { useState } from "react";
import GastoOperativo from "./forms/GastoOperativo";
import Viaje from "./forms/Viaje";
import CaptainViaje from "./forms/CaptainViaje";
import Aportacion from "./forms/Aportacion";
import Extraordinario from "./forms/Extraordinario";
import Renta from "./forms/Renta";
import Insumos from "./forms/Insumos";
import AbonoExtraordinario from "./forms/AbonoExtraordinario";

type Socio = { id: number; nombre: string };
type Cliente = { id: number; nombre: string; socio_id: number | null };
type Motor = { id: number; etiqueta: string; horometro_actual: string };
type ViajeOpt = { id: number; fecha: string; cliente: string };
type MarineroCat = { id: number; nombre: string };

const TABS = [
  { k: "viaje", label: "Viaje" },
  { k: "gasto", label: "Gasto operativo" },
  { k: "insumos", label: "Insumos" },
  { k: "aportacion", label: "Aportación" },
  { k: "extraordinario", label: "Extraordinario" },
  { k: "abono_extra", label: "Abono extraord." },
  { k: "renta", label: "Renta" },
] as const;

type TabKey = (typeof TABS)[number]["k"];

export default function Forms(props: {
  periodoId: number;
  socios: Socio[];
  clientes: Cliente[];
  motores: Motor[];
  viajes: ViajeOpt[];
  marineros?: MarineroCat[];
  precioLitro: number;
  tipoCambio: number;
  rol?: "admin" | "capitan" | "socio";
}) {
  const [tab, setTab] = useState<TabKey>("viaje");

  // El capitán solo registra viajes, con el formulario simplificado.
  if (props.rol === "capitan") {
    return (
      <CaptainViaje periodoId={props.periodoId} clientes={props.clientes} motores={props.motores} precioLitro={props.precioLitro}
        marineros={props.marineros} viajesExistentes={props.viajes} />
    );
  }

  return (
    <>
      <div className="tabs">
        {TABS.map((t) => (
          <button key={t.k} className={`tab ${tab === t.k ? "active" : ""}`} onClick={() => setTab(t.k)} type="button">
            {t.label}
          </button>
        ))}
      </div>

      {tab === "viaje" && (
        <Viaje periodoId={props.periodoId} socios={props.socios} clientes={props.clientes} motores={props.motores} precioLitro={props.precioLitro}
          marineros={props.marineros} viajesExistentes={props.viajes} />
      )}
      {tab === "gasto" && <GastoOperativo periodoId={props.periodoId} tipoCambio={props.tipoCambio} />}
      {tab === "insumos" && <Insumos periodoId={props.periodoId} tipoCambio={props.tipoCambio} viajes={props.viajes} />}
      {tab === "aportacion" && <Aportacion periodoId={props.periodoId} socios={props.socios} />}
      {tab === "extraordinario" && <Extraordinario periodoId={props.periodoId} tipoCambio={props.tipoCambio} />}
      {tab === "abono_extra" && <AbonoExtraordinario periodoId={props.periodoId} socios={props.socios} />}
      {tab === "renta" && <Renta periodoId={props.periodoId} />}
    </>
  );
}
