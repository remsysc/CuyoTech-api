# Product Requirements — CuyoTech University SSIS

> **Status:** Draft prototype scope
> **Product owner:** Rem

## Document ownership

- This document owns the product problem, goals, scope, and functional requirements.
- `SPEC.md` is the single source of truth for data models, API contracts, validation, and resolved technical behavior.
- `SPRINTS.md` owns delivery sequencing and task status. Do not maintain a second implementation checklist here.
- `TEST_PLAN.md` owns verification strategy; `docs/ai/current.md` records the implementation and latest observed test status.

## Product context

CuyoTech University currently handles student services such as enrollment, grade viewing, payments, clearances, and document requests manually. The prototype aims to let students and university staff complete these workflows online.

## Project constraints

- The repository is a Laravel 13 application (PHP `^8.3`) with React/Inertia in the same repository. Laravel JSON endpoints are under `/api`; web-facing pages use the app's web routes.
- Authentication for the API uses Laravel Sanctum. Authorization is enforced on the server.
- SQLite is the repository's default local and test database. The production database has not been selected in the project configuration; do not assume MySQL or PostgreSQL without a deployment decision.
- Store monetary values as integer centavos, not floating-point values.
- The separate-frontend-repository assumption from earlier drafts is no longer current.

## Goals

- Students can view their profile, subjects, and grades.
- Registrar staff can manage enrollment and grades.
- Cashiers can record payments and provide receipts.
- Department staff can review clearances.
- Students can request official documents, subject to clearance and balance rules.
- Admins can manage accounts and roles.

## Non-goals for this prototype

- Payment gateway integration; cashier payments are recorded manually.
- SMS or email notifications.
- A native mobile application.
- Multi-campus support.
- Automated GWA or latin-honors computation (FR-4 is dropped).

## Target users

- **Student:** views their profile, subjects, grades, balance, clearances, and document-request status; submits document requests.
- **Registrar:** manages enrollment, grades, and document requests.
- **Cashier:** records payments and provides receipts.
- **Department staff:** reviews clearances for their department.
- **Admin:** manages user accounts and roles.

## Functional requirements

| ID    | Requirement                                                                                                                                                                                                                  | Priority |
| ----- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------- |
| FR-1  | A student can authenticate using their student number or email and password.                                                                                                                                                 | P0       |
| FR-2  | A student can view their own profile.                                                                                                                                                                                        | P0       |
| FR-3  | A student can view their own subjects and completed grades by term.                                                                                                                                                          | P0       |
| FR-4  | **Dropped:** compute GWA; grading and rounding rules are not defined for this prototype.                                                                                                                                     | —        |
| FR-5  | A registrar can enroll a student in a course for a school year and semester; enrollment applies the configured per-unit charge to the student's balance. Course sections are not represented in the current prototype model. | P0       |
| FR-6  | A registrar can encode or edit a student's grade for a course.                                                                                                                                                               | P0       |
| FR-7  | A registrar can view a course roster for a term.                                                                                                                                                                             | P1       |
| FR-8  | A cashier can record a payment against a student.                                                                                                                                                                            | P0       |
| FR-9  | The system provides a stable, printable receipt after payment.                                                                                                                                                               | P0       |
| FR-10 | A cashier can view a student's payment history.                                                                                                                                                                              | P1       |
| FR-11 | Department staff can approve or deny clearances within their own department.                                                                                                                                                 | P0       |
| FR-12 | Authorized staff can view a student's clearance status across departments.                                                                                                                                                   | P1       |
| FR-13 | Document release is blocked until required department clearances are approved.                                                                                                                                               | P0       |
| FR-14 | New document requests are blocked while the student has an outstanding balance.                                                                                                                                              | P0       |
| FR-15 | Admins can create, edit, and deactivate accounts and assign roles.                                                                                                                                                           | P0       |
| FR-16 | Admins can reset a user's password.                                                                                                                                                                                          | P1       |
| FR-17 | Admins can view an audit log of account changes.                                                                                                                                                                             | P2       |
| FR-18 | A student can request a TOR, COR, or certification and provide a purpose.                                                                                                                                                    | P0       |
| FR-19 | A registrar can move a document request through its allowed statuses.                                                                                                                                                        | P0       |
| FR-20 | A student can view the current status of their document request.                                                                                                                                                             | P1       |

## Success criteria

- Demonstrate the in-scope student, registrar, cashier, department, and admin workflows end to end.
- A grade entered by a registrar appears in the owning student's grade view.
- A cashier payment updates the student's balance and produces a receipt.
- Document requests and release enforce the balance and clearance requirements.
- No P0 defects remain at project submission.

## Decisions still requiring confirmation

- Which database engine, if any, is required for deployment beyond the current SQLite default.
- Whether CuyoTech's actual grading scale matches the provisional scale in `SPEC.md`.
- How current-term clearance records are created and which departments are required for a term.
- Whether course sections are required for the course deliverable; the current schema models courses only.
