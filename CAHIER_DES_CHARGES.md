# Cahier des charges — Application de gestion d'une entreprise d'installation de fibre optique (FTTH)

Version 1.9 — 30/10/2026

**Changements v1.9** : la structure du réseau est précisée par le client (schéma du 30/10) : **Projet → Plaque → Sous-plaque → SRO → Splitter → PCO**. La **SRO** devient un niveau à part (une par sous-plaque), ce qui remplace la décision de la v1.6 « hub et SRO sont deux noms de la sous-plaque » (§3.1). Une **plaque n'existe que sous un projet** : elle naît avec lui, par un parcours guidé (nom, OLT choisi ou créé, sous-plaques) ou par un **import CSV rattaché au projet** (§3.7, §6.1). **Dimensionnement standard** (8 splitters par SRO, 8 PCO par splitter, 16 ports et 8 clients par PCO) et **génération automatique** des splitters et des PCO à la création d'une sous-plaque (§3.8). Un PCO est saturé à 8 clients : au 9ᵉ, on le **désature** en créant un autre PCO.

**Changements v1.8** : le parcours réel d'un projet de déploiement est intégré : phase d'avant-projet menée par le bureau d'études (plans, KMZ, AVP avec devis), validations en chaîne (chef du BE, DT, client), surveys d'implantation et final, documents versionnés (§1.2, §3.7, `docs/workflow-projet-deploiement.md`) ; nouveaux rôle (chef du BE) et habilitations ; l'avant-projet passe avant l'import des clients.

**Changements v1.7** : un superviseur couvre une plaque entière ; les cas spécifiques de Divo sont mis de côté, la structure du fichier de déploiement est la seule référence de l'architecture.

**Changements v1.6** : la structure du fichier de déploiement devient l'architecture de référence pour tous les sites, Divo compris : OLT → plaque → sous-plaque (zone) → splitter (JDV) → PCO. Le « nœud d'accès » séparé disparaît : hub et SRO sont deux noms de la sous-plaque selon le fabricant.

**Changements v1.5** : JDV = splitter ; chaîne OLT → plaque → zone → splitter → PCO observée dans le fichier de suivi de déploiement Nokia ; statuts réels du PCO et blocages (§3.6) ; import du suivi de déploiement (§6.1).

**Changements v1.4** : sens de « Sans PCO » / « Avec PCO » corrigé (§3.4) ; plus de limite de 8 × 8 pour les JDV et les PCO (§3.1) ; « D » = division.

**Changements v1.3** : hiérarchie réseau confirmée (zone SRO ⊃ JDV ⊃ PCO ; hub ou SRO selon le fabricant) ; poteaux existants et à installer, béton pour le transport et métalliques pour le raccordement client (§3.1, §5.1).

**Changements v1.2** : JDV, HUB et PCO précisés (§3.1) ; règle de la distance par nombre de poteaux (§5.1) ; fichier d'études confirmé comme liste de l'opérateur.

**Changements v1.1 depuis la v1.0** : flux réel de sous-traitance intégré (§1.1) ; décision « option B » (pas d'étape de recette, champ observation) ; formulaires d'étude et d'installation (§5) ; règles d'import fondées sur le fichier réel (§6) ; vocabulaire PCO/HUB repris de la fiche de suivi ; import et export passés dans le MVP.

## 1. Contexte et périmètre

L'entreprise est **sous-traitante d'un opérateur**. Elle déploie le réseau FTTH (tirages, soudures de PCO), raccorde les clients finaux et assure la maintenance.

**Dans le périmètre** : planification, études, suivi terrain, reporting, matériel, incidents, tableaux de bord, import de la liste des clients, export de la fiche de suivi.

**Hors périmètre (v1)** : facturation et paie (le **devis de l'AVP** est saisi, §3.7, mais rien n'est facturé) et rémunération des techniciens ; intégration technique avec la plateforme de l'opérateur.

**Contraintes** : réseau mobile instable → **mode hors-ligne obligatoire** pour les techniciens. Volumes variables selon les chantiers → listes paginées, traitements lourds en file d'attente.

### 1.1 Flux de travail réel

1. L'opérateur envoie au **coordinateur** la liste des clients à raccorder (fichier Excel).
2. Le coordinateur **importe** la liste dans l'application : une commande client est créée par ligne, après contrôle de la qualité des données (§6).
3. Il met les clients **en étude** et affecte des techniciens.
4. Les résultats d'étude sont saisis (formulaire terrain ou par le coordinateur) : PCO de rattachement, distance PCO–client, besoin de poteaux béton/métalliques, client présent ou absent, problèmes, commentaires.
5. Le coordinateur **programme l'installation** (équipe, date).
6. Après l'installation, le résultat est renseigné **sur la plateforme de l'opérateur et sur celle de l'entreprise** (double saisie aujourd'hui).

