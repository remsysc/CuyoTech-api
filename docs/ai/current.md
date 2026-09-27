# CuyoTech University SSIS - Current Context

**Version:** 1.0.0-alpha
**Current Milestone:** Sprint 2 (Student Portal + Registrar + Cashier)

## Implementation Status

- **Sprint 1 (Foundation + Auth):** Completed. Pull requests created for:
    - Migrations and models
    - Sanctum and CORS
    - Role-based Policies
    - Global middlewares for active status and role refresh
    - Factories and seeders
    - Login/Logout endpoints with tests

## Active Work

- Moving to **Sprint 2**, which covers FR-2, FR-3, FR-5, FR-6, FR-8, and FR-9.
- Focus: Student profile, viewing subjects/grades, Registrar enrollment and grade encoding, Cashier payments and receipts.

## Important Decisions & Constraints

- Money handling convention: integer centavos, never floats (`config/fees.php`).
- Role discriminator logic uses STI via `role` column on the `User` model, not separate subtype models.
- Authentication uses Laravel Sanctum tokens.

## Relevant Documents

- Sprints & Tasks: `docs/SPRINTS.md`
- Tech Spec: `docs/SPEC.md`
- Product Requirements: `docs/PRD.md`
