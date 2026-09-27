# PRD — CuyoTech University Student Services Information System (SSIS)

> Status: Draft | Date: 2026-09-27 | Owner: Rem

## 1. Global Engineering Constraints

- **Stack:** Laravel 13.x (PHP 8.3+) as an API-only backend, MySQL 8 for the database, React SPA in a **separate repo** consuming the API over `/api/*`, Laravel Sanctum for token-based auth (session auth doesn't work cleanly across two repos/origins). — CRITICAL FOR AGENTS
- **Standards:** snake_case DB columns / plural table names (Laravel convention), all `/api/*` routes return JSON only (no Blade views), REST-ish route naming per module (`/api/registrar/...`, `/api/cashier/...`, etc.), all money stored as integers (centavos) to avoid float rounding errors, all destructive actions gated by policy classes per role, CORS configured on the Laravel side to allow the React repo's origin. **Role authorization is server-side only** — Laravel policies/middleware are the actual gate on every request; React reads `role` from the login response purely to drive dashboard routing/UI and treats a 403 as a fallback signal, never as the primary control.

_Assumption:_ the case study doesn't name a DB engine — MySQL is the default, lowest-friction choice for a 5-person Laravel course project. Swap to PostgreSQL if you want parity with your other stack. Since the frontend is a separate repo, treat this PRD's backend sections (7 and 8) as the contract both repos build against — the React repo shouldn't need its own copy of this document, just section 8. **SPEC.md is the fully-detailed implementation contract** (every response code, edge case, and resolved assumption) — this PRD stays intentionally lighter; if the two ever disagree, SPEC.md wins.

## 2. Problem Statement

CuyoTech University's student services — enrollment, clearance, grade viewing, payments, and document requests (TOR/COR/Certifications) — are handled manually, forcing students to queue physically for each transaction. The university needs a single system so students, the registrar, the cashier, and department staff can handle these workflows online instead of in person.

## 3. Goals

- Students can enroll, view grades/subjects, pay fees, and request documents without a physical queue.
- Registrar, cashier, and department staff each get a role-scoped dashboard for their part of the workflow.
- A document request (TOR/COR/Certification) can't be released until clearance and payment are both settled — the system enforces this automatically instead of relying on manual checks.
- Admin can manage accounts/roles without touching the database directly.

## 4. Non-Goals (Out of Scope for this prototype)

- Real payment gateway integration (GCash/PayMongo/bank). Cashier records payments manually; no live transaction processing.
- SMS/email notifications — in-app status only.
- A mobile app — web only.
- Multi-campus / multi-branch support.
- Automated GWA computation or latin-honors logic.

_Assumption:_ these are excluded to fit the Oct 26 deadline with 5 people covering 6 modules. State this explicitly as an assumption in the term paper per the "justify your assumptions" note.

## 5. Target Users

- **Primary:** Students (view profile/grades/subjects, pay fees, request documents).
- **Secondary:** Registrar staff (enrollment, grade encoding, document processing), Cashier staff (payments, receipts), Department staff (clearance approval/denial), Admin (account/role management).

## 6. Functional Requirements

| ID    | Requirement                                                                                                                                                                             | Priority |
| ----- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------- |
| FR-1  | Student can log in with student number + password                                                                                                                                       | P0       |
| FR-2  | Student can view their profile                                                                                                                                                          | P0       |
| FR-3  | Student can view enrolled subjects and grades per semester                                                                                                                              | P0       |
| FR-4  | ~~Student can view a computed running GWA~~ — **dropped from scope**, needed an undefined grading-scale/rounding decision (see SPEC.md §9)                                              | ~~P2~~   |
| FR-5  | Registrar can enroll a student into a course section for a school year/semester — **enrolling automatically charges the student's balance** (`units × a flat per-unit rate`, see §7/§8) | P0       |
| FR-6  | Registrar can encode/edit a student's grade per course                                                                                                                                  | P0       |
| FR-7  | Registrar can generate a class list per course section                                                                                                                                  | P1       |
| FR-8  | Cashier can record a payment against a student                                                                                                                                          | P0       |
| FR-9  | System generates a printable receipt (with OR number) on payment                                                                                                                        | P0       |
| FR-10 | Cashier can view a student's payment history                                                                                                                                            | P1       |
| FR-11 | Department staff can approve/deny a clearance request scoped to their own department — **revisable indefinitely**, no history kept (same as grade edits)                                | P0       |
| FR-12 | Department staff can view a student's clearance status across all departments                                                                                                           | P1       |
| FR-13 | System blocks document release until all department clearances for that student are approved                                                                                            | P0       |
| FR-14 | System blocks new document requests if the student has an outstanding balance                                                                                                           | P0       |
| FR-15 | Admin can create/edit/deactivate accounts and assign roles                                                                                                                              | P0       |
| FR-16 | Admin can reset a user's password                                                                                                                                                       | P1       |
| FR-17 | Admin can view an audit log of account changes                                                                                                                                          | P2       |
| FR-18 | Student can submit a document request (TOR, COR, Certification) with a stated purpose                                                                                                   | P0       |
| FR-19 | Registrar can move a document request through status (pending → processing → ready → released)                                                                                          | P0       |
| FR-20 | Student sees their document request status update in their portal                                                                                                                       | P1       |

## 7. Database Schema

**users** (base account — role via single-table inheritance)

| Column        | Type                                                       | Notes                                                                            |
| ------------- | ---------------------------------------------------------- | -------------------------------------------------------------------------------- |
| id            | bigint PK                                                  |                                                                                  |
| name          | varchar                                                    |                                                                                  |
| email         | varchar unique                                             |                                                                                  |
| password      | varchar                                                    | hashed                                                                           |
| role          | enum(student, registrar, cashier, department_staff, admin) | discriminator                                                                    |
| department_id | bigint FK → departments, nullable                          | set when role = department_staff                                                 |
| is_active     | boolean, default true                                      | flips false on deactivation — never delete a user row, other tables reference it |
| timestamps    |                                                            |                                                                                  |

**students** (1:1 with users where role=student)

| Column           | Type                              | Notes                                                                               |
| ---------------- | --------------------------------- | ----------------------------------------------------------------------------------- |
| id               | bigint PK                         |                                                                                     |
| user_id          | bigint FK → users, unique         |                                                                                     |
| student_number   | varchar unique                    |                                                                                     |
| program          | varchar                           |                                                                                     |
| year_level       | tinyint                           |                                                                                     |
| status           | enum(active, on_leave, graduated) |                                                                                     |
| balance_centavos | bigint, default 0                 | amount currently owed; increased on enrollment (FR-5), decreased by payments (FR-8) |
| timestamps       |                                   |                                                                                     |

**departments**

| Column     | Type           | Notes |
| ---------- | -------------- | ----- |
| id         | bigint PK      |       |
| name       | varchar        |       |
| code       | varchar unique |       |
| timestamps |                |       |

**courses**

| Column        | Type                    | Notes |
| ------------- | ----------------------- | ----- |
| id            | bigint PK               |       |
| department_id | bigint FK → departments |       |
| code          | varchar unique          |       |
| title         | varchar                 |       |
| units         | tinyint                 |       |
| timestamps    |                         |       |

**enrollments** (association: student ↔ course, per term)

| Column      | Type                               | Notes                                                |
| ----------- | ---------------------------------- | ---------------------------------------------------- |
| id          | bigint PK                          |                                                      |
| student_id  | bigint FK → students               |                                                      |
| course_id   | bigint FK → courses                |                                                      |
| school_year | varchar                            | e.g. "2026-2027"                                     |
| semester    | tinyint                            |                                                      |
| grade       | decimal(3,2) nullable              |                                                      |
| status      | enum(enrolled, completed, dropped) |                                                      |
| timestamps  |                                    | unique(student_id, course_id, school_year, semester) |

**payments** (composition: owned by a student)

| Column          | Type                                  | Notes                   |
| --------------- | ------------------------------------- | ----------------------- |
| id              | bigint PK                             |                         |
| student_id      | bigint FK → students                  |                         |
| cashier_id      | bigint FK → users                     | must have role=cashier  |
| amount_centavos | bigint                                | store money as integer  |
| or_number       | varchar unique                        | official receipt number |
| payment_type    | enum(tuition, misc_fee, document_fee) |                         |
| paid_at         | timestamp                             |                         |
| timestamps      |                                       |                         |

**clearances** (composition: owned by a student)

| Column        | Type                            | Notes                                                    |
| ------------- | ------------------------------- | -------------------------------------------------------- |
| id            | bigint PK                       |                                                          |
| student_id    | bigint FK → students            |                                                          |
| department_id | bigint FK → departments         |                                                          |
| school_year   | varchar                         |                                                          |
| semester      | tinyint                         |                                                          |
| status        | enum(pending, approved, denied) |                                                          |
| remarks       | varchar nullable                |                                                          |
| reviewed_by   | bigint FK → users, nullable     | must have role=department_staff                          |
| reviewed_at   | timestamp nullable              |                                                          |
| timestamps    |                                 | unique(student_id, department_id, school_year, semester) |

**document_requests** (composition: owned by a student)

| Column       | Type                                                 | Notes                    |
| ------------ | ---------------------------------------------------- | ------------------------ |
| id           | bigint PK                                            |                          |
| student_id   | bigint FK → students                                 |                          |
| type         | enum(tor, cor, certification)                        |                          |
| purpose      | varchar                                              |                          |
| status       | enum(pending, processing, ready, released, rejected) |                          |
| requested_at | timestamp                                            |                          |
| released_at  | timestamp nullable                                   |                          |
| processed_by | bigint FK → users, nullable                          | must have role=registrar |
| timestamps   |                                                      |                          |

**audit_logs** (needed for FR-17 — not in Task 2C's required entity list, add it anyway for the account-audit requirement)

| Column      | Type              | Notes                                           |
| ----------- | ----------------- | ----------------------------------------------- |
| id          | bigint PK         |                                                 |
| actor_id    | bigint FK → users | who made the change                             |
| action      | varchar           | e.g. "user.created", "user.role_changed"        |
| target_type | varchar           | "user" in v1 — only account changes are audited |
| target_id   | bigint            |                                                 |
| changes     | json nullable     | before/after diff                               |
| created_at  | timestamp         |                                                 |

**Config value (not a table):** `rate_per_unit_centavos` — a single flat per-unit tuition rate, set in application config, not the database. Every enrollment charges `course.units × rate_per_unit_centavos` to `students.balance_centavos`.

**Class-diagram relationships this satisfies (Task 2C):**

- **Inheritance** — `User` is the base type; `Student`, `Registrar`, `Cashier`, `DepartmentStaff`, `AdminUser` are role subtypes (implemented as single-table inheritance via `role`, not separate tables — call this out as a deliberate simplification in your paper).
- **Association** — `Student` ↔ `Course` through `Enrollment`.
- **Aggregation** — `Department` has `Course`s (a course belongs to a department but isn't destroyed if the department record changes).
- **Composition** — `Student` owns `Payment`, `Clearance`, and `DocumentRequest` records; these have no meaning without their parent student and should cascade-delete with it.

## 8. API Contracts

```
POST /api/login
Request:  { "student_number_or_email": "2023-00123", "password": "..." }
Response: { "token": "...", "role": "student", "redirect": "/student/dashboard" }

POST /api/logout
Request:  (no body — Bearer token in Authorization header)
Response: 204 No Content
  -> revokes the current Sanctum token
```

```
GET /api/student/profile
Response: { "student_number": "2023-00123", "name": "...", "program": "BSCS", "year_level": 3, "status": "active" }

GET /api/student/subjects?school_year=2026-2027&semester=1
Response: [ { "course_code": "CCS112", "title": "...", "units": 3, "status": "enrolled" } ]

GET /api/student/grades?school_year=2026-2027&semester=1
Response: [ { "course_code": "CCS112", "title": "...", "grade": 1.75 } ]
  -> only returns rows where enrollments.status = "completed"
```

```
POST /api/registrar/enrollments
Request:  { "student_id": 14, "course_id": 7, "school_year": "2026-2027", "semester": 1 }
Response: { "id": 88, "status": "enrolled", "charge_applied_centavos": 900000 }
  -> charge_applied_centavos = course.units × rate_per_unit_centavos, added to the student's balance in the same transaction

PATCH /api/registrar/enrollments/{id}/grade
Request:  { "grade": 1.75 }
Response: { "id": 88, "grade": 1.75, "status": "completed" }

GET /api/registrar/courses/{id}/roster?school_year=2026-2027&semester=1
Response: [ { "student_id": 14, "student_number": "2023-00123", "name": "...", "grade": null, "status": "enrolled" } ]
```

```
POST /api/cashier/payments
Request:  { "student_id": 14, "amount_centavos": 500000, "payment_type": "tuition" }
Response: { "or_number": "OR-2026-000451", "receipt_url": "/receipts/451.pdf" }
```

```
PATCH /api/department/clearances/{id}
Request:  { "status": "approved", "remarks": "No outstanding items." }
Response: { "id": 22, "status": "approved", "reviewed_at": "2026-10-01T09:00:00Z" }
```

```
POST /api/documents/requests
Request:  { "type": "tor", "purpose": "Job application" }
Response: { "id": 61, "status": "pending" }
  -> 422 if student has an outstanding balance or an unapproved clearance

PATCH /api/documents/requests/{id}/status
Request:  { "status": "ready" }
Response: { "id": 61, "status": "ready" }
```

```
POST /api/admin/users
Request:  { "name": "...", "email": "...", "role": "cashier", "password": "..." }
Response: { "id": 9, "role": "cashier" }

PATCH /api/admin/users/{id}
Request:  { "role": "department_staff", "department_id": 3, "is_active": false }
Response: { "id": 9, "role": "department_staff", "is_active": false }
  -> deactivation is a soft flag, not a delete — payments/clearances reference this user and must not be orphaned

PATCH /api/admin/users/{id}/password
Request:  { "new_password": "..." }
Response: 204 No Content
```

## 9. Success Metrics

- All 6 modules are demoable end-to-end in the video walkthrough (rubric item 3, 40 pts).
- A student enrolled and graded by the Registrar sees that grade correctly on their Student Portal.
- A payment recorded by the Cashier reduces the student's outstanding balance and unblocks document requests.
- A document request is blocked until (a) balance is zero and (b) all department clearances are approved — demoable as a rejected request and then a successful one.
- All 3 identified security vulnerabilities (Task 4) each have a working mitigation demonstrated, not just described.
- Zero P0 defects open at submission.

## 10. Active Feature Development

<pending_tasks>

- [ ] Architect: Write migrations for the 8 tables above (incl. `audit_logs` and `students.balance_centavos`); set up `User` role-based model access (STI pattern) and per-role Laravel policies
- [ ] Architect: Add `rate_per_unit_centavos` to application config; wire enrollment creation to charge it atomically (see SPEC.md Edge Case 11)
- [ ] Architect: Install/configure Sanctum, set up CORS for the React repo's origin
- [ ] Architect: Decide and document the money-handling convention (integer centavos) project-wide
- [ ] Backend: Student Portal endpoints (profile, grades, subjects) — FR-1–4
- [ ] Backend: Registrar endpoints (enrollment, grade encoding, class list) — FR-5–7
- [ ] Backend: Cashier endpoints (payments, receipt PDF, payment history) — FR-8–10
- [ ] Backend: Department clearance endpoints — FR-11–13
- [ ] Backend: Document request workflow + balance/clearance guard — FR-14, FR-18–20
- [ ] Backend: Admin user management + audit log — FR-15–17
- [ ] Frontend (separate repo): Login page + token storage + role-based dashboard redirect
- [ ] Frontend (separate repo): Student dashboard (profile, grades, subjects)
- [ ] Frontend (separate repo): Document request page + status tracker
- [ ] QA: Test cases for Student Login (unit), Registrar↔Student Portal (integration), full SSIS flow (system), 3 security test cases (Task 4)
      </pending_tasks>
