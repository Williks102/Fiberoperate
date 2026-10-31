"use client";

import { useEffect } from "react";

/** Pages à ouvrir une fois en ligne pour qu'elles soient disponibles sans réseau (le technicien n'a pas à les visiter d'abord). */
const WARM_PAGES = ["/", "/mes-visites", "/mes-visites/saisie"];
const WARMED = "ftth.pages-warmed";

export function ServiceWorkerRegister() {
  useEffect(() => {
    if (process.env.NODE_ENV !== "production" || !("serviceWorker" in navigator)) return;

    navigator.serviceWorker
      .register("/sw.js")
      .then(() => navigator.serviceWorker.ready)
      .then(() => {
        try {
          if (sessionStorage.getItem(WARMED)) return;
          sessionStorage.setItem(WARMED, "1");
        } catch {
          // stockage indisponible : on réchauffe à chaque ouverture, sans conséquence
        }
        for (const page of WARM_PAGES) void fetch(page, { headers: { Accept: "text/html" } }).catch(() => {});
      })
      .catch(() => {});
  }, []);

  return null;
}
