# CuyoTech SSIS

Laravel 13 / PHP 8.3+ prototype for CuyoTech University student services, with React/Inertia in the same repository and Sanctum-protected JSON endpoints under `/api`.

## Documentation map

- [`docs/PRD.md`](docs/PRD.md) — product goals, scope, and functional requirements.
- [`docs/SPEC.md`](docs/SPEC.md) — authoritative data model and API/behavior contract.
- [`docs/SPRINTS.md`](docs/SPRINTS.md) — planned work and task status.
- [`docs/TEST_PLAN.md`](docs/TEST_PLAN.md) — focused verification strategy.
- [`docs/ai/current.md`](docs/ai/current.md) — current implementation and test-status snapshot.

`DATABASE_DESIGN.md` and `SDLC_PLAN.md` are supporting/coursework references, not alternative sources of requirements or contracts.

## Local setup and checks

Use the project scripts in `composer.json` and `package.json`. The configured test environment uses in-memory SQLite.

```bash
composer setup
php artisan test --compact
vendor/bin/pest tests/Feature/E2E --compact
```

Check `docs/ai/current.md` for implementation gaps and the latest observed test result before treating the planned E2E suite as passing.
