# Sprint Plan — CuyoTech University SSIS

> Derived from: `SPEC.md` v1.2 and `PRD.md` | Updated: 2026-10-12
> Every task traces to a requirement ID and deliverable module. Tasks without one are marked SETUP.
>
> **Status snapshot:**
>
> - Backend API: Sprints 1–4 are fully implemented and verified (325 tests passing across Auth, Student, Registrar, Cashier, Department, Admin, and Document Request endpoints).
> - Frontend UI: Sprints 1–5 are fully implemented and verified (68 tests passing across 22 suites in `ssis-cuyotech` covering Student, Registrar, Cashier, Department, and Admin portals).
> - All six core deliverables are completed and operational end-to-end.

---

## Overview

| Sprint | Dates           | Goal                                                                                             | Deliverables Covered / Requirements             | Est. Size | Status   |
| ------ | --------------- | ------------------------------------------------------------------------------------------------ | ----------------------------------------------- | --------- | -------- |
| 1      | Sep 27 – Oct 3  | Foundation: project setup, all migrations, auth                                                  | SETUP, FR-1                                     | L         | COMPLETE |
| 2      | Oct 4 – Oct 10  | Student portal, registrar (enrollment + grading), cashier payments API                           | FR-2, FR-3, FR-5, FR-6, FR-8, FR-9              | XL        | COMPLETE |
| 3      | Oct 11 – Oct 17 | Department clearances, document requests, admin user management API                              | FR-11, FR-13, FR-14, FR-15, FR-18, FR-19, FR-20 | XL        | COMPLETE |
| 4      | Oct 18 – Oct 24 | P1/P2: class roster, payment history, cross-department clearance view, password reset, audit log | FR-7, FR-10, FR-12, FR-16, FR-17                | L         | COMPLETE |
| 5      | Oct 25 – Oct 31 | Staff & Admin UI: Registrar, Cashier, Department, Admin modules & role-based routing             | Registrar, Cashier, Department, Admin Modules   | XL        | COMPLETE |

> **Buffer & Demo Prep:** End-to-end multi-role integration run-through verified across all 6 core deliverable modules.

---

## Sprint 1 — Foundation + Auth

**Dates:** Sep 27 – Oct 3

**Demoable outcome:** A user can log in and receive a role-scoped token. A second POST with wrong credentials returns 401. A deactivated account returns 403.

**Requirements covered:** SETUP, FR-1

### Tasks

#### SETUP

- [x] SETUP-1 — Create the nine SSIS domain tables and their migrations in dependency order: `departments`, `users`, `students`, `courses`, `enrollments`, `payments`, `clearances`, `document_requests`, `audit_logs`. Add composite unique constraints per SPEC §4. (M)
- [x] SETUP-2 — Add `rate_per_unit_centavos` to `config/fees.php` (or `config/app.php`) and expose it via `.env`. Document the money-handling convention (integer centavos, never floats) in a code comment in that config file. (S)
- [x] SETUP-3 — Install and configure Laravel Sanctum. Configure CORS only for an explicitly cross-origin client; React/Inertia currently lives in this repository. (S)
- [x] SETUP-4 — Create Eloquent models for the nine SSIS domain tables. Add the `role` discriminator logic to `User` (no separate subtype models — STI via `role` column). Add `$fillable`, casts, and relationships per SPEC §4. (M)
- [x] SETUP-5 — Create per-role Policy classes (StudentPolicy, RegistrarPolicy, CashierPolicy, DepartmentStaffPolicy, AdminPolicy) with a base `role` check. Wire them to `AuthServiceProvider`. (S)
- [x] SETUP-6 — Add a global middleware that checks `is_active` on every authenticated request and returns `403 ACCOUNT_DEACTIVATED` if false (SPEC §6 Edge Case 6, A-9). (S)
- [x] SETUP-7 — Add a global middleware that reads `role` live from `users.role` on every request (never cached in token) (A-8). (S)
- [x] SETUP-8 — Create database seeders for departments and a set of test users (one per role, plus 2–3 students). (S)
- [x] SETUP-9 — Set up factories for all models. (S)

#### FR-1 — Auth