En v1, l'application n'est pas connectée à la plateforme de l'opérateur. Elle produit un **export au format de la fiche de suivi** pour faciliter le report, et conserve pour chaque commande l'indicateur « reporté sur la plateforme opérateur » (oui/non + date) afin d'éviter les oublis.

### 1.2 Parcours d'un projet de déploiement (v1.8)

Un projet porte sur **une plaque**. Le **bureau d'études (BE)** dimensionne la plaque (sous-plaques, splitters, PCO) et produit le plan des plaques, le plan synoptique, le plan KMZ et l'AVP (devis). Le chef du BE, puis le DT, valident ; le client (l'opérateur) valide à son tour. Le technicien du BE en charge fait ensuite le **survey d'implantation** avec un superviseur. Le superviseur et son équipe déploient (poteaux, tirage dans les chambres, pose et mise en réseau des PCO). Le BE fait un **relevé final** pour les coordonnées exactes. Quand le client envoie la liste des personnes à raccorder, les techniciens du BE font l'étude avant raccordement et la transmettent au coordinateur, qui planifie l'installation et la pose de poteaux métalliques. Détail : `docs/workflow-projet-deploiement.md`.

## 2. Rôles et hiérarchie

| Rôle | Description |
|---|---|
| Admin | Gère utilisateurs, rôles, référentiels (motifs, types de mission, matériel), paramètres |
| DG | Lecture seule globale, tableaux de bord |
| DT (Directeur technique) | Supervise toutes les activités ; encadre les superviseurs ; valide plannings et clôtures de zone |
| Superviseur | Supervise les travaux de déploiement d'une plaque entière (toutes ses sous-plaques) ; encadre les techniciens déploiement |
| Coordinateur | Reçoit et importe la liste des clients, pilote études et installations, supervise les techniciens raccordement/maintenance/études, fait le reporting |
| Chef du BE | Supervise les activités du bureau d'études ; valide le dossier d'avant-projet avant le DT (§3.7) |
| Technicien | Un seul rôle, avec des **types de mission** (voir 2.1). Au BE : dessinateurs ou techniciens réseau |

Chaîne hiérarchique :
- Technicien déploiement → Superviseur → DT
- Technicien raccordement / maintenance → Coordinateur → DT
- Technicien du BE (dessin, survey, étude avant raccordement) → Chef du BE → DT ; le coordinateur reçoit les résultats d'étude (relation fonctionnelle, confirmé le 30/10/2026)

### 2.1 Technicien et missions

Un technicien est un utilisateur de rôle `technicien` ayant une ou plusieurs **habilitations de mission** : `deploiement`, `raccordement_client`, `maintenance`, `etude`. Un planning ne peut affecter un technicien qu'à une mission pour laquelle il est habilité. Les techniciens travaillent en **équipes** (chef d'équipe + membres).

### 2.2 Matrice de permissions (résumé)

| Action | Admin | DG | DT | Superv. | Coord. | Tech. |
|---|---|---|---|---|---|---|
| Gérer utilisateurs/référentiels | ✔ | | | | | |
| Voir tableaux de bord globaux | ✔ | ✔ | ✔ | plaque | périmètre | |
| Importer la liste des clients | ✔ | | ✔ | | ✔ | |
| Corriger/compléter une commande client | ✔ | | ✔ | | ✔ | |
| Créer/modifier planning | ✔ | | ✔ | déploiement (sa plaque) | études, raccord., maint. | |
| Exécuter une intervention (étude, installation…) | | | | | | ✔ (les siennes) |
| Saisir un résultat à la place du technicien | | | | | ✔ | |
| Valider un rapport | | | ✔ | ✔ (déploiement) | ✔ (autres) | |
| Saisir le reporting journalier | | | | | ✔ | |
| Exporter la fiche de suivi | ✔ | | ✔ | | ✔ | |
| Gérer le stock / sorties matériel | ✔ | | ✔ | | ✔ (demandes) | |
| Déclarer un incident | ✔ | | ✔ | ✔ | ✔ | ✔ |

