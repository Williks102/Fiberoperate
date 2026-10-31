// Service worker (lot 4b) : l'application s'ouvre sans réseau une fois qu'elle a été ouverte en ligne sur le téléphone.
// - /_next/static (fichiers à empreinte, qui ne changent jamais) : cache d'abord.
// - pages et leurs données de navigation (RSC) du même site : réseau d'abord, repli sur le cache si le réseau est coupé
//   ou met plus de 4 secondes à répondre.
// - jamais l'API (autre origine), jamais autre chose qu'un GET : les données de l'API (clients, visites) ne passent JAMAIS par ce
//   cache, elles sont gardées dans IndexedDB par l'application et effacées à la déconnexion.
const VERSION = "v2";
const STATIC = `ftth-static-${VERSION}`;
const PAGES = `ftth-pages-${VERSION}`;
const SLOW_NETWORK_MS = 4000;

const OFFLINE_PAGE = `<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Hors ligne</title>
<style>body{font-family:system-ui,sans-serif;margin:0;display:grid;place-items:center;min-height:100vh;background:#f8fafc;color:#0f172a}main{max-width:22rem;padding:1.5rem;text-align:center}a{display:inline-block;margin-top:1rem;padding:.75rem 1.25rem;border-radius:.5rem;background:#0369a1;color:#fff;text-decoration:none}</style></head>
<body><main><h1>Vous êtes hors ligne</h1><p>Cette page n'a pas encore été ouverte sur ce téléphone. Revenez à vos visites ou reconnectez-vous au réseau.</p><a href="/mes-visites">Mes visites</a></main></body></html>`;

self.addEventListener("install", () => self.skipWaiting());

self.addEventListener("activate", (event) => {
  event.waitUntil(
    (async () => {
      for (const key of await caches.keys()) {
        if (key !== STATIC && key !== PAGES) await caches.delete(key);
      }
      await self.clients.claim();
    })(),
  );
});

async function cacheFirst(request) {
  const cache = await caches.open(STATIC);
  const cached = await cache.match(request);
  if (cached) return cached;
  const response = await fetch(request);
  if (response.ok) await cache.put(request, response.clone());

  return response;
}

function offlineResponse(request) {
  return request.mode === "navigate" ? new Response(OFFLINE_PAGE, { status: 503, headers: { "Content-Type": "text/html; charset=utf-8" } }) : Response.error();
}

async function networkFirst(event, request) {
  const cache = await caches.open(PAGES);
  const cached = await cache.match(request, { ignoreVary: true });

  const network = fetch(request).then((response) => {
    if (response.ok) event.waitUntil(cache.put(request, response.clone()));

    return response;
  });

  if (!cached) return network.catch(() => offlineResponse(request));

  // Une copie existe : on ne laisse pas un réseau lent ou coupé bloquer l'écran.
  const slow = new Promise((resolve) => setTimeout(() => resolve(cached), SLOW_NETWORK_MS));

  return Promise.race([network, slow]).catch(() => cached);
}

self.addEventListener("fetch", (event) => {
  const request = event.request;
  if (request.method !== "GET") return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  if (url.pathname.startsWith("/_next/static/")) {
    event.respondWith(cacheFirst(request));

    return;
  }

  const isPage = request.mode === "navigate" || request.headers.has("rsc") || (request.headers.get("accept") || "").includes("text/html");
  if (isPage) event.respondWith(networkFirst(event, request));
});
