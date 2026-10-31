"use client";

import { useCallback, useEffect, useState } from "react";
import { ApiError } from "@/lib/api";
import type { Visit } from "@/lib/types";
import { useOnline } from "./use-online";
import { useLive } from "./use-live";
import { readLastSync, readVisits, refreshVisits } from "./visits";

const REFRESH_EVERY_MS = 5 * 60_000;
const NO_VISITS: Visit[] = []; // stockage local indisponible : on s'en remet au réseau

/**
 * Visites du technicien, lues en direct sur le poste (elles s'affichent tout de suite, avec ou sans réseau, et suivent les mises à
 * jour faites par le moteur d'envoi), puis remises à jour dès que le réseau est là et toutes les 5 minutes.
 */
export function useVisits() {
  const online = useOnline();
  const visits = useLive<Visit[] | null>(readVisits, null, NO_VISITS);
  const lastSyncAt = useLive<string | null>(readLastSync, null);
  const [syncing, setSyncing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const refresh = useCallback(async () => {
    setSyncing(true);
    try {
      await refreshVisits();
      setError(null);
    } catch (e) {
      setError(e instanceof ApiError && e.status > 0 ? e.message : "Pas de réseau : voici vos dernières visites enregistrées sur ce téléphone.");
    } finally {
      setSyncing(false);
    }
  }, []);

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
