# CuyoTech SSIS — Current Context

**Snapshot date:** 2026-10-22
**Current work:** Sprints 1–5 Deliverables Complete, Interactive QA & Balance Clamping Hardened, and Full TypeScript Migration (`ssis-cuyotech`) Promoted to `main`

## Implementation status observed

- **Infrastructure & Containerization:** Root `compose.yaml` in API repo orchestrates `cuyotech-postgres` (Postgres 17 Alpine), `cuyotech-api` (PHP 8.4 Alpine), and `ssis-cuyotech` (Node 22 Alpine). Container entrypoints automate `.env` setup while preserving injected container `DB_HOST`/`DB_PORT` environment variables, `APP_KEY` generation, Composer/npm volume caching, database migrations, seeders, and healthchecks (`/up`).
- **Seeders (Pamantasan ng Cabuyao):** Comprehensive curriculum offerings across 6 academic colleges (CCS, COE, CBAA, COED, CAS, CHAS) and 4 administrative offices (LIB, OSA, RO, CASH). Authentic Filipino student profiles (Juan Miguel Dela Cruz, Maria Clara Santos, Crisostomo Ibarra, etc.) with enrollments, grades, multi-office clearances, and synchronized non-negative payment ledgers.
- **Backend API Endpoints:** All 22 Functional Requirements implemented and verified across 6 core deliverable modules:
    - Core Auth (`/api/login`, `/api/logout`, `/api/user`)
    - Student Portal (`/api/student/profile`, `/api/student/subjects`, `/api/student/grades`, `/api/student/payments`, `/api/student/queue`, `/api/student/queue/presence`)
    - Registrar Module (`/api/registrar/courses`, `/api/registrar/students`, `/api/registrar/enrollments`, `/api/registrar/enrollments/{id}/grade`, `/api/registrar/courses/{id}/roster`, `/api/registrar/document-requests`)
    - Cashier Module (`/api/cashier/students`, `/api/cashier/payments`, `/api/cashier/students/{id}/payments`, `/api/cashier/queue`, `/api/cashier/queue/call-next`, `/api/cashier/queue/{id}/status`, `/receipts/{or_number}`) — includes tuition overpayment guards (`TUITION_OVERPAYMENT`) and non-negative balance clamping.
    - Department Clearance Module (`/api/department/clearances`, `/api/department/clearances/{id}`, `/api/students/{id}/clearances`)
    - Admin Module (`/api/admin/users`, `/api/admin/users/{id}`, `/api/admin/users/{id}/password`, `/api/admin/departments`, `/api/admin/audit-logs`)
    - Document Requests (`/api/documents/requests`, `ClearanceGuard`, `BalanceGuard`)
- **Frontend SPA Client (`ssis-cuyotech`):** Sibling repository running React 19 + TypeScript 6 + Vite 8.
    - **Full TypeScript Codebase:** All 60 source and test modules in `src/` migrated to `.ts`/`.tsx` with shared domain contracts in `src/types/ssis.ts` and `npm run typecheck` (`tsc --noEmit`).
    - **Modern University Portal UI:** Split-screen login page with institutional navy and gold theming, registrar pillars, quick role switcher, and demo personas (`Demo (Cleared)` and `Demo (With Holds)`).
    - **All 6 Deliverable Portals:** Fully implemented and button-by-button QA-verified for Student, Registrar, Cashier, Department, and Admin.
    - **Official Branding:** Vector CuyoTech seal logo in `public/cuyotech-logo.svg` and `index.html`.
- **Release Promotion PRs:**
    - Backend: [remsysc/CuyoTech-api#31](https://github.com/remsysc/CuyoTech-api/pull/31) (`staging -> main`) — **Merged**.
    - Frontend: [Suizei1906/ssis-cuyotech#30](https://github.com/Suizei1906/ssis-cuyotech/pull/30) and [Suizei1906/ssis-cuyotech#33](https://github.com/Suizei1906/ssis-cuyotech/pull/33) (`staging -> main`) — **Merged**.

## Important technical facts

- Laravel `^13.17`, PHP `^8.3` (tested on 8.4 and 8.5), Pest `^5.2`.
- Decoupled architecture: pure Laravel REST API backend (`cuyotech-api`) and standalone React 19 + TypeScript SPA frontend (`ssis-cuyotech`), proxied via `VITE_PROXY_TARGET=http://api:8000`.
- PostgreSQL running on a container (`cuyotech-postgres`, database `cuyotech_ssis`) is the primary runtime database for development and containerized deployments; SQLite is retained for fast in-memory test isolation.
- Money is represented canonically as integer centavos; tuition payments are guarded against overpayment and displayed balances are clamped to `>= 0`; role and account status checks are enforced server-side.

## Current test snapshot

- **Backend:** 331 tests passed, 1,246 assertions (`php artisan test --compact`), Level 8 PHPStan clean (0 errors), Pint code style clean.
- **Frontend:** 75 tests passed across 23 suites (`npm test -- --run`), TypeScript compiler clean (`npm run typecheck`, 0 errors), ESLint clean (0 errors), Vite production build clean.
- **Remote CI:** All GitHub Actions pipelines passed on promoted topic, staging, and release PR branches.

## Source-of-truth documents

- Product scope & deliverables: `docs/PRD.md`
- Technical/data/API contract: `docs/SPEC.md`
- Work sequencing/status: `docs/SPRINTS.md`
- Test strategy: `docs/TEST_PLAN.md`
