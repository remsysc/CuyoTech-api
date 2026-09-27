# Sprint Plan — CuyoTech University SSIS

> Derived from: SPEC.md v1.1 | PRD.md | Date: 2026-09-27
> Every task traces to a requirement ID. Tasks without one are marked SETUP.
> Sizing: T-shirt (S / M / L / XL). Deadline: Oct 26.
> All P0 requirements land in Sprints 1–3. P1/P2 land in Sprint 4.

---

## Overview

| Sprint | Dates           | Goal                                                                                             | Requirements                                    | Est. Size |
| ------ | --------------- | ------------------------------------------------------------------------------------------------ | ----------------------------------------------- | --------- |
| 1      | Sep 27 – Oct 3  | Foundation: project setup, all migrations, auth                                                  | SETUP, FR-1                                     | L         |
| 2      | Oct 4 – Oct 10  | Student portal, registrar (enrollment + grading), cashier payments                               | FR-2, FR-3, FR-5, FR-6, FR-8, FR-9              | XL        |
| 3      | Oct 11 – Oct 17 | Department clearances, document requests, admin user management                                  | FR-11, FR-13, FR-14, FR-15, FR-18, FR-19, FR-20 | XL        |
| 4      | Oct 18 – Oct 24 | P1/P2: class roster, payment history, cross-department clearance view, password reset, audit log | FR-7, FR-10, FR-12, FR-16, FR-17                | L         |

> **Oct 25–26 — Buffer:** Integration testing, bug fixes, demo prep. No new features.

---

## Sprint 1 — Foundation + Auth

**Dates:** Sep 27 – Oct 3

**Demoable outcome:** A user can log in and receive a role-scoped token. A second POST with wrong credentials returns 401. A deactivated account returns 403.

**Requirements covered:** SETUP, FR-1

### Tasks

#### SETUP

- [x] SETUP-1 — Create all 8 migrations in dependency order: `departments`, `users`, `students`, `courses`, `enrollments`, `payments`, `clearances`, `document_requests`, `audit_logs`. Add composite unique constraints per SPEC §4. (M)
- [x] SETUP-2 — Add `rate_per_unit_centavos` to `config/fees.php` (or `config/app.php`) and expose it via `.env`. Document the money-handling convention (integer centavos, never floats) in a code comment in that config file. (S)
- [x] SETUP-3 — Install and configure Laravel Sanctum. Configure CORS (`config/cors.php`) to allow the React repo's origin via an env var (`FRONTEND_URL`). (S)
- [x] SETUP-4 — Create Eloquent models for all 8 tables. Add the `role` discriminator logic to `User` (no separate subtype models — STI via `role` column). Add `$fillable`, casts, and relationships per SPEC §4. (M)
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

- [x] All 8 migrations run cleanly on a fresh DB.
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

#### FR-2 — Student Profile

- [ ] FR-2.1 — Create `GET /api/student/profile`: return `{ student_number, name, program, year_level, status }` scoped to the authenticated student only. (S)
- [ ] FR-2.2 — Return 403 if a non-student or another student's token is used (ownership enforced by reading `auth()->user()->student->id`). (S)
- [ ] FR-2.3 — Test: student A's token returns only A's profile; non-student role returns 403. (S)

#### FR-3 — Student Subjects & Grades

- [ ] FR-3.1 — Create `GET /api/student/subjects`: accept required `school_year` + `semester` query params; return `[{ course_code, title, units, status }]` scoped to the authenticated student for that term. (S)
- [ ] FR-3.2 — Create `GET /api/student/grades`: same params; return `[{ course_code, title, grade }]` for `enrollments.status=completed` rows only. (S)
- [ ] FR-3.3 — Return `400 { error: "MISSING_TERM" }` if `school_year` or `semester` is absent on either endpoint (A-1). (S)
- [ ] FR-3.4 — Test: only completed enrollments appear in grades; in-progress enrollment is absent; missing params return 400. (S)

#### FR-5 — Registrar Enrollment

