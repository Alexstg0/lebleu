"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { fechaISO } from "@/lib/format";

export type Msg = { ok: boolean; t: string } | null;

export function useSubmit(url: string, method: "POST" | "PATCH" = "POST") {
  const router = useRouter();
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState<Msg>(null);

  async function submit(payload: unknown, onOk?: () => void) {
    setBusy(true);
    setMsg(null);
    try {
      const res = await fetch(url, {
        method,
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      const j = await res.json();
      if (j.ok) {
        setMsg({ ok: true, t: method === "PATCH" ? "Actualizado." : "Guardado correctamente." });
        onOk?.();
        router.refresh();
      } else {
        setMsg({ ok: false, t: j.error || "Error al guardar." });
      }
    } catch (e: any) {
      setMsg({ ok: false, t: String(e?.message || e) });
    } finally {
      setBusy(false);
    }
  }

  return { busy, msg, submit };
}

export async function deleteRow(url: string, id: number): Promise<{ ok: boolean; error?: string }> {
  try {
    const res = await fetch(`${url}?id=${id}`, { method: "DELETE" });
    return await res.json();
  } catch (e: any) {
    return { ok: false, error: String(e?.message || e) };
  }
}

export const today = () => {
  const t = new Date();
  return `${t.getFullYear()}-${String(t.getMonth() + 1).padStart(2, "0")}-${String(t.getDate()).padStart(2, "0")}`;
};
export const round2 = (x: number) => Math.round(x * 100) / 100;
export const toDate = (v: unknown) => fechaISO(v);
