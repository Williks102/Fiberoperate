import { apiFetch, ApiError } from "@/lib/api";
import { getToken } from "@/lib/auth/token";
import { applyResults, markAttempt, readOutbox, type PushResult } from "./outbox";
import { refreshVisits } from "./visits";

const BATCH = 100;
let running: Promise<{ applied: number; failed: number }> | null = null;

/**
 * Envoie la file au serveur, par lots de 100 triés par heure de saisie. Un seul envoi à la fois (un second appel attend le premier).
 * Une opération ne quitte la file qu'avec la réponse du serveur ; sur une erreur réseau ou serveur, tout reste en file et le nombre
 * d'essais augmente. Après un envoi qui a appliqué quelque chose, les visites sont rechargées.
 */
export function pushOutbox(): Promise<{ applied: number; failed: number }> {
  running ??= run().finally(() => {
    running = null;
  });

  return running;
}

async function run(): Promise<{ applied: number; failed: number }> {
  const total = { applied: 0, failed: 0 };
  const token = getToken();

  for (;;) {
    const rows = (await readOutbox()).slice(0, BATCH);
    if (rows.length === 0) break;

    try {
      const response = await apiFetch<{ data: PushResult[] }>(
        "/sync/push",
        { method: "POST", body: JSON.stringify({ operations: rows.map((r) => ({ client_uuid: r.client_uuid, type: r.type, customer_order_id: r.customer_order_id, occurred_at: r.occurred_at, device: r.device, payload: r.payload })) }) },
        token,
      );
      const done = await applyResults(rows, response.data);
      total.applied += done.applied;
      total.failed += done.failed;
      if (done.applied + done.failed === 0) break; // aucune réponse exploitable : on réessaiera plus tard, sans boucler
    } catch (e) {
      await markAttempt(rows, e instanceof ApiError ? e.message : "Réseau indisponible.");
      break;
    }
  }

  if (total.applied > 0) await refreshVisits().catch(() => {});

  return total;
}
