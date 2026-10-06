# CuyoTech SSIS — Current Project Snapshot

## Architecture

- Laravel 13 (`^13.17`), PHP `^8.3`, React/Inertia in the same repository.
- Sanctum authentication for the JSON API under `/api`.
- SQLite is the configured default for local development and tests; no production database decision is recorded.
- Money values use integer centavos. Keep multi-record financial changes transactional.

## Implemented surface observed in this repository

- Login and logout.
- Live active-account and role middleware.
- Student profile, subjects, and grades endpoints.
- Feature tests for authentication and student portal behavior.

For the current route inventory, run `php artisan route:list`; see `docs/ai/current.md` for the 2026-10-01 implementation and test snapshot.

## Planned but not present in the current route list

- Registrar enrollment and grade encoding.
- Cashier payments and receipt viewing.
- Department clearances, document requests, and admin management.

Use `docs/SPEC.md` for intended contracts and `docs/SPRINTS.md` for task status. Do not duplicate API payloads here.
