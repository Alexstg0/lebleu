"use client";

import { useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { mesNombre } from "@/lib/format";

type P = { id: number; anio: number; mes: number };
type Rol = "admin" | "capitan" | "socio";
type Active = "dashboard" | "capturar" | "periodos" | "movimientos" | "calendario" | "usuarios" | "bitacora" | "caja" | "reportes" | "analisis" | "auditoria";
type Item = { href: string; label: string; key: Active };

export default function Topbar({
  periodos,
  periodoId,
  active,
  rol,
  nombre,
}: {
  periodos: P[];
  periodoId: number;
  active: Active;
  rol: Rol;
  nombre?: string;
}) {
  const router = useRouter();
  const [open, setOpen] = useState(false);          // drawer móvil
  const [menu, setMenu] = useState<string | null>(null); // dropdown abierto en escritorio
  const navRef = useRef<HTMLElement>(null);

  // Cierra los dropdowns al hacer clic fuera.
  useEffect(() => {
    if (!menu) return;
    const onDown = (e: MouseEvent) => {
      if (navRef.current && !navRef.current.contains(e.target as Node)) setMenu(null);
    };
    document.addEventListener("mousedown", onDown);
    return () => document.removeEventListener("mousedown", onDown);
  }, [menu]);

  async function salir() {
    await fetch("/api/logout", { method: "POST" });
    router.push("/login");
    router.refresh();
  }

  // Navegación principal (lo del día a día, siempre visible en escritorio).
  const principales: Item[] = [];
  if (rol === "admin" || rol === "socio") principales.push({ href: `/?periodo=${periodoId}`, label: "Estado de cuenta", key: "dashboard" });
  principales.push({ href: "/calendario", label: "Calendario", key: "calendario" });
  if (rol === "admin" || rol === "capitan") principales.push({ href: "/capturar", label: rol === "capitan" ? "Registrar viaje" : "Capturar", key: "capturar" });
  if (rol === "admin" || rol === "capitan") principales.push({ href: "/bitacora", label: "Bitácora", key: "bitacora" });
  if (rol === "admin" || rol === "capitan") principales.push({ href: "/caja-chica", label: "Caja chica", key: "caja" });

  // Grupos desplegables.
  const grupos: { nombre: string; items: Item[] }[] = [];
  if (rol === "admin" || rol === "socio") {
    grupos.push({
      nombre: "Reportes",
      items: [
        { href: "/reportes", label: "Reportes", key: "reportes" },
        { href: "/analisis", label: "Análisis", key: "analisis" },
      ],
    });
  }
  if (rol === "admin") {
    grupos.push({
      nombre: "Administración",
      items: [
        { href: `/movimientos?periodo=${periodoId}`, label: "Movimientos", key: "movimientos" },
        { href: "/periodos", label: "Periodos", key: "periodos" },
        { href: "/usuarios", label: "Usuarios", key: "usuarios" },
        { href: "/auditoria", label: "Auditoría", key: "auditoria" },
      ],
    });
  }

  const todos: Item[] = [...principales, ...grupos.flatMap((g) => g.items)];
  const showPeriodo = (rol === "admin" || rol === "socio") && periodos.length > 0;
  const PeriodoSelect = ({ cls }: { cls?: string }) => (
    <select className={cls} value={periodoId} onChange={(e) => router.push(`/?periodo=${e.target.value}`)} aria-label="Periodo">
      {periodos.map((p) => (
        <option key={p.id} value={p.id}>{mesNombre(p.mes)} {p.anio}</option>
      ))}
    </select>
  );
  const Chevron = () => (
    <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6" /></svg>
  );

  return (
    <>
      <header className="topbar">
        <div className="topbar-left">
          <button className="topbar-burger" onClick={() => setOpen(true)} aria-label="Abrir menú">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><path d="M3 6h18M3 12h18M3 18h18" /></svg>
          </button>
          <div className="brand">
            Le Bleu
            <small>Control de embarcación</small>
          </div>
        </div>

        {/* Navegación en línea (escritorio) */}
        <nav className="topbar-nav" ref={navRef}>
          {showPeriodo && <PeriodoSelect />}
          {principales.map((it) => (
            <a key={it.key} href={it.href} className={active === it.key ? "active" : ""}>{it.label}</a>
          ))}
          {grupos.map((g) => {
            const activo = g.items.some((it) => it.key === active);
            const abierto = menu === g.nombre;
            return (
              <div key={g.nombre} className="nav-group">
                <button className={`nav-group-btn ${activo ? "active" : ""}`} onClick={() => setMenu(abierto ? null : g.nombre)} aria-expanded={abierto}>
                  {g.nombre} <Chevron />
                </button>
                {abierto && (
                  <div className="nav-menu">
                    {g.items.map((it) => (
                      <a key={it.key} href={it.href} className={active === it.key ? "active" : ""} onClick={() => setMenu(null)}>{it.label}</a>
                    ))}
                  </div>
                )}
              </div>
            );
          })}
          <div className="nav-group">
            <button className="nav-group-btn nav-user" onClick={() => setMenu(menu === "user" ? null : "user")} aria-expanded={menu === "user"}>
              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4" /><path d="M4 21c0-4 4-6 8-6s8 2 8 6" /></svg>
              {nombre ? nombre.split(" ")[0] : "Cuenta"} <Chevron />
            </button>
            {menu === "user" && (
              <div className="nav-menu" style={{ right: 0, left: "auto" }}>
                {nombre && <div className="nav-menu-nombre">{nombre}</div>}
                <a href="/api/manual" target="_blank" rel="noopener" onClick={() => setMenu(null)}>Manual de uso</a>
                <button onClick={salir}>Cerrar sesión</button>
              </div>
            )}
          </div>
        </nav>
      </header>

      {/* Menú lateral (móvil) */}
      <div className={`drawer-overlay ${open ? "show" : ""}`} onClick={() => setOpen(false)} />
      <aside className={`drawer ${open ? "open" : ""}`} aria-hidden={!open}>
        <div className="drawer-head">
          <div className="brand">Le Bleu<small>Control de embarcación</small></div>
          <button className="drawer-close" onClick={() => setOpen(false)} aria-label="Cerrar menú">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><path d="M6 6l12 12M18 6L6 18" /></svg>
          </button>
        </div>
        {showPeriodo && (
          <div className="drawer-periodo">
            <label>Periodo</label>
            <PeriodoSelect cls="drawer-select" />
          </div>
        )}
        <nav className="drawer-nav">
          {principales.map((it) => (
            <a key={it.key} href={it.href} className={active === it.key ? "active" : ""} onClick={() => setOpen(false)}>{it.label}</a>
          ))}
          {grupos.map((g) => (
            <div key={g.nombre}>
              <div className="drawer-seccion">{g.nombre}</div>
              {g.items.map((it) => (
                <a key={it.key} href={it.href} className={active === it.key ? "active" : ""} onClick={() => setOpen(false)}>{it.label}</a>
              ))}
            </div>
          ))}
        </nav>
        <a className="drawer-manual" href="/api/manual" target="_blank" rel="noopener" onClick={() => setOpen(false)}>Manual de uso</a>
        <button className="drawer-salir" onClick={salir}>
          {nombre ? `Salir · ${nombre}` : "Salir"}
        </button>
      </aside>
    </>
  );
}
