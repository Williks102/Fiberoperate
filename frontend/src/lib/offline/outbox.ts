import { getDb, type OutboxRow, type OutcomeRow } from "./db";

export type OperationType = OutboxRow["type"];

export interface NewOperation {
  type: OperationType;
  customer_order_id: string;
  payload: Record<string, unknown>;
  /** Nom du client, gardé pour dire de qui il s'agit si le serveur n'applique pas la saisie. */
  client_name?: string | null;
}

/** Identifiant de l'opération : généré sur le téléphone, il rend l'envoi sans danger même s'il est répété. */
function newUuid(): string {
  return crypto.randomUUID();
}

function deviceName(): string {
  const ua = typeof navigator === "undefined" ? "" : navigator.userAgent;

  return (ua.match(/\(([^)]+)\)/)?.[1] ?? "Téléphone").slice(0, 60);
}

/** Met une opération dans la file (avec l'heure de la saisie) ; elle y reste jusqu'à la réponse du serveur. */
export async function enqueue(operation: NewOperation): Promise<OutboxRow> {
  const row: OutboxRow = {
    client_uuid: newUuid(),
    type: operation.type,
    customer_order_id: operation.customer_order_id,
    occurred_at: new Date().toISOString(),
    device: deviceName(),
    payload: operation.payload,
    client_name: operation.client_name ?? null,
    attempts: 0,
    last_error: null,
  };
  await getDb().outbox.put(row);

  return row;
}

/** Opérations en attente, de la plus ancienne à la plus récente (l'ordre dans lequel le serveur doit les voir). */
export const readOutbox = () => getDb().outbox.orderBy("occurred_at").toArray();

export const countPending = () => getDb().outbox.count();

export type PushResult = {
  client_uuid: string | null;
  status: "applied" | "duplicate" | "conflict" | "rejected";
  original_status?: "applied" | "conflict" | "rejected";
  message?: string | null;
};

/**
 * Range les réponses du serveur : une opération n'est retirée de la file que si le serveur en a répondu (appliquée, doublon déjà
 * appliqué, conflit ou rejet). Les conflits et rejets sont gardés avec leur raison ; une opération sans réponse reste en file.
 * Renvoie le nombre d'opérations appliquées.
 */
export async function applyResults(sent: OutboxRow[], results: PushResult[]): Promise<{ applied: number; failed: number }> {
  const db = getDb();
  const byUuid = new Map(results.filter((r) => r.client_uuid).map((r) => [r.client_uuid as string, r]));
  let applied = 0;
  let failed = 0;

  await db.transaction("rw", db.outbox, db.outcomes, async () => {
    for (const row of sent) {
      const result = byUuid.get(row.client_uuid);
      if (!result) continue; // pas de réponse pour celle-ci : on la renverra

      const final = result.status === "duplicate" ? result.original_status : result.status;
      if (final === "applied") {
        applied++;
      } else if (final === "conflict" || final === "rejected") {
        failed++;
        const outcome: OutcomeRow = {
          client_uuid: row.client_uuid,
          type: row.type,
          customer_order_id: row.customer_order_id,
          status: final,
          message: result.message || "Le serveur n'a pas appliqué cette saisie.",
          occurred_at: row.occurred_at,
          client_name: row.client_name,
          seen: 0,
        };
        await db.outcomes.put(outcome);
      } else {
        continue; // réponse inattendue : on garde l'opération
      }
      await db.outbox.delete(row.client_uuid);
    }
  });

  return { applied, failed };
}

/** Une erreur d'envoi (réseau, serveur) : l'opération reste en file, avec le nombre d'essais et la dernière erreur. */
export async function markAttempt(rows: OutboxRow[], error: string): Promise<void> {
  const db = getDb();
  await db.transaction("rw", db.outbox, async () => {
    for (const row of rows) await db.outbox.update(row.client_uuid, { attempts: row.attempts + 1, last_error: error });
  });
}

export const readUnseenOutcomes = () => getDb().outcomes.where("seen").equals(0).sortBy("occurred_at");

export async function markOutcomeSeen(clientUuid: string): Promise<void> {
  await getDb().outcomes.update(clientUuid, { seen: 1 });
}

/** Commandes qui ont une saisie en attente d'envoi (pour ne pas en proposer une seconde sur la même visite). */
export const pendingOrderIds = async () => (await readOutbox()).map((row) => row.customer_order_id);

export type SubmitOutcome = { state: "applied" } | { state: "queued" } | { state: "conflict" | "rejected"; message: string };
