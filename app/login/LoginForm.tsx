"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";

const Mail = () => (
  <svg className="lead" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><rect x="3" y="5" width="18" height="14" rx="2" /><path d="M3 7l9 6 9-6" /></svg>
);
const Lock = () => (
  <svg className="lead" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><rect x="4" y="11" width="16" height="9" rx="2" /><path d="M8 11V8a4 4 0 0 1 8 0v3" /></svg>
);
const Eye = ({ off }: { off?: boolean }) => (
  <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z" /><circle cx="12" cy="12" r="3" />
    {off && <path d="M3 3l18 18" />}
  </svg>
);
const AnchorBtn = () => (
  <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="5" r="2.2" /><path d="M12 7.2V21M5 13a7 7 0 0 0 14 0M4 13H2m20 0h-2" /></svg>
);

export default function LoginForm() {
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [show, setShow] = useState(false);
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState("");

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setErr("");
    try {
      const res = await fetch("/api/login", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email, password }),
      });
      const j = await res.json();
      if (j.ok) {
        router.push(j.rol === "capitan" ? "/calendario" : "/");
        router.refresh();
      } else {
        setErr(j.error || "No se pudo iniciar sesión.");
      }
    } catch (e: any) {
      setErr(String(e?.message || e));
    } finally {
      setBusy(false);
    }
  }

  return (
    <form onSubmit={onSubmit}>
      <div className="auth-field">
        <label>Correo</label>
        <div className="auth-input">
          <Mail />
          <input type="email" required value={email} onChange={(e) => setEmail(e.target.value)} placeholder="Ingresa tu correo" autoFocus />
        </div>
      </div>

      <div className="auth-field">
        <label>Contraseña</label>
        <div className="auth-input">
          <Lock />
          <input type={show ? "text" : "password"} required value={password} onChange={(e) => setPassword(e.target.value)} placeholder="Ingresa tu contraseña" />
          <button type="button" className="auth-eye" onClick={() => setShow((s) => !s)} aria-label={show ? "Ocultar" : "Mostrar"}>
            <Eye off={show} />
          </button>
        </div>
      </div>

      <div className="auth-row">
        <label className="auth-remember"><input type="checkbox" /> Recordarme</label>
        <button type="button" className="auth-link" onClick={() => alert("Contacta al administrador para restablecer tu contraseña.")}>
          ¿Olvidaste tu contraseña?
        </button>
      </div>

      {err && <div className="msg-err" style={{ marginBottom: 14 }}>{err}</div>}

      <button className="auth-submit" disabled={busy}>
        <AnchorBtn />
        {busy ? "Entrando…" : "Iniciar sesión"}
      </button>
    </form>
  );
}
