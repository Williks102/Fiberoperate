# Backend — Lot 1 (fondations des données)

Fichiers de départ Laravel : enums, migrations, modèles, seeders, tests. À copier dans un projet Laravel 11/12 neuf.

## Installation
```bash
composer create-project laravel/laravel backend && cd backend
php artisan install:api                 # Sanctum
# Dans la migration personal_access_tokens : remplacer morphs('tokenable') par uuidMorphs('tokenable')
rm database/migrations/0001_01_01_000000_create_users_table.php database/factories/UserFactory.php
# copier ici app/, database/, tests/ de ce dossier (remplacer app/Models/User.php)
# configurer PostgreSQL dans .env
php artisan migrate --seed
php artisan test
```

## Choix de conception
- UUID partout, suppression logique, index uniques partiels (`WHERE deleted_at IS NULL`) : un code supprimé peut être recréé.
- `code_normalized` calculé à la sauvegarde (`CodeNormalizer`) : `ACA03_PCO-001` = `ACA003_PCO001`.
- Aucun nombre fixe de JDV/PCO. Le PCO déduit sa zone de son JDV ; incohérence = exception.
- `pcos.status` nullable : le libellé d'origine reste dans `status_raw` tant que la correspondance n'est pas validée. « Bloqué » = `is_blocked` + motif.
- `Pco::changeStatus()` écrit l'historique (qui, quand, appareil) dans la même transaction.
- DevSeeder : uniquement en environnement `local`.

## État de vérification
Syntaxe PHP vérifiée (`php -l`) et `CodeNormalizer` exécuté. **Migrations et tests non exécutés** (installation de Laravel impossible dans l'environnement de préparation) : à lancer au premier `php artisan test`.
