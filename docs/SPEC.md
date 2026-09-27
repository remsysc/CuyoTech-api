# SPEC — CuyoTech University Student Services Information System (SSIS)

> Status: Draft | Version: 1.1 | Date: 2026-09-27
> Source of truth for implementation. See PRD.md for problem/goals/rationale — not restated here.
> Any behavior not covered here is an open question, not a green light to assume.

## 1. Scope

This spec covers the Laravel API backend and shared data model for the SSIS prototype — Student Portal, Registrar, Cashier, Department, Admin, and Document Request modules — consumed by a React SPA in a separate repo. The React repo builds against §5 only. §4 (data models) and §6 (edge cases) are backend-internal but explain *why* §5 responds the way it does.

## 2. Non-Goals

- No real payment gateway integration; cashier records payments manually.
- No SMS/email notifications — in-app status only.
- No mobile app.
- No multi-campus support.
- No automated GWA/latin-honors computation — FR-4 is dropped from prototype scope (resolved, see §9).
- No self-registration — all accounts, including students, are created by Admin (FR-15). There is no public signup endpoint.
- No multi-role accounts — a user has exactly one `role` at a time; changing it (`PATCH /api/admin/users/{id}`) overwrites it, doesn't add to it.
- No history kept on grade edits (FR-6) or clearance re-reviews (FR-11) — only account changes are audited (FR-17). Both grades and clearances are last-write-wins by design (resolved, see §9 OQ-4).
- No refund/reversal flow for dropped enrollments — `enrollments.status=dropped` exists in the schema but no endpoint transitions to it in this spec (A-14).
- No optimistic locking on any table — concurrent edits are last-write-wins (A-3).
- No token refresh flow — Sanctum tokens don't auto-expire (A-4).

## 3. Requirements (EARS)

| ID | Requirement |
|----|-------------|
| FR-1 | WHEN a client POSTs valid `student_number_or_email` + `password` to `/api/login` THE SYSTEM SHALL return 200 with a token and role. IF the credentials don't match THEN THE SYSTEM SHALL return 401. IF the matched account has `is_active=false` THEN THE SYSTEM SHALL return 403. |
| FR-2 | WHEN an authenticated student GETs `/api/student/profile` THE SYSTEM SHALL return only that student's own profile. |
| FR-3 | WHEN an authenticated student GETs `/api/student/subjects` or `/api/student/grades` with `school_year`+`semester` THE SYSTEM SHALL return only their own rows for that term; grades are returned only for `enrollments.status=completed`. |
| FR-4 | **DROPPED** — GWA computation is out of scope for this prototype (resolved 2026-09-27; was P2, needed a grading-scale decision anyway). Not implemented. |
| FR-5 | WHEN a Registrar POSTs an enrollment THE SYSTEM SHALL create it with `status=enrolled` AND increase the student's `balance_centavos` by `course.units × rate_per_unit_centavos` (resolved 2026-09-27, see A-12). IF `(student_id, course_id, school_year, semester)` already exists THEN THE SYSTEM SHALL return 409. |
| FR-6 | WHEN a Registrar PATCHes an enrollment's grade THE SYSTEM SHALL set `status=completed` and store the grade, overwriting any previous value with no history kept. |
| FR-7 | WHEN a Registrar GETs a course's roster for a term THE SYSTEM SHALL return every enrolled student with their current grade/status. |
| FR-8 | WHEN a Cashier POSTs a payment THE SYSTEM SHALL create a payment record, generate a unique `or_number`, and decrement the student's `balance_centavos` by `amount_centavos`. IF `amount_centavos <= 0` THEN THE SYSTEM SHALL return 400. |
| FR-9 | WHEN a payment is recorded THE SYSTEM SHALL make a receipt available at a stable URL showing the OR number, student, amount, and date. |
| FR-10 | WHEN a Cashier or Admin GETs a student's payment list THE SYSTEM SHALL return all payments for that student, newest first. |
| FR-11 | WHEN Department staff PATCH a clearance in their own department THE SYSTEM SHALL set status to approved/denied and overwrite `reviewed_by`/`reviewed_at`, with no history kept — including re-reviewing a clearance already in a terminal state (resolved 2026-09-27, same pattern as FR-6). IF the clearance belongs to a different department THEN THE SYSTEM SHALL return 403. |
| FR-12 | WHEN Department staff, Registrar, or Admin GET a student's clearance record THE SYSTEM SHALL return that student's status across *all* departments, not just the caller's own. |
| FR-13 | WHEN a document request is created, or transitions to `released`, THE SYSTEM SHALL verify all of that student's clearances for the current term are `approved`. IF any are not THEN THE SYSTEM SHALL reject the transition with 422. |
| FR-14 | WHEN a document request is created, or transitions to `released`, THE SYSTEM SHALL verify the student's `balance_centavos = 0`. IF not THEN THE SYSTEM SHALL reject the transition with 422. |
| FR-15 | WHEN Admin POSTs/PATCHes a user THE SYSTEM SHALL create/update the account and role. THE SYSTEM SHALL NOT hard-delete a user row — deactivation is `is_active=false` only. |
| FR-16 | WHEN Admin PATCHes a user's password THE SYSTEM SHALL replace the hash and revoke all of that user's existing tokens. |
| FR-17 | WHEN any account is created, edited, or (de)activated THE SYSTEM SHALL append an `audit_logs` row recording actor, action, target, and a diff. |
| FR-18 | WHEN a Student POSTs a document request THE SYSTEM SHALL create it with `status=pending`, subject to FR-13/FR-14. |
| FR-19 | WHEN a Registrar PATCHes a document request's status THE SYSTEM SHALL only allow the transitions in the state diagram (§6). Any other transition returns 409. |
| FR-20 | WHEN a document request's status changes THE SYSTEM SHALL make the new status visible on the owning student's next GET — no push notification (non-goal). |

