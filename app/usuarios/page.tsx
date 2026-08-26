import { listPeriodos, getCatalogos, getUsuarios } from "@/lib/queries";
import Topbar from "../Topbar";
import UsuariosClient from "./UsuariosClient";
import { requireUser } from "@/lib/auth";

export const dynamic = "force-dynamic";

export default async function Usuarios() {
  const user = await requireUser(["admin"]);
  const periodos = await listPeriodos();
  const { socios } = await getCatalogos();
  const usuarios = await getUsuarios();

  return (
    <>
      <Topbar periodos={periodos} periodoId={periodos[0]?.id ?? 0} active="usuarios" rol={user.rol} nombre={user.nombre} />
      <div className="wrap">
        <h1 style={{ margin: "4px 0 2px", fontSize: 22 }}>Usuarios</h1>
        <div className="hint" style={{ marginBottom: 18 }}>
          Da de alta al capitán y a los socios. Cada quien entra con su correo y contraseña.
        </div>
        <UsuariosClient usuarios={usuarios as any} socios={socios as any} meId={user.id} />
      </div>
    </>
  );
}
