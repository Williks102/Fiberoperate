import Dexie, { type Table } from "dexie";
import type { Visit } from "@/lib/types";

/** Opération saisie sur le terrain, en attente d'envoi : elle ne quitte la file qu'avec la réponse du serveur. */
export interface OutboxRow {
  client_uuid: string;
  type: "study.result" | "installation.result";
  customer_order_id: string;
  occurred_at: string;
  device: string;
  payload: Record<string, unknown>;
  /** Nom du client, gardé pour dire de qui il s'agit si le serveur n'applique pas la saisie. */
  client_name: string | null;
  attempts: number;
  last_error: string | null;
}

/** Saisie que le serveur n'a pas appliquée (conflit ou rejet) : gardée, avec sa raison, jusqu'à ce que le technicien l'ait lue. */
export interface OutcomeRow {
  client_uuid: string;
  type: OutboxRow["type"];
  customer_order_id: string;
  status: "conflict" | "rejected";
  message: string;
  occurred_at: string;
  client_name: string | null;
  seen: 0 | 1;
}

/** Un PCO gardé sur le poste pour choisir le rattachement d'une étude sans réseau (`search` : code sans séparateurs, pour chercher). */
export interface PcoRow {
  id: string;
  code: string;
  zone: string | null;
  splitter: string | null;
  search: string;
}

/**
 * Photo prise sur le terrain, gardée sur le téléphone jusqu'à son envoi à Cloudinary puis son enregistrement par l'API. Une photo
 * n'est envoyée qu'une fois « rattachée » à un résultat saisi (sinon elle est jetée après un jour : photo abandonnée).
 */
export interface PhotoRow {
  client_uuid: string;
  customer_order_id: string;
  blob: Blob;
  width: number;
  height: number;
  hash: string;
  taken_at: string;
  attached: 0 | 1;
  attempts: number;
  last_error: string | null;
}

/** Données du poste : la copie locale des visites du technicien, et quelques réglages (dernier utilisateur, dernière mise à jour). */
export interface MetaRow {
  key: string;
  value: unknown;
}

class TerrainDb extends Dexie {
  visits!: Table<Visit, string>;
  meta!: Table<MetaRow, string>;
  outbox!: Table<OutboxRow, string>;
  outcomes!: Table<OutcomeRow, string>;
  pcos!: Table<PcoRow, string>;
  photos!: Table<PhotoRow, string>;

  constructor() {
    super("ftth-terrain");
    this.version(1).stores({ visits: "id, planned_date", meta: "key" });
    this.version(2).stores({ visits: "id, planned_date", meta: "key", outbox: "client_uuid, occurred_at, customer_order_id", outcomes: "client_uuid, seen" });
    this.version(3).stores({ visits: "id, planned_date", meta: "key", outbox: "client_uuid, occurred_at, customer_order_id", outcomes: "client_uuid, seen", pcos: "id" });
    this.version(4).stores({ visits: "id, planned_date", meta: "key", outbox: "client_uuid, occurred_at, customer_order_id", outcomes: "client_uuid, seen", pcos: "id", photos: "client_uuid, attached" });
  }
}

let db: TerrainDb | null = null;

/** Base locale, créée à la première utilisation (jamais côté serveur : IndexedDB n'y existe pas). */
export function getDb(): TerrainDb {
  db ??= new TerrainDb();

  return db;
}

/** Pour les tests : referme la base et repart de zéro. */
export async function resetDb(): Promise<void> {
  if (db) await db.delete();
  db = null;
}