- [x] FR-1.1 — Create `POST /api/login`: accept `student_number_or_email` + `password`; look up by `student_number` (via `students` join) or `email` (direct on `users`); return `200 { token, role, redirect }` on success. (M)
- [x] FR-1.2 — Return `401 { error: "INVALID_CREDENTIALS" }` when credentials don't match. (S)
- [x] FR-1.3 — Return `403 { error: "ACCOUNT_DEACTIVATED" }` when credentials match but `is_active=false`. (S)
- [x] FR-1.4 — Create `POST /api/logout`: revoke only the current token; return 204. (S)
- [x] FR-1.5 — Apply default `throttle:api` (60 req/min/token) to all `/api/*` routes (A-6). (S)
- [x] FR-1.6 — Test: valid login returns 200 + token + role; wrong password returns 401; deactivated account returns 403 (maps directly to FR-1 acceptance criteria). (S)

### Definition of Done

- [x] All nine SSIS domain tables migrate cleanly on a fresh DB.
- [x] `POST /api/login` returns 200 with token and role for a valid active user.
- [x] Wrong password returns 401 `INVALID_CREDENTIALS`.
- [x] Deactivated account returns 403 `ACCOUNT_DEACTIVATED`.
- [x] `POST /api/logout` revokes the token (subsequent request returns 401).
- [x] Pint passes on all modified PHP files.

### Depends on

- Nothing.

---

## Sprint 2 — Student Portal + Registrar + Cashier (P0)

**Dates:** Oct 4 – Oct 10

**Demoable outcome:** A registrar can enroll a student (charges their balance atomically), encode a grade, and a cashier can record a payment that reduces the student's balance. The student can log in and see their own profile, subjects, and completed grades. A payment receipt URL is stable and accessible.

**Requirements covered:** FR-2, FR-3, FR-5, FR-6, FR-8, FR-9

### Tasks

#### FR-2 — Student Profile (P0)

- [x] FR-2.1 — Create `GET /api/student/profile`: return authenticated student's record (`student_number`, `program`, `year_level`, `status`, `balance_centavos`, `balance_formatted`). (S)
- [x] FR-2.2 — Test: authenticated student receives only their own row; wrong role returns 403. (S)

#### FR-3 — Subjects & Grades Read (P0)

- [x] FR-3.1 — Create `GET /api/student/subjects`: accept `school_year` + `semester`; return enrolled courses for that term. (S)
- [x] FR-3.2 — Create `GET /api/student/grades`: accept `school_year` + `semester`; return courses with `status=completed` and their grades for that term. (S)
- [x] FR-3.3 — Test: non-completed grades are omitted from the grades endpoint; another student's data is never returned. (S)

#### FR-5 — Registrar Enrollment (P0)

- [x] FR-5.1 — Create `POST /api/registrar/enrollments`: accept `{ student_id, course_id, school_year, semester }`. Run in a DB transaction: insert `enrollments` row (`status=enrolled`), compute `charge = course.units × rate_per_unit_centavos`, increment `students.balance_centavos` by `charge`. Return `201 { enrollment_id, charge_applied_centavos }`. (M)
- [x] FR-5.2 — Return `409 { error: "DUPLICATE_ENROLLMENT" }` if that student is already enrolled in that course for that term. (S)
- [x] FR-5.3 — Return `404 { error: "STUDENT_NOT_FOUND" }` / `{ error: "COURSE_NOT_FOUND" }` for invalid IDs. (S)
- [x] FR-5.4 — Test: enrollment increments balance atomically; duplicate enrollment returns 409 and does NOT increment balance a second time. (M)

#### FR-6 — Registrar Grade Encoding (P0)

- [x] FR-6.1 — Create `PATCH /api/registrar/enrollments/{id}/grade`: accept `{ grade }`. Validate against provisional grading scale in SPEC §4 (`1.00`, `1.25`, `1.50`, `1.75`, `2.00`, `2.25`, `2.50`, `2.75`, `3.00`, `5.00`, `INC`, `DRP`). Set `status=completed`, overwrite `grade`. Return `200 { enrollment_id, grade, status }`. (S)
- [x] FR-6.2 — SPEC §6 Edge Case 4: Grade must be in the allowed set. Return `422 { error: "INVALID_GRADE_RANGE" }` if outside that. (S)
- [x] FR-6.3 — Return `404 { error: "ENROLLMENT_NOT_FOUND" }` for unknown enrollment ID. (S)
- [x] FR-6.4 — Test: graded enrollment appears in FR-3 grades response; grade outside range returns 422; re-grading overwrites cleanly with no history. (S)

