import type { Metadata } from "next";
import { listPeriodos, getCajaChica } from "@/lib/queries";
import { mesNombre } from "@/lib/format";
import Topbar from "../Topbar";
import PrintButton from "../PrintButton";
import CajaChicaClient from "./CajaChicaClient";
import { requireUser } from "@/lib/auth";

export const dynamic = "force-dynamic";
export const metadata: Metadata = { title: "Le Bleu — Caja Chica" };

export default async function CajaChicaPage() {
  const user = await requireUser(["admin", "capitan"]);
  const periodos = await listPeriodos();
  const movimientos = await getCajaChica();

  const hoy = new Date();
  const hoyTexto = `${hoy.getDate()} de ${mesNombre(hoy.getMonth() + 1).toLowerCase()} del ${hoy.getFullYear()}`;

  return (
    <>
      <Topbar periodos={periodos} periodoId={periodos[0]?.id ?? 0} active="caja" rol={user.rol} nombre={user.nombre} />
      <PrintButton />
      <div className="wrap cal-page">
        <h1 style={{ margin: "4px 0 14px", fontSize: 22 }} className="cc-screen-title">Caja chica</h1>
        <CajaChicaClient
          movimientos={movimientos as any}
          hoyTexto={hoyTexto}
          embarcacion={periodos[0]?.embarcacion}
          razonSocial={periodos[0]?.razon_social}
        />
      </div>
    </>
  );
}