## 4. Data Models

```
User
  id: bigint unsigned, pk, auto-increment
  name: string(255), not null
  email: string(255), unique, not null
  password: string(255), not null            # bcrypt hash
  role: enum(student, registrar, cashier, department_staff, admin), not null
  department_id: bigint unsigned, fk -> departments.id, nullable  # set iff role=department_staff
  is_active: boolean, not null, default true
  created_at, updated_at: timestamp, not null

Student
  id: bigint unsigned, pk, auto-increment
  user_id: bigint unsigned, fk -> users.id, unique, not null
  student_number: string(20), unique, not null
  program: string(100), not null
  year_level: tinyint unsigned, not null       # 1-5+
  status: enum(active, on_leave, graduated), not null, default active
  balance_centavos: bigint, not null, default 0   # amount currently owed; see A-2, OQ-3
  created_at, updated_at: timestamp, not null

Department
  id: bigint unsigned, pk, auto-increment
  name: string(150), not null
  code: string(20), unique, not null
  created_at, updated_at: timestamp, not null

Course
  id: bigint unsigned, pk, auto-increment
  department_id: bigint unsigned, fk -> departments.id, not null
  code: string(20), unique, not null
  title: string(150), not null
  units: tinyint unsigned, not null
  created_at, updated_at: timestamp, not null

Enrollment
  id: bigint unsigned, pk, auto-increment
  student_id: bigint unsigned, fk -> students.id, not null
  course_id: bigint unsigned, fk -> courses.id, not null
  school_year: string(9), not null              # "2026-2027"
  semester: tinyint unsigned, not null           # 1 | 2 | 3=summer (A-10)
  grade: decimal(3,2), nullable                  # 1.00 (highest) - 5.00 (failing), A-7
  status: enum(enrolled, completed, dropped), not null, default enrolled
  created_at, updated_at: timestamp, not null
  unique: (student_id, course_id, school_year, semester)

Payment
  id: bigint unsigned, pk, auto-increment
  student_id: bigint unsigned, fk -> students.id, not null
  cashier_id: bigint unsigned, fk -> users.id, not null   # must have role=cashier
  amount_centavos: bigint unsigned, not null              # must be > 0
  or_number: string(30), unique, not null                 # server-generated, see §6 Edge Case 2
  payment_type: enum(tuition, misc_fee, document_fee), not null
  paid_at: timestamp, not null
  created_at, updated_at: timestamp, not null

Clearance
  id: bigint unsigned, pk, auto-increment
  student_id: bigint unsigned, fk -> students.id, not null
  department_id: bigint unsigned, fk -> departments.id, not null
  school_year: string(9), not null
  semester: tinyint unsigned, not null
  status: enum(pending, approved, denied), not null, default pending
  remarks: string(255), nullable
  reviewed_by: bigint unsigned, fk -> users.id, nullable   # must have role=department_staff
  reviewed_at: timestamp, nullable
  created_at, updated_at: timestamp, not null
  unique: (student_id, department_id, school_year, semester)

DocumentRequest
  id: bigint unsigned, pk, auto-increment
  student_id: bigint unsigned, fk -> students.id, not null
  type: enum(tor, cor, certification), not null
  purpose: string(255), not null                # required, non-blank (Edge Case 7)
  status: enum(pending, processing, ready, released, rejected), not null, default pending
  requested_at: timestamp, not null
  released_at: timestamp, nullable
  processed_by: bigint unsigned, fk -> users.id, nullable   # must have role=registrar
  created_at, updated_at: timestamp, not null

AuditLog                                        # new in this spec — needed to implement FR-17
  id: bigint unsigned, pk, auto-increment
  actor_id: bigint unsigned, fk -> users.id, not null
  action: string(50), not null                  # e.g. "user.created", "user.role_changed"
  target_type: string(50), not null             # always "user" in v1 — see Non-Goals
  target_id: bigint unsigned, not null
  changes: json, nullable                       # before/after diff
  created_at: timestamp, not null
```

