# Lots de travail et d'implémentation

Référence : `CAHIER_DES_CHARGES.md` v1.7 et `CLAUDE.md`. Tailles relatives : S < M < L < XL (pas d'estimation en jours tant que l'équipe n'est pas connue).

## Chemin critique
Lot 0 → 1 → 2 → 3 → 4 → 5. Le lot 4 (hors-ligne) conditionne la mise sur le terrain ; démarrer les spikes (IndexedDB, file d'actions, `client_uuid`) dès le lot 3.

## MVP = lots 0 à 5
Utilisateurs/rôles, référentiels, import clients, études, planning, installation hors-ligne, reporting coordinateur, export fiche de suivi, tableaux de bord de base.

---

## Lot 0 — Fondations et outillage (S)
- **Objectif** : socle de développement reproductible.
- **Livrables** : dépôt, Docker (PHP, PostgreSQL, Redis, stockage objets type MinIO), CI (lint, tests), `.env.example`, squelette Next.js PWA, conventions (Form Requests, API Resources, pagination).
- **Acceptation** : `docker compose up` donne API + front ; CI verte.

## Lot 1 — Auth, rôles, équipes, référentiels (M) — *fichiers de départ fournis*
- **Périmètre** : Sanctum, 6 rôles, habilitations technicien, hiérarchie (technicien déploiement → superviseur → DT ; autres → coordinateur → DT), équipes, OLT, plaques, zones (sous-plaques), JDV, PCO, motifs de blocage, historique de statut PCO.
- **Dépendances** : lot 0.
- **Livrables** : migrations/modèles (fournis), policies par rôle et périmètre (plaque / équipes), API CRUD + pagination, écrans d'admin, import simple des référentiels.
- **Acceptation** : un superviseur ne voit que sa plaque ; un coordinateur que ses équipes ; un technicien non habilité n'est pas planifiable sur la mission.
- **Tests** : matrice rôle × périmètre, normalisation des codes, unicité, cycle de statut PCO.
- **Questions** : sens du « F » des codes ; coordinateur avec plusieurs équipes.

## Lot 2 — Import clients et commandes (L)
- **Périmètre** : import Excel/CSV asynchrone en 2 temps (analyse → revue → validation), lignes `ok / to_fix / rejected`, découpage d'adresse (`address_raw` conservée), GPS (inversion, séparateurs, aberrations, jamais corrigé en silence), téléphones (+225, `*` retiré), doublons RAB/FT/nom+adresse, réimport = mise à jour, dates (numéros de série Excel), étude initiale « Sans PCO / Avec PCO ».
- **Dépendances** : lot 1.
- **Acceptation** : jeu réel anonymisé importé, anomalies listées, aucun doublon sur réimport.
- **Questions** : sens des deux colonnes « État ligne » et du préfixe `*`.

## Lot 3 — Études et planning (L)
- **Périmètre** : mise en étude, intervention `study`, formulaire d'étude (poteaux par type, existants / à installer, distance préremplie poteaux × 50 m modifiable), résultat (réalisable / non / client absent), programmation de l'installation (équipe, date), report/échec avec motif, historique de statuts.
- **Dépendances** : lot 2.
- **Acceptation** : une commande suit `received → … → appointment_set` ; planification refusée si habilitation manquante.
- **Question** : types de poteaux comptés par défaut (configurable).

## Lot 4 — PWA technicien hors-ligne (XL)
- **Périmètre** : pré-chargement des interventions, IndexedDB, file d'actions locale, rejeu, endpoints idempotents sur `client_uuid`, conflits signalés, photos compressées envoyées en arrière-plan, signature.
- **Dépendances** : lot 3 (API stable).
- **Acceptation** : une journée complète en mode avion puis synchronisation sans doublon ni perte ; conflit visible, jamais écrasé.
- **Tests** : idempotence, coupures réseau, rejeu partiel.
- **Question** : photos/signature obligatoires.

## Lot 5 — Reporting coordinateur, export, tableaux de bord (L)
- **Périmètre** : saisie du résultat d'installation, `operator_line_status` + date, case `operator_synced_at`, export Excel « fiche de suivi » (mêmes colonnes et libellés opérateur), tableaux de bord de base.
- **Dépendances** : lots 3 et 4.
- **Acceptation** : export ouvert tel quel par le coordinateur ; indicateurs cohérents avec les données.
- **Question** : valeurs de l'état de ligne opérateur.

## Lot 6 — Suivi du déploiement (L) — V1.1
- **Périmètre** : import du fichier de suivi (une feuille = une plaque, feuilles vides signalées), codes normalisés, doublons signalés sans fusion, table de correspondance des statuts (libellé inconnu proposé), indicateur de blocage, export au même format de feuilles.
- **Dépendances** : lot 1.
- **Questions** : statuts normalisés et motifs de blocage ; fichier remplacé par l'application ou seulement importé.

## Lot 7 — Matériel/stock et incidents (M) — V1.1
Matériel utilisé par intervention, stock, incidents rattachés aux interventions/PCO.

## Lot 8 — Cartographie, notifications, indicateurs avancés (M) — V1.2
Carte MapLibre/Leaflet des PCO et commandes, notifications asynchrones, indicateurs avancés.

## Lot 9 — Recette, pilote, mise en production (M)
Pilote sur une plaque, formation, reprise de données, supervision, sauvegardes, journalisation sans données clients en clair.

---

## Premiers tickets (sprint 1)
1. Docker + CI + squelette Laravel/Next.js.
2. Intégrer les fichiers du lot 1 et lancer `php artisan test` (premier passage des migrations).
3. Auth Sanctum + login front.
4. Policies : superviseur (plaque) et coordinateur (équipes) avec tests.
5. API référentiels (OLT, plaques, zones, JDV, PCO) paginée.
6. Spike hors-ligne : service worker + IndexedDB + `client_uuid`.

## Hors périmètre v1
Facturation, paie, intégration technique avec la plateforme opérateur, étape de recette.
