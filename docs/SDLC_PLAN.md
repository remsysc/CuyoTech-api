# Delivery Workflow — CuyoTech SSIS

> This concise plan replaces the former expanded SDLC/RACI document. Keep the course's phase terminology if required for submission, but use the repository documents below for actual project decisions.

## Sources of truth

- `PRD.md`: product scope and functional requirements.
- `SPEC.md`: data model, API contracts, and technical behavior.
- `SPRINTS.md`: sequencing and task status.
- `TEST_PLAN.md`: verification strategy.
- `docs/ai/current.md`: current implementation snapshot.

## Practical workflow

1. **Plan:** select the next in-scope requirement from `SPRINTS.md`.
2. **Specify:** clarify the behavior in `SPEC.md` before implementing when the contract is incomplete.
3. **Implement:** follow the existing Laravel, Inertia, and route conventions in the repository.
4. **Verify:** run focused Pest tests for the changed behavior, then broader checks when appropriate. Do not label a suite ready unless it passes.
5. **Review:** compare the implementation and tests to the SPEC; record unresolved product decisions as open questions instead of guessing.
6. **Release/operate:** deployment, production database, load targets, uptime, and operational monitoring are not currently specified; define them only if deployment becomes part of scope.

## Minimum quality bar

- Enforce authorization and input validation on the server.
- Keep multi-record financial state changes atomic and use integer centavos.
- Cover expected behavior and relevant failure cases with tests.
- Run the project's formatter and static-analysis scripts when changing application code.

This project does not currently require a separate RACI matrix, zero-downtime deployment plan, production SLO, or load-test target. Those can be added if the team or course deliverable requires them.