- [ ] FR-5.1 — Create `POST /api/registrar/enrollments`: create enrollment with `status=enrolled`; charge `course.units × config('fees.rate_per_unit_centavos')` to `students.balance_centavos` in a single DB transaction (SPEC §6 Edge Case 11). Return `201 { id, status: "enrolled", charge_applied_centavos }`. (M)
- [ ] FR-5.2 — Return `404 { error: "STUDENT_NOT_FOUND" }` or `{ error: "COURSE_NOT_FOUND" }` when IDs don't resolve (Edge Case 8). (S)
- [ ] FR-5.3 — Return `409 { error: "ALREADY_ENROLLED" }` on duplicate `(student_id, course_id, school_year, semester)` with no second charge applied. (S)
- [ ] FR-5.4 — Test: balance increases by `units × rate`; duplicate attempt returns 409 with no second charge; transaction rolls back if either operation fails (crash safety). (M)

#### FR-6 — Grade Encoding

- [ ] FR-6.1 — Create `PATCH /api/registrar/enrollments/{id}/grade`: set `grade` and `status=completed`, overwriting any previous value (no history, A-3). Return `200 { id, grade, status: "completed" }`. (S)
- [ ] FR-6.2 — Validate grade is in `1.00–5.00` range in `0.25` steps (A-7). Return `422 { error: "INVALID_GRADE_RANGE" }` if outside that. (S)
- [ ] FR-6.3 — Return `404 { error: "ENROLLMENT_NOT_FOUND" }` for unknown enrollment ID. (S)
- [ ] FR-6.4 — Test: graded enrollment appears in FR-3 grades response; grade outside range returns 422; re-grading overwrites cleanly with no history. (S)

#### FR-8 — Cashier Payments

- [ ] FR-8.1 — Create `POST /api/cashier/payments`: create a payment record and decrement `students.balance_centavos` by `amount_centavos`. Generate `or_number` inside the same DB transaction using a lock (not `MAX()+1` — Edge Case 2). Return `201 { or_number, receipt_url }`. (M)
- [ ] FR-8.2 — Return `400 { error: "INVALID_AMOUNT" }` if `amount_centavos <= 0`. (S)
- [ ] FR-8.3 — Return `404 { error: "STUDENT_NOT_FOUND" }` for unknown student. (S)
- [ ] FR-8.4 — Test: balance decrements by payment amount; `or_number` is unique across concurrent cashiers; `amount_centavos=0` returns 400. (M)

#### FR-9 — Payment Receipt

- [ ] FR-9.1 — Create a stable receipt route (e.g. `GET /receipts/{or_number}`) returning a simple HTML/PDF view showing OR number, student name, amount, and `paid_at`. (M)
- [ ] FR-9.2 — The `receipt_url` in the FR-8 response must resolve correctly for any client with a valid token. (S)

### Definition of Done

- [ ] Registrar enrolls student A in course X; response includes `charge_applied_centavos = units × rate`; student A's balance increases by that amount.
- [ ] Duplicate enrollment returns 409 with no second charge.
- [ ] Registrar grades that enrollment; `grade=1.75`, `status=completed`.
- [ ] Graded enrollment appears in student A's `GET /api/student/grades` response.
- [ ] Cashier records a payment; `or_number` is unique; student's balance decrements.
- [ ] Receipt URL is stable and renders correctly.
- [ ] Missing term params return 400 on student endpoints.
- [ ] All new endpoints return 401 without a token, 403 for wrong role.
- [ ] Pint passes.

### Depends on

- Sprint 1 (auth, models, migrations, policies).

---

## Sprint 3 — Clearances + Document Requests + Admin (P0)

**Dates:** Oct 11 – Oct 17

**Demoable outcome:** Department staff can approve/deny their own department's clearances. A student can submit a document request only when balance is zero and all clearances are approved. A registrar can move requests through the state machine. Admin can create users and deactivate them; deactivation takes effect on the very next request.

**Requirements covered:** FR-11, FR-13, FR-14, FR-15, FR-18, FR-19, FR-20

### Tasks

#### FR-11 — Clearance Review

- [ ] FR-11.1 — Create `GET /api/department/clearances`: return clearances auto-scoped server-side to `auth()->user()->department_id`; optional `?status` filter. (S)
- [ ] FR-11.2 — Create `PATCH /api/department/clearances/{id}`: accept `{ status: "approved"|"denied", remarks }`; overwrite `status`, `remarks`, `reviewed_by`, `reviewed_at` (last-write-wins, A-13). (S)
- [ ] FR-11.3 — Return `403 { error: "UNAUTHORIZED_ROLE" }` if the clearance's `department_id` differs from the caller's `department_id`. (S)
- [ ] FR-11.4 — Test: Library staff can patch Library clearances; Registrar Office staff get 403 on Library clearance; re-reviewing an already-approved clearance succeeds and overwrites with no history. (S)

