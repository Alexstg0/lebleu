import type { Metadata } from "next";
import { listPeriodos, getEstadoCuenta } from "@/lib/queries";
import { mxn, num, mesNombre, fechaCorta, n } from "@/lib/format";
import Topbar from "./Topbar";
import PrintButton from "./PrintButton";
import ClickableRow from "./ClickableRow";
import PagadoToggle from "./PagadoToggle";
import { requireUser } from "@/lib/auth";

export const dynamic = "force-dynamic";

const svgProps = { className: "ci", viewBox: "0 0 24 24", fill: "none", stroke: "currentColor", strokeWidth: 2, strokeLinecap: "round" as const, strokeLinejoin: "round" as const, "aria-hidden": true };
const IFuel = () => (<svg {...svgProps}><path d="M12 3s5 5.5 5 9a5 5 0 0 1-10 0c0-3.5 5-9 5-9z" /></svg>);
const ICrew = () => (<svg {...svgProps}><circle cx="12" cy="5" r="2" /><path d="M12 7v13M5 13a7 7 0 0 0 14 0M4 13H2m20 0h-2" /></svg>);
const ISupply = () => (<svg {...svgProps}><path d="M6 8h12l-1 12H7L6 8zM9 8V6a3 3 0 0 1 6 0v2" /></svg>);

export async function generateMetadata({
  searchParams,
}: {
  searchParams: Promise<{ periodo?: string }>;
}): Promise<Metadata> {
  const sp = await searchParams;
  const periodos = await listPeriodos();
  const id = Number(sp.periodo) || periodos[0]?.id;
  const p = periodos.find((x) => x.id === id);
  return { title: p ? `Le Bleu — Estado de Cuenta ${mesNombre(p.mes)} ${p.anio}` : "Le Bleu" };
}