#### FR-8 — Cashier Payments (P0)

- [x] FR-8.1 — Create `POST /api/cashier/payments`: create a payment record and decrement `students.balance_centavos` by `amount_centavos`. Generate `or_number` inside the same DB transaction using a lock (not `MAX()+1` — Edge Case 2). Return `201 { or_number, receipt_url }`. (M)
- [x] FR-8.2 — Return `400 { error: "INVALID_AMOUNT" }` if `amount_centavos <= 0`. (S)
- [x] FR-8.3 — Return `404 { error: "STUDENT_NOT_FOUND" }` for unknown student. (S)
- [x] FR-8.4 — Test: balance decrements by payment amount; `or_number` is unique across concurrent cashiers; `amount_centavos=0` returns 400. (M)

#### FR-9 — Payment Receipt (P0)

- [x] FR-9.1 — Create `GET /receipts/{or_number}` as an authenticated printable HTML web response showing OR number, student name, amount, and `paid_at` (SPEC §5). (M)
- [x] FR-9.2 — The `receipt_url` in the FR-8 response must resolve for the student who owns the payment and for authorized cashier/admin users. Review query-string token support before production (SPEC §9 OQ-8). (S)
- [x] FR-9.3 — Return `404` for an unissued OR number. (S)
- [x] FR-9.4 — Test: receipt renders with correct student name and amount; unauthorized user gets 403. (S)

### Definition of Done

- [x] Student profile, subjects, and grades endpoints are covered by passing feature tests.
- [x] Registrar enrolls student A in course X; response includes `charge_applied_centavos = units × rate`; student A's balance increases by that amount.
- [x] Duplicate enrollment returns 409 with no second charge.
- [x] Registrar grades that enrollment; `grade=1.75`, `status=completed`.
- [x] Graded enrollment appears in student A's `GET /api/student/grades` response.
- [x] Cashier records a payment; `or_number` is unique; student's balance decrements.
- [x] Receipt URL is stable and renders correctly.
- [x] Registrar and cashier endpoints return 401 without a token and 403 for a wrong role.
- [x] Pint passes for the Sprint 2 changes.

### Depends on

- Sprint 1 (auth, models, seeders).

---

## Sprint 3 — Clearances + Document Requests + Admin (P0)

**Dates:** Oct 11 – Oct 17

**Demoable outcome:** Department staff can approve or deny a clearance. A student with incomplete clearances or an outstanding balance is blocked from requesting documents. Once cleared and zero balance, the request succeeds and the registrar can update its status. Admin can manage user accounts.

**Requirements covered:** FR-11, FR-13, FR-14, FR-15, FR-18, FR-19, FR-20

### Tasks

#### FR-11 — Department Clearances (P0)

- [x] FR-11.1 — Create `GET /api/department/clearances`: return clearances auto-scoped server-side to `auth()->user()->department_id`; optional `?status` filter. (S)
- [x] FR-11.2 — Create `PATCH /api/department/clearances/{id}`: accept `{ status: "approved"|"denied", remarks }`; overwrite `status`, `remarks`, `reviewed_by`, `reviewed_at` (last-write-wins, A-13). (S)
- [x] FR-11.3 — Return `403 { error: "UNAUTHORIZED_ROLE" }` if the clearance's `department_id` differs from the caller's `department_id`. (S)
- [x] FR-11.4 — Test: Library staff can patch Library clearances; Registrar Office staff get 403 on Library clearance; re-reviewing an already-approved clearance succeeds and overwrites with no history. (S)

#### FR-13 / FR-14 / FR-18 / FR-19 / FR-20 — Document Requests (P0)