#### FR-13 + FR-14 — Clearance & Balance Guard

- [ ] FR-13.1 — Create a reusable `ClearanceGuard` (service method or invokable class) that checks all of a student's clearances for the current term are `approved`. Return `422 { error: "CLEARANCE_INCOMPLETE" }` if any are not. Used by FR-18 and FR-19. (S)
- [ ] FR-14.1 — Create a reusable `BalanceGuard` that checks `students.balance_centavos = 0`. Return `422 { error: "OUTSTANDING_BALANCE" }` if not. Used by FR-18 and FR-19. (S)

#### FR-18 — Student Document Request

- [ ] FR-18.1 — Create `POST /api/documents/requests`: run `ClearanceGuard` + `BalanceGuard`; create with `status=pending`. Return `201 { id, status: "pending" }`. (S)
- [ ] FR-18.2 — Return `409 { error: "DUPLICATE_REQUEST" }` if an open request of the same `type` (pending/processing/ready) already exists (Edge Case 3). (S)
- [ ] FR-18.3 — Return `422 VALIDATION_FAILED` for blank/whitespace-only `purpose` (Edge Case 7). (S)
- [ ] FR-18.4 — Test: submit with incomplete clearance → 422; submit with balance > 0 → 422; submit when both clear → 201; second request of same type while first is open → 409. (M)

#### FR-19 + FR-20 — Registrar Document Status Transitions

- [ ] FR-19.1 — Create `PATCH /api/documents/requests/{id}/status`: validate the transition against the state machine (`pending→processing→ready→released`, `pending→rejected`, `processing→rejected`; terminal states block all transitions). Return `409 { error: "INVALID_TRANSITION" }` for invalid moves. (M)
- [ ] FR-19.2 — Re-check `ClearanceGuard` + `BalanceGuard` only on transition into `released` (SPEC §5). (S)
- [ ] FR-19.3 — Test: valid transitions succeed; `released→processing` returns 409; `released→released` returns 409; transitioning to `released` with incomplete clearance returns 422. (M)
- [ ] FR-20.1 — Verify the student's next `GET` (via existing student profile or a new status endpoint) reflects the updated status — no push; pure polling (non-goal for notifications). (S)

#### FR-15 — Admin User Management

- [ ] FR-15.1 — Create `POST /api/admin/users`: create user with hashed password; `department_id` required iff `role=department_staff`. Return `201 { id, role }`. (S)
- [ ] FR-15.2 — Return `409 { error: "EMAIL_TAKEN" }` on duplicate email. (S)
- [ ] FR-15.3 — Create `PATCH /api/admin/users/{id}`: update `role`, `department_id`, `is_active`; no hard-delete ever (FR-15). Return `200 { id, role, is_active }`. (S)
- [ ] FR-15.4 — Return `404 { error: "USER_NOT_FOUND" }` for unknown user ID. (S)
- [ ] FR-15.5 — Append `audit_logs` row on every create/edit/deactivation (FR-17 data model — the log write is wired here even if the read endpoint lands in Sprint 4). (S)
- [ ] FR-15.6 — Test: admin deactivates active cashier; cashier's very next request with their still-valid token returns 403 `ACCOUNT_DEACTIVATED` (SPEC acceptance criteria FR-15 + Edge Case 6). (M)
- [ ] FR-15.7 — Test: audit log row is written on user create and on `is_active` toggle. (S)

### Definition of Done

- [ ] Department staff can list and review their own clearances; cross-department patch returns 403.
- [ ] Clearance is re-reviewable; subsequent PATCH overwrites with no error.
- [ ] Student's document request blocked by incomplete clearance (422) and by balance > 0 (422); both clear → 201.
- [ ] Duplicate open request of the same type → 409.
- [ ] Blank `purpose` → 422.
- [ ] Registrar can walk a request through valid states; terminal state transition → 409.
- [ ] Admin creates a user; duplicate email → 409; deactivation is immediate on next request.
- [ ] Audit log rows are written for every account change.
- [ ] Pint passes.

### Depends on

- Sprint 1 (auth, models, policies), Sprint 2 (balance logic via FR-5/FR-8).

---

## Sprint 4 — P1 / P2 Features + Buffer

**Dates:** Oct 18 – Oct 24

**Demoable outcome:** Registrar can pull a course roster. Cashier/Admin can see a student's payment history. Any authorized role can view a student's clearance status across all departments. Admin can reset passwords and view the audit log.

