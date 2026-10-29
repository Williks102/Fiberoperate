"use client";

import { useEffect, useState } from "react";

/** Valeur retardée (recherche pendant la frappe, sans bouton « Rechercher »). */
export function useDebounced<T>(value: T, delayMs = 300): T {
  const [debounced, setDebounced] = useState(value);

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delayMs);
    return () => clearTimeout(timer);
  }, [value, delayMs]);

  return debounced;
}