- [x] FR-13.1 — Create a reusable `ClearanceGuard` (service method or invokable class) that checks all of a student's clearances for the current term are `approved`. Return `422 { error: "CLEARANCE_INCOMPLETE" }` if any are not. Used by FR-18 and FR-19. (S)
- [x] FR-14.1 — Create a reusable `BalanceGuard` that checks `students.balance_centavos = 0`. Return `422 { error: "OUTSTANDING_BALANCE" }` if not. Used by FR-18 and FR-19. (S)
- [x] FR-18.1 — Create `POST /api/documents/requests`: accept `{ type, purpose }`. Validate `type in (tor, cor, certification)`, `purpose` non-empty. Run `ClearanceGuard` and `BalanceGuard`. If both pass, insert `document_requests` row (`status=pending`, `requested_at=now()`). Return `201 { id, status: "pending" }`. (M)
- [x] FR-18.2 — Return `409 { error: "OPEN_REQUEST_EXISTS" }` if an open request of the same type already exists for this student (A-11). (S)
- [x] FR-18.3 — Return `422 { error: "OUTSTANDING_BALANCE" }` or `{ error: "CLEARANCE_INCOMPLETE" }` when guards fail. (S)
- [x] FR-19.1 — Create `PATCH /api/documents/requests/{id}/status`: accept `{ status }`. Validate against allowed transitions in SPEC §6 state diagram. Return `200 { id, status }`. Return `409 { error: "INVALID_STATUS_TRANSITION" }` for disallowed transitions. (M)
- [x] FR-19.2 — When transitioning to `released`, re-verify `ClearanceGuard` and `BalanceGuard` (A-10). If either fails, block transition with 422. If both pass, set `released_at=now()`, `processed_by=auth()->id()`. (S)
- [x] FR-20.1 — Create `GET /api/documents/requests`: return student's own requests newest-first. (S)
- [x] FR-20.2 — Test: student A can view their own requests; student B's requests don't appear. (S)

#### FR-15 — Admin User Management (P0)

- [x] FR-15.1 — Create `POST /api/admin/users`: create user with hashed password; `department_id` required iff `role=department_staff`. Return `201 { id, role }`. (S)
- [x] FR-15.2 — Return `409 { error: "EMAIL_TAKEN" }` on duplicate email. (S)
- [x] FR-15.3 — Create `PATCH /api/admin/users/{id}`: update `role`, `department_id`, `is_active`; no hard-delete ever (FR-15). Return `200 { id, role, is_active }`. (S)
- [x] FR-15.4 — Return `404 { error: "USER_NOT_FOUND" }` for unknown user ID. (S)
- [x] FR-15.5 — Append `audit_logs` row on every create/edit/deactivation (FR-17 data model — the log write is wired here even if the read endpoint lands in Sprint 4). (S)
- [x] FR-15.6 — Test: admin deactivates active cashier; cashier's very next request with their still-valid token returns 403 `ACCOUNT_DEACTIVATED` (SPEC acceptance criteria FR-15 + Edge Case 6). (M)
- [x] FR-15.7 — Test: audit log row is written on user create and on `is_active` toggle. (S)

### Definition of Done

- [x] Department staff can list and review their own clearances; cross-department patch returns 403.
- [x] Clearance is re-reviewable; subsequent PATCH overwrites with no error.
- [x] Student's document request blocked by incomplete clearance (422) and by balance > 0 (422); both clear → 201.
- [x] Duplicate open request of the same type → 409.
- [x] Blank `purpose` → 422.
- [x] Registrar can walk a request through valid states; terminal state transition → 409.
- [x] Admin creates a user; duplicate email → 409; deactivation is immediate on next request.
- [x] Audit log rows are written for every account change.
- [x] Pint passes.

### Depends on

- Sprint 1 (auth, models, middleware), Sprint 2 (balance tracking on students).

---

## Sprint 4 — P1 / P2 Features (API Endpoints)

**Dates:** Oct 18 – Oct 24

**Demoable outcome:** Registrar can pull a course roster. Cashier/Admin can see a student's payment history. Any authorized role can view a student's clearance status across all departments. Admin can reset passwords and view the audit log.

**Requirements covered:** FR-7, FR-10, FR-12, FR-16, FR-17

### Tasks

#### FR-7 — Course Roster (P1)

- [x] FR-7.1 — Create `GET /api/registrar/courses/{id}/roster`: accept required `school_year` + `semester`; return all enrolled students with their current grade/status. (S)
- [x] FR-7.2 — Return `404 { error: "COURSE_NOT_FOUND" }` for unknown course. (S)
- [x] FR-7.3 — Test: roster includes all enrolled students for the term; different-term enrollments don't appear. (S)

#### FR-10 — Payment History (P1)

