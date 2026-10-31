# Fichier « Suivi raccordement » (canevas du 19/02)

Analyse du fichier d'exemple fourni le 30/10/2026. **Le fichier lui-même n'est pas dans le dépôt** (noms, téléphones et GPS de clients) : ce document ne décrit que sa structure. Il sert de référence au lot 2 (import des clients et commandes).

## Ce que le fichier représente
Des clients dont l'**étude est déjà réalisée** (colonne « Etude technique » = « SANS PCO », soit étude faite et client raccordable, cahier §3.4) et qui sont prêts pour l'installation. Une **box (ONT)** leur est déjà attribuée.

> Règle métier retenue (client, 30/10/2026) : **après l'étude, une box est attribuée à un client pour son installation. Elle est rattachée à un SN (numéro de série de l'ONT) et à un numéro fixe.**

Taille de l'exemple : 1 feuille, 17 clients, 27 colonnes renseignées (44 colonnes au total : 17 sans en-tête et vides).

## Colonnes
| Colonne du fichier | Contenu observé | Correspondance prévue |
|---|---|---|
| `NumeroAbonnement` | texte de 16 caractères commençant par `RAB` | ID client (ancien RAB), clé d'import (jamais 13 caractères supposés) |
| `NOMS_PRENOMS` | nom et prénoms en un champ | nom du client |
| `LOCALISATION` | texte libre court (quartier, repère) | adresse brute (`address_raw`), conservée telle quelle |
| `Contact1`, `Contact2` | **nombre** de 9 chiffres | téléphones (voir ci-dessous) |
| `Latitude client`, `Longitude client` | décimaux ; 17/17 dans la Côte d'Ivoire | GPS déclaré |
| `ZONE PCO(SRO,OLT..)` | code de sous-plaque (2 valeurs : `DBA01`, `DMI01`) | zone (sous-plaque) |
| `JDV/HUB` | `HUB001` à `HUB004`, répétés d'une zone à l'autre | **JDV ? (voir questions)** |
| `PCO` | `DBA01_PCO010`… | PCO de rattachement (déjà connu) |
| `SN ONT` | texte de 16 caractères | **numéro de série de la box** |
| `LIGNE FIXE` | **nombre** de 10 chiffres commençant par `21` | **numéro fixe de la box** |
| `Date d'enregistrement d'instances` | vraie date Excel | date d'enregistrement |
| `Durée d'attente (en jour)` | texte, vide pour 6 lignes | calculée ? (voir questions) |
| `date d'envoi des instances aux prestataires` | vraie date Excel | date d'envoi au prestataire |
| `Etude technique` | `SANS PCO ` (espace final) | étude réalisée ; libellé d'origine conservé |
| `Besoin en poteau betons` | vide | poteaux béton à installer |
| `Besoin en poteau metalliques` | 0 à 4, décimaux, souvent vide | poteaux métalliques à installer |
| `réalisation survey poteau beton ( OK ou NOK)`, `… Implantation Poteau beton …` | `RAS` (pas OK/NOK) | à conserver en texte |
| `Date previsionnelle de raccordement du client` | une seule valeur pour tout le fichier | date planifiée ? |
| `Superviseur Moov` | un nom, identique partout | superviseur de l'opérateur (texte) |
| `Distance PCO-Client` | 100 à 500, multiples de 50 | distance (cahier §5.1) |
| `Entreprise FTTH` | `ITC` partout | entreprise sous-traitante |
| `Avancement` | `En cours de raccordement` | état de ligne opérateur ? |
| `Problèmes` | `RAS` | texte libre |
| ` Commentaires` | `zone, PCO, BRIN n, LONG xxxM, n ANCIEN PB…` | texte libre contenant brin, longueur et anciens points de branchement |

## Pièges à gérer à l'import
- **Téléphones stockés comme nombres : le zéro initial est perdu.** Les 34 contacts ont 9 chiffres (`7…`, `1…`, `5…`) : ce sont des numéros ivoiriens à 10 chiffres (`07…`, `01…`, `05…`). Règle : 9 chiffres numériques, on ajoute le `0` ; on le signale dans le rapport, jamais en silence.
- **`LIGNE FIXE` est un nombre** (flottant dans Excel) : le lire comme texte de 10 chiffres sans décimale ; toujours conserver le texte d'origine.
- **Libellés avec espaces parasites** (`SANS PCO `) : couper les espaces avant de comparer, conserver le libellé d'origine.
- **Faute de saisie dans un code** : un PCO `DM01_PCO089` rattaché à la zone `DMI01`. La normalisation ne le rapproche pas de `DMI01` : à signaler (avertissement du réseau déjà prévu), pas à corriger en silence.
- **Données utiles cachées dans le commentaire** : le brin (`BRIN 7`), la longueur (`LONG 150M`) et le nombre d'anciens points de branchement. À conserver en texte ; ne pas les extraire sans validation.
- Les dates sont de vraies dates Excel ici ; d'autres fichiers peuvent contenir des numéros de série.
- 17 colonnes vides sans en-tête : à ignorer.

