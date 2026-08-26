"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { useSubmit, deleteRow } from "../capturar/forms/useSubmit";

type Socio = { id: number; nombre: string };
type Usuario = {
  id: number; nombre: string; email: string; rol: string;
  socio_id: number | null; activo: boolean; socio_nombre: string | null;
};

const ROL_LABEL: Record<string, string> = { admin: "Administrador", capitan: "Capitán", socio: "Socio" };

export default function UsuariosClient({
  usuarios,
  socios,
  meId,
}: {
  usuarios: Usuario[];
  socios: Socio[];
  meId: number;
}) {
  const router = useRouter();
  const { busy, msg, submit } = useSubmit("/api/usuarios");
  const [f, setF] = useState({
    nombre: "",
    email: "",
    rol: "capitan",
    socio_id: String(socios[0]?.id ?? ""),
    password: "",
  });

  function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    submit(
      { ...f, socio_id: f.rol === "socio" ? Number(f.socio_id) : null },
      () => setF({ ...f, nombre: "", email: "", password: "" })
    );
  }

  async function borrar(id: number, nombre: string) {
    if (!confirm(`¿Borrar la cuenta de ${nombre}?`)) return;
    const r = await deleteRow("/api/usuarios", id);
    if (r.ok) router.refresh();
    else alert(r.error || "No se pudo borrar.");
  }

  async function resetPass(id: number, nombre: string) {
    const pw = prompt(`Nueva contraseña para ${nombre} (mínimo 6 caracteres):`);
    if (!pw) return;
    const res = await fetch("/api/usuarios", {
      method: "PATCH",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id, password: pw }),
    });
    const j = await res.json();
    alert(j.ok ? "Contraseña actualizada." : j.error || "Error.");
  }

  return (
    <>
      <div className="table-card" style={{ marginBottom: 22 }}>
        <table>
          <thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Socio</th><th className="tc">Acciones</th></tr></thead>
          <tbody>
            {usuarios.map((u) => (
              <tr key={u.id}>
                <td>{u.nombre}</td>
                <td>{u.email}</td>
                <td><span className="tag b">{ROL_LABEL[u.rol] ?? u.rol}</span></td>
                <td>{u.socio_nombre ?? "—"}</td>
                <td className="tc" style={{ whiteSpace: "nowrap" }}>
                  <button className="btn-link" onClick={() => resetPass(u.id, u.nombre)}>Contraseña</button>
                  {u.id !== meId && <button className="btn-link danger" onClick={() => borrar(u.id, u.nombre)}>Borrar</button>}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <form className="form-card" onSubmit={onSubmit}>
        <h2>Nueva cuenta</h2>
        <div className="grid">
          <div className="field">
            <label>Nombre</label>
            <input required value={f.nombre} onChange={(e) => setF({ ...f, nombre: e.target.value })} />
          </div>
          <div className="field">
            <label>Correo</label>
            <input type="email" required value={f.email} onChange={(e) => setF({ ...f, email: e.target.value })} />
          </div>
          <div className="field">
            <label>Rol</label>
            <select value={f.rol} onChange={(e) => setF({ ...f, rol: e.target.value })}>
              <option value="capitan">Capitán</option>
              <option value="socio">Socio</option>
              <option value="admin">Administrador</option>
            </select>
          </div>
          {f.rol === "socio" && (
            <div className="field">
              <label>Socio asociado</label>
              <select value={f.socio_id} onChange={(e) => setF({ ...f, socio_id: e.target.value })}>
                {socios.map((s) => <option key={s.id} value={s.id}>{s.nombre}</option>)}
              </select>
            </div>
          )}
          <div className="field">
            <label>Contraseña (mínimo 6)</label>
            <input type="text" required minLength={6} value={f.password} onChange={(e) => setF({ ...f, password: e.target.value })} placeholder="La comparten al usuario" />
          </div>
        </div>
        <div style={{ marginTop: 16, display: "flex", alignItems: "center", gap: 14 }}>
          <button className="btn" disabled={busy}>{busy ? "Creando…" : "Crear cuenta"}</button>
          {msg && <span className={msg.ok ? "msg-ok" : "msg-err"}>{msg.t}</span>}
        </div>
      </form>
    </>
  );
}