- [x] FR-10.1 — Create `GET /api/cashier/students/{id}/payments`: return all payments newest-first; accessible by `role in (cashier, admin)`. (S)
- [x] FR-10.2 — Return `404 { error: "STUDENT_NOT_FOUND" }` for unknown student. (S)
- [x] FR-10.3 — Test: payments returned newest-first; non-cashier/admin role returns 403. (S)

#### FR-12 — Cross-Department Clearance View (P1)

- [x] FR-12.1 — Create `GET /api/students/{id}/clearances`: return clearances across all departments; accessible by `role in (department_staff, registrar, admin)` and the student themselves (self-only — return 403 for another student's ID). (S)
- [x] FR-12.2 — Test: student A token returns A's clearances; student A token for student B's ID returns 403; admin token returns any student's clearances. (S)

#### FR-16 — Admin Password Reset (P1)

- [x] FR-16.1 — Create `PATCH /api/admin/users/{id}/password`: replace the password hash and revoke all existing tokens for that user (A-5). Return 204. (S)
- [x] FR-16.2 — Test: after reset, all pre-existing tokens return 401; only a new login succeeds. (S)

#### FR-17 — Audit Log Read (P2)

- [x] FR-17.1 — Create `GET /api/admin/audit-logs`: return paginated logs; support optional filters `actor_id`, `target_type`, `page`. (S)
- [x] FR-17.2 — Confirm audit rows written in FR-15.5 (Sprint 3) appear correctly in this response. (S)
- [x] FR-17.3 — Test: admin can retrieve logs; non-admin returns 403. (S)

### Definition of Done

- [x] Course roster returns all enrolled students for the specified term.
- [x] Payment history returns payments newest-first; 403 for wrong role.
- [x] Student's own clearances visible to self, dept staff, registrar, admin; another student's record → 403.
- [x] Password reset revokes all prior tokens immediately.
- [x] Audit log is paginated and filterable by admin only.
- [x] Pint passes.

### Depends on

- Sprint 1 (auth, models), Sprint 2 (payments, enrollments), Sprint 3 (clearances, audit log writes).

---

## Sprint 5 — Staff & Admin UI Modules (Frontend Deliverables)

**Dates:** Oct 25 – Oct 31

**Demoable outcome:** University staff (Registrar, Cashier, Department Staff) and Administrators can log into their dedicated web portals in `ssis-cuyotech`, perform their operational duties, and interact seamlessly with the live CuyoTech API backend.

**Deliverables covered:** Registrar Module, Cashier Module, Department Module, Admin Module, Dynamic Navigation & Role Protection

### Tasks

#### 5.1 — Navigation & Role Routing (All Roles)

- [x] UI-AUTH.1 — Extend login to navigate to role-specific dashboard upon successful authentication (`/dashboard` for student, `/registrar` for registrar, `/cashier` for cashier, `/department` for department staff, `/admin` for admin).
- [x] UI-AUTH.2 — Update sidebar navigation to render modules corresponding to the active user's role.
- [x] UI-AUTH.3 — Add client-side route protection checking user role before rendering staff/admin pages.

#### 5.2 — Registrar Module UI (FR-5, FR-6, FR-7, FR-19)

- [x] UI-REG.1 — Create Student Enrollment view (`/registrar/enrollment`): select student and course, choose term, submit enrollment (`POST /api/registrar/enrollments`), and display applied per-unit balance charge.
- [x] UI-REG.2 — Create Grade Encoding view (`/registrar/grades`): select course enrollment, input valid grade (1.00–5.00), submit patch (`PATCH /api/registrar/enrollments/{id}/grade`).
- [x] UI-REG.3 — Create Course Roster view (`/registrar/roster`): filter by course and term (`GET /api/registrar/courses/{id}/roster`), view enrolled students, status, and encoded grades.
- [x] UI-REG.4 — Create Document Requests Processing view (`/registrar/requests`): list requests, advance status (`processing`, `ready`, `released`, `rejected`) using state machine rules.

#### 5.3 — Cashier Module UI (FR-8, FR-9, FR-10)

- [x] UI-CASH.1 — Create Payment Entry view (`/cashier/payments`): look up student, enter payment amount, select fee type (`tuition`, `misc_fee`, `document_fee`), submit payment (`POST /api/cashier/payments`).
- [x] UI-CASH.2 — Link and render official printable receipt (`/receipts/{or_number}`) showing unique OR number, student name, amount, and timestamp.
- [x] UI-CASH.3 — Create Student Payment History view (`/cashier/history`): search student, retrieve payment records newest-first (`GET /api/cashier/students/{id}/payments`).

#### 5.4 — Department Clearance Module UI (FR-11, FR-12)

- [x] UI-DEPT.1 — Create Department Clearance Review queue (`/department/clearances`): list clearances auto-scoped to staff department (`GET /api/department/clearances`), filter by status.
- [x] UI-DEPT.2 — Add Approve/Deny actions with optional remarks (`PATCH /api/department/clearances/{id}`).
- [x] UI-DEPT.3 — Multi-department clearance overview lookup for authorized staff (`GET /api/students/{id}/clearances`).

#### 5.5 — Admin Module UI (FR-15, FR-16, FR-17)

- [x] UI-ADM.1 — Create User Management view (`/admin/users`): list users, create account (`POST /api/admin/users`) with role & department, toggle `is_active` (`PATCH /api/admin/users/{id}`).
- [x] UI-ADM.2 — Add Admin Password Reset action (`PATCH /api/admin/users/{id}/password`).
- [x] UI-ADM.3 — Create Audit Log view (`/admin/audit-logs`): display paginated audit logs with actor, action, target, diff changes, and timestamp.

### Definition of Done

- [x] A registrar user can log in, enroll a student, and encode grades.
- [x] A cashier user can log in, record a payment, and open the printable receipt.
- [x] A department staff user can log in and approve/deny pending clearances for their department.
- [x] An admin user can log in, create a user, edit roles, deactivate an account, and view audit logs.
- [x] A student user can log in, view their updated balance, view grades, and request documents when cleared.
- [x] All 6 deliverable modules operate cleanly without console errors or broken navigation.

### Depends on

- Sprints 1–4 (backend API endpoints fully verified and live).

---

## Dependency Map

```
Sprint 1 (SETUP, FR-1)
  └─▶ Sprint 2 (FR-2, FR-3, FR-5, FR-6, FR-8, FR-9)
        └─▶ Sprint 3 (FR-11, FR-13, FR-14, FR-15, FR-18, FR-19, FR-20)
              └─▶ Sprint 4 (FR-7, FR-10, FR-12, FR-16, FR-17)
                    └─▶ Sprint 5 (Staff & Admin UI Modules: Registrar, Cashier, Dept, Admin)
```

## Requirement Coverage & Delivery Tracking

| ID    | Priority | Deliverable Module | Backend API | Frontend UI | Overall Status |
| ----- | -------- | ------------------ | ----------- | ----------- | -------------- |
| FR-1  | P0       | Core Auth          | DONE        | DONE        | DONE           |
| FR-2  | P0       | Student Portal     | DONE        | DONE        | DONE           |
| FR-3  | P0       | Student Portal     | DONE        | DONE        | DONE           |
| FR-4  | —        | Student Portal     | DROPPED     | DROPPED     | —              |
| FR-5  | P0       | Registrar Module   | DONE        | DONE        | DONE           |
| FR-6  | P0       | Registrar Module   | DONE        | DONE        | DONE           |
| FR-7  | P1       | Registrar Module   | DONE        | DONE        | DONE           |
| FR-8  | P0       | Cashier Module     | DONE        | DONE        | DONE           |
| FR-9  | P0       | Cashier Module     | DONE        | DONE        | DONE           |
| FR-10 | P1       | Cashier Module     | DONE        | DONE        | DONE           |
| FR-11 | P0       | Department Module  | DONE        | DONE        | DONE           |
| FR-12 | P1       | Department Module  | DONE        | DONE        | DONE           |
| FR-13 | P0       | Document Requests  | DONE        | DONE        | DONE           |
| FR-14 | P0       | Document Requests  | DONE        | DONE        | DONE           |
| FR-15 | P0       | Admin Module       | DONE        | DONE        | DONE           |
| FR-16 | P1       | Admin Module       | DONE        | DONE        | DONE           |
| FR-17 | P2       | Admin Module       | DONE        | DONE        | DONE           |
| FR-18 | P0       | Document Requests  | DONE        | DONE        | DONE           |
| FR-19 | P0       | Registrar / Docs   | DONE        | DONE        | DONE           |
| FR-20 | P1       | Student / Docs     | DONE        | DONE        | DONE           |
