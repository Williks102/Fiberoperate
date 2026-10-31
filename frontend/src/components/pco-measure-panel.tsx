"use client";

import { useState } from "react";
import { Field, inputClass } from "@/components/field";
import { ApiError, apiFetch } from "@/lib/api";
import { getToken } from "@/lib/auth/token";
import { formatDateTime } from "@/lib/format";
import type { Pco } from "@/lib/types";

const toNumberOrNull = (value: string) => (value.trim() === "" ? null : value.trim().replace(",", "."));

/** Relevé de terrain : position mesurée d'un PCO, comparée à la position prévue. */
export function PcoMeasurePanel({ pco: initial, canMeasure }: { pco: Pco; canMeasure: boolean }) {
  const [pco, setPco] = useState(initial);
  const [latitude, setLatitude] = useState(initial.measured_latitude ?? "");
  const [longitude, setLongitude] = useState(initial.measured_longitude ?? "");
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [message, setMessage] = useState<{ type: "error" | "success"; text: string } | null>(null);
  const [submitting, setSubmitting] = useState(false);

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault();
    setErrors({});
    setMessage(null);
    setSubmitting(true);
    try {
      const res = await apiFetch<{ data: Pco }>(
        `/pcos/${pco.id}/measured-position`,
        { method: "PUT", body: JSON.stringify({ latitude: toNumberOrNull(latitude), longitude: toNumberOrNull(longitude) }) },
        getToken(),
      );
      setPco(res.data);
      setMessage({ type: "success", text: "Relevé enregistré." });
    } catch (e) {
      if (e instanceof ApiError) {
        setErrors(e.errors);
        setMessage({ type: "error", text: Object.keys(e.errors).length ? "Corrigez les champs indiqués." : e.message });
      } else {
        setMessage({ type: "error", text: "Une erreur est survenue." });
      }
    } finally {
      setSubmitting(false);
    }
  }

  const planned = pco.latitude && pco.longitude ? `${pco.latitude}, ${pco.longitude}` : "non renseignée";

  return (
    <section aria-labelledby="releve" className="space-y-3 rounded-xl border border-slate-200 bg-white p-4">
      <h2 id="releve" className="text-lg font-semibold">
        Relevé de terrain
      </h2>
      <dl className="grid gap-2 text-sm sm:grid-cols-2">
        <div>
          <dt className="text-slate-700">Position prévue</dt>
          <dd className="font-medium">{planned}</dd>
        </div>
        <div>
          <dt className="text-slate-700">Position mesurée</dt>
          <dd className="font-medium">{pco.measured_latitude ? `${pco.measured_latitude}, ${pco.measured_longitude}` : "pas encore relevée"}</dd>
        </div>
        {pco.measured_at && (
          <div className="sm:col-span-2">
            <dt className="text-slate-700">Dernier relevé</dt>
            <dd>
              {formatDateTime(pco.measured_at)} · {pco.measured_by?.name ?? "auteur inconnu"}
              {pco.deviation_m != null && <span className="font-medium"> · écart avec le prévu : {pco.deviation_m} m</span>}
            </dd>
          </div>
        )}
      </dl>

      {canMeasure && (
        <form onSubmit={onSubmit} className="space-y-3" noValidate>
          {message && (
            <p role={message.type === "error" ? "alert" : "status"} className={`rounded-lg p-3 text-sm ${message.type === "error" ? "bg-red-50 text-red-800" : "bg-green-50 text-green-800"}`}>
              {message.text}
            </p>
          )}
          <div className="grid gap-3 sm:grid-cols-2">
            <Field id="measured-latitude" label="Latitude mesurée" error={errors.latitude?.[0]}>
              <input id="measured-latitude" inputMode="decimal" placeholder="5.3600" value={latitude} onChange={(e) => setLatitude(e.target.value)} className={inputClass} />
            </Field>
            <Field id="measured-longitude" label="Longitude mesurée" error={errors.longitude?.[0]}>
              <input id="measured-longitude" inputMode="decimal" placeholder="-4.0083" value={longitude} onChange={(e) => setLongitude(e.target.value)} className={inputClass} />
            </Field>
          </div>
          <button type="submit" disabled={submitting} className="h-11 rounded-lg bg-primary hover:bg-primary-hover px-6 font-medium text-white disabled:opacity-60">
            {submitting ? "Enregistrement…" : "Enregistrer le relevé"}
          </button>
        </form>
      )}
    </section>
  );
}
