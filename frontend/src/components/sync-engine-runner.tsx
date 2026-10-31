"use client";

import { useEffect } from "react";
import { useAuth } from "@/lib/auth/auth-context";
import { pushOutbox } from "@/lib/offline/engine";
import { uploadPendingPhotos } from "@/lib/offline/photos";
import { useOnline } from "@/lib/offline/use-online";

const PUSH_EVERY_MS = 30_000;

/** Résultats d'abord (ils comptent le plus), puis les photos, plus lourdes. */
const syncNow = () => pushOutbox().catch(() => {}).then(() => uploadPendingPhotos()).catch(() => {});

/** Envoie les résultats puis les photos en attente dès que le réseau revient, puis toutes les 30 secondes, depuis n'importe quel écran. */
export function SyncEngineRunner() {
  const { user } = useAuth();
  const online = useOnline();
  const isTechnician = user?.role === "technician";

  useEffect(() => {
    if (!isTechnician || !online) return;
    const first = setTimeout(() => void syncNow(), 0);
    const every = setInterval(() => void syncNow(), PUSH_EVERY_MS);

    return () => {
      clearTimeout(first);
      clearInterval(every);
    };
  }, [isTechnician, online]);

  return null;
}