**Relationships (unchanged from PRD §7):** Inheritance — `User` base, role subtypes via the `role` discriminator (STI). Association — `Student` ↔ `Course` through `Enrollment`. Aggregation — `Department` has `Course`s. Composition — `Student` owns `Payment`, `Clearance`, `DocumentRequest`.

**Config value (not a DB table):** `rate_per_unit_centavos` — a single global flat rate, read from application config (e.g. `config('fees.rate_per_unit_centavos')` / `.env`), not editable through the API in v1 (A-12). Every enrollment charges `course.units × rate_per_unit_centavos` against the student's balance (FR-5).

**New in this spec, not in PRD §7:** `students.balance_centavos` and the `AuditLog` table. Both were referenced by FR-14/FR-17 but had no backing field/table in the PRD — added here to make those requirements actually implementable (see Changelog).

## 5. API Contracts

**Common responses** (apply to every authenticated endpoint below unless overridden):
- `401 { error: "UNAUTHENTICATED" }` — missing/invalid/expired token
- `403 { error: "UNAUTHORIZED_ROLE" }` — authenticated, wrong role or ownership
- `422 { error: "VALIDATION_FAILED", fields: {...} }` — request body fails validation
- `429 { error: "RATE_LIMITED" }` — default Laravel `throttle:api`, 60 req/min per token (A-6)
- `500 { error: "SERVER_ERROR" }`

### Auth
```
POST /api/login
  Auth: none
  Request:  { student_number_or_email: string, password: string }
  Response 200: { token: string, role: enum, redirect: string }
  Response 401: { error: "INVALID_CREDENTIALS" }
  Response 403: { error: "ACCOUNT_DEACTIVATED" }

POST /api/logout
  Auth: Bearer token
  Response 204: (no body) — revokes only the current token
```

### Student Portal
```
GET /api/student/profile
  Auth: role=student
  Response 200: { student_number, name, program, year_level, status }

GET /api/student/subjects
  Auth: role=student
  Query: school_year (required), semester (required)
  Response 200: [ { course_code, title, units, status } ]
  Response 400: { error: "MISSING_TERM" }   # A-1: no default-term resolution in v1

GET /api/student/grades
  Auth: role=student
  Query: school_year (required), semester (required)
  Response 200: [ { course_code, title, grade } ]   # only status=completed rows
  Response 400: { error: "MISSING_TERM" }
```

### Registrar
```
POST /api/registrar/enrollments
  Auth: role=registrar
  Request:  { student_id, course_id, school_year, semester }
  Response 201: { id, status: "enrolled", charge_applied_centavos }
    -> charge_applied_centavos = course.units × rate_per_unit_centavos, added to the student's balance in the same transaction
  Response 404: { error: "STUDENT_NOT_FOUND" } | { error: "COURSE_NOT_FOUND" }
  Response 409: { error: "ALREADY_ENROLLED" }

PATCH /api/registrar/enrollments/{id}/grade
  Auth: role=registrar
  Request:  { grade: number }   # 1.00-5.00 in 0.25 steps, A-7
  Response 200: { id, grade, status: "completed" }
  Response 404: { error: "ENROLLMENT_NOT_FOUND" }
  Response 422: { error: "INVALID_GRADE_RANGE" }

GET /api/registrar/courses/{id}/roster
  Auth: role=registrar
  Query: school_year (required), semester (required)
  Response 200: [ { student_id, student_number, name, grade, status } ]
  Response 404: { error: "COURSE_NOT_FOUND" }
```