export default async function Dashboard({
  searchParams,
}: {
  searchParams: Promise<{ periodo?: string }>;
}) {
  const user = await requireUser(["admin", "socio"]);
  const sp = await searchParams;
  const periodos = await listPeriodos();
  if (periodos.length === 0) {
    return <div className="empty">No hay periodos cargados.</div>;
  }
  const periodoId = Number(sp.periodo) || periodos[0].id;
  const data = await getEstadoCuenta(periodoId);
  if (!data) return <div className="empty">Periodo no encontrado.</div>;

  const { periodo, balances, variable, extraSocio, operativos, insumos, extraordinarios, extraCuenta, viajes, kpis } = data;
  const isAdmin = user.rol === "admin";
  const hayExtra = (extraCuenta as any[]).some((c) => c.saldoAnterior !== 0 || c.cargoMes !== 0 || c.abonosMes !== 0 || c.saldoPendiente !== 0);
  const varBy = (sid: number) => variable.find((v: any) => v.socio_id === sid) as any;
  const extraBy = (sid: number) => extraSocio.find((e: any) => e.socio_id === sid) as any;
  const firstSocio = balances[0]?.socio_id;

  return (
    <>
      <Topbar periodos={periodos} periodoId={periodoId} active="dashboard" rol={user.rol} nombre={user.nombre} />
      <PrintButton />
      <div className="wrap">
        <div className="ec-header">
          <img src="/logo-lebleu.png" alt="Le Bleu" className="ec-logo" />
          <div>
            <h1 style={{ margin: "0 0 2px", fontSize: 22 }}>
              Estado de cuenta — {mesNombre(periodo.mes)} {periodo.anio}
            </h1>
            <div className="hint">
              {periodo.embarcacion} · {periodo.razon_social} · T/C ${num(periodo.tipo_cambio, 2)} ·
              Combustible ${num(periodo.precio_litro, 2)}/Lt · Cifras en MXN
            </div>
          </div>
        </div>

        {/* KPIs */}
        <div className="kpi-grid" style={{ marginTop: 18 }}>
          <div className="kpi-card k-navy">
            <div className="kpi-label">Gastos operativos</div>
            <div className="kpi-value mono">{mxn(kpis.opTotal)}</div>
            <div className="kpi-note">
              50% c/u: {mxn(kpis.opMitad)}
              <br />Extraordinarios: {mxn(kpis.extraTotal)}
            </div>
          </div>
          <div className="kpi-card k-teal">
            <div className="kpi-label">Gasto variable</div>
            <div className="kpi-value mono">{mxn(kpis.varTotal)}</div>
            <div className="kpi-note">
              Comb. {mxn(kpis.combTotal)} · Mar. {mxn(kpis.marTotal)} · Cons. {mxn(kpis.consTotal)}
            </div>
          </div>
          <div className="kpi-card k-gold">
            <div className="kpi-label">Ingresos por renta</div>
            <div className="kpi-value mono">{mxn(kpis.ingresosRenta)}</div>
            <div className="kpi-note">{kpis.ingresosRenta > 0 ? "Renta del mes" : "Sin renta este mes"}</div>
          </div>
          <div className="kpi-card k-navy">
            <div className="kpi-label">Viajes realizados</div>
            <div className="kpi-value mono">{kpis.numViajes} viajes</div>
            <div className="kpi-note">
              {num(kpis.litrosTotal, 2)} Lts consumo total
              <br />PU: ${num(periodo.precio_litro, 2)} / Lt
            </div>
          </div>
        </div>

        {/* Socios */}
        <div className="section-label">Estado de cuenta por socio</div>
        <div className="socios-grid">
          {balances.map((b: any, i: number) => {
            const v = varBy(b.socio_id);
            const x = extraBy(b.socio_id);
            return (
              <div key={b.socio_id} className={`socio-card ${i === 0 ? "" : "s-green"}`}>
                <div className="socio-name">{b.nombre}</div>
                <div className="flow-item">
                  <span className="flow-desc">Saldo de inicio</span>
                  <span className="amt-in mono">+ {mxn(b.saldo_inicio)}</span>
                </div>
                <div className="flow-item">
                  <span className="flow-desc">Aportaciones este mes</span>
                  <span className="amt-in mono">+ {mxn(b.aportaciones)}</span>
                </div>
                <div className="flow-item">
                  <span className="flow-desc">Gastos generales (50%)</span>
                  <span className="amt-out mono">− {mxn(b.gastos_generales)}</span>
                </div>
                <div className="flow-item" style={{ flexDirection: "column", alignItems: "stretch", gap: 0 }}>
                  <div style={{ display: "flex", width: "100%" }}>
                    <span className="flow-desc" style={{ fontWeight: 600, color: "var(--text)" }}>Gastos variables</span>
                    <span className="amt-out mono" style={{ marginLeft: "auto" }}>− {mxn(b.gastos_variables)}</span>
                  </div>
                  {/* Siempre se muestran los tres renglones (aunque vayan en cero) para que ambas tarjetas sean simétricas. */}
                  <div style={{ width: "100%", marginTop: 4 }}>
                    <div className="sub-item"><span><IFuel /> Combustible · {v?.num_viajes ?? 0} viajes</span><span className="mono">− {mxn(v?.combustible ?? 0)}</span></div>
                    <div className="sub-item"><span><ICrew /> Marinero</span><span className="mono">− {mxn(v?.marinero ?? 0)}</span></div>
                    <div className="sub-item"><span><ISupply /> Consumibles</span><span className="mono">− {mxn(v?.consumibles ?? 0)}</span></div>
                  </div>
                </div>
                <div className="flow-item">
                  <span className="flow-desc">Utilidad renta</span>
                  <span className={n(b.utilidad_renta) > 0 ? "amt-in mono" : "amt-zero mono"}>
                    {n(b.utilidad_renta) > 0 ? "+ " : ""}{mxn(b.utilidad_renta)}
                  </span>
                </div>
                <div className="socio-balance">
                  <div>
                    <div className="balance-label">BALANCE OPERATIVO</div>
                    <div className="hint">Extraordinarios se liquidan por separado</div>
                  </div>
                  <div className="balance-amt mono">{mxn(b.balance_operativo)}</div>
                </div>
                {x && n(x.liquidacion_extraordinaria) > 0 && (
                  <div className="extra-box">
                    <div>
                      <div className="lbl">Pago extraordinario</div>
                      <div className="hint">Muelle + refacciones — liquidación separada</div>
                    </div>
                    <div className="amt mono">{mxn(x.liquidacion_extraordinaria)}</div>
                  </div>
                )}
              </div>
            );
          })}
        </div>

        {/* Gastos operativos */}
        <div className="section-label">Gastos operativos del mes</div>
        <div className="table-card">
          <table className="resp-table">
            <thead>
              <tr><th>#</th><th>Fecha</th><th>Proveedor</th><th>Concepto</th><th className="tr">Cargo</th><th className="tc">Comprobante</th></tr>
            </thead>
            <tbody>
              {operativos.map((g: any, i: number) => (
                <ClickableRow key={g.id} href={g.adjunto_id ? `/api/adjuntos/${g.adjunto_id}` : null}>
                  <td data-label="#">{i + 1}</td>
                  <td data-label="Fecha">{fechaCorta(g.fecha)}</td>
                  <td data-label="Proveedor">{g.proveedor}</td>
                  <td data-label="Concepto">
                    {g.concepto}
                    {g.moneda === "USD" && (
                      <span className="costo-sub">USD ${num(g.monto_original)} × T/C {num(g.tipo_cambio)}</span>
                    )}
                  </td>
                  <td data-label="Cargo" className="tr mono">{mxn(g.monto_mxn)}</td>
                  <td data-label="Comprobante" className="tc">
                    <span className={`tag ${g.adjunto_id ? "pdf-doc" : ""}`}>{g.comprobante_folio || (g.adjunto_id ? "Ver PDF" : "—")}</span>
                  </td>
                </ClickableRow>
              ))}
              <tr className="row-total"><td colSpan={4} className="tr">SUBTOTAL OPERATIVO</td><td className="tr mono">{mxn(kpis.opTotal)}</td><td /></tr>
              <tr className="row-total"><td colSpan={4} className="tr">50% por socio</td><td className="tr mono">{mxn(kpis.opMitad)}</td><td /></tr>
            </tbody>
          </table>
        </div>

        {/* Insumos de viajes */}
        <div className="section-label">Insumos de viajes</div>
        <div className="table-card">
          <table className="resp-table">
            <thead>
              <tr><th>#</th><th>Fecha</th><th>Viaje</th><th>Proveedor</th><th>Concepto</th><th className="tr">Cargo</th><th className="tc">Comprobante</th></tr>
            </thead>
            <tbody>
              {insumos.length === 0 && (
                <tr><td colSpan={7} className="tc hint">Sin insumos registrados este mes. Captúralos en Capturar → Insumos.</td></tr>
              )}
              {insumos.map((it: any, i: number) => (
                <ClickableRow key={it.id} href={it.adjunto_id ? `/api/adjuntos/${it.adjunto_id}` : null}>
                  <td data-label="#">{i + 1}</td>
                  <td data-label="Fecha">{fechaCorta(it.fecha)}</td>
                  <td data-label="Viaje">{it.viaje_id ? `${it.viaje_cliente} · ${fechaCorta(it.viaje_fecha)}` : "—"}</td>
                  <td data-label="Proveedor">{it.proveedor || "—"}</td>
                  <td data-label="Concepto">{it.concepto || "—"}</td>
                  <td data-label="Cargo" className="tr mono">{mxn(it.monto_mxn)}</td>
                  <td data-label="Comprobante" className="tc">
                    <span className={`tag ${it.adjunto_id ? "pdf-doc" : ""}`}>{it.comprobante_folio || (it.adjunto_id ? "Ver PDF" : "—")}</span>
                  </td>
                </ClickableRow>
              ))}
              {insumos.length > 0 && (
                <tr className="row-total"><td colSpan={5} className="tr">TOTAL INSUMOS</td><td className="tr mono">{mxn(kpis.insumosTotal)}</td><td /></tr>
              )}
            </tbody>
          </table>
        </div>

        {/* Resumen variable */}
        <div className="section-label">Resumen de gastos variables por socio</div>
        <div className="table-card">
          <table className="resp-table">
            <thead>
              <tr><th>Socio</th><th className="tc">Viajes</th><th className="tr">Combustible</th><th className="tr">Marinero</th><th className="tr">Consumibles</th><th className="tr">Total</th></tr>
            </thead>
            <tbody>
              {balances.map((b: any) => {
                const v = varBy(b.socio_id);
                return (
                  <tr key={b.socio_id}>
                    <td data-label="Socio">{b.nombre}</td>
                    <td data-label="Viajes" className="tc">{v?.num_viajes ?? 0}</td>
                    <td data-label="Combustible" className="tr mono">{mxn(v?.combustible)}</td>
                    <td data-label="Marinero" className="tr mono">{mxn(v?.marinero)}</td>
                    <td data-label="Consumibles" className="tr mono">{mxn(v?.consumibles)}</td>
                    <td data-label="Total" className="tr mono">{mxn(v?.total_variable)}</td>
                  </tr>
                );
              })}
              <tr className="row-total">
                <td className="tr" colSpan={2}>TOTAL</td>
                <td className="tr mono">{mxn(kpis.combTotal)}</td>
                <td className="tr mono">{mxn(kpis.marTotal)}</td>
                <td className="tr mono">{mxn(kpis.consTotal)}</td>
                <td className="tr mono">{mxn(kpis.varTotal)}</td>
              </tr>
            </tbody>
          </table>
        </div>

        {/* Viajes */}
        <div className="section-label">Reporte de viajes — detalle por salida</div>
        <div className="viajes-grid">
          {viajes.map((v: any) => (
            <div key={v.id} className={`viaje-card ${v.bandera ? "warn" : ""}`}>
              <div className={`viaje-header ${v.socio_id === firstSocio ? "h-acosta" : "h-garcia"}`}>
                <div>
                  <div className="viaje-date">{fechaCorta(v.fecha)} / {periodo.anio}</div>
                  <div className="viaje-owner">{v.cliente ?? v.socio}</div>
                </div>
                <span className="dur-badge">
                  {v.duracion_horas ? `${num(v.duracion_horas, 0)} Hrs` : "—"} · {v.num_personas ?? "NA"} pers.
                </span>
              </div>
              <div className="viaje-body">
                <div className="horometros">
                  {v.horometros.map((h: any, k: number) => (
                    <div key={k} className="horo-item">
                      <div className="horo-label">{h.etiqueta}</div>
                      <div className="horo-vals mono">
                        {num(h.lectura_inicio)}<span className="horo-arrow">→</span>{num(h.lectura_fin)}
                      </div>
                    </div>
                  ))}
                </div>
                <div className="costo-row">
                  <span className="costo-label"><IFuel /> Combustible<span className="costo-sub">{num(v.litros)} Lts × ${num(v.precio_litro)}</span></span>
                  <span className="mono">{mxn(v.costo_combustible)}</span>
                </div>
                <div className="costo-row">
                  <span className="costo-label"><ICrew /> Marinero</span>
                  <span className="mono">{mxn(v.costo_marinero)}</span>
                </div>
                <div className="costo-row">
                  <span className="costo-label"><ISupply /> Consumibles{v.consumibles_comprobante && <span className="costo-sub">{v.consumibles_comprobante}</span>}</span>
                  <span className="mono">{n(v.costo_consumibles) > 0 ? mxn(v.costo_consumibles) : "—"}</span>
                </div>
                <div className="viaje-total">
                  <span>Total viaje</span>
                  <span className="mono">{mxn(v.total)}</span>
                </div>
              </div>
            </div>
          ))}
        </div>

        {/* Estado de extraordinarios por socio (arriba de la tabla) */}
        {hayExtra && (
          <>
            <div className="section-label" style={{ borderLeftColor: "#c05621", color: "#c05621" }}>Estado de extraordinarios por socio</div>
            <div className="socios-grid">
              {(extraCuenta as any[]).map((c, i) => (
                <div key={c.socio_id} className={`socio-card ${i === 0 ? "" : "s-green"}`} style={{ borderTopColor: "#c05621" }}>
                  <div className="socio-name">{c.nombre}</div>
                  <div className="flow-item">
                    <span className="flow-desc">Saldo anterior pendiente</span>
                    <span className="amt-out mono">{mxn(c.saldoAnterior)}</span>
                  </div>
                  <div className="flow-item">
                    <span className="flow-desc">Cargo del mes (50%)</span>
                    <span className="amt-out mono">+ {mxn(c.cargoMes)}</span>
                  </div>
                  <div className="flow-item">
                    <span className="flow-desc">Abonos del mes</span>
                    <span className={n(c.abonosMes) > 0 ? "amt-in mono" : "amt-zero mono"}>{n(c.abonosMes) > 0 ? "− " : ""}{mxn(c.abonosMes)}</span>
                  </div>
                  <div className="socio-balance">
                    <div>
                      <div className="balance-label">SALDO PENDIENTE</div>
                      <div className="hint">Gastos extraordinarios</div>
                    </div>
                    <div className="balance-amt mono" style={{ color: n(c.saldoPendiente) > 0 ? "#c0392b" : "var(--green)" }}>{mxn(c.saldoPendiente)}</div>
                  </div>
                </div>
              ))}
            </div>
          </>
        )}

        {/* Extraordinarios */}
        <div className="section-label" style={{ borderLeftColor: "#c05621", color: "#c05621" }}>
          Gastos extraordinarios
        </div>
        {extraordinarios.length === 0 ? (
          <div className="empty">Sin gastos extraordinarios registrados este mes.</div>
        ) : (
        <div className="table-card" style={{ border: "1px solid #f5b7a0" }}>
          <table className="resp-table">
            <thead>
              <tr><th>#</th><th>Fecha</th><th>Proveedor</th><th>Concepto</th><th className="tr">USD</th><th className="tr">MXN</th><th className="tc">Pagado</th><th className="tc">Comprobante</th></tr>
            </thead>
            <tbody>
              {extraordinarios.map((e: any, i: number) => (
                <ClickableRow key={e.id} href={e.adjunto_id ? `/api/adjuntos/${e.adjunto_id}` : null}>
                  <td data-label="#">E-{i + 1}</td>
                  <td data-label="Fecha">{fechaCorta(e.fecha)}</td>
                  <td data-label="Proveedor">{e.proveedor}</td>
                  <td data-label="Concepto">{e.concepto}</td>
                  <td data-label="USD" className="tr mono">{e.moneda === "USD" ? `$${num(e.monto_original)}` : "—"}</td>
                  <td data-label="MXN" className="tr mono">{mxn(e.monto_mxn)}</td>
                  <td data-label="Pagado" className="tc">
                    {isAdmin
                      ? <PagadoToggle id={e.id} pagado={!!e.liquidado} />
                      : <span className={`pago-badge ${e.liquidado ? "si" : "no"}`}>{e.liquidado ? "Pagado" : "Pendiente"}</span>}
                  </td>
                  <td data-label="Comprobante" className="tc">
                    <span className={`tag ${e.adjunto_id ? "pdf-doc" : "a"}`}>{e.comprobante_folio || (e.adjunto_id ? "Ver PDF" : "—")}</span>
                  </td>
                </ClickableRow>
              ))}
              <tr className="row-total">
                <td colSpan={5} className="tr">TOTAL EXTRAORDINARIOS</td>
                <td className="tr mono">{mxn(kpis.extraTotal)}</td>
                <td className="tc mono" colSpan={2}>50% c/u: {mxn(kpis.extraMitad)}</td>
              </tr>
            </tbody>
          </table>
        </div>
        )}

        <div style={{ textAlign: "center", color: "var(--muted)", fontSize: 11, margin: "30px 0" }}>
          {periodo.razon_social} · Embarcación {periodo.embarcacion} · {mesNombre(periodo.mes)} {periodo.anio}
        </div>
      </div>
    </>
  );
}
