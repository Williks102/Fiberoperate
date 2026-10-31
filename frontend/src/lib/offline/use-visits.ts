"use client";

import { useCallback, useEffect, useState } from "react";
import { ApiError } from "@/lib/api";
import type { Visit } from "@/lib/types";
import { readLastSync, readVisits, refreshVisits } from "./visits";
import { useOnline } from "./use-online";

const REFRESH_EVERY_MS = 5 * 60_000;

/**
 * Visites du technicien, lues d'abord sur le poste (elles s'affichent tout de suite, avec ou sans réseau), puis remises à jour
 * dès que le réseau est là et toutes les 5 minutes.
 */
export function useVisits() {
  const online = useOnline();
  const [visits, setVisits] = useState<Visit[] | null>(null);
  const [lastSyncAt, setLastSyncAt] = useState<string | null>(null);
  const [syncing, setSyncing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      setVisits(await readVisits());
      setLastSyncAt(await readLastSync());
    } catch {
      setVisits([]); // stockage local indisponible : on s'en remet au réseau
    }
  }, []);

  const refresh = useCallback(async () => {
    setSyncing(true);
    try {
      await refreshVisits();
      setError(null);
    } catch (e) {
      setError(e instanceof ApiError && e.status > 0 ? e.message : "Pas de réseau : voici vos dernières visites enregistrées sur ce téléphone.");
    } finally {
      await load();
      setSyncing(false);
    }
  }, [load]);

  // Lecture locale d'abord (rappel différé : l'état n'est jamais modifié dans le corps de l'effet).
  useEffect(() => {
    const timer = setTimeout(() => void load(), 0);

    return () => clearTimeout(timer);
  }, [load]);

  // Mise à jour dès que le réseau est là, puis toutes les 5 minutes.
  useEffect(() => {
    if (!online) return;
    const first = setTimeout(() => void refresh(), 0);
    const every = setInterval(() => void refresh(), REFRESH_EVERY_MS);

    return () => {
      clearTimeout(first);
      clearInterval(every);
    };
  }, [online, refresh]);

  return { visits, lastSyncAt, syncing, error, online, refresh };
}
