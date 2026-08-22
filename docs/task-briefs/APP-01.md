# APP-01 — Temporary light-only appearance

## Goal and boundaries

Force the accepted light appearance and remove dark/system controls without deleting stored user preferences or weakening shared component behavior.

## Acceptance

- Theme initialization always removes `.dark` and sets `color-scheme: light` before first paint.
- Appearance controls no longer expose Dark or System.
- Existing local/cookie values are ignored but preserved for future compatibility.
- Shared components and auth/landing pages render consistently without flash-of-dark-theme.
- No user data migration deletes appearance values.

## Applicable decisions

- D-052 and D-054.

## Initial inspection targets

- Theme initialization hook, appearance controls/settings, Blade bootstrap, CSS dark tokens/usages, auth layouts.

## Relevant contracts and UI

- Runtime `Appearance` resolves to `light` in this release.
- Stored legacy strings remain tolerated at the persistence boundary.

## Focused verification

- Exact frontend theme bootstrap and appearance-interface tests.
- Scoped lint, format, types, and no-dark-first-paint manual check.

## Exact reference links

- [Decision D-052](../10_DECISIONS.md#d-052--appearance-is-temporarily-light-only)
- [RDSN-02 brief](RDSN-02.md)
- [Working rules](../00_WORKING_RULES.md)
