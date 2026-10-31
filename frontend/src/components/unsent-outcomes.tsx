"use client";

import { formatDateTime } from "@/lib/format";
import { markOutcomeSeen, readUnseenOutcomes } from "@/lib/offline/outbox";
import { useLive } from "@/lib/offline/use-live";

const NONE: Awaited<ReturnType<typeof readUnseenOutcomes>> = [];

/** Saisies que le serveur n'a pas appliquées (visite reprogrammée, déjà saisie, saisie invalide) : gardées, avec la raison, jusqu'à lecture. */
export function UnsentOutcomes() {
  const outcomes = useLive(readUnseenOutcomes, NONE);
  if (outcomes.length === 0) return null;

  return (
    <section aria-label="Saisies non appliquées" className="space-y-2 rounded-xl border border-amber-300 bg-amber-50 p-3">
      <h2 className="text-sm font-semibold text-amber-900">Saisies non appliquées</h2>
      <ul className="space-y-2">
        {outcomes.map((o) => (
          <li key={o.client_uuid} className="space-y-1 rounded-lg bg-white p-3 text-sm">
            <p className="font-medium">
              {o.type === "study.result" ? "Étude" : "Installation"} · {o.client_name ?? "Client"} · saisie du {formatDateTime(o.occurred_at)}
            </p>
            <p className="text-slate-700">{o.message}</p>
            <button type="button" onClick={() => void markOutcomeSeen(o.client_uuid)} className="h-11 rounded-lg border border-slate-300 px-4 text-sm font-medium">
              J&apos;ai compris
            </button>
          </li>
        ))}
      </ul>
    </section>
  );
}
