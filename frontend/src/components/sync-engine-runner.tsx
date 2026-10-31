"use client";

import { useEffect } from "react";
import { useAuth } from "@/lib/auth/auth-context";
import { pushOutbox } from "@/lib/offline/engine";
import { useOnline } from "@/lib/offline/use-online";

const PUSH_EVERY_MS = 30_000;

/** Envoie les résultats en attente dès que le réseau revient, puis toutes les 30 secondes, depuis n'importe quel écran. */
export function SyncEngineRunner() {
  const { user } = useAuth();
  const online = useOnline();
  const isTechnician = user?.role === "technician";

  useEffect(() => {
    if (!isTechnician || !online) return;
    const first = setTimeout(() => void pushOutbox().catch(() => {}), 0);
    const every = setInterval(() => void pushOutbox().catch(() => {}), PUSH_EVERY_MS);

    return () => {
      clearTimeout(first);
      clearInterval(every);
    };
  }, [isTechnician, online]);

  return null;
}
