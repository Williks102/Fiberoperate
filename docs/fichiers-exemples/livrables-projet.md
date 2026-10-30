# Livrables d'un projet de déploiement (exemples du 31/10/2026)

Analyse de quatre fichiers fournis par le client pour des sous-plaques différentes. **Les fichiers eux-mêmes ne sont pas dans le dépôt** (contacts du client et de l'installateur, plans) : ce document ne décrit que leur structure. Il sert de référence au lot 1B (documents, devis), au lot 6 (suivi du déploiement) et au lot 7 (matériel).

| Livrable | Fichier d'exemple | Lot concerné |
|---|---|---|
| Plan KMZ de la sous-plaque | `PLAN KMZ 7TR05` (12 Ko) | 1B : document et lecture des positions prévues |
| Bordereau de prix / devis | `BOQ GENERAL PROJET DE DESAT SRO FAYA22` (Excel, 270 Ko) | 1B : devis de l'AVP |
| Bon de sortie de matériel | `Bon de sortie Matériel DEPLOIEMENT FTTH ATB7` (Excel, 2 feuilles) | 7 : matériel |
| Plan de la sous-plaque | DWG (AutoCAD) | 1B : document seulement |

## 1. Plan KMZ (sous-plaque 7TR05)
Fichier compressé contenant un seul `doc.kml` (126 Ko décompressé). **Taille : 12 Ko** : aucun souci de stockage pour un KMZ.

Trois dossiers :
- **PCO** : 48 points nommés `PCO-001` … `PCO-049`, avec leurs coordonnées GPS.
- **JVD** (dossier ainsi nommé dans le fichier) : 8 splitters `JDV001` … `JDV008`, un point **`SPL/TR05`** (source des splitters, c'est-à-dire la SRO de la sous-plaque), et deux points `PEP01`, `PEP02` (sens à confirmer).
- **CABLES** : 65 lignes. `CABLE SPL/TR05-JDV003` (liaison source → splitter), `CABLE JDV001-PCO-001` (splitter → premier PCO), `CABLE PCO-001-PCO-002` (les PCO d'un splitter sont **chaînés**), plus `LIMITE DE ZONE`, `ILOTS`, `LienGC` et un polygone.

Ce que le fichier montre :
- **Le réseau suit la règle standard** : les PCO sont numérotés en continu par blocs de 8 par splitter (JDV001 : PCO 001 à 008, JDV002 : 009 à 016… ; les derniers splitters en ont moins). C'est exactement la génération de l'application.
- **Les PCO ne portent pas le code de la sous-plaque** (`PCO-001`, pas `7TR05_PCO001`) : l'application les nommerait `{CODE}_PCO001`.
- **Les noms contiennent des fautes** (`PCO-09`, `PCO-0015`, `PC0-001`, `JVV007`, `P¨CO-020`, `JDV027-PCO-008`) : on ne peut pas s'en servir sans contrôle. La topologie ne doit pas être déduite des noms de câbles.
- **Les coordonnées sont exploitables** : elles donnent les **positions prévues** des PCO et des splitters. Les splitters n'ont pas encore de position dans l'application.

Piste (à valider) : « Importer un plan KMZ » sur la fiche du projet, qui rapproche les points du réseau existant par leur code normalisé et renseigne les positions prévues, avec la même revue ligne par ligne que l'import CSV (corrections jamais silencieuses).

## 2. Bordereau de prix = devis de l'AVP (BOQ)
Titre : « Bordereau de prix des fournitures et prestations » pour un **projet de désaturation d'une SRO** (FAYA 22). Une feuille, environ 460 lignes.

Colonnes : **Réf.**, **Désignation**, **unité**, puis trois blocs : **ÉTUDE** (quantité, prix unitaire, prix total), **RÉALISÉ** (quantité, prix total), **ÉCART** (quantité, prix total).

Structure : un **catalogue de prix de l'opérateur** (76 rubriques, références du type `MGC01`, `POT02`, `CPT14`, `RFO01`), en deux grandes parties :
- **FOURNITURES** : génie civil, étiquetage, connectique, protection et raccordement, divers, câbles abonnés / aériens / souterrains, construction d'artères aériennes.
- **PRESTATIONS** : I génie civil, II travaux réseau (poteaux, dépose), III travaux fibre (pose, raccordement, mesures, dépose), IV travaux clients.

Pour ce projet : 38 lignes ont une quantité (sur environ 380), total étude **12 328 670 FCFA** (par exemple 47 poteaux béton 9 m, 7 960 m de câble aérien 16 FO, 214 fusions). Le client **renseigne les quantités, les prix unitaires viennent du catalogue**.

Conséquence pour l'application : le devis n'est pas un texte libre mais une **liste de lignes du catalogue de prix avec une quantité d'étude**, puis une **quantité réalisée** (renseignée en fin de projet) et l'**écart**. Cela correspond à la comparaison prévu / réel du cahier. Rappel : aucune facturation, c'est un chiffrage de projet (montants entiers en FCFA).

## 3. Bon de sortie de matériel
Feuille « BON MATERIEL » : formulaire du client (« Demande Sortie Matériel ») pour une **sous-zone** (ici `ATB 07`, splitters `JDV1 à JDV8`, `PCO1 à PCO38`) : nom du projet, site, région, client, installateur et son contact, **tableau de matériel** (projet, référence, marque, désignation, quantité commandée / livrée, commentaires), responsable de l'enlèvement, transport, date souhaitée et date réelle de mise à disposition, remarques, **quatre visas** (préparateur, responsable du magasin, transporteur, réceptionnaire). À imprimer en 3 exemplaires.

Les quantités suivent le réseau : **38 boîtiers PCO de 8 ports** pour 38 PCO, 45 mini-modules splitter 1:8, câbles aériens, boîtes d'épissure, feuillard et boucles inox, pinces d'ancrage, étiquettes.

Feuille masquée « BOM » : suivi par SRO (`SRO N° : ABB02`), avec par colonnes `D01`…`D04` et `T01`…`T03` (sous le titre « TRANSPORT » pour les `T`) : **quantité totale, réalisé, restant, %**. Les `D` ressemblent à de la **distribution** et les `T` à du **transport** : cela remet en cause la note « D = division » de `CLAUDE.md` (à confirmer).

Conséquence : c'est le sujet du **lot 7 (matériel et stock)**, à ne pas anticiper : demande de sortie par sous-plaque, livré / restant.

## 4. Plan de la sous-plaque (DWG)
Format AutoCAD : l'application ne le lit pas (ni la lecture ni l'aperçu). Il est **stocké comme document versionné** du projet, sans traitement. Taille à connaître avant de choisir le stockage (un DWG dépasse vite les limites d'un service d'images).

## Questions ouvertes
1. **`SPL/TR05`** est-il bien la SRO de la sous-plaque (et `7TR05` ou `TR05` son code) ? Le point s'appelle `SPL/…` alors que `SPL001` est la convention des splitters.
2. **`PEP01` et `PEP02`** : que représentent-ils ?
3. **Projet de désaturation** : le BOQ parle d'un « projet de désaturation de la SRO FAYA22 ». Un projet peut-il porter sur une **SRO existante** (désaturer) et pas sur une nouvelle plaque ? Quel lien avec la désaturation d'un PCO au 9ᵉ client ?
4. **Lettres `D` et `T`** du fichier de matériel : distribution et transport ?
5. **Catalogue de prix** : est-il le même pour tous les projets du client, et qui le met à jour ? Les prix changent-ils ?
6. **Taille d'un DWG** et durée de conservation des versions.
