"use client";

import { useState } from "react";
import { Field, inputClass } from "@/components/field";
import { ApiError, apiFetch } from "@/lib/api";
import { getToken } from "@/lib/auth/token";
import type { Project } from "@/lib/types";

/** Circuit de validation : envoi de l'avant-projet, puis décision (validé ou refusé avec motif) de la phase en cours. */
export function ProjectActions({ project, onDone }: { project: Project; onDone: () => void }) {
  const [refusing, setRefusing] = useState(false);
  const [reason, setReason] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [reasonError, setReasonError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function send(path: string, body?: object) {
    setError(null);
    setReasonError(null);
    setBusy(true);
    try {
      await apiFetch(`/projects/${project.id}/${path}`, { method: "POST", body: body ? JSON.stringify(body) : undefined }, getToken());
      setRefusing(false);
      setReason("");
      onDone();
    } catch (e) {
      if (e instanceof ApiError) {
        setReasonError(e.errors.reason?.[0] ?? null);
        setError(e.errors.reason ? null : (e.errors.phase?.[0] ?? e.message));
      } else {
        setError("Une erreur est survenue.");
      }
    } finally {
      setBusy(false);
    }
  }

  if (project.actions?.submit) {
    return (
      <section aria-labelledby="action" className="space-y-3 rounded-xl border border-zinc-300 bg-white p-4">
        <h2 id="action" className="text-lg font-semibold">
          Envoyer en validation
        </h2>
        <p className="text-sm text-zinc-700">L&apos;avant-projet sera validé par le chef du BE, puis par le DT, puis par le client. Un refus le renvoie ici avec son motif.</p>
        {error && (
          <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-800">
            {error}
          </p>
        )}
        <button onClick={() => send("submit")} disabled={busy} className="h-11 rounded-lg bg-slate-900 px-6 font-medium text-white disabled:opacity-60">
          {busy ? "Envoi…" : "Envoyer en validation"}
        </button>
      </section>
    );
  }

  if (project.actions?.decide) {
    return (
      <section aria-labelledby="action" className="space-y-3 rounded-xl border border-zinc-300 bg-white p-4">
        <h2 id="action" className="text-lg font-semibold">
          Décision : {project.phase_label}
        </h2>
        {error && (
          <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-800">
            {error}
          </p>
        )}
        {refusing ? (
          <div className="space-y-3">
            <Field id="reason" label="Motif du refus" hint="Le projet retourne en avant-projet avec ce motif." error={reasonError ?? undefined}>
              <textarea id="reason" value={reason} onChange={(e) => setReason(e.target.value)} rows={3} className={inputClass} />
            </Field>
            <div className="flex flex-wrap gap-3">
              <button onClick={() => send("decision", { decision: "rejected", reason })} disabled={busy} className="h-11 rounded-lg bg-red-700 px-6 font-medium text-white disabled:opacity-60">
                {busy ? "Envoi…" : "Confirmer le refus"}
              </button>
              <button onClick={() => setRefusing(false)} disabled={busy} className="h-11 rounded-lg border border-zinc-300 px-4 font-medium">
                Annuler
              </button>
            </div>
          </div>
        ) : (
          <div className="flex flex-wrap gap-3">
            <button onClick={() => send("decision", { decision: "approved" })} disabled={busy} className="h-11 rounded-lg bg-slate-900 px-6 font-medium text-white disabled:opacity-60">
              {busy ? "Envoi…" : "Valider"}
            </button>
            <button onClick={() => setRefusing(true)} disabled={busy} className="h-11 rounded-lg border border-zinc-300 px-4 font-medium">
              Refuser…
            </button>
          </div>
        )}
      </section>
    );
  }

  return null;
}
