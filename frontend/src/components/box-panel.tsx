"use client";

import { useState } from "react";
import { Field, inputClass } from "@/components/field";
import { ApiError, apiFetch } from "@/lib/api";
import { getToken } from "@/lib/auth/token";
import type { CustomerOrder } from "@/lib/types";

/** Box du client, une fois l'installation programmée : le coordinateur corrige le numéro de série de l'ONT et le numéro fixe (la box se donne à la programmation). */
export function BoxPanel({ order, onDone }: { order: CustomerOrder; onDone: () => void }) {
  const [ontSerial, setOntSerial] = useState(order.ont_serial ?? "");
  const [fixedLine, setFixedLine] = useState(order.fixed_line ?? "");
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);
  const assigned = !!(order.ont_serial || order.fixed_line);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setErrors({});
    setBusy(true);
    try {
      await apiFetch(`/customer-orders/${order.id}/box`, { method: "PUT", body: JSON.stringify({ ont_serial: ontSerial.trim() || null, fixed_line: fixedLine.trim() || null }) }, getToken());
      onDone();
    } catch (err) {
      if (err instanceof ApiError) {
        const flat = Object.fromEntries(Object.entries(err.errors).map(([k, v]) => [k, v[0]]));
        setErrors(Object.keys(flat).length ? flat : { form: err.message });
      } else {
        setErrors({ form: "Une erreur est survenue." });
      }
    } finally {
      setBusy(false);
    }
  }

  return (
    <section aria-labelledby="box" className="space-y-3 rounded-xl border border-slate-300 bg-white p-4">
      <h2 id="box" className="text-lg font-semibold">
        {assigned ? "Box du client" : "Attribuer la box"}
      </h2>
      <p className="text-sm text-slate-700">Donnée en même temps que la programmation de l&apos;installation ; corrigez-la ici si besoin, jusqu&apos;à l&apos;installation.</p>
      {(errors.form || errors.status) && (
        <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-800">
          {errors.form ?? errors.status}
        </p>
      )}
      <form onSubmit={submit} className="grid gap-3 sm:grid-cols-2" noValidate>
        <Field id="ont-serial" label="N° de série de l'ONT" error={errors.ont_serial}>
          <input id="ont-serial" value={ontSerial} onChange={(e) => setOntSerial(e.target.value)} autoCapitalize="characters" className={inputClass} aria-invalid={!!errors.ont_serial} />
        </Field>
        <Field id="fixed-line" label="Numéro fixe" hint="De 8 à 12 chiffres." error={errors.fixed_line}>
          <input id="fixed-line" type="tel" inputMode="numeric" value={fixedLine} onChange={(e) => setFixedLine(e.target.value)} className={inputClass} aria-invalid={!!errors.fixed_line} />
        </Field>
        <div className="sm:col-span-2">
          <button type="submit" disabled={busy || (ontSerial.trim() === "" && fixedLine.trim() === "")} className="h-11 rounded-lg bg-primary px-6 font-medium text-white hover:bg-primary-hover disabled:opacity-60">
            {busy ? "Enregistrement…" : assigned ? "Enregistrer la box" : "Attribuer la box"}
          </button>
        </div>
      </form>
    </section>
  );
}
