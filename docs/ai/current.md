# CuyoTech SSIS — Current Context

**Snapshot date:** 2026-10-12
**Current work:** Sprints 1–5 Deliverables Complete — Decoupled Architecture, PRD/SPEC/SPRINT Alignment, and Full 6-Module Operational Verification

## Implementation status observed

- **Infrastructure & Containerization:** Root `compose.yaml` in API repo orchestrates both `cuyotech-api` (PHP 8.4 Alpine) and `ssis-cuyotech` (Node 22 Alpine). Container entrypoints automate `.env` setup, `APP_KEY` generation, SQLite database initialization, Composer/npm volume caching, database migrations, seeders, and healthchecks (`/up`).
- **Seeders (Pamantasan ng Cabuyao):** Comprehensive curriculum offerings across 6 academic colleges (CCS, COE, CBAA, COED, CAS, CHAS) and 4 administrative offices (LIB, OSA, RO, CASH). Authentic Filipino student profiles (Juan Miguel Dela Cruz, Maria Clara Santos, etc.) with enrollments, grades, multi-office clearances, and payment ledgers.
- **Backend API Endpoints:** All 20 Functional Requirements implemented and verified across 6 core deliverable modules:
    - Core Auth (`/api/login`, `/api/logout`, `/api/user`)
    - Student Portal (`/api/student/profile`, `/api/student/subjects`, `/api/student/grades`)
    - Registrar Module (`/api/registrar/courses`, `/api/registrar/students`, `/api/registrar/enrollments`, `/api/registrar/enrollments/{id}/grade`, `/api/registrar/courses/{id}/roster`, `/api/registrar/document-requests`)
    - Cashier Module (`/api/cashier/students`, `/api/cashier/payments`, `/api/cashier/students/{id}/payments`, `/receipts/{or_number}`)
    - Department Clearance Module (`/api/department/clearances`, `/api/department/clearances/{id}`, `/api/students/{id}/clearances`)
    - Admin Module (`/api/admin/users`, `/api/admin/users/{id}`, `/api/admin/users/{id}/password`, `/api/admin/departments`, `/api/admin/audit-logs`)
    - Document Requests (`/api/documents/requests`, `ClearanceGuard`, `BalanceGuard`)
- **Frontend SPA Client (`ssis-cuyotech`):** Sibling repository running React 19 + Vite 8.
    - **Modern University Portal UI:** Split-screen login page with institutional navy and gold theming, registrar pillars, quick role switcher, and demo personas (`Demo (Cleared)` and `Demo (With Holds)`).
    - **All 6 Deliverable Portals:** Fully implemented for Student, Registrar, Cashier, Department, and Admin.
    - **Official Branding:** Vector CuyoTech seal logo in `public/favicon.svg` and `index.html`.
- **Release Promotion PRs:**
    - Backend: [remsysc/CuyoTech-api#29](https://github.com/remsysc/CuyoTech-api/pull/29) (`staging -> main`) — **Merged**.
    - Frontend: [Suizei1906/ssis-cuyotech#28](https://github.com/Suizei1906/ssis-cuyotech/pull/28) (`staging -> main`) — **Merged**.

## Important technical facts

- Laravel `^13.17`, PHP `^8.3` (tested on 8.4 and 8.5), Pest `^5.2`.
- Decoupled architecture: pure Laravel REST API backend (`cuyotech-api`) and standalone React 19 SPA frontend (`ssis-cuyotech`), proxied via `VITE_PROXY_TARGET=http://api:8000`.
- PostgreSQL running on a Podman container (`cuyotech-postgres`, database `cuyotech_ssis`) is the primary runtime database for development and containerized deployments; SQLite is retained for fast in-memory test isolation.
- Money is represented canonically as integer centavos; role and account status checks enforced server-side.

## Current test snapshot

- **Backend:** 325 tests passed, 1,196 assertions (`php artisan test --compact`), Level 8 PHPStan clean (0 errors), Pint code style clean.
- **Frontend:** 68 tests passed across 22 suites (`npm test`), ESLint clean (0 errors), Vite production build clean.
- **Remote CI:** All GitHub Actions pipelines passed on promoted topic, staging, and release PR branches.

## Source-of-truth documents

- Product scope & deliverables: `docs/PRD.md`
- Technical/data/API contract: `docs/SPEC.md`
- Work sequencing/status: `docs/SPRINTS.md`
- Test strategy: `docs/TEST_PLAN.md`
