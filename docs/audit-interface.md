# Audit de l'interface (31/10/2026) et plan de refonte

Méthode : captures de 7 écrans à 390 px (téléphone), 768 px (tablette) et 1280 px (PC) avec des données de démonstration, mesures automatiques (défilement horizontal, taille des zones tactiles) et relecture à l'œil. Grille inspirée du skill « ui-ux-pro-max » (accessibilité, tactile, mobile d'abord, jetons de couleur).

## Ce qui est bon
- Aucun défilement horizontal à 390, 768 et 1280 px.
- Boutons et champs à 44 px ; libellés visibles au-dessus des champs ; trois états (chargement, vide, erreur) sur les listes.
- Contrastes de texte suffisants ; thème clair stable ; messages en français.

## Ce qui manquait (constats)
1. **Aucune identité visuelle** : tout en noir, blanc et gris, couleurs en dur (`slate-900`), pas de couleur de marque. → *Charte visuelle (cette PR)*.
2. **En-tête du téléphone encombré** : « FTTH Gestion » passe sur deux lignes, le bouton « Se déconnecter » prend la place de l'essentiel. → coque de l'application.
3. **Accueil presque vide** : une carte « Bonjour » et rien d'autre. → accueil en tableau de bord.
4. **Menu latéral** sans icônes, liens d'activité de 16 px de haut (sous les 44 px). → coque de l'application.
5. **Écrans à plat** : toutes les cartes se ressemblent ; états (phase, statut) en gris ; sur PC la fiche d'un projet n'occupe qu'une colonne étroite et laisse un tiers de l'écran vide. → badges d'état colorés, fiche projet en deux colonnes.
6. **Champs de formulaire natifs** (listes déroulantes par défaut) : corrects mais ternes. → habillage des champs.

## Plan (une PR par étape)
1. **Charte visuelle** : jetons (couleur principale bleu fibre, repère cyan, fond doux, encre), police Inter embarquée, remplacement des couleurs en dur.
2. **Coque de l'application** : marque, menu latéral avec icônes, en-tête compact et menu du compte, page de connexion.
3. **Accueil** : mot de bienvenue et une carte par activité avec ses écrans (*fait*). Les **chiffres clés par rôle** relèvent des tableaux de bord du **lot 5** : pas avant, faute de données (clients, études, installations).
4. **Écrans** : badges d'état colorés, fiche projet en deux colonnes sur PC (*fait*).
5. **Formulaires** : champs arrondis avec anneau de focus, listes déroulantes à flèche commune, barre d'action collée en bas sur téléphone avec ombre (*fait*).

## Règles à tenir (reprises dans `CLAUDE.md`)
Mobile d'abord (360 px puis `md`), zones tactiles de 44 px au moins, contraste AA (4,5:1), jamais l'information par la couleur seule, rien d'accessible uniquement au survol, couleurs uniquement par les jetons de `globals.css`, animations de 200 ms au plus et coupées si l'utilisateur réduit les animations.
