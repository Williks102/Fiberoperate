import Dexie, { type Table } from "dexie";
import type { Visit } from "@/lib/types";

/** Données du poste : la copie locale des visites du technicien, et quelques réglages (dernier utilisateur, dernière mise à jour). */
export interface MetaRow {
  key: string;
  value: unknown;
}

class TerrainDb extends Dexie {
  visits!: Table<Visit, string>;
  meta!: Table<MetaRow, string>;

  constructor() {
    super("ftth-terrain");
    this.version(1).stores({ visits: "id, planned_date", meta: "key" });
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
