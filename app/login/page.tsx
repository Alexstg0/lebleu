import { getSession, homeFor } from "@/lib/auth";
import { redirect } from "next/navigation";
import LoginForm from "./LoginForm";

export const dynamic = "force-dynamic";

export default async function LoginPage() {
  const u = await getSession();
  if (u) redirect(homeFor(u.rol));

  return (
    <div className="auth">
      <div className="auth-hero">
        <div className="auth-anchor">
          {/* La misma ancla del ícono de la app */}
          <img src="/icon-192.png" alt="Le Bleu" />
        </div>
        <div className="auth-hero-name">Le Bleu</div>
        <div className="auth-hero-tag">Experiencias inolvidables</div>
        <div className="auth-hero-rule" />
      </div>

      <div className="auth-panel">
        <div className="auth-card">
          <img src="/logo-lebleu.png" alt="Le Bleu" className="auth-card-logo" />
          <div className="auth-sub">Control de embarcación</div>
          <LoginForm />
          <div className="auth-divider">o</div>
          <div className="auth-powered">Powered by <b>Le Bleu</b></div>
        </div>

      </div>
    </div>
  );
}
