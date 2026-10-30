# CLAUDE.md — Application de gestion FTTH (sous-traitant opérateur)

Référence fonctionnelle complète : `CAHIER_DES_CHARGES.md` (v1.9). Parcours d'un projet : `docs/workflow-projet-deploiement.md`. En cas de doute métier, ce document fait foi.

## Objectif
Application de gestion pour une entreprise sous-traitante qui installe la fibre optique : import de la liste des clients, études, plannings (raccordements, déploiements, tirages, maintenance), suivi des zones et des PCO, reporting du coordinateur, matériel, incidents, tableaux de bord. **Opérationnel uniquement** : pas de facturation ni de paie (le **devis de l'AVP** est saisi, sans facturation). **Pas d'intégration technique** avec la plateforme de l'opérateur en v1.

## Stack
- Backend : Laravel (API REST, Sanctum, policies), PostgreSQL, Redis + queues, Cloudinary pour les photos.
- Hébergement : API Laravel sur **Render** (Docker + worker de files + Key Value Redis), PostgreSQL sur **Neon** (URL pooled pour l'app, URL directe pour les migrations), front Next.js sur **Vercel**, photos sur **Cloudinary** (envoi direct signé depuis la PWA, mode `authenticated`, URL signées ; en base seulement `public_id` + `client_uuid`). Auth entre domaines : jetons Sanctum Bearer + CORS limité à `FRONTEND_URL`. Détails : `DEPLOIEMENT.md`.
- Frontend : Next.js + TypeScript, PWA (service worker, IndexedDB), carte MapLibre/Leaflet.
- Interface et messages en **français**. Code, noms de tables/variables en **anglais**.

## Vocabulaire
- **PCO** (point de concentration optique) = **PBO** = le « boîtier » : table `pcos`, code du type `FAY03_PCO001`.
- Hiérarchie (structure du fichier de suivi de déploiement, **seule référence de l'architecture**) : **Projet** → **plaque** (`plaques`, ex. FAYA, ARCADE ; n'existe que sous un projet ; desservie par un **OLT**, `olts`) → **sous-plaque = zone** (`zones` : `FAY03` = FAYA 03, `ABB01`) → **SRO** (`sros`, une par sous-plaque, v1.9 ; part de l'OLT, source du signal de la plaque, et alimente les splitters de sa sous-plaque ; nom = lettres de la sous-plaque + numéro donné par l'opérateur (saisi, indépendant de celui de la sous-plaque), ex. `ABB456` ; slot, port et brin de l'OLT saisis à la SRO) → **JDV = splitter** (`jdvs`, convention `SPL001`…) → **PCO** → `customer_orders`. Le mot « zone » du brief désigne la sous-plaque. Un **superviseur couvre une plaque entière** (toutes ses sous-plaques) : `plaques.supervisor_id`. L'OLT peut être renseigné plus tard (`plaques.olt_id` nullable).
- **Aucun nombre imposé** : standards (v1.9) = 8 splitters par SRO, 8 PCO par splitter, PCO de 16 ports avec 8 clients raccordés au plus. Ce sont des **valeurs par défaut modifiables, jamais bloquantes** : ne jamais valider ni refuser un nombre de JDV par SRO ou de PCO par JDV (observé : 1 à 16 PCO par splitter, 1 à 11 splitters par sous-plaque). Un PCO est saturé à 8 clients ; au 9ᵉ, on le **désature** en créant un autre PCO.
- **Génération d'une sous-plaque** : code + nombre de PCO libre (32 et 64 suggérés) → SRO, splitters `SPL001…` (8 PCO chacun) et PCO `{CODE}_PCO001…` créés en une transaction, refusée si la sous-plaque a déjà des splitters ou des PCO. Le technicien du BE ajuste ensuite.
- L'équipement de la sous-plaque se dit « hub » chez Huawei et « SRO » chez Nokia : stocker le fabricant et afficher le libellé correspondant. Depuis la v1.9 la SRO est un niveau à part (table `sros`, 1:1 avec la sous-plaque), ce qui remplace la décision « pas de table `nodes` séparée ».
- Le « D » des codes (D01…) = **division** : pas exploité pour l'instant, conserver le texte brut. Le « F » (F22, F77…) est peut-être un numéro de fibre : à confirmer, ne pas le coder en dur.
- **Étude technique initiale** (vocabulaire de l'opérateur, à ne pas inverser) : « **Sans PCO** » = étude **réalisée**, client raccordable ; « **Avec PCO** » = étude **à réaliser**. Stocker le libellé d'origine (`initial_study_status_raw`) et un état normalisé ; afficher des libellés clairs dans l'interface ; reprendre les libellés de l'opérateur dans l'export de la fiche de suivi.
- **Box (ONT)** : après l'étude, une box est attribuée au client pour son installation ; elle est rattachée à un **SN ONT** (numéro de série) et à un **numéro fixe** (colonne « LIGNE FIXE » du fichier de suivi raccordement). Structure du fichier et pièges d'import : `docs/fichiers-exemples/suivi-raccordement.md`.
- **BE** = bureau d'études (chef du BE, dessinateurs, techniciens réseau). **AVP** = avant-projet (plans et devis). **KMZ** = plan lisible dans Google Earth. **Survey** = relevé de terrain (d'implantation avant le déploiement, final après).
- **RAB** = référence de commande opérateur (clé d'import). **FT** = numéro de ligne FTTH. Ne pas supposer une longueur fixe : les données réelles varient.

## Rôles (un seul rôle `technician` avec habilitations)
`admin`, `dg`, `dt`, `supervisor`, `coordinator`, `technician`, et `be_manager` (chef du BE, créé le 30/10/2026 : lecture du réseau et des OLT ; ses écrans viendront avec le lot 1B).
Habilitations technicien : `deployment`, `customer_connection`, `maintenance`, `study`, et `drawing` (dessin, AVP) et `survey` (relevés), créées le 30/10/2026.
Hiérarchie : technicien déploiement → superviseur → DT ; technicien raccordement ou maintenance → coordinateur → DT ; technicien du BE → chef du BE → DT (le coordinateur reçoit les résultats d'étude, sans être leur supérieur).
**Écriture du réseau** (sous-plaques, SRO, splitters, PCO, génération) : administrateur ; chef du BE et techniciens du BE (habilitation `drawing`) tant que le projet de la plaque est en avant-projet (`ProjectPhase::beMayEditNetwork`, hypothèse à confirmer, cahier §9 point 11). Les techniciens `drawing` voient toutes les plaques. Plaques et OLT : administrateur seul.
Toute requête est filtrée par rôle **et** périmètre (plaque pour le superviseur, équipes pour le coordinateur). Policies Laravel obligatoires, jamais de contrôle uniquement côté front.

## Flux métier
0. **Projet de déploiement** (la plaque naît avec le projet : parcours guidé nom → OLT choisi ou créé → sous-plaques, ou import CSV rattaché au projet) : avant-projet par le BE (plans, KMZ, AVP et devis), validation du chef du BE, puis du DT, puis du client ; survey d'implantation ; déploiement par le superviseur ; relevé final. Détail et phases : `docs/workflow-projet-deploiement.md`. Documents toujours versionnés, jamais écrasés ; un refus renvoie en avant-projet avec un motif obligatoire.
1. Le coordinateur importe la liste des clients (Excel/CSV) → une `customer_order` par ligne.
2. Mise en étude → intervention `study` → résultat saisi (formulaire terrain ou par le coordinateur).
3. Programmation de l'installation (équipe, date) → intervention `customer_connection`.
4. Résultat d'installation → le coordinateur reporte aussi sur la plateforme de l'opérateur (hors application) et coche `operator_synced_at`.
5. Export Excel « fiche de suivi » avec les mêmes colonnes que la fiche actuelle.

## Règles métier clés
- Statuts PCO (`pcos.status`) : `to_pull → pulled → spliced → activated`. `spliced` = **soudure du PCO terminée** (pas client branché). **Pas d'étape de recette** (décision du client) ; champ `observation` libre sur les statuts et interventions. Ne pas ajouter `tested` sans nouvelle décision.
- Le fichier de suivi de déploiement utilise d'autres libellés (en attente BE, travaux en cours, tirage NOK, posé, en service, bloqué…). **Liste normalisée à valider** (cahier §3.6) : en attendant, conserver le libellé d'origine dans `status_raw` et gérer « bloqué » comme un indicateur (`is_blocked` + `block_reason_id`), pas comme un statut.
- Statuts commande (`customer_orders.status`) : `received → in_study → studied (feasible | not_feasible | customer_absent) → appointment_set → installed | postponed | failed → activated`.
- `operator_line_status` (état de ligne côté opérateur) est un champ distinct du statut interne, avec sa date.
- Étude : poteaux saisis par type (béton = transport, métalliques = raccordement client), chacun en **existants** et **à installer**. `distance_m` est préremplie avec nombre de poteaux du trajet × 50 m et reste modifiable par le technicien ; les types à compter par défaut sont à confirmer (le rendre configurable).
- Un report ou un échec exige un `reason_id` du référentiel.
- Un technicien n'est planifiable que sur un type de mission pour lequel il est habilité.
- Tout changement de statut écrit une ligne d'historique (qui, quand, appareil).
- Zones (sous-plaques) : `planned → deploying → in_operation → closed` (clôture validée par le DT).

## Import de la liste des clients
- Import asynchrone en deux temps : analyse → écran de revue → validation. Chaque ligne : `ok`, `to_fix` ou `rejected`, avec la liste des anomalies.
- Découper l'adresse en champs (ville, commune, quartier, rue, précision) ; extraire le GPS et le code PCO s'ils sont dans l'adresse ; **conserver toujours l'adresse brute d'origine** (`address_raw`).
- Si la colonne d'étude initiale est présente : « Sans PCO » → commande directement `studied` (`feasible`), « Avec PCO » → `received` (étude à planifier).
- Valider le GPS : zone attendue, latitude/longitude inversées, séparateurs (`;`, espace, virgule), valeurs aberrantes ; ne jamais corriger silencieusement.
- Téléphones : supprimer le `*` en tête, normaliser en +225 + 10 chiffres, signaler les longueurs anormales.
- Doublons : RAB, FT, nom + adresse. Réimport d'un même RAB = mise à jour, pas de doublon.
- Dates : stocker des vraies dates ; les fichiers Excel fournissent parfois des numéros de série.

## Import du suivi de déploiement
- Une feuille = une plaque ; ignorer et signaler les feuilles vides.
- **Import sous un projet** (v1.9, plus d'import global) : un fichier = **une plaque, celle du projet** (colonne plaque facultative, une autre plaque = ligne refusée) ; colonne SRO (numéro donné par l'opérateur) qui crée la SRO de la sous-plaque si elle n'en a pas.
- Colonnes : OLT, plaque, sous-plaque (code de plaque), SRO, splitter, PCO, latitude/longitude, slot, port, brin de fibre, statut, commentaire. Slot, port et brin sont souvent vides : ne pas les exiger.
- Normaliser les codes avant comparaison (`ACA03_PCO-001` / `ACA003` / `FAY03_PCO001` : tiret et zéros variables) ; signaler les doublons sans fusion automatique.
- Statuts en texte libre avec variantes d'écriture : table de correspondance (cahier §3.6), libellé d'origine toujours conservé, libellé inconnu proposé à la correspondance, jamais ignoré.
- Export dans le même format de feuilles.

## Hors-ligne (priorité de conception)
- Pré-chargement des interventions, file d'actions locale, rejeu à la reconnexion.
- Chaque création côté client porte un `client_uuid` ; endpoints d'écriture **idempotents** sur ce champ.
- Le serveur fait foi pour le planning, le terrain fait foi pour l'exécution. Conflits signalés, jamais écrasés silencieusement.
- Photos : compression locale, envoi en arrière-plan.

## Conventions de code
- UUID, `created_at/updated_at`, suppression logique (`deleted_at`) sur les tables métier.
- Migrations versionnées ; aucun changement de schéma hors migration.
- Validation via Form Requests ; réponses via API Resources ; pagination obligatoire sur les listes.
- Traitements lourds (import, export, notifications) en jobs asynchrones.
- Tests : permissions par rôle/périmètre, cycles de statut, idempotence de la synchronisation, règles d'import sur un jeu de données réel anonymisé.
- Pas de secrets dans le dépôt ; configuration via `.env`. Données clients (noms, téléphones, GPS) : ne jamais les journaliser en clair.

## Ordre de développement
1. Auth, rôles, habilitations, équipes, référentiels (OLT, plaques, zones/sous-plaques, JDV, PCO).
1B. **Projet de déploiement et bureau d'études** (décision du 30/10/2026 : avant l'import des clients, qui se fait quand la structure de l'activité est maîtrisée). Le formulaire PCO s'y rattache. Cahier v1.9 : niveau SRO, création de projet guidée, import sous un projet, génération des sous-plaques (tranches dans `LOTS_DE_TRAVAIL.md`).
2. Import de la liste des clients et commandes.
3. Études et planning.
4. PWA technicien hors-ligne (études, installation, photos, signature).
5. Reporting coordinateur, export de la fiche de suivi, tableaux de bord.
6. Matériel/stock, incidents, suivi du déploiement (tirage, soudure).

## Méthode de travail (comme sur ETAM)
- **Petites tranches verticales** : une branche = un sujet visible (ex. « écran de connexion », « liste des plaques »). Une PR vise moins de 400 lignes hors tests et fichiers générés ; au-delà, on découpe.
- **Chaque fusion fait apparaître une fonctionnalité** utilisable, avec son API, son écran et ses tests. Pas de lot livré d'un bloc.
- Branches `feat/…`, `fix/…`, `refactor/…`, `chore/…` ; commits en Conventional Commits ; fusion en **squash** vers `main`.
- **Pas de CI** : `./scripts/check.sh` avant chaque push, résultat dans la PR.
- Tests écrits avec le code (permissions par rôle et périmètre pour toute nouvelle route).
- Fin de tranche : résumé de ce qui est visible, de ce qui reste, des hypothèses à valider.

## Navigation par activité (décision du 30/10/2026)
Le menu est regroupé par activité du métier, pas par table (`frontend/src/lib/navigation.ts`, `NAV_GROUPS`). Ce regroupement est une décision de présentation : il ne change le contenu d'aucun lot.
- **Projet** (lot 1B) : projets par plaque et leurs phases (l'avant-projet n'en est qu'une), documents versionnés, AVP et devis, circuit de validation (BE, chef du BE, DT).
- **Déploiement** : Plaques (l'arbre plaque → sous-plaques → SRO → splitters → PCO ; l'ancien écran « Réseau », une plaque se crée avec son projet), Sous-plaques, SRO, Splitters, PCO, OLT (superviseur, DT, DG, administrateur). L'import du réseau se fait depuis la fiche d'un projet.
- **Études** (lot 3) : clients à étudier, formulaire d'étude, résultats (coordinateur, techniciens habilités « étude »).
- **Raccordement** (lots 2 et 3) : clients et commandes, programmation et résultat de l'installation, attribution de la box (SN ONT et numéro fixe) (coordinateur, techniciens habilités « raccordement client »).
- **Suivi Rac** (lot 5) : fiche de suivi du raccordement, état de ligne opérateur, export Excel (coordinateur, DT, DG).
- **Administration** : Équipes, Utilisateurs.
- Maintenance : pas d'écran avant le lot 7 ; à ranger quand elle existera (activité propre à confirmer).
- Chaque activité a une page d'accueil qui liste ses écrans ; une activité sans écran accessible au rôle n'apparaît pas ; le menu n'affiche un écran qu'une fois livré. Téléphone : barre du bas = Accueil + activités (4 au plus), le reste et le menu complet dans « Plus ».

## Points ouverts (ne pas trancher seul)
Sens des deux colonnes « État ligne », du préfixe `*` et du « F » des codes ; statuts normalisés du PCO et motifs de blocage ; fichier de déploiement remplacé ou seulement importé ; types de poteaux comptés dans la distance par défaut ; valeurs de l'état de ligne opérateur ; photos/signature obligatoires.
