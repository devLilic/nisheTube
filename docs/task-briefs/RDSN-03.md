# RDSN-03 — Research-journey shell and navigation

## Goal and boundaries

Replace the current application shell with a floating desktop rail, responsive command bar, and complete mobile Glass Sheet while preserving backend routes and owner-safe context.

## Acceptance

- Desktop groups are Discover, Validate, Analyze, Organize, and Manage; collapsed rail remains fully keyboard/tooltip accessible.
- Mobile exposes the complete menu in a focus-trapped, scroll-locked Glass Sheet with reliable close/navigation behavior.
- Search, research context, quota, and notifications fit the command bar; below 1200px the context controls consolidate without losing values.
- Compare niches and Shortlist point to their correct destinations and stale library copy is removed.
- Active states, breadcrumbs, skip navigation, 200% zoom, long localized labels, and owner-private context continue to work.

## Applicable decisions

- D-005–D-008, D-049, D-051–D-054.

## Initial inspection targets

- App shell/layout, sidebar/header/nav components, mobile sheet, global search, research context, quota, notifications, navigation types/tests.

## Relevant contracts and UI

- Existing routes remain stable except corrected links.
- Desktop rail targets 248px/72px; page canvas targets a bounded twelve-column layout.

## Focused verification

- Exact navigation/context feature tests and frontend route/keyboard/responsive tests.
- Scoped lint, format, types, and designer review at three target widths.

## Exact reference links

- [RDSN-02 brief](RDSN-02.md)
- [Responsive shell contract](../reference/RDSN_LIQUID_GLASS_DESIGN_CONTRACT.md#5-responsive-shell)
- [Shell and navigation migration matrix](../reference/RDSN_PAGE_COMPONENT_MATRIX.md#shared-shell-and-component-migration)
- [Decision D-053](../10_DECISIONS.md#d-053--navigation-follows-the-research-journey-and-mobile-uses-a-full-sheet)
- [Working rules](../00_WORKING_RULES.md)