### Cashier
```
POST /api/cashier/payments
  Auth: role=cashier
  Request:  { student_id, amount_centavos, payment_type }
  Response 201: { or_number, receipt_url }
  Response 400: { error: "INVALID_AMOUNT" }   # amount_centavos <= 0
  Response 404: { error: "STUDENT_NOT_FOUND" }

GET /api/cashier/students/{id}/payments
  Auth: role in (cashier, admin)
  Response 200: [ { or_number, amount_centavos, payment_type, paid_at } ]   # newest first
  Response 404: { error: "STUDENT_NOT_FOUND" }
```

### Department
```
GET /api/department/clearances
  Auth: role=department_staff
  Query: status (optional filter)
  Response 200: [ { id, student_id, student_number, school_year, semester, status } ]
    -> auto-scoped server-side to the caller's own department_id; never client-supplied

GET /api/students/{id}/clearances
  Auth: role in (student [self only], department_staff, registrar, admin)
  Response 200: [ { department_code, status, remarks, reviewed_at } ]   # all departments
  Response 403: { error: "UNAUTHORIZED_ROLE" }   # e.g. a student requesting another student's record

PATCH /api/department/clearances/{id}
  Auth: role=department_staff, must own the clearance's department_id
  Request:  { status: "approved"|"denied", remarks }
  Response 200: { id, status, reviewed_at }
    -> re-reviewable indefinitely; each PATCH overwrites status/remarks/reviewed_by/reviewed_at, no history kept
  Response 403: { error: "UNAUTHORIZED_ROLE" }   # different department
```

### Document Requests
```
POST /api/documents/requests
  Auth: role=student
  Request:  { type: enum(tor,cor,certification), purpose: string }
  Response 201: { id, status: "pending" }
  Response 409: { error: "DUPLICATE_REQUEST" }   # an open request of the same type exists
  Response 422: { error: "CLEARANCE_INCOMPLETE" } | { error: "OUTSTANDING_BALANCE" }

PATCH /api/documents/requests/{id}/status
  Auth: role=registrar
  Request:  { status: "processing"|"ready"|"released"|"rejected" }
  Response 200: { id, status }
  Response 409: { error: "INVALID_TRANSITION" }   # see state diagram, §6
  Response 422: { error: "CLEARANCE_INCOMPLETE" } | { error: "OUTSTANDING_BALANCE" }
    -> re-checked only on the transition into "released"
```

### Admin
```
POST /api/admin/users
  Auth: role=admin
  Request:  { name, email, role, password, department_id? }   # department_id required iff role=department_staff
  Response 201: { id, role }
  Response 409: { error: "EMAIL_TAKEN" }

PATCH /api/admin/users/{id}
  Auth: role=admin
  Request:  { role?, department_id?, is_active? }
  Response 200: { id, role, is_active }
  Response 404: { error: "USER_NOT_FOUND" }

PATCH /api/admin/users/{id}/password
  Auth: role=admin
  Request:  { new_password }
  Response 204: (no body) — also revokes all of that user's existing tokens (A-5)

GET /api/admin/audit-logs
  Auth: role=admin
  Query: actor_id?, target_type?, page?
  Response 200: [ { actor_id, action, target_type, target_id, changes, created_at } ]
```

## 6. Edge Cases & Error Handling

**Document request state machine (FR-19):**
```
pending -> processing -> ready -> released
pending -> rejected
processing -> rejected
(ready, released, rejected are terminal — no transitions out)
```

