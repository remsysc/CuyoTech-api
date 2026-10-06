# Test Plan — CuyoTech SSIS

> This plan covers the current prototype. API contracts come from `SPEC.md`; this document does not define alternate response shapes or performance SLAs.

## Test environment

- Laravel 13, PHP `^8.3`, Pest `^5.2` (see `composer.json`).
- PHPUnit/Pest test configuration uses in-memory SQLite (`phpunit.xml`).
- Use factories and isolated database state for feature tests.

## Verification priorities

1. **Authentication and authorization:** valid/invalid credentials, deactivated accounts, unauthenticated access, role boundaries, and ownership boundaries.
2. **Student portal:** profile privacy, term filtering, empty results, and completed-grade filtering.
3. **Registrar:** duplicate enrollment prevention, atomic balance charge, grade range validation, and student-visible state changes.
4. **Cashier:** positive payment validation, atomic balance update, unique receipt number, and receipt access.
5. **Clearance, documents, and admin:** department scoping, allowed status transitions, balance/clearance guards, account changes, and audit records.

Tests for a feature should be added or enabled as that feature is implemented. The four existing E2E tiers are one way the current suite groups cases; they are not a minimum test-count quota or evidence that a feature is complete.

## Run tests

```bash
# Focused test file
php artisan test --compact tests/Feature/StudentPortalTest.php

# Full suite
php artisan test --compact

# Current E2E contract suite
vendor/bin/pest tests/Feature/E2E --compact
```

The E2E contract suite currently includes planned registrar, cashier, and receipt behavior that is not yet implemented. Check `docs/ai/current.md` for the latest observed result; do not treat authored test cases as passing coverage.

## Out of scope unless explicitly required

There are no agreed production load, throughput, latency, availability, or coverage-percentage targets. Do not claim these targets are met or add a load-testing gate until a deployment environment and workload requirement are defined. Concurrency tests should be added when the relevant shared-resource feature is implemented.
