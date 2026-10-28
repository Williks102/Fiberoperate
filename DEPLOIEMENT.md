# Déploiement : Render (API) · Neon (PostgreSQL) · Vercel (front)

## Architecture
```
Navigateur / PWA ──► Vercel (Next.js) ──HTTPS──► Render : ftth-api (Laravel)
                                                    ├─► Neon (PostgreSQL)
                                                    ├─► Render Key Value (Redis)
                                                    └─► Cloudinary (photos, URL signées)
PWA ──envoi direct (signature fournie par l'API)──► Cloudinary
                                    Render : ftth-queue (worker : imports, exports, notifications)
```

## 1. Neon
1. Créer le projet et une base `ftth`. Prendre la région la plus proche de Render (ex. Europe/Francfort).
2. Récupérer **deux** URL : *pooled* (hôte `…-pooler…`) → `DB_URL` pour l'application ; *directe* → `DB_URL_DIRECT` pour les migrations (le pooler en mode transaction gêne certaines commandes).
3. Branches Neon : une branche `dev`/`preview` par environnement, jamais de données clients réelles hors production.
4. Plan gratuit : la base se met en veille après inactivité (réveil de quelques secondes) → prévoir un plan payant avant le pilote.

## 2. Render
1. Pousser le dépôt (racine : `render.yaml`, dossier `backend/`), puis *New → Blueprint*.
2. Renseigner les variables marquées `sync: false` : `APP_KEY` (`php artisan key:generate --show`), `APP_URL`, `DB_URL`, `DB_URL_DIRECT`, clés du stockage, `FRONTEND_URL`.
3. Render n'a pas de PHP natif : l'image Docker fournie est utilisée. Le service web exécute les migrations au démarrage ; le worker non.
4. Éviter le plan gratuit pour l'API et le worker (mise en veille = synchro terrain lente, jobs d'import interrompus).
5. **Photos** : voir §3 bis (Cloudinary). Render n'a pas de stockage d'objets et son disque est éphémère.

## 3. Vercel
1. Importer le dépôt, *Root Directory* = `frontend`.
2. Variable `NEXT_PUBLIC_API_URL` = URL de l'API Render. Une variable par environnement (Production / Preview).
3. PWA : servir le service worker depuis la racine ; ne jamais mettre en cache les réponses authentifiées de l'API sans stratégie explicite (la file d'actions hors-ligne est gérée par l'application).

## 3 bis. Cloudinary (photos et signatures)
1. Créer un compte, récupérer `CLOUDINARY_URL` (`cloudinary://clé:secret@nom_du_cloud`) et le renseigner sur Render (API et worker). Le secret ne va **jamais** dans Vercel ni dans le front.
2. **Envoi direct depuis la PWA** : l'API fournit une signature (`POST /api/uploads/sign`, durée courte, dossier imposé) ; la PWA envoie la photo compressée à Cloudinary en arrière-plan, puis confirme à l'API avec le `public_id` et le `client_uuid` (endpoint idempotent). Cela allège l'API et convient au mode hors-ligne (la photo reste en file locale jusqu'au retour du réseau).
3. **Confidentialité** : les photos de clients/domiciles sont envoyées en mode `authenticated` (pas d'URL publique) ; l'API génère des URL signées à durée limitée.
4. En base, on ne stocke que `public_id`, format, taille, hash et `client_uuid` ; jamais l'URL complète.
5. Côté Laravel : SDK `cloudinary/cloudinary_php` (ou le paquet Laravel dédié) pour signer, générer les URL et supprimer. Ce n'est pas un disque S3 : pas de `Storage::disk()` pour les photos.
6. Quotas du plan gratuit (stockage, transformations, bande passante) à vérifier avant le pilote ; prévoir une politique de rétention.
7. Même remarque que pour la base : serveurs hors de Côte d'Ivoire, à valider (voir §5).

## 4. Authentification entre domaines
Front (`*.vercel.app`) et API (`*.onrender.com`) sont sur des domaines différents : les cookies de session Sanctum « SPA » ne fonctionneront pas de façon fiable. Choix retenu : **jetons Sanctum (Bearer)**, conservés côté client, avec CORS limité à `FRONTEND_URL`. Si un domaine propre est acquis plus tard (`app.exemple.ci` / `api.exemple.ci`), le mode cookie redevient possible.

## 5. Points d'attention
- **Données personnelles** (noms, téléphones, GPS des clients) hébergées hors de Côte d'Ivoire : à valider avec l'opérateur donneur d'ordre et au regard de la réglementation locale (ARTCI).
- Sauvegardes : activer la restauration à un instant donné de Neon et tester une restauration avant la production.
- Journaux : `LOG_CHANNEL=stderr`, jamais de données clients en clair.
- Coûts : API + worker + base + stockage sont des abonnements séparés ; vérifier les tarifs actuels avant de choisir les plans.