**Enumerated cases:**
1. Concurrent grade edits on the same enrollment — last write wins, no locking (A-3).
2. `or_number` generation must happen inside the same DB transaction as the payment insert, using a real sequence/lock — not `SELECT MAX(...)+1`, which collides under concurrent cashiers.
3. A second document request of the same `type` while one is still open (pending/processing/ready) — 409 `DUPLICATE_REQUEST`; a new one is only allowed once the prior one is `released` or `rejected`.
4. Token expiry/invalidity mid-session — 401 `UNAUTHENTICATED` on any endpoint; catching this globally and redirecting to login is the React repo's job, not this spec's.
5. Admin changes a user's `role` while they hold a still-valid token — the token stays valid, but authorization is checked against the *current* `users.role` on every request (never cached in the token), so the change takes effect on that user's very next request (A-8).
6. Admin deactivates a user (`is_active=false`) while they hold a still-valid token — `is_active` is checked live on every request, not only at login, so deactivation is immediate, not "on next login" (A-9). Skipping this check would make deactivation security theater.
7. Blank/whitespace-only `purpose` on a document request — 422 `VALIDATION_FAILED`.
8. Payment or enrollment referencing a `student_id` that doesn't exist — 404, not a raw FK constraint error.
9. Grade submitted outside the valid range (A-7) — 422 `INVALID_GRADE_RANGE`.
10. Network failure between the two repos where React can't tell if a payment POST succeeded before retrying — currently unhandled; a retry creates a second payment (accepted risk, A-11).
11. Enrollment creation and its `balance_centavos` charge (FR-5) must happen in one DB transaction — a crash between the two must never leave a student enrolled without being charged, or charged without being enrolled.

## 7. Acceptance Criteria

```
FR-1: WHEN login credentials are valid THE SYSTEM SHALL return 200 with a token.
  Given an active user with a known password
  When they POST /api/login with correct credentials
  Then the response is 200 with a token and their role
  And a second POST with a wrong password returns 401 INVALID_CREDENTIALS
  And the same correct POST for a deactivated account returns 403 ACCOUNT_DEACTIVATED

FR-2: WHEN a student requests their profile THE SYSTEM SHALL return only their own.
  Given student A is authenticated
  When they GET /api/student/profile
  Then the response contains only student A's data, never another student's

FR-3: WHEN a student requests grades THE SYSTEM SHALL return only completed enrollments.
  Given student A has one completed and one in-progress enrollment this term
  When they GET /api/student/grades?school_year=2026-2027&semester=1
  Then only the completed enrollment appears, with its grade
  And omitting school_year or semester returns 400 MISSING_TERM

FR-5: WHEN a registrar enrolls a student THE SYSTEM SHALL charge their balance for the course's units.
  Given student A has balance_centavos=0 and course X is worth 3 units
  When the registrar POSTs an enrollment for student A in course X
  Then the response includes charge_applied_centavos = 3 × rate_per_unit_centavos
  And student A's balance_centavos increases by that same amount
  And POSTing the same (student_id, course_id, school_year, semester) again returns 409 ALREADY_ENROLLED with no second charge applied

FR-6: WHEN a registrar grades an enrollment THE SYSTEM SHALL mark it completed.
  Given student A is enrolled in course X with no grade
  When the registrar PATCHes grade=1.75
  Then the enrollment's status becomes "completed" and grade=1.75
  And it now appears in FR-3's grades response

FR-8: WHEN a cashier records a payment THE SYSTEM SHALL reduce the student's balance.
  Given student A has balance_centavos=500000
  When the cashier POSTs a payment of amount_centavos=500000
  Then the response includes a unique or_number
  And student A's balance_centavos becomes 0
  And a POST with amount_centavos=0 returns 400 INVALID_AMOUNT

FR-11: WHEN department staff review a clearance outside their department THE SYSTEM SHALL reject it.
  Given a clearance belongs to the Library department
  When a Registrar's Office staff account PATCHes that clearance
  Then the response is 403 UNAUTHORIZED_ROLE
  And when the Library's own staff PATCHes it to "approved", reviewed_by/reviewed_at are stamped
  And a later PATCH by the same department changing it to "denied" succeeds, overwriting the prior decision with no history kept

FR-13 + FR-14: WHEN balance or clearance is incomplete THE SYSTEM SHALL block document release.
  Given student A has balance_centavos=0 but one department clearance still "pending"
  When student A POSTs a document request
  Then the response is 422 CLEARANCE_INCOMPLETE
  And once that clearance is approved and balance stays 0, the same POST returns 201 pending
  And a registrar PATCHing that request's status to "released" re-checks both conditions

FR-15: WHEN admin deactivates a user THE SYSTEM SHALL block them immediately, not just at next login.
  Given a cashier is logged in with a valid token
  When admin PATCHes that user's is_active to false
  Then the cashier's very next request (with their still-valid token) returns 403 ACCOUNT_DEACTIVATED
  And the user row still exists — it is never hard-deleted

FR-19: WHEN a registrar attempts an invalid status transition THE SYSTEM SHALL reject it.
  Given a document request is in "released" status (terminal)
  When the registrar PATCHes status="processing"
  Then the response is 409 INVALID_TRANSITION
```
*(Remaining FRs — 4, 7, 9, 10, 12, 16, 17, 18, 20 — follow the same Given/When/Then shape directly off their §3/§5 entries; not spelled out individually to keep this section from padding out on repetitive cases.)*

