# Déploiement : Render (API) · Neon (PostgreSQL) · Vercel (front)

## Guide pas à pas (premier déploiement)

À faire dans cet ordre. **Ne partagez jamais** les mots de passe ni les URL de base de données (dans un message, un ticket ou le dépôt) : saisissez-les uniquement dans les réglages de Render et de Vercel.

### 1. Neon (base de données)
1. Créez un projet, base `ftth`, région Europe (Francfort).
2. Dans *Connection details*, copiez **deux** URL (elles se terminent par `?sslmode=require`) :
   - **pooled** (l'hôte contient `-pooler`) → servira de `DB_URL` ;
   - **directe** (sans `-pooler`) → servira de `DB_URL_DIRECT` (migrations).

### 2. Render (API et worker)
1. Générez la clé d'application sur votre poste : `cd backend && php artisan key:generate --show`.
2. *New → Blueprint*, choisissez le dépôt `Fiberoperate` (fichier `render.yaml`). Trois services sont créés : `ftth-api`, `ftth-queue`, `ftth-redis`.
3. Renseignez les variables demandées : `APP_KEY` (étape 1), `APP_URL` (l'adresse `https://ftth-api….onrender.com` du service, visible après création), `DB_URL`, `DB_URL_DIRECT`, `FRONTEND_URL` (mettez `http://localhost:3000` pour l'instant, vous la changerez à l'étape 4).
4. Au premier démarrage, le service `ftth-api` applique les migrations (connexion directe) et charge les motifs de blocage. Le worker ne migre pas.
5. Vérifiez : `https://<votre-api>/up` répond OK.
6. Créez le **premier administrateur** : onglet *Shell* du service `ftth-api` (plan payant) :
   `php artisan users:create-admin vous@exemple.ci "Votre Nom"` (le mot de passe est saisi de façon masquée).

### 3. Vercel (front)
1. *Add New → Project*, importez le dépôt, **Root Directory = `frontend`**.
2. Variable `NEXT_PUBLIC_API_URL` = l'adresse de l'API Render **sans `/` final** (Production et Preview).
3. Déployez et notez l'adresse `https://….vercel.app`.

### 4. Relier le front à l'API
Sur Render, remplacez `FRONTEND_URL` par l'adresse Vercel (sans `/` final) et laissez `ftth-api` redémarrer (sinon le navigateur bloque les appels : erreur CORS).

### 5. Vérification finale
Ouvrez l'adresse Vercel, connectez-vous avec le compte créé, ouvrez *Utilisateurs* et créez un deuxième compte. Aucune donnée de démonstration n'existe en production.

### Dépannage rapide
- **« Impossible de joindre le serveur »** à la connexion : `NEXT_PUBLIC_API_URL` incorrecte, ou `FRONTEND_URL` ne correspond pas exactement à l'adresse Vercel.
- **L'API ne démarre pas** : journaux de `ftth-api` ; vérifiez `APP_KEY`, `DB_URL` et `DB_URL_DIRECT`.
- **Première requête lente** : la base Neon gratuite se réveille après une période d'inactivité.

### Variante gratuite (démonstration) : `render.free.yaml`
Pour tester sans payer Render. **Jamais pour le terrain** : le service s'endort après environ 15 minutes d'inactivité et il n'y a ni worker ni Redis.

1. Étapes 1 (Neon) et 3 (Vercel) identiques.
2. Sur Render, *New → Blueprint*, puis choisissez le fichier **`render.free.yaml`** (champ *Blueprint file path*) : un seul service, `ftth-api-demo`, en plan gratuit.
3. Variables à saisir : `APP_KEY`, `APP_URL`, `DB_URL`, `DB_URL_DIRECT` (vous pouvez mettre la même URL Neon directe dans les deux), `FRONTEND_URL`, et le premier administrateur : `ADMIN_EMAIL`, `ADMIN_NAME`, `ADMIN_PASSWORD` (8 caractères, une lettre et un chiffre).
4. Il n'y a pas de Shell en plan gratuit : l'administrateur est créé **au démarrage**, une seule fois (s'il en existe déjà un, rien n'est touché et le mot de passe n'est jamais réinitialisé).
5. Une fois connecté, **supprimez la variable `ADMIN_PASSWORD`** de Render et changez votre mot de passe dans l'application (*Utilisateurs* → votre compte).
6. Si Render n'offre pas le plan gratuit à votre compte, il refusera ce fichier comme l'autre : utilisez alors `render.yaml` (payant).

---

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
3. Render n'a pas de PHP natif : l'image Docker fournie est utilisée. Le service web exécute les migrations au démarrage (avant la mise en cache de la configuration, pour utiliser `DB_URL_DIRECT`) ; le worker non.
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
