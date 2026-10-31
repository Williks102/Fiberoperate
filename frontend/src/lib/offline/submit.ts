import { pushOutbox } from "./engine";
import { uploadPendingPhotos } from "./photos";
import { enqueue, type NewOperation, type SubmitOutcome } from "./outbox";
import { getDb } from "./db";

/**
 * Saisie d'un résultat par un technicien : la saisie est d'abord enregistrée sur le téléphone (elle ne peut plus se perdre), puis
 * envoyée tout de suite si le réseau répond. Résultat : appliquée, en attente d'envoi (réseau coupé ou serveur muet), ou refusée
 * par le serveur avec sa raison (conflit ou rejet).
 */
export async function submitOperation(operation: NewOperation): Promise<SubmitOutcome> {
  const row = await enqueue(operation);

  if (typeof navigator === "undefined" || navigator.onLine) await pushOutbox().catch(() => {});

  void uploadPendingPhotos().catch(() => {}); // les photos partent en arrière-plan, sans faire attendre l'écran

  const db = getDb();
  if (await db.outbox.get(row.client_uuid)) return { state: "queued" };

  const outcome = await db.outcomes.get(row.client_uuid);
  if (outcome) {
    // La réponse est donnée tout de suite à l'écran : inutile de la remontrer ensuite dans « Saisies non appliquées ».
    await db.outcomes.update(row.client_uuid, { seen: 1 });

    return { state: outcome.status, message: outcome.message };
  }

  return { state: "applied" };
}