Les périmètres limitent la visibilité : la **plaque** pour le superviseur, les équipes (et leurs zones) pour le coordinateur.

## 3. Modèle métier et cycles de vie

### 3.1 Hiérarchie réseau

Hiérarchie précisée le 30/10 (schéma du client), qui remplace celle de la v1.7 :

- **Projet** : porte sur **une plaque** et la fait naître (§3.7).
- **OLT** : **source du signal**, il alimente toute la plaque. On le choisit ou on le crée à la création du projet ; il peut aussi être renseigné plus tard.
- **Plaque** : grande zone géographique (par ex. FAYA, ARCADE, ATTOBAN). Un **superviseur couvre une plaque entière**. Une plaque n'existe que sous un projet.
- **Sous-plaque, ou zone** : subdivision d'une plaque, avec un code (`FAY03` pour FAYA 03, `ABB01`…). C'est l'unité de suivi du déploiement. Le mot « zone » du brief initial désigne la sous-plaque.
- **SRO** (sous-répartiteur optique) : **une par sous-plaque**. Elle **part de l'OLT** et **alimente les splitters d'une sous-plaque précise** : c'est le maillon entre la source du signal (l'OLT, commun à la plaque) et les splitters de sa sous-plaque. Le slot, le port et le brin de fibre de départ de l'OLT sont donc portés par la SRO. Son nom dépend du fabricant : « hub » chez Huawei, « SRO » chez Nokia ; l'application stocke le fabricant et affiche le libellé correspondant. *Le rôle de la SRO est confirmé (30/10). Restent à préciser son code ou nom, son GPS et le détail du raccordement à l'OLT (§9).*
- **JDV** (aussi appelé **splitter**, convention de nommage `SPL001`, `SPL002`…) : raccordé à la SRO, regroupe des PCO.
- **PCO** (point de concentration optique, par ex. `FAY03_PCO001`) = **PBO**, le « boîtier ». Un client est rattaché à un PCO après l'étude.
- **Chaîne complète** : Projet → plaque → sous-plaque → SRO → JDV (splitter) → PCO → client.

**Nombres standard, jamais bloquants** : voir §3.8. Dans les fichiers réels, le nombre de splitters et de PCO varie d'une zone à l'autre (1 à 16 PCO par splitter, 8 pour la majorité). Le code d'un PCO comporte parfois un « D » suivi d'un numéro (D01 à D04) : il désigne la **division** ; il est conservé tel quel dans l'adresse brute et n'est pas exploité pour l'instant.

### 3.2 Statuts d'une zone (sous-plaque)
`planifiée → en déploiement → en exploitation → clôturée` (clôture validée par le DT).

### 3.3 Statuts d'un PCO (cycle infrastructure)
`à tirer → tiré → raccordé (soudure du PCO) → activé`

- « Raccordé » = **soudure du PCO terminée** (et non client branché).
- **Décision : pas d'étape de recette** (option B). Les mesures éventuelles sont jointes comme pièces et un champ **Observation** (texte libre) est disponible sur chaque changement de statut et sur chaque intervention. L'étape « recetté » pourra être ajoutée plus tard par paramètre.
- Chaque changement de statut est historisé (qui, quand, depuis quel appareil).

### 3.4 Statuts d'une commande client (cycle service)
`reçue → en étude → étudiée (faisable / non faisable / client absent) → installation programmée → installé | reporté | échec → activé`

- Un **report** ou un **échec** exige un motif du référentiel (client absent, accès impossible, câble ou poteau manquant, PCO saturé, etc.) et crée si besoin une nouvelle planification.
- **Étude technique initiale** (colonne de la fiche), vocabulaire de l'opérateur à ne pas inverser : **« Sans PCO » = étude réalisée, le client est raccordable** ; **« Avec PCO » = étude à réaliser**. L'application enregistre le libellé d'origine et un état normalisé (étude à réaliser / étude réalisée – raccordable), affiche des libellés clairs et réutilise les libellés de l'opérateur dans l'export. Quand la colonne est présente à l'import, « Sans PCO » place directement la commande à « étudiée (faisable) » et « Avec PCO » à « reçue » (étude à planifier).
- Le PCO de rattachement est saisi ou confirmé lors de l'étude.
- L'**état de ligne côté opérateur** (par ex. « En cours de raccordement ») est stocké à part, avec sa date, car il vient de la plateforme de l'opérateur et peut différer du statut interne.

