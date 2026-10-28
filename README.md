# Fiberoperate

Application de gestion d'une entreprise sous-traitante d'installation de fibre optique (FTTH).
Référence fonctionnelle : `CAHIER_DES_CHARGES.md`. Conventions : `CLAUDE.md`. Plan : `LOTS_DE_TRAVAIL.md`. Déploiement : `DEPLOIEMENT.md`.

## Structure
- `backend/` : API Laravel 13 (Sanctum, PostgreSQL, files Redis)
- `frontend/` : Next.js (App Router, TypeScript, Tailwind), PWA
- `docker-compose.yml` : environnement de développement
- `render.yaml` : déploiement de l'API et du worker sur Render

## Démarrage avec Docker
```bash
docker compose up
```
API : http://localhost:8000 (santé : `/up`) · Front : http://localhost:3000 · MinIO : http://localhost:9001

## Démarrage sans Docker
PHP 8.3 (extensions pdo_pgsql, redis), PostgreSQL 16, Node 22.
```bash
# Backend
cd backend
composer install
cp .env.example .env && php artisan key:generate
# créer les bases `ftth` et `ftth_test` (utilisateur ftth / ftth)
php artisan migrate --seed
php artisan serve

# Frontend
cd frontend
cp .env.example .env.local
npm install && npm run dev
```

## Contrôles (identiques à la CI, `.github/workflows/ci.yml`)
```bash
cd backend  && vendor/bin/pint --test && php artisan test
cd frontend && npm run lint && npm run typecheck && npm run build
```
