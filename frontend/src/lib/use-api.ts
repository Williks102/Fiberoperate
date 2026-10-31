"use client";

import { useCallback, useEffect, useState } from "react";
import { apiFetch } from "@/lib/api";
import { getToken } from "@/lib/auth/token";

type Settled<T> = { key: string; data?: T; error?: string };

export type ApiState<T> = { status: "loading" } | { status: "error"; message: string } | { status: "ok"; data: T };

/** GET sur l'API avec états chargement / erreur / ok et nouvel essai. */
/** `path` à `null` : rien n'est demandé (l'écran lit ses données ailleurs, par exemple sur le poste). */
export function useApi<T>(path: string | null): ApiState<T> & { reload: () => void } {
  const [tick, setTick] = useState(0);
  const [settled, setSettled] = useState<Settled<T> | null>(null);
  const key = `${path}#${tick}`;

  useEffect(() => {
    if (path === null) return;
    let cancelled = false;
    apiFetch<T>(path, {}, getToken())
      .then((data) => !cancelled && setSettled({ key, data }))
      .catch((e: Error) => !cancelled && setSettled({ key, error: e.message }));
    return () => {
      cancelled = true;
    };
  }, [path, key]);

  const reload = useCallback(() => setTick((n) => n + 1), []);

  if (!settled || settled.key !== key) return { status: "loading", reload };
  if (settled.error !== undefined) return { status: "error", message: settled.error, reload };
  return { status: "ok", data: settled.data as T, reload };
}
