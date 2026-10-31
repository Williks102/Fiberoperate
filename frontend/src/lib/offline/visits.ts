import { apiFetch } from "@/lib/api";
import { getToken } from "@/lib/auth/token";
import type { Paged, User, Visit } from "@/lib/types";
import { getDb } from "./db";

export interface OutcomeReason {
  id: string;
  code: string;
  label: string;
}

export interface StudyDefaults {
  pole_spacing_m: number;
  counted_pole_types: string[];
}

const LAST_SYNC = "last_sync_at";
const USER = "user";
const REASONS = "outcome_reasons";
const STUDY_DEFAULTS = "study_defaults";

async function getMeta<T>(key: string): Promise<T | null> {
  const row = await getDb().meta.get(key);

  return (row?.value as T | undefined) ?? null;
}

/**
 * Télécharge les visites du technicien (et les listes dont ses formulaires ont besoin : motifs, réglages d'étude) et remplace la
 * copie locale en une seule transaction : une visite réalisée ou reprogrammée ailleurs disparaît du poste. Une erreur réseau ne
 * touche à rien : le poste garde ce qu'il avait.
 */
export async function refreshVisits(): Promise<{ count: number; syncedAt: string }> {
  const token = getToken();
  const [visits, reasons, defaults] = await Promise.allSettled([
    apiFetch<Paged<Visit>>("/my-visits?per_page=100", {}, token),
    apiFetch<{ data: OutcomeReason[] }>("/customer-orders/outcome-reasons", {}, token),
    apiFetch<{ data: StudyDefaults }>("/customer-orders/study-defaults", {}, token),
  ]);

  if (visits.status === "rejected") throw visits.reason;

  const syncedAt = new Date().toISOString();
  const db = getDb();
  await db.transaction("rw", db.visits, db.meta, async () => {
    await db.visits.clear();
    await db.visits.bulkPut(visits.value.data);
    await db.meta.put({ key: LAST_SYNC, value: syncedAt });
    // Les listes de référence sont facultatives : si elles échouent, on garde les précédentes.
    if (reasons.status === "fulfilled") await db.meta.put({ key: REASONS, value: reasons.value.data });
    if (defaults.status === "fulfilled") await db.meta.put({ key: STUDY_DEFAULTS, value: defaults.value.data });
  });

  return { count: visits.value.data.length, syncedAt };
}

/** Visites gardées sur le poste, de la plus ancienne à la plus lointaine. */
export async function readVisits(): Promise<Visit[]> {
  return getDb().visits.orderBy("planned_date").toArray();
}

export const readLastSync = () => getMeta<string>(LAST_SYNC);
export const readReasons = async () => (await getMeta<OutcomeReason[]>(REASONS)) ?? [];
export const readStudyDefaults = () => getMeta<StudyDefaults>(STUDY_DEFAULTS);

/** Dernier utilisateur connu : permet d'ouvrir l'application sans réseau (le jeton, lui, est dans `localStorage`). */
export async function saveUser(user: User): Promise<void> {
  await getDb().meta.put({ key: USER, value: user });
}

export const loadUser = () => getMeta<User>(USER);

/**
 * Efface ce que le poste garde (visites, contacts et adresses des clients, utilisateur). À la déconnexion et quand un autre
 * technicien se connecte : les données d'un technicien ne restent pas sur le téléphone d'un autre. Avec `keepUnsent` (jeton expiré),
 * les résultats pas encore envoyés et les saisies non appliquées restent, avec le dernier utilisateur : ils attendent la reconnexion.
 */
export async function clearLocalData(options: { keepUnsent?: boolean } = {}): Promise<void> {
  try {
    const db = getDb();
    await db.transaction("rw", db.visits, db.meta, db.outbox, db.outcomes, async () => {
      await db.visits.clear();
      if (options.keepUnsent) {
        await db.meta.where("key").notEqual(USER).delete();

        return;
      }
      await db.meta.clear();
      await db.outbox.clear();
      await db.outcomes.clear();
    });
  } catch {
    // IndexedDB indisponible (navigation privée) : il n'y a rien à effacer
  }
}
