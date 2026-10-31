"use client";

import { useState } from "react";
import { Field, inputClass } from "@/components/field";
import { ApiError, apiFetch } from "@/lib/api";
import { useAuth } from "@/lib/auth/auth-context";
import { getToken } from "@/lib/auth/token";
import { PhotoCapture } from "@/components/photo-capture";
import { attachPhotos } from "@/lib/offline/photos";
import { submitOperation } from "@/lib/offline/submit";
import { useLive } from "@/lib/offline/use-live";
import { readReasons, type OutcomeReason } from "@/lib/offline/visits";
import type { CustomerOrder } from "@/lib/types";
import { useApi } from "@/lib/use-api";

const NO_REASONS: OutcomeReason[] = [];

type Result = "installed" | "postponed" | "failed";
const RESULTS: { value: Result; label: string }[] = [
  { value: "installed", label: "Installé" },
  { value: "postponed", label: "Reporté" },
  { value: "failed", label: "Échec" },
];

/** Issue de la visite d'installation : installé, reporté ou en échec (motif obligatoire). La box est attribuée avant par le coordinateur. */
export function InstallationResultPanel({ order, onDone }: { order: Pick<CustomerOrder, "id" | "name">; onDone: () => void }) {
  const { user } = useAuth();
  // Le technicien lit les motifs sur le téléphone (ils marchent sans réseau) ; les autres rôles, sur le serveur.
  const onPhone = user?.role === "technician";
  const remoteReasons = useApi<{ data: OutcomeReason[] }>(onPhone ? null : "/customer-orders/outcome-reasons");
  const localReasons = useLive<OutcomeReason[]>(readReasons, NO_REASONS);
  const [result, setResult] = useState<Result | "">("");
  const [reasonId, setReasonId] = useState("");
  const [observation, setObservation] = useState("");
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);
  const [photoIds, setPhotoIds] = useState<string[]>([]);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setErrors({});
    if (!result) {
      setErrors({ result: "Indiquez le résultat de l'installation." });
      return;
    }
    if (onPhone && photoIds.length === 0) {
      setErrors({ photos: "Ajoutez au moins une photo : elle est obligatoire." });
      return;
    }
    setBusy(true);
    const payload = { result, reason_id: result === "installed" ? null : reasonId || null, observation: observation.trim() || null };
    try {
      if (user?.role === "technician") {
        // Le technicien saisit sur le terrain : la saisie est gardée sur le téléphone puis envoyée, même si le réseau lâche.
        await attachPhotos(photoIds);
        const outcome = await submitOperation({ type: "installation.result", customer_order_id: order.id, payload: { ...payload, photo_uuids: photoIds }, client_name: order.name });
        if (outcome.state === "applied") onDone();
        else if (outcome.state !== "queued") setErrors({ form: outcome.message });
        return; // « en attente » : la fiche l'indique d'elle-même
      }
      await apiFetch(`/customer-orders/${order.id}/installation-result`, { method: "POST", body: JSON.stringify(payload) }, getToken());
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

  const list = onPhone ? localReasons : remoteReasons.status === "ok" ? remoteReasons.data.data : NO_REASONS;

  return (
    <section aria-labelledby="install-result" className="space-y-4 rounded-xl border border-slate-300 bg-white p-4">
      <h2 id="install-result" className="text-lg font-semibold">
        Résultat de l&apos;installation
      </h2>
      {(errors.form || errors.status) && (
        <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-800">
          {errors.form ?? errors.status}
        </p>
      )}

      <form onSubmit={submit} className="space-y-4" noValidate>
        <fieldset className="space-y-1.5">
          <legend className="text-sm font-medium text-ink">L&apos;installation est</legend>
          <div className="grid gap-2 sm:grid-cols-3">
            {RESULTS.map((r) => (
              <label key={r.value} className={`flex h-11 cursor-pointer items-center justify-center rounded-xl border text-sm font-medium ${result === r.value ? "border-primary bg-primary-soft text-primary-ink" : "border-slate-300 bg-white"}`}>
                <input type="radio" name="install-result" value={r.value} checked={result === r.value} onChange={() => setResult(r.value)} className="sr-only" />
                {r.label}
              </label>
            ))}
          </div>
          {errors.result && (
            <p role="alert" className="text-sm text-red-700">
              {errors.result}
            </p>
          )}
        </fieldset>

        {(result === "postponed" || result === "failed") && (
          <Field id="reason" label={result === "postponed" ? "Motif du report" : "Motif de l'échec"} error={errors.reason_id}>
            <select id="reason" value={reasonId} onChange={(e) => setReasonId(e.target.value)} className={inputClass} aria-invalid={!!errors.reason_id}>
              <option value="">Choisir un motif</option>
              {list.map((r) => (
                <option key={r.id} value={r.id}>
                  {r.label}
                </option>
              ))}
            </select>
          </Field>
        )}

        <Field id="install-observation" label="Observation (facultatif)" error={errors.observation}>
          <textarea id="install-observation" rows={2} value={observation} onChange={(e) => setObservation(e.target.value)} className={inputClass + " h-auto py-2"} />
        </Field>

        {onPhone && <PhotoCapture
            orderId={order.id}
            onChange={(ids) => {
              setPhotoIds(ids);
              setErrors((current) => Object.fromEntries(Object.entries(current).filter(([key]) => key !== "photos"))); // la photo est prise : le rappel disparaît
            }}
            error={errors.photos}
          />}

        <button type="submit" disabled={busy} className="h-11 rounded-lg bg-primary px-6 font-medium text-white hover:bg-primary-hover disabled:opacity-60">
          {busy ? "Enregistrement…" : "Enregistrer le résultat"}
        </button>
      </form>
    </section>
  );
}
