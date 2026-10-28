// Service worker minimal (lot 0) : rend l'application installable.
// La stratégie hors-ligne (IndexedDB, file d'actions, rejeu) arrive au lot 4.
// Les réponses authentifiées de l'API ne sont jamais mises en cache ici.
self.addEventListener("install", () => self.skipWaiting());
self.addEventListener("activate", (event) => event.waitUntil(self.clients.claim()));
self.addEventListener("fetch", () => {});