### 3.5 Types d'intervention
`tirage`, `soudure_pco`, `raccordement_client`, `etude`, `maintenance`.

### 3.7 Projet de déploiement (v1.8)

Un **projet** = une plaque. Phases, dans l'ordre : avant-projet, validation du chef du BE, validation du DT, validation du client, survey d'implantation, déploiement, relevé final, exploitation.

- Chaque changement de phase est historisé (qui, quand, décision, motif). Un refus renvoie le projet en avant-projet avec un motif obligatoire (confirmé le 30/10/2026).
- **Documents versionnés** (plan des plaques, plan synoptique, KMZ, AVP) : chaque version est conservée, jamais écrasée.
- Le **devis de l'AVP** est saisi dans l'application (sans facturation) ; le nombre de poteaux y figure, les poteaux ne sont pas suivis un par un.
- Le client n'a pas de compte : sa décision, sa date et son justificatif sont enregistrés.
- Le dimensionnement est le réseau lui-même (sous-plaques, SRO, splitters, PCO créés à l'avant-projet) ; la phase du projet indique s'il est « prévu » ou posé. Chaque PCO a des coordonnées prévues et des coordonnées mesurées au relevé final, **saisies par les techniciens du BE** (mission « Relevés »), qui voient le réseau.
- **Création d'un projet (v1.9)** : la plaque naît avec le projet. Deux façons de décrire le réseau : (1) un **parcours guidé** : nom de la plaque, OLT (choisi parmi les existants ou créé), puis les sous-plaques (§3.8) ; (2) l'**import d'un fichier CSV** (plaque, sous-plaque, splitter, PCO…) rattaché au projet (§6.1). Un projet = une plaque : un fichier qui en contient plusieurs, ou dont la plaque ne correspond pas à celle du projet, est refusé *(à confirmer)*. Tout est créé en une seule opération (tout ou rien).
- **Mise sous tension** : à l'OLT, par les **techniciens**, pour la **sous-plaque entière** ; elle est marquée « OK » avec la prise des puissances *(enregistrement des puissances à confirmer)*.
- Nouveaux types de travail : survey d'implantation, relevé final, pose de poteaux métalliques.

### 3.6 Suivi de déploiement (fichier « Nouveaux OLT Nokia »)

Le fichier de suivi reçu (5 feuilles de données et 1 feuille vide, 2 681 lignes de PCO, 7 OLT, 56 sous-plaques, 347 splitters) montre la structure réelle et ses variations. Il concerne surtout des sites d'immeubles (autorisations de syndic) ; l'application ne doit pas supposer ce contexte pour tous les sites.

**Structure d'une ligne** : **OLT → plaque (une feuille par plaque) → sous-plaque (code de plaque, par ex. `FAY03`) → splitter (JDV) → PCO**, avec latitude et longitude du PCO, **slot, port et brin de fibre** (raccordement à l'OLT), statut du PCO et commentaire.

**Constats** :
- Volumes variables : de 1 à 16 PCO par splitter (8 pour 231 des 347 splitters) et de 1 à 11 splitters par sous-plaque. Cela confirme qu'aucun nombre ne doit être fixé.
- Données peu renseignées : GPS pour 31 PCO sur 2 681, slot/port/brin pour environ 78, statut vide sur 1 900 lignes.
- Codes incohérents : `ACA03_PCO-001`, `ACA003`, `FAY03_PCO001` (tiret ou non, un ou deux zéros) ; 69 codes de PCO apparaissent plusieurs fois.
- Statuts saisis en texte libre, avec de nombreuses variantes d'écriture.

**Statuts observés et correspondance proposée (à valider)** :

| Libellé dans le fichier | Statut normalisé proposé |
|---|---|
| En attente, En attente BE | `waiting` (attente du bureau d'études ou d'une autorisation) |
| Travaux non (encore) réalisés | `to_pull` |
| Travaux en cours, En cours, Câblage immeuble en cours | `in_progress` |
| Tirage NOK, Tirage non ok | `pull_failed` (à reprendre) |
| Posé, PCO posé (mais pas en service) | `installed` |
| En service | `activated` |
| Bloqué, Blocage syndic… | indicateur **bloqué** + motif (voir ci-dessous) |

Le **blocage** est un indicateur indépendant du statut, avec un motif du référentiel. Motifs relevés : blocage du syndic (courrier déposé ou non), pas d'accès à la cité, autorisation de câblage NOK, besoin de jarretière pour l'OLT. Le libellé d'origine est toujours conservé.

