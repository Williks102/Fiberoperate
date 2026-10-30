# Parcours d'un projet de déploiement et gestion dans l'application

Document de cadrage du 30/10/2026, issu des explications du client. Il décrit le parcours réel, ce que l'application doit en gérer, et ce qui reste à confirmer. Le cahier des charges (v1.8) et `CLAUDE.md` en reprennent l'essentiel.

Vocabulaire : **BE** = bureau d'études ; **AVP** = avant-projet (dossier avec devis) ; **KMZ** = fichier de plan lisible dans Google Earth ; **survey** = relevé de terrain ; **DT** = directeur technique.

## 1. Le parcours
Un **projet** porte sur **une plaque**, subdivisée en sous-plaques. Chaîne réseau (v1.9) : projet → plaque → sous-plaque → **SRO** → splitter → PCO → client. L'**OLT** est la source du signal et alimente toute la plaque ; la **SRO** part de l'OLT et alimente les splitters d'**une** sous-plaque.

| # | Phase | Qui | Ce qui est produit |
|---|---|---|---|
| 1 | **Avant-projet** : le BE dimensionne la plaque (combien de sous-plaques, de splitters, de PCO) | BE (dessinateurs, techniciens réseau) | Plan des plaques, plan synoptique, plan KMZ, AVP (devis) |
| 2 | **Validation du chef du BE**, puis **validation du DT** (l'un après l'autre) | Chef du BE, DT | Dossier validé en interne |
| 3 | **Validation du client** (l'opérateur) | Client (sans compte dans l'application) | Accord, avec son justificatif |
| 4 | **Survey d'implantation** : à partir du KMZ, situer les implantations de poteaux, splitters et PCO | Technicien du BE en charge, avec un superviseur | Implantations repérées |
| 5 | **Déploiement** : implantation des poteaux, tirage dans les chambres, pose et mise en réseau des PCO | Superviseur et son équipe | Réseau posé |
| 6 | **Relevé final** : survey après déploiement pour prendre les coordonnées exactes | BE | Coordonnées mesurées |
| 7 | **Exploitation** | | Réseau en service |

Ensuite, quand le **client envoie la liste des personnes à raccorder** : les techniciens du BE font l'**étude avant raccordement**, la transmettent au coordinateur, qui planifie l'**installation du client** et, au besoin, la **pose de poteaux métalliques**. Le DT supervise l'ensemble.

## 2. Ce que l'application doit gérer
### Acteurs et rôles
- **Chef du BE** : nouveau rôle. Il supervise les activités du BE, valide le dossier d'avant-projet avant le DT.
- **Techniciens du BE** : dessinateurs ou techniciens réseau. Ce sont des **techniciens avec habilitations** (nouvelles habilitations proposées : `drawing` pour le dessin et l'AVP, `survey` pour les relevés ; `study` existe déjà pour l'étude avant raccordement).
- Hiérarchie (confirmée le 30/10/2026) : technicien du BE → chef du BE → DT. Le coordinateur reçoit les résultats d'étude (relation fonctionnelle, pas hiérarchique).
- Le **client n'a pas de compte** : l'application enregistre sa décision, sa date et le justificatif (fichier).

### Projet et phases
- Un **projet par plaque**, avec une phase courante parmi : avant-projet, validation du chef du BE, validation du DT, validation du client, survey d'implantation, déploiement, relevé final, exploitation.
- Chaque changement de phase est **historisé** (qui, quand, décision, motif), comme pour les statuts de PCO.
- Un refus (chef du BE, DT ou client) **renvoie le projet en avant-projet avec un motif obligatoire** (confirmé le 30/10/2026).

### Documents
- Plan des plaques, plan synoptique, KMZ, AVP : des **documents versionnés**. Chaque version est conservée, jamais écrasée ni supprimée (qui, quand, commentaire, taille).
- Stockage des fichiers : à choisir (voir questions).

### AVP et devis
- Le **devis de l'AVP est saisi dans l'application** (décision du client). Il reste un devis de projet : l'application ne facture rien et ne gère pas la paie.
- Le nombre de poteaux est mentionné dans l'AVP ; les **poteaux ne sont pas suivis un par un**.

