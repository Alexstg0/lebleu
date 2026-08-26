import { listPeriodos, getMesesViajes, getBitacora, getCatalogos } from "@/lib/queries";
import { mesNombre, fechaCorta, num, mxn, n } from "@/lib/format";
import Topbar from "../Topbar";
import MonthFilter from "./MonthFilter";
import DeleteViajeButton from "./DeleteViajeButton";
import EditarViajeButton from "./EditarViajeButton";
import { requireUser } from "@/lib/auth";

export const dynamic = "force-dynamic";

export default async function Bitacora({
  searchParams,
}: {
  searchParams: Promise<{ y?: string; m?: string }>;
}) {
  const user = await requireUser(["admin", "capitan"]);
  const sp = await searchParams;
  const periodos = await listPeriodos();
  const meses = await getMesesViajes();

  const hoy = new Date();
  const def = meses[0] ?? { anio: hoy.getFullYear(), mes: hoy.getMonth() + 1 };
  const anio = Number(sp.y) || def.anio;
  const mes = Number(sp.m) || def.mes;

  const viajes = await getBitacora(anio, mes);
  const horo = (v: any, i: number) => v.horometros[i];
  const isAdmin = user.rol === "admin";
  const cat = isAdmin ? await getCatalogos() : { socios: [], clientes: [], motores: [], marineros: [] };
  const precioLitro = Number(periodos[0]?.precio_litro ?? 0);

  return (
    <>
      <Topbar periodos={periodos} periodoId={periodos[0]?.id ?? 0} active="bitacora" rol={user.rol} nombre={user.nombre} />
      <div className="wrap">
        <div className="cal-toolbar">
          <div className="cal-title">
            <h1>Bitácora</h1>
            <span className="yr">de viajes</span>
          </div>
          {meses.length > 0 && <MonthFilter meses={meses} anio={anio} mes={mes} />}
        </div>

        {viajes.length === 0 ? (
          <div className="empty">Sin viajes registrados en {mesNombre(mes)} {anio}.</div>
        ) : (
          <div className="table-card" style={{ overflowX: "auto" }}>
            <table className="bita-table resp-table">
              <thead>
                <tr>
                  {isAdmin && <th className="tc"></th>}
                  <th>Fecha</th><th>Cliente</th><th className="tr">Insumos</th><th className="tc">Pers.</th><th>Marinero</th>
                  <th>Motor B</th><th>Motor E</th>
                  <th className="tr">Comb. inicio</th><th className="tr">Comb. fin</th><th className="tr">Consumo</th>
                  <th className="tc">Duración</th>
                </tr>
              </thead>
              <tbody>
                {viajes.map((v: any) => (
                  <tr key={v.id}>
                    {isAdmin && (
                      <td className="tc bita-acc" data-label="Acciones" style={{ whiteSpace: "nowrap" }}>
                        <EditarViajeButton viaje={v} periodoId={periodos[0]?.id ?? 0} socios={cat.socios as any} clientes={cat.clientes as any} motores={cat.motores as any} marineros={cat.marineros as any} precioLitro={precioLitro} />
                        <DeleteViajeButton id={v.id} etiqueta={`${v.cliente} (${fechaCorta(v.fecha)})`} />
                      </td>
                    )}
                    <td data-label="Fecha" style={{ whiteSpace: "nowrap" }}>{fechaCorta(v.fecha)}</td>
                    <td data-label="Cliente">{v.cliente}{v.es_renta ? " · Renta" : ""}</td>
                    <td data-label="Insumos" className="tr mono" style={{ whiteSpace: "nowrap" }}>{n(v.costo_consumibles) ? mxn(v.costo_consumibles) : "—"}</td>
                    <td data-label="Personas" className="tc">{v.num_personas ?? "—"}</td>
                    <td data-label="Marinero">{v.marinero ?? "—"}</td>
                    <td data-label="Motor B" className="mono" style={{ whiteSpace: "nowrap" }}>{horo(v, 0) ? `${num(horo(v, 0).lectura_inicio)} → ${num(horo(v, 0).lectura_fin)}` : "—"}</td>
                    <td data-label="Motor E" className="mono" style={{ whiteSpace: "nowrap" }}>{horo(v, 1) ? `${num(horo(v, 1).lectura_inicio)} → ${num(horo(v, 1).lectura_fin)}` : "—"}</td>
                    <td data-label="Comb. inicio" className="tr mono">{v.combustible_inicio != null ? `${num(v.combustible_inicio)} L` : "—"}</td>
                    <td data-label="Comb. fin" className="tr mono">{v.combustible_fin != null ? `${num(v.combustible_fin)} L` : "—"}</td>
                    <td data-label="Consumo" className="tr mono">{num(v.litros)} L</td>
                    <td data-label="Duración" className="tc">{v.duracion_horas != null ? `${num(v.duracion_horas, 0)} h` : "—"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        <div className="hint" style={{ marginTop: 12 }}>
          {viajes.length} viaje{viajes.length === 1 ? "" : "s"} en {mesNombre(mes)} {anio}
          {isAdmin && viajes.length > 0 && " · Como administrador puedes Editar cualquier viaje para completar datos que falten (marinero, personas, horómetros, etc.)."}
        </div>
      </div>
    </>
  );
}
