"use client";

import { liveQuery } from "dexie";
import { useEffect, useState } from "react";

/** Valeur d'une requête IndexedDB, remise à jour toute seule quand les données du poste changent. `querier` et `onError` doivent être stables ; `onError` est la valeur prise si le stockage local est indisponible. */
export function useLive<T>(querier: () => Promise<T> | T, initial: T, onError?: T): T {
  const [value, setValue] = useState<T>(initial);

  useEffect(() => {
    const subscription = liveQuery(querier).subscribe({ next: setValue, error: () => onError !== undefined && setValue(onError) });

    return () => subscription.unsubscribe();
  }, [querier, onError]);

  return value;
}
