# LAND-01 — NisheTube landing and authentication entry

## Goal and boundaries

Create a clear English-only local landing experience that explains NisheTube’s stored-evidence research purpose and directs visitors into registration or sign-in. Preserve the existing Laravel session-authentication flow and do not add deployment, billing, external identity providers, or claims of YouTube search-volume measurement.

## Acceptance

- Guests receive an accessible landing page with accurate product framing and clear registration/sign-in actions.
- Authenticated users receive a useful in-app destination without exposing another user’s information.
- Responsive, loading, empty, error, and keyboard navigation states are explicit where applicable.
- Existing authentication, owner scope, and local-only assumptions remain unchanged.
- Focused feature and frontend tests cover guest/authenticated routing, copy safety, and accessible calls to action.

## Applicable decisions

- D-005–D-008: Authentication and user-owned access remain scoped and private.
- D-038: Desktop accessibility, keyboard behavior, and exact values are required.
- D-041–D-042: Product claims, unavailable values, and partial evidence remain explicit.

## Initial inspection targets

- Existing root routes, authenticated layout, registration/sign-in pages, shared navigation, and focused authentication tests.
- Existing product terminology and research-evidence copy.
- Shared responsive primitives and accessible button/link patterns.

## Focused verification

- Feature tests for guest and authenticated root routing plus authentication entry points.
- Frontend tests for responsive, accessible landing actions and safe evidence wording.
- Formatter, lint, type, PHPStan, and Pint checks only for changed files.

## Exact reference links

- [Working rules](../00_WORKING_RULES.md)
- [Task index](../TASK_INDEX.md)
