# Backend — API Laravel

Lot 0 (squelette, Sanctum, PostgreSQL) et lot 1 (enums, migrations, modèles, seeders, tests).

## Installation
Le squelette Laravel est en place (lot 0). Voir le `README.md` à la racine pour lancer l'API, les migrations et les tests.

## Choix de conception
- UUID partout, suppression logique, index uniques partiels (`WHERE deleted_at IS NULL`) : un code supprimé peut être recréé.
- `code_normalized` calculé à la sauvegarde (`CodeNormalizer`) : `ACA03_PCO-001` = `ACA003_PCO001`.
- Aucun nombre fixe de JDV/PCO. Le PCO déduit sa zone de son JDV ; incohérence = exception.
- `pcos.status` nullable : le libellé d'origine reste dans `status_raw` tant que la correspondance n'est pas validée. « Bloqué » = `is_blocked` + motif.
- `Pco::changeStatus()` écrit l'historique (qui, quand, appareil) dans la même transaction.
- DevSeeder : uniquement en environnement `local`.

## État de vérification
Migrations, seeders et tests exécutés sur PostgreSQL 16 (`php artisan test`, Pint).

## API v1 (lot 1)
Préfixe `/api/v1`, jetons Sanctum Bearer (`SANCTUM_TOKEN_EXPIRATION`, 12 h par défaut), CORS limité à `FRONTEND_URL`.

- `POST auth/login` (5 essais/min par e-mail + IP), `POST auth/logout`, `GET auth/me`
- `users` (admin ; DG et DT en lecture), `teams`, `olts`, `plaques`, `zones`, `jdvs`, `pcos`, `GET block-reasons`

**Périmètre** (scope `visibleTo` des modèles, repris par les Policies) :
- admin, DG, DT, coordinateur : tout le réseau ; superviseur : sa plaque et ses zones, JDV, PCO ; technicien : la plaque de son supérieur (technicien déploiement) ; OLT masqués aux techniciens.
- équipes : le coordinateur voit les siennes ; admin et DT les gèrent, DG lecture.
- écriture du réseau et des utilisateurs : administrateur seul.

Hypothèse à valider : le schéma n'a pas de lien équipe ↔ zone, donc le coordinateur lit tout le réseau (utile pour choisir un PCO à l'étude).
