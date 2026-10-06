# CuyoTech SSIS — Current Context

**Snapshot date:** 2026-10-06
**Current work:** Sprint 2 Completed — Student Portal + Registrar + Cashier

## Implementation status observed

- **Foundation/auth:** login and logout routes, Sanctum, role/active-account middleware, migrations, models, and supporting factories are present.
- **API contracts & error envelopes:** `bootstrap/app.php` standardizes JSON error responses to match `docs/SPEC.md` §5 (`VALIDATION_FAILED` with `fields`, `UNAUTHENTICATED`, `UNAUTHORIZED_ROLE`, `RATE_LIMITED`, `CONFLICT`, `NOT_FOUND`). Verified by `ErrorEnvelopeTest.php`.
- **Currency formatting & schema:** integer centavos remain canonical across database and input payloads; `App\Support\Money::format()` and accessors (`Student::balance_formatted`, `Payment::amount_formatted`) provide display formatting (`MoneyTest.php`). `students.user_id` foreign key updated to `cascadeOnDelete()`.
- **Student portal:** profile, subjects, and grades routes/controllers are present and verified.
- **Registrar/cashier/receipts:** `POST /api/registrar/enrollments`, `PATCH /api/registrar/enrollments/{id}/grade`, `POST /api/cashier/payments`, and `GET /receipts/{or_number}` are implemented and fully verified against E2E tiers 1-4.

## Important technical facts

- Laravel `^13.17`, PHP `^8.3`, Pest `^5.2` (`composer.json`).
- React/Inertia is part of this repository; the frontend is not a separate repository.
- SQLite is the configured default for local/testing. A production database has not been selected.
- Money is represented as integer centavos; role and active status are checked server-side.

## Current test snapshot

- Complete test suite: 269 tests passed, 901 assertions (`php artisan test --compact`).
- E2E contract suite: 103 tests passed, 349 assertions (`vendor/bin/pest tests/Feature/E2E --compact`).

## Source-of-truth documents

- Product scope: `docs/PRD.md`
- Technical/data/API contract: `docs/SPEC.md`
- Work sequencing/status: `docs/SPRINTS.md`
- Test strategy: `docs/TEST_PLAN.md`