## Questions ouvertes (à confirmer avec le client)
1. **`JDV/HUB`** contient `HUB001`… : ce niveau correspond-il au **splitter (JDV)** de la hiérarchie OLT → plaque → sous-plaque → JDV → PCO ? Le même code `HUB001` existe dans deux zones, ce qui correspond à un JDV propre à chaque sous-plaque.
2. **`LIGNE FIXE`** est-il le même numéro que le « FT » (numéro de ligne FTTH) du premier fichier clients, ou un autre numéro ?
3. **`Avancement`** (« En cours de raccordement ») est-il l'état de ligne côté opérateur ?
4. **`Durée d'attente`** est-elle calculée (date d'envoi − date d'enregistrement) ou saisie ?
5. **`Date previsionnelle de raccordement`** est-elle la date planifiée de l'installation, ou une date de campagne ?
6. Faut-il stocker **`Superviseur Moov`** et **`Entreprise FTTH`** ? (valeurs constantes dans l'exemple)
7. À quel moment la box est-elle attribuée : lors de la clôture de l'étude, ou à la programmation de l'installation ? (impacte le formulaire d'étude, lot 3)

## Structure retenue pour la commande client (31/10/2026)
Une **commande** par abonnement, clé **ID client** (ancien RAB ; une seule commande vivante par ID client : un réimport met à jour, il ne crée pas de doublon). Tables `customer_orders` et `order_status_history`. Les valeurs d'origine sont **toujours conservées** à côté des valeurs normalisées.

| Colonne du fichier (modèle `clients.csv`) | Champ de la commande | Remarque |
|---|---|---|
| `id_client` | `customer_ref` | clé, aucune longueur supposée (la valeur commence souvent par RAB) |
| `ft`, `compte` | `ft_line`, `account_number` | facultatifs |
| `nom` | `name` | |
| `adresse` | `address_raw` | conservée telle quelle ; ville, commune, quartier, rue et précision sont découpés ensuite (`city`, `commune`, `district`, `street`, `address_detail`) |
| `contact1`, `contact2` | `phone_1`, `phone_2` (+ `_raw`) | normalisés en `+225` + 10 chiffres ; le texte d'origine est gardé ; toute correction est signalée (`PhoneNumber`) |
| `latitude`, `longitude` | `declared_latitude`, `declared_longitude` | GPS déclaré ; le GPS relevé sur le terrain est un autre champ |
| `sous_plaque`, `pco` | `zone_id`, `pco_id` | rapprochés du réseau par leur code normalisé ; un code inconnu est signalé, jamais créé |
| `sn_ont`, `ligne_fixe` | `ont_serial`, `fixed_line` | la box attribuée après l'étude ; texte, jamais un nombre |
| `date_enregistrement`, `date_envoi` | `registered_at`, `sent_to_contractor_at` | vraies dates (les numéros de série Excel sont convertis) |
| `etude_technique` | `initial_study_raw`, `initial_study` | « SANS PCO » = étude réalisée, « AVEC PCO » = étude à réaliser ; libellé inconnu proposé à la correspondance |
| `distance` | `distance_m` | |
| `etat_ligne` | `operator_line_status` (+ date) | état côté opérateur, distinct du statut interne |
| `commentaire` | `observation` | brin, longueur et anciens points de branchement restent en texte |

Statut interne (`status`) : reçue → en étude → étudiée (faisable, non faisable, client absent) → installation programmée → installée, reportée ou échec → activée ; chaque changement écrit une ligne d'historique. Une commande dont l'étude initiale est « SANS PCO » démarre « étudiée (faisable) », « AVEC PCO » démarre « reçue ».

**Pas encore construit** : l'import lui-même (analyse, écran de revue, validation), les écrans, les droits d'accès et les interventions.
