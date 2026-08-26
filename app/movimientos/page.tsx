import { listPeriodos, getCatalogos, getMovimientos } from "@/lib/queries";
import { mesNombre } from "@/lib/format";
import Topbar from "../Topbar";
import MovList from "./MovList";
import PeriodoPicker from "../PeriodoPicker";
import { requireUser } from "@/lib/auth";

export const dynamic = "force-dynamic";

export default async function Movimientos({
  searchParams,
}: {
  searchParams: Promise<{ periodo?: string }>;
}) {
  const user = await requireUser(["admin"]);
  const sp = await searchParams;
  const periodos = await listPeriodos();
  if (periodos.length === 0) return <div className="empty">No hay periodos.</div>;
  const periodoId = Number(sp.periodo) || periodos[0].id;
  const periodo = periodos.find((p) => p.id === periodoId) ?? periodos[0];
  const { socios, clientes, motores, marineros } = await getCatalogos();
  const mov = await getMovimientos(periodoId);

  return (
    <>
      <Topbar periodos={periodos} periodoId={periodoId} active="movimientos" rol={user.rol} nombre={user.nombre} />
      <div className="wrap">
        <div className="rep-head">
          <div>
            <h1 style={{ margin: "4px 0 2px", fontSize: 22 }}>Movimientos</h1>
            <div className="hint">
              {mesNombre(periodo.mes)} {periodo.anio} · editar o borrar lo capturado
              {periodo.estado === "cerrado" && <span className="tag a" style={{ marginLeft: 8 }}>cerrado</span>}
            </div>
          </div>
          {periodos.length > 1 && <PeriodoPicker periodos={periodos as any} periodoId={periodoId} base="/movimientos" />}
        </div>
        <div style={{ height: 18 }} />
        <MovList
          periodoId={periodoId}
          mov={mov as any}
          socios={socios as any}
          clientes={clientes as any}
          motores={motores as any}
          marineros={marineros as any}
          precioLitro={Number(periodo.precio_litro)}
          tipoCambio={Number(periodo.tipo_cambio)}
        />
      </div>
    </>
  );
}
