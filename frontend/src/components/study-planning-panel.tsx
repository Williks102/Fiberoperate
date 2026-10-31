"use client";

import { useState } from "react";
import { Field, inputClass } from "@/components/field";
import { ApiError, apiFetch } from "@/lib/api";
import { getToken } from "@/lib/auth/token";
import type { CustomerOrder } from "@/lib/types";
import { useApi } from "@/lib/use-api";

const today = () => new Date().toISOString().slice(0, 10);

/** Mise en étude : le coordinateur choisit un technicien habilité « étude » et la date ; la commande passe « en étude ». */
export function StudyPlanningPanel({ order, onDone }: { order: CustomerOrder; onDone: () => void }) {
  const technicians = useApi<{ data: { id: string; name: string }[] }>("/customer-orders/study-technicians");
  const [technicianId, setTechnicianId] = useState("");
  const [date, setDate] = useState(today());
  const [observation, setObservation] = useState("");
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setErrors({});
    setBusy(true);
    try {
      await apiFetch(
        `/customer-orders/${order.id}/study`,
        { method: "POST", body: JSON.stringify({ technician_id: technicianId, planned_date: date, observation: observation.trim() || null }) },
        getToken(),
      );
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

  const list = technicians.status === "ok" ? technicians.data.data : [];

  return (
    <section aria-labelledby="study" className="space-y-3 rounded-xl border border-slate-300 bg-white p-4">
      <h2 id="study" className="text-lg font-semibold">
        Mettre en étude
      </h2>
      <p className="text-sm text-slate-700">Choisissez le technicien qui ira chez le client. La commande passera « En étude ».</p>

      {technicians.status === "ok" && list.length === 0 && (
        <p role="status" className="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
          Aucun technicien n&apos;est habilité « Étude ». Donnez cette habilitation à un technicien dans Utilisateurs.
        </p>
      )}
      {errors.form && (
        <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-800">
          {errors.form}
        </p>
      )}
      {errors.status && (
        <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-800">
          {errors.status}
        </p>
      )}

      <form onSubmit={submit} className="grid gap-3 sm:grid-cols-2" noValidate>
        <Field id="technician" label="Technicien" error={errors.technician_id}>
          <select id="technician" value={technicianId} onChange={(e) => setTechnicianId(e.target.value)} className={inputClass} aria-invalid={!!errors.technician_id}>
            <option value="">Choisir un technicien</option>
            {list.map((t) => (
              <option key={t.id} value={t.id}>
                {t.name}
              </option>
            ))}
          </select>
        </Field>
        <Field id="planned-date" label="Date de l'étude" error={errors.planned_date}>
          <input id="planned-date" type="date" min={today()} value={date} onChange={(e) => setDate(e.target.value)} className={inputClass} aria-invalid={!!errors.planned_date} />
        </Field>
        <div className="sm:col-span-2">
          <Field id="observation" label="Observation (facultatif)" error={errors.observation}>
            <textarea id="observation" rows={2} value={observation} onChange={(e) => setObservation(e.target.value)} className={inputClass + " h-auto py-2"} />
          </Field>
        </div>
        <div className="sm:col-span-2">
          <button type="submit" disabled={busy || technicianId === ""} className="h-11 rounded-lg bg-primary px-6 font-medium text-white hover:bg-primary-hover disabled:opacity-60">
            {busy ? "Enregistrement…" : "Mettre en étude"}
          </button>
        </div>
      </form>
    </section>
  );
}