**Requirements covered:** FR-7, FR-10, FR-12, FR-16, FR-17

> **Oct 25–26** — Integration testing, end-to-end demo run-through, bug fixes. No new features.

### Tasks

#### FR-7 — Course Roster (P1)

- [ ] FR-7.1 — Create `GET /api/registrar/courses/{id}/roster`: accept required `school_year` + `semester`; return all enrolled students with their current grade/status. (S)
- [ ] FR-7.2 — Return `404 { error: "COURSE_NOT_FOUND" }` for unknown course. (S)
- [ ] FR-7.3 — Test: roster includes all enrolled students for the term; different-term enrollments don't appear. (S)

#### FR-10 — Payment History (P1)

- [ ] FR-10.1 — Create `GET /api/cashier/students/{id}/payments`: return all payments newest-first; accessible by `role in (cashier, admin)`. (S)
- [ ] FR-10.2 — Return `404 { error: "STUDENT_NOT_FOUND" }` for unknown student. (S)
- [ ] FR-10.3 — Test: payments returned newest-first; non-cashier/admin role returns 403. (S)

#### FR-12 — Cross-Department Clearance View (P1)

- [ ] FR-12.1 — Create `GET /api/students/{id}/clearances`: return clearances across all departments; accessible by `role in (department_staff, registrar, admin)` and the student themselves (self-only — return 403 for another student's ID). (S)
- [ ] FR-12.2 — Test: student A token returns A's clearances; student A token for student B's ID returns 403; admin token returns any student's clearances. (S)

#### FR-16 — Admin Password Reset (P1)

- [ ] FR-16.1 — Create `PATCH /api/admin/users/{id}/password`: replace the password hash and revoke all existing tokens for that user (A-5). Return 204. (S)
- [ ] FR-16.2 — Test: after reset, all pre-existing tokens return 401; only a new login succeeds. (S)

#### FR-17 — Audit Log Read (P2)

- [ ] FR-17.1 — Create `GET /api/admin/audit-logs`: return paginated logs; support optional filters `actor_id`, `target_type`, `page`. (S)
- [ ] FR-17.2 — Confirm audit rows written in FR-15.5 (Sprint 3) appear correctly in this response. (S)
- [ ] FR-17.3 — Test: admin can retrieve logs; non-admin returns 403. (S)

### Definition of Done

- [ ] Course roster returns all enrolled students for the specified term.
- [ ] Payment history returns payments newest-first; 403 for wrong role.
- [ ] Student's own clearances visible to self, dept staff, registrar, admin; another student's record → 403.
- [ ] Password reset revokes all prior tokens immediately.
- [ ] Audit log is paginated and filterable by admin only.
- [ ] Pint passes.

### Depends on

- Sprint 1 (auth, models), Sprint 2 (payments, enrollments), Sprint 3 (clearances, audit log writes).

---

## Dependency Map

```
Sprint 1 (SETUP, FR-1)
  └─▶ Sprint 2 (FR-2, FR-3, FR-5, FR-6, FR-8, FR-9)
        └─▶ Sprint 3 (FR-11, FR-13, FR-14, FR-15, FR-18, FR-19, FR-20)
              └─▶ Sprint 4 (FR-7, FR-10, FR-12, FR-16, FR-17)
```

## Requirement Coverage

| ID    | Priority | Sprint | Status  |
| ----- | -------- | ------ | ------- |
| FR-1  | P0       | 1      | DONE    |
| FR-2  | P0       | 2      | —       |
| FR-3  | P0       | 2      | —       |
| FR-4  | —        | —      | DROPPED |
| FR-5  | P0       | 2      | —       |
| FR-6  | P0       | 2      | —       |
| FR-7  | P1       | 4      | —       |
| FR-8  | P0       | 2      | —       |
| FR-9  | P0       | 2      | —       |
| FR-10 | P1       | 4      | —       |
| FR-11 | P0       | 3      | —       |
| FR-12 | P1       | 4      | —       |
| FR-13 | P0       | 3      | —       |
| FR-14 | P0       | 3      | —       |
| FR-15 | P0       | 3      | —       |
| FR-16 | P1       | 4      | —       |
| FR-17 | P2       | 4      | —       |
| FR-18 | P0       | 3      | —       |
| FR-19 | P0       | 3      | —       |
| FR-20 | P1       | 3      | —       |
