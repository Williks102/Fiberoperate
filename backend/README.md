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
