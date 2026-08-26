import { listPeriodos, getCatalogos, getViajesDelPeriodo } from "@/lib/queries";
import { mesNombre } from "@/lib/format";
import Topbar from "../Topbar";
import Forms from "./Forms";
import PeriodoPicker from "../PeriodoPicker";
import { requireUser } from "@/lib/auth";

export const dynamic = "force-dynamic";

export default async function Capturar({
  searchParams,
}: {
  searchParams: Promise<{ periodo?: string }>;
}) {
  const user = await requireUser(["admin", "capitan"]);
  const sp = await searchParams;
  const periodos = await listPeriodos();
  const periodoId = Number(sp.periodo) || periodos[0]?.id;
  const periodo = periodos.find((p) => p.id === periodoId) ?? periodos[0];
  const { socios, clientes, motores, marineros } = await getCatalogos();
  const viajes = await getViajesDelPeriodo(periodoId);

  return (
    <>
      <Topbar periodos={periodos} periodoId={periodoId} active="capturar" rol={user.rol} nombre={user.nombre} />
      <div className="wrap">
        <div className="rep-head">
          <div>
            <h1 style={{ margin: "4px 0 2px", fontSize: 22 }}>
              {user.rol === "capitan" ? "Registrar viaje" : "Capturar movimientos"}
            </h1>
            <div className="hint">
              Registrando en el periodo: <strong>{mesNombre(periodo.mes)} {periodo.anio}</strong>
              {periodo.estado === "cerrado" && <span className="tag a" style={{ marginLeft: 8 }}>cerrado</span>}
            </div>
          </div>
          {user.rol === "admin" && periodos.length > 1 && <PeriodoPicker periodos={periodos as any} periodoId={periodoId} base="/capturar" />}
        </div>
        <div style={{ height: 18 }} />
        <Forms
          periodoId={periodoId}
          socios={socios as any}
          clientes={clientes as any}
          motores={motores as any}
          viajes={viajes as any}
          marineros={marineros as any}
          precioLitro={Number(periodo.precio_litro)}
          tipoCambio={Number(periodo.tipo_cambio)}
          rol={user.rol}
        />
      </div>
    </>
  );
}
