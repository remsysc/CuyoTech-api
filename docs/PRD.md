# Product Requirements — CuyoTech University SSIS

> **Status:** Prototype Complete
> **Product owner:** Rem

## Document ownership

- This document owns the product problem, goals, scope, and functional requirements.
- `SPEC.md` is the single source of truth for data models, API contracts, validation, and resolved technical behavior.
- `SPRINTS.md` owns delivery sequencing and task status. Do not maintain a second implementation checklist here.
- `TEST_PLAN.md` owns verification strategy; `docs/ai/current.md` records the implementation and latest observed test status.

## Product context

CuyoTech University is transitioning from manual, paper-based student services where students physically queued for enrollment, clearances, grade viewing, and document requests. The university developed the Student Services Information System (SSIS) to automate these workflows across university departments and provide modern self-service portals.

## Core System Deliverables & Modules

The system delivers six core interactive modules:

1. **Student Portal** – student profile, enrolled subjects, term grades, account balance, and clearance status.
2. **Registrar Module** – student enrollment with automatic unit fee calculation, grade encoding, course rosters, and document request processing.
3. **Cashier Module** – payment entry, printable official receipts, and student payment history ledger.
4. **Department Module** – department-scoped clearance review queue, approval/denial actions with remarks, and multi-office clearance inspection.
5. **Admin Module** – user management, role assignments, account activation/deactivation, password resets, and comprehensive audit logging.
6. **Document Request System** – self-service requests for TOR, COR, and Certifications, protected by server-side clearance and balance guards.

## Project constraints

- **Decoupled Architecture:** The system consists of a Laravel 13 REST API backend (`cuyotech-api`, PHP `^8.3`) providing endpoints under `/api` (plus web routes for printable receipts) and a standalone React 19 + Vite 8 SPA frontend client (`ssis-cuyotech`).
- **Containerization:** Orchestrated via Docker Compose (`compose.yaml`) with automated setup, healthchecks, and environment seeding.
- **Authentication & Security:** API authentication uses Laravel Sanctum bearer tokens; authorization and role boundaries (`student`, `registrar`, `cashier`, `department_staff`, `admin`) are enforced server-side on every request.
- **Database:** SQLite is the zero-configuration default database for local development, testing, and container environments (`database/database.sqlite`).
- **Money Handling:** Monetary values are canonically stored and calculated as integer centavos, never floating-point numbers.

## Goals

- Students can view their profile, subjects, grades, balance, and clearance status online.
- Registrar staff can manage enrollment, calculate per-unit fees, encode grades, and view course rosters without paper forms.
- Cashiers can record student payments, maintain an audit-proof ledger, and provide stable, printable official receipts.
- Department staff can review and process clearances online with reviewer remarks.
- Students can request official documents (TOR, COR, Certifications), strictly gated by zero balance and approved clearances.
- Admins can manage user accounts, assign roles, reset credentials, and inspect system audit logs.

## Non-goals for this prototype

- Third-party payment gateway integration; payments are processed through university cashier counters or demo sandbox modes.
- SMS or email notifications; workflow updates are displayed in-app.
- Native mobile application; responsive web design is utilized.
- Multi-campus support.
- Automated GWA or latin-honors computation (FR-4 is dropped).

## Target users

- **Student:** views their profile, enrolled courses, term grades, tuition balance, departmental clearances, and document request statuses; submits document requests.
- **Registrar:** manages student enrollments, encodes and updates grades, views course rosters, and processes document requests through fulfillment stages.
- **Cashier:** records student payments, generates official receipts, and reviews payment history.
- **Department Staff:** reviews and approves or denies clearances for their designated department (Library, Guidance, Student Affairs, College Dean, etc.).
- **Admin:** manages user accounts, assigns system roles, handles password resets, and reviews security audit logs.

## Functional requirements (Grouped by Deliverable)

### 1. Student Portal Module

| ID   | Requirement                                                                              | Priority |
| ---- | ---------------------------------------------------------------------------------------- | -------- |
| FR-1 | A user can authenticate using their student number or email and password.                | P0       |
| FR-2 | A student can view their own profile.                                                    | P0       |
| FR-3 | A student can view their own subjects and completed grades by term.                      | P0       |
| FR-4 | **Dropped:** compute GWA; grading and rounding rules are not defined for this prototype. | —        |

### 2. Registrar Module

| ID   | Requirement                                                                                                                                                                                                                  | Priority |
| ---- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------- |
| FR-5 | A registrar can enroll a student in a course for a school year and semester; enrollment applies the configured per-unit charge to the student's balance. Course sections are not represented in the current prototype model. | P0       |
| FR-6 | A registrar can encode or edit a student's grade for a course.                                                                                                                                                               | P0       |
| FR-7 | A registrar can view a course roster for a term.                                                                                                                                                                             | P1       |

### 3. Cashier Module

| ID    | Requirement                                                    | Priority |
| ----- | -------------------------------------------------------------- | -------- |
| FR-8  | A cashier can record a payment against a student.              | P0       |
| FR-9  | The system provides a stable, printable receipt after payment. | P0       |
| FR-10 | A cashier can view a student's payment history.                | P1       |

### 4. Department Module

| ID    | Requirement                                                                  | Priority |
| ----- | ---------------------------------------------------------------------------- | -------- |
| FR-11 | Department staff can approve or deny clearances within their own department. | P0       |
| FR-12 | Authorized staff can view a student's clearance status across departments.   | P1       |

### 5. Admin Module

| ID    | Requirement                                                        | Priority |
| ----- | ------------------------------------------------------------------ | -------- |
| FR-15 | Admins can create, edit, and deactivate accounts and assign roles. | P0       |
| FR-16 | Admins can reset a user's password.                                | P1       |
| FR-17 | Admins can view an audit log of account changes.                   | P2       |

### 6. Document Request System

| ID    | Requirement                                                                     | Priority |
| ----- | ------------------------------------------------------------------------------- | -------- |
| FR-13 | Document release is blocked until required department clearances are approved.  | P0       |
| FR-14 | New document requests are blocked while the student has an outstanding balance. | P0       |
| FR-18 | A student can request a TOR, COR, or certification and provide a purpose.       | P0       |
| FR-19 | A registrar can move a document request through its allowed statuses.           | P0       |
| FR-20 | A student can view the current status of their document request.                | P1       |

## Success criteria

- Demonstrate all in-scope student, registrar, cashier, department, and admin workflows end to end across both web UI and API.
- A grade entered by a registrar appears in the owning student's grade view immediately.
- A cashier payment updates the student's balance atomically and produces an official printable receipt.
- Document requests and document release enforce both the zero-balance and all-cleared guards.
- No P0 defects remain.

## Decisions still requiring confirmation

- Which database engine, if any, is required for production deployment beyond the current SQLite default.
- Whether CuyoTech's actual grading scale matches the provisional scale in `SPEC.md`.
- How current-term clearance records are generated in bulk for new academic terms.
- Whether course sections or scheduling timeslots are required beyond course units in future iterations.