### Réseau : prévu et réel
- **La plaque naît avec le projet**, créé par le **chef du BE ou l'administrateur** : on saisit son nom, on choisit ou on crée son OLT, puis on définit ses sous-plaques. Une plaque n'existe que sous un projet ; une plaque existante ne peut être importée que sous un projet (import CSV plaque / sous-plaque / splitter / PCO possible dès la création du projet).
- **Sous-plaque générée** : on choisit le nombre de PCO (libre ; 32 et 64 suggérés) ; la SRO, les splitters (`SPL001…`, 8 PCO chacun) et les PCO (`{CODE}_PCO001…`) sont créés automatiquement. Le **technicien du BE ajuste** ensuite selon son plan.
- **Saturation** : un PCO accueille 8 clients (16 ports physiques) ; au 9ᵉ client, on crée un autre PCO (désaturation, lot 3). Standards 8 splitters par SRO et 8 PCO par splitter : valeurs par défaut, jamais bloquantes.
- Le dimensionnement du BE **est le réseau lui-même** : sous-plaques, SRO, splitters et PCO sont créés à l'avant-projet (même import CSV, mais sous le projet). Tant que le projet n'a pas atteint le déploiement, ils sont « prévus » (c'est la **phase du projet** qui le dit, sans nouveau statut de PCO).
- Les statuts de PCO restent ceux du cahier : à tirer, tiré, soudure faite, activé. L'implantation, la pose et la mise en réseau s'y rattachent.
- Chaque PCO a des **coordonnées prévues** (survey d'implantation) et des **coordonnées mesurées** (relevé final), avec la date et l'auteur du relevé.
- Un tableau compare le **prévu** (dimensionnement) et le **réel** (ce qui existe) par sous-plaque.

### Mise sous tension (précisé le 30/10/2026)
Après la pose des splitters puis des PCO, le déploiement se termine par la **mise sous tension à l'OLT**, faite par les **techniciens** et qui concerne la **sous-plaque entière**. Elle est marquée « OK » avec la **prise des puissances**. *À confirmer : les puissances relevées doivent-elles être enregistrées dans l'application (valeurs, date, auteur) ? Et à quel niveau (sous-plaque, splitter, PCO) ?*

### Types de travail nouveaux
`implantation_survey` (survey d'implantation), `as_built_survey` (relevé final), `metal_pole_installation` (pose de poteaux métalliques demandée après l'étude avant raccordement).

### Menu
Activités : **Projet** (le BE y prépare l'avant-projet ; l'avant-projet est une **phase** du projet), **Déploiement**, **Études** (avant raccordement), **Raccordement**, **Suivi Rac**, **Administration**.

## 3. Ordre de réalisation
Décision du client : **l'avant-projet passe avant l'import des clients**, qui ne se fait que lorsque la structure de l'activité est maîtrisée.

Nouveau **lot 1B — Projet de déploiement et bureau d'études**, en petites tranches visibles :
1. Rôle chef du BE et habilitations `drawing` et `survey` (formulaire utilisateur).
2. Projet par plaque : liste, fiche, phase courante et historique *(fait)*.
   - **2c. Niveau SRO** (v1.9) : table, API, écrans Réseau et splitters.
   - **2d. Création guidée du projet** : nom de la plaque, OLT choisi ou créé, sous-plaques, en une opération.
   - **2e. Génération d'une sous-plaque** (32 / 64 / libre).
   - **2f. Import CSV sous un projet** (colonne SRO) ; retrait de l'import global et de la création de plaque isolée.
   - **2g. Droits du BE** sur le réseau d'un projet.
3. Documents versionnés (après le choix du stockage).
4. AVP et devis.
5. Circuit de validation : chef du BE, puis DT, puis client, avec refus motivé.
6. Coordonnées prévues et mesurées sur les PCO, avec le **formulaire PCO** (qui était la dernière PR du lot 1).
7. Comparaison prévu et réel par sous-plaque.

## 4. Questions encore ouvertes
Confirmé le 30/10/2026 (donc retiré de cette liste) : hiérarchie des techniciens du BE, retour en avant-projet en cas de refus, « prévu » porté par la phase du projet.
1. **Stockage des fichiers** : un KMZ est petit (12 Ko dans l'exemple) ; reste la **taille d'un DWG** et le service à utiliser (Cloudinary est prévu pour les photos, mais limité en taille pour les fichiers). Exemples analysés : `docs/fichiers-exemples/livrables-projet.md`.
2. **Structure du devis** : *répondu par l'exemple du 31/10* — des lignes d'un **catalogue de prix de l'opérateur** (référence, désignation, unité, prix unitaire) avec une quantité d'étude, puis une quantité réalisée et l'écart. À confirmer : le catalogue est-il commun à tous les projets ?
3. **Visibilité** : le BE voit-il tous les projets ? Le superviseur ne voit-il que celui de sa plaque ?
4. **Qui lance chaque phase** — décidé le 31/10 : le BE (ou l'administrateur) envoie l'avant-projet en validation ; le chef du BE valide, puis le DT ; **le DT enregistre l'accord du client** (le client n'a pas de compte) et **le BE est notifié** ; un refus renvoie en avant-projet avec un motif. **Le BE fait ensuite avancer le projet** : survey d'implantation → déploiement → relevé final → exploitation. Les personnes concernées sont prévenues par la cloche de l'application (chef du BE, DT, auteur de l'envoi, superviseur au lancement du déploiement). *Reste à décider* : un dossier (plans, devis) exigé avant l'envoi en validation, quand les documents seront dans l'application ; notification par e-mail.
5. **Correspondance exacte** entre « implanté / posé / mis en réseau » et les statuts de PCO du cahier.