### 3.8 Dimensionnement standard et génération d'une sous-plaque (v1.9)

**Valeurs standard**, proposées par défaut et modifiables par projet, **jamais bloquantes** (la réalité s'en écarte) :

| Niveau | Standard |
|---|---|
| Sous-plaque | 1 SRO |
| SRO | 8 splitters |
| Splitter | 8 PCO |
| PCO | 16 ports ; **8 clients raccordés au plus** |

Une sous-plaque standard de 64 PCO compte donc 8 splitters et 512 clients raccordés ; de 32 PCO, 4 splitters et 256 clients.

**Saturation et désaturation** : un PCO est **saturé à 8 clients**. Au **9ᵉ client**, on fait une **désaturation** : on crée un autre PCO (rattachement du nouveau PCO : voir §9). Les 16 ports sont la capacité physique, pas le seuil.

**Génération à la création d'une sous-plaque** : on saisit le code de la sous-plaque (par ex. `ABB01`) et le **nombre de PCO**, libre ; la liste **suggère 32 et 64** (modifiables). L'application crée alors automatiquement :
- la **SRO** de la sous-plaque ;
- les **splitters** (`SPL001`, `SPL002`…), à raison de 8 PCO chacun (le dernier peut en compter moins) ;
- les **PCO**, nommés `{code de la sous-plaque}_PCO001`… en continu dans la sous-plaque : le splitter 1 reçoit les PCO 001 à 008, le splitter 2 les PCO 009 à 016, etc. ; statut de départ « À tirer ».

La génération est **atomique** (tout ou rien) et **refusée si la sous-plaque contient déjà** des splitters ou des PCO, pour ne jamais écraser un travail existant. Le **technicien du BE ajuste ensuite** selon son plan (ajout, retrait, renommage, déplacement d'un PCO entre splitters de la même sous-plaque).

## 4. Modules fonctionnels

1. **Authentification et utilisateurs** : rôles, habilitations, équipes, zones affectées, journal d'audit.
2. **Référentiels** : OLT, plaques, sous-plaques (zones), JDV, PCO, types de mission, motifs de report/échec, matériel, motifs d'incident.
3. **Import de la liste des clients** (§6) : contrôle qualité, écran de correction, validation.
4. **Planning** : vue calendrier/liste/carte par type d'intervention, technicien, équipe, zone ; détection de conflits ; reprogrammation avec motif.
5. **Études** : mise en étude, formulaire de résultat (§5.1), faisabilité.
6. **Exécution terrain (PWA technicien)** : interventions du jour, démarrage/clôture, formulaires (§5), photos géolocalisées, signature client, incident ; **fonctionne hors-ligne**.
7. **Suivi des zones et des PCO** : avancement par statut, carte, historique.
8. **Reporting du coordinateur** : clients raccordés / reportés (avec motifs), études réalisées ou non, matériel utilisé, incidents ; rapport journalier validé ; **export de la fiche de suivi**.
9. **Matériel et stock** : magasins, sorties par technicien/équipe, consommation par intervention, outillage.
10. **Incidents** : chantier, réseau, HSE ; gravité, statut, résolution.
11. **Tableaux de bord** (DG, DT, superviseur, coordinateur) : voir §7.
12. **Notifications** : planning modifié, rapport à valider, incident grave.
13. **Projet de déploiement et bureau d'études** (v1.8, précisé en v1.9) : création du projet (la plaque naît avec lui, parcours guidé ou import CSV), sous-plaques générées au standard, phases, documents versionnés, AVP et devis, circuit de validation (chef du BE, DT, client), surveys d'implantation et final, comparaison prévu et réel.

## 5. Formulaires terrain

Ils reprennent les colonnes de la fiche de planning et de suivi actuelle.

### 5.1 Formulaire d'étude
- Client présent / absent / injoignable ; adresse confirmée ; **GPS terrain** (capture automatique, corrigeable sur carte)
- **PCO de rattachement** (liste filtrée par zone) ; le JDV, la sous-plaque et la plaque s'en déduisent
- **Poteaux** : pour chaque type, le nombre de poteaux **existants** et **à installer**. Les poteaux **béton** servent au transport, les poteaux **métalliques** au raccordement du client.
- **Distance PCO–client** : déterminée lors de l'étude, par le nombre de poteaux du trajet (existants et à installer), avec **50 m entre deux poteaux**. Le champ est prérempli (nombre de poteaux × 50 m) et reste modifiable par le technicien. Les types de poteaux à compter par défaut (béton, métalliques ou les deux) sont à confirmer (§9). Les distances de la fiche actuelle (100 à 350 m) sont toutes des multiples de 50 m, ce qui est cohérent.
- **JDV et plaque** : déduits du PCO choisi ; la colonne « JDV sous plaque » de la fiche de suivi est alimentée automatiquement.
- Faisable / non faisable + motif ; problème rencontré
- Commentaires terrain, observation, photos

### 5.2 Formulaire d'installation
- Résultat : installé / reporté / échec + motif ; date et heure ; équipe
- **N° fixe FTTH**, **numéro de série ONT**, PCO et port utilisés
- Matériel utilisé (métrage de câble, connecteurs…) ; mesures optiques facultatives
- Photos avant/après, signature du client
- Observation ; indicateur « reporté sur la plateforme opérateur » (rempli par le coordinateur)

## 6. Import de la liste des clients

Le fichier reçu (107 lignes, colonnes : référence RAB, ligne FT, nom, adresse en un seul champ, deux contacts) présente des anomalies typiques. Constats sur le fichier fourni :

| Constat | Volume |
|---|---|
| Référence RAB de 13 caractères / de 15–16 caractères | 100 / 7 |
| Ligne FT de 12 caractères / de 9 caractères | 105 / 2 |
| Doublons probables (même FT ; même nom et même adresse) | 1 FT en double ; 1 nom en double |
| Adresse en champ unique : structurée (Ville/Commune/Quartier/Rue…) / texte libre | 82 / 25 |
| Code PCO écrit dans le champ GPS | 18 |
| GPS plausible pour Divo / invalide, inversé ou hors zone / absent | 53 / 26 / 28 |
| Contact 1 préfixé par « * » | 105 |
| Numéros à 13 chiffres / 12 chiffres / 9 chiffres | 174 / 36 / 4 |
| Lignes où contact 1 = contact 2 | 38 |

**Règles d'import** :
1. Séparer l'adresse en champs (ville, commune, quartier, rue, précision) et en extraire le GPS et le code PCO s'ils s'y trouvent.
2. Valider le GPS (zone attendue, latitude/longitude inversées, séparateurs, doublons de point) ; proposer une correction sur carte quand il est invalide ou absent.
3. Normaliser les téléphones (suppression du « * », format +225 et 10 chiffres) ; signaler les numéros à longueur anormale.
4. Détecter les doublons (RAB, FT, nom + adresse) et les formats anormaux de RAB/FT, sans bloquer l'import : chaque ligne reçoit un statut **OK / à corriger / rejetée**.
5. Écran de revue avant validation : le coordinateur corrige, fusionne ou ignore, puis confirme. Rapport d'import téléchargeable.
6. Un même client réimporté met à jour sa commande (clé : RAB) sans créer de doublon.

### 6.1 Import du suivi de déploiement

**Depuis la v1.9, l'import du réseau se fait sous un projet** (il n'existe plus d'import global) : à la création du projet ou plus tard (réimport : phases autorisées à confirmer, §9). Second type d'import, pour le fichier de suivi des PCO par OLT et plaque :
1. Une feuille = une plaque ; les feuilles vides sont ignorées et signalées.
2. Normaliser les codes (tiret, zéros) avant de comparer ; signaler les codes de PCO en double sans les fusionner automatiquement.
3. Rattacher chaque ligne à son OLT, sa plaque, sa sous-plaque, son splitter (JDV) et son PCO, en créant les éléments manquants après validation du coordinateur ou du superviseur.
4. Traduire les libellés de statut avec la table de correspondance (§3.6), en conservant le libellé d'origine ; tout libellé inconnu est proposé à la correspondance, jamais ignoré.
5. Valider le GPS comme pour les clients ; ne pas exiger slot, port et brin, souvent absents.
6. Exporter ensuite le suivi dans le même format de feuilles, pour remplacer la mise à jour manuelle du fichier.

## 7. Indicateurs

- Taux de réussite au premier passage ; taux de report et principaux motifs.
- Délai de raccordement (réception de la commande → installation).
- Taux d'études faisables ; poteaux à installer par type et par zone (aide à l'approvisionnement).
- Productivité par technicien/équipe.
- Avancement par zone et par PCO.
- Commandes installées non encore reportées sur la plateforme opérateur.
- Qualité des données importées (GPS manquants, doublons).
- Consommation de matériel ; incidents par catégorie.

## 8. Modèle de données (principales tables)

- `users` (rôle, supérieur_id, actif) · `technician_skills` · `teams`, `team_members`
- `olts` (nom) · `plaques` (nom, `olt_id` facultatif, renseigné dès qu'il est connu, `supervisor_id`) · `zones` (= sous-plaques : code, `plaque_id`, statut) · (v1.9) `sros` (une par sous-plaque : `zone_id` unique, code ou nom, fabricant huawei|nokia ; autres informations à préciser) · `jdvs` (splitter : code, `sro_id` en v1.9, au lieu de `zone_id`) · `pcos` (code, numéro, `jdv_id`, `zone_id`, statut, `status_raw`, `is_blocked`, `block_reason_id`, coordonnées, `olt_slot`, `olt_port`, `fiber_strand`, commentaire, capacité en ports (16) et nombre maximal de clients (8, v1.9)) · `pco_status_history`
- `customer_orders` : réf. RAB, n° compte client, n° ligne FT, nom, contacts normalisés, adresse en champs et adresse brute d'origine, `initial_study_status` (libellé d'origine + état normalisé), GPS déclaré et GPS terrain, dates d'enregistrement et d'envoi au prestataire, `pco_id`, distance, SN ONT, `status`, `operator_line_status` et sa date, `operator_synced_at`, observation · `order_status_history`
- `interventions` (type, zone, PCO, commande, équipe/technicien, date planifiée, statut, motif, début/fin, observation)
- `studies` (intervention_id, client présent, PCO, distance (poteaux × 50 m par défaut, modifiable), poteaux béton existants / à installer, poteaux métalliques existants / à installer, faisable, problème, commentaires terrain, commentaires internes)
- `intervention_measurements` (facultatif) · `intervention_attachments` (photos, signature, PV)
- `daily_reports`, `daily_report_lines`
- `materials`, `warehouses`, `stock_movements`, `intervention_materials`, `tool_assignments`
- `incidents`
- `import_batches`, `import_rows` (statut OK/à corriger/rejetée, anomalies détectées)
- (v1.8) `projects` (plaque_id unique, phase) · `project_phase_history` (de, vers, décision, motif, utilisateur) · `project_documents` et `project_document_versions` (type, version, fichier, taille, auteur, commentaire) · `project_quotes` (devis de l'AVP, montants entiers en FCFA, structure à préciser) · sur `pcos` : coordonnées mesurées, date et auteur du relevé
- `audit_logs`, `notifications`

Toutes les tables métier : UUID, horodatage, `deleted_at`, `client_uuid` pour les créations hors-ligne. Les dates sont stockées en vraies dates (la fiche actuelle contient des numéros de série Excel).

## 9. Points restant à confirmer

Confirmé le 28/10 : le fichier d'études est bien la liste envoyée par l'opérateur ; PCO = PBO ; la plaque est une grande zone (FAYA) divisée en sous-plaques (FAYA 03 = `FAY03`) ; la structure OLT → plaque → sous-plaque → splitter (JDV) → PCO du fichier de déploiement est la seule référence de l'architecture ; un superviseur couvre une plaque entière ; JDV et PCO sans nombre fixé ; « Sans PCO » = étude réalisée (client raccordable), « Avec PCO » = étude à réaliser ; « D » = division ; la sous-plaque se dit « hub » chez Huawei et « SRO » chez Nokia ; la distance est déterminée à l'étude par le nombre de poteaux du trajet, existants et à installer (50 m entre poteaux) ; poteaux béton = transport, métalliques = raccordement client.

1. Sens exact des **deux colonnes « État ligne »**, du préfixe **« * »** devant le contact 1, et du **« F »** (F22, F77…) accolé aux codes PCO.
2. **Distance par défaut** : compter les poteaux béton, les poteaux métalliques, ou les deux ?
3. Valeurs possibles de l'**état de ligne** côté opérateur, et colonnes supplémentaires éventuelles dans le fichier reçu.
4. Un coordinateur peut-il avoir plusieurs équipes ? Photos et signature obligatoires ?
5. **Statuts du PCO** : valider la correspondance proposée en §3.6 (libellés du fichier → statuts normalisés) et la liste des motifs de blocage.
6. Le fichier de suivi de déploiement doit-il être **remplacé par l'application** (saisie par les superviseurs, export identique) ou seulement importé ?
7. **(v1.8)** Stockage des fichiers du projet (taille d'un KMZ ou d'un plan, service) ; structure du devis de l'AVP (un exemple sera fourni) ; visibilité des projets par rôle ; qui lance chaque phase ; correspondance entre implanté / posé / mis en réseau et les statuts de PCO. Détail : `docs/workflow-projet-deploiement.md`.

Confirmé le 30/10 (v1.9) : Projet → plaque → sous-plaque → SRO → splitter → PCO ; la plaque naît avec le projet (et n'existe que sous un projet) ; import CSV sous un projet ; standard 8 splitters par SRO, 8 PCO par splitter, 16 ports et 8 clients par PCO (valeurs par défaut modifiables) ; saturation à 8 clients puis désaturation par un autre PCO ; génération à la création d'une sous-plaque (nombre de PCO libre, 32 et 64 suggérés) ; convention `SPL` pour les splitters ; le technicien du BE ajuste le réseau généré ; les techniciens du BE saisissent les relevés et voient le réseau ; mise sous tension par les techniciens, sur la sous-plaque entière.

8. **(v1.9) SRO** : rôle confirmé le 30/10 (part de l'OLT, alimente les splitters d'une sous-plaque). À préciser : code ou nom (et nom donné à la SRO créée automatiquement), GPS, fabricant, et si le raccordement à l'OLT (slot, port, brin) est saisi à la SRO ou aux splitters.
9. **(v1.9) Fichier « Suivi raccordement »** : à quel niveau correspondent « ZONE PCO (SRO, OLT…) » (`DBA01`) et « JDV/HUB » (`HUB001` à `HUB004`, plusieurs par zone) ?
10. **(v1.9) Création d'un projet** : qui peut le créer (chef du BE, DT, administrateur) ; nom de la plaque (saisi ou pris dans le fichier) ; un seul projet par fichier ; réimport sous un même projet (à quelles phases) ; droits du BE sur la création de la plaque, de l'OLT et des sous-plaques.
11. **(v1.9) Modification du réseau par le BE** : jusqu'à quelle phase le technicien du BE peut-il ajuster le réseau (avant-projet, survey d'implantation) ; après, corrections par le DT ou l'administrateur ?
12. **(v1.9) Données existantes** : y a-t-il des plaques, splitters ou PCO réels à conserver au moment du changement de structure (SRO), ou seulement des essais ?
13. **(v1.9) Désaturation (lot 3)** : où se rattache le PCO créé au 9ᵉ client (splitter qui a de la place, sinon autre splitter de la même SRO) ? L'enregistrement des puissances relevées à la mise sous tension (niveau et valeurs).

## 10. Architecture technique proposée

- **API** : Laravel (REST, Sanctum, policies par rôle/périmètre), PostgreSQL (PostGIS si cartographie avancée), Redis + files d'attente (imports, exports, notifications), stockage objets pour les photos.
- **Web/PWA** : Next.js, TypeScript, carte (MapLibre/Leaflet), IndexedDB pour le hors-ligne.
- **Hors-ligne** : pré-chargement des données du jour, file d'actions locale rejouée à la reconnexion, identifiant client unique pour l'idempotence, photos compressées envoyées en arrière-plan. Le serveur fait foi pour le planning, le terrain pour l'exécution ; les conflits sont signalés, jamais écrasés.
- **Sécurité** : RBAC + périmètre, journal d'audit, sauvegardes, chiffrement en transit.
- **Exports** : Excel (fiche de suivi, même colonnes que l'existant) et PDF (rapport journalier).

## 11. Feuille de route

1. **MVP** : utilisateurs/rôles, référentiels (OLT, plaques, zones/sous-plaques, SRO, JDV, PCO), **projet de déploiement et bureau d'études (v1.8, v1.9 : création du projet avec son réseau, avant l'import des clients)**, **import de la liste clients**, études, planning, installation terrain hors-ligne, reporting coordinateur, **export de la fiche de suivi**, tableaux de bord de base.
2. **V1.1** : matériel et stock, incidents, suivi du déploiement (tirage, soudure) pour superviseurs, avec import et export du fichier de suivi par OLT et plaque.
3. **V1.2** : cartographie avancée, notifications, indicateurs avancés.
4. **V2** : API de la plateforme opérateur si elle existe ; étape de recette et facturation si besoin.
