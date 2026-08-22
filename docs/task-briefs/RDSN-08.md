# RDSN-08 — Settings, landing, and authentication redesign

## Goal and boundaries

Finish the production-page migration with settings, landing, and authentication while preserving local privacy, security, and retention behavior.

## Acceptance

- Settings uses category navigation plus stable forms on desktop and a navigation stack on mobile.
- Landing has a minimal glass header, accurate product preview, and no search-volume/deployment claims.
- Auth has a purposeful Liquid Glass panel, accessible validation, and RO/EN selection before login/registration.
- Appearance exposes light only; security, retention, YouTube integration, and destructive actions stay clearly separated.
- Guest/auth redirects, loopback registration, secrets, ownership, and long-form validation copy remain safe.

## Applicable decisions

- D-004–D-006, D-009, D-049, D-051–D-054.

## Initial inspection targets

- Settings layouts/pages/controllers/requests, landing controller/page, auth layouts/pages, locale/appearance selectors and tests.

## Relevant contracts and UI

- Locale changes presentation only; appearance resolves to light; provider secrets never enter props or screenshots.

## Focused verification

- Exact Settings/auth/landing feature tests and focused interface/localization/responsive/accessibility tests.
- Scoped lint, format, types, secret scan, and designer review.

## Exact reference links

- [L10N-01 brief](L10N-01.md)
- [APP-01 brief](APP-01.md)
- [Manage and public/auth migration matrix](../reference/RDSN_PAGE_COMPONENT_MATRIX.md#manage)
- [Localization and evidence design rules](../reference/RDSN_LIQUID_GLASS_DESIGN_CONTRACT.md#8-localization-and-evidence-rules)
- [Working rules](../00_WORKING_RULES.md)