## 8. Assumptions

- **A-1** — `school_year`/`semester` are required query params on student-facing GETs; there's no "current term" auto-resolution in v1.
- **A-2** — `students.balance_centavos` is a running balance: increased automatically on enrollment (FR-5, resolved 2026-09-27) and decremented by payments.
- **A-3** — No optimistic locking on any table; concurrent edits are last-write-wins.
- **A-4** — Sanctum tokens don't auto-expire; revoked only by explicit logout or an admin-triggered password reset.
- **A-5** — Resetting a user's password (FR-16) revokes *all* of that user's existing tokens, not just future ones.
- **A-6** — Default Laravel `throttle:api` (60 req/min/token) applies everywhere; no endpoint has a bespoke limit.
- **A-7** — Grades use the Philippine 1.00 (highest)–5.00 (failing) scale, enforced 1.00–5.00 in 0.25 steps. Inferred from standard PH university convention (and consistent with the PRD's own `grade: 1.75` example) — **confirm against CuyoTech's actual grading scale before implementing.**
- **A-8** — `role` is read live from `users.role` on every request, never embedded in the token — a role change takes effect on the user's very next request.
- **A-9** — `is_active` is likewise checked live on every request, not only at login.
- **A-10** — `semester` is `1` | `2` | `3` (1st sem, 2nd sem, summer).
- **A-11** — `POST /api/cashier/payments` has no idempotency key (resolved 2026-09-27). A retried request after an ambiguous network failure creates a duplicate payment. Mitigated operationally — the cashier checks the OR log before retrying — not technically. Accepted for prototype scope.
- **A-12** — `rate_per_unit_centavos` is a single flat rate for the whole university, stored in application config (not the database) — changing it requires a deploy, not an admin UI action. No per-program or per-year-level rate variation in this spec.
- **A-13** — Clearances are revisable indefinitely (resolved 2026-09-27) — same last-write-wins, no-history pattern as grade edits (A-3). If a department later needs a full audit trail of every review over time, `reviewed_by`/`reviewed_at` would need to become a history table — not covered here.
- **A-14** — Dropping an enrollment (`status=dropped`) is out of scope for this spec — no endpoint transitions to it. If added later, it must reverse the corresponding `balance_centavos` charge from FR-5, or a dropped course keeps charging the student.

## 9. Open Questions

- ✅ ~~OQ-1 (GWA scope)~~ — resolved 2026-09-27, dropped. See FR-4.
- ✅ ~~OQ-2 (payment idempotency)~~ — resolved 2026-09-27, accepted as operational risk. See A-11.
- ✅ ~~OQ-3 (balance assessment)~~ — resolved 2026-09-27: automatic, tied to enrollment. See FR-5, A-2, A-12.
- ✅ ~~OQ-4 (clearance revision)~~ — resolved 2026-09-27: revisable indefinitely, no history. See FR-11, A-13.

## 10. Changelog

- **2026-09-27 — v1.0.** Initial spec, derived from `PRD.md`. Added `students.balance_centavos` and the `AuditLog` table — both referenced by FR-14/FR-17 but absent from the PRD's schema; without them those requirements weren't actually buildable. Flagged four open questions (OQ-1–4) rather than guessing at their resolution.
- **2026-09-27 — v1.1.** Resolved all four open questions: dropped FR-4 (GWA) from scope; accepted payment-retry duplication as an operational risk (A-11); balance assessment is now automatic on enrollment via a flat `rate_per_unit_centavos` (FR-5, A-12) — surfaced a new gap in the process (A-14: dropping an enrollment isn't specified, so a drop would leave a stale charge if implemented later without also reversing it); clearances are now revisable indefinitely, matching the grade-edit pattern (FR-11, A-13).
