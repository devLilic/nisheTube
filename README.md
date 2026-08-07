# NisheTube

NisheTube is a local YouTube niche-research application. It searches and samples public YouTube data, stores timestamped research snapshots, compares results over time, and produces an explainable opportunity score.

## Product scope

- English application interface.
- Multiple local users with login and registration.
- Three research markets: Global/English, Romania/Romanian, and Russia/Russian.
- Keyword search, video and channel analysis, niche discovery, opportunity scoring, history and comparisons, projects and favorites, CSV/Excel export, API/quota settings, and snapshot retention.
- Data stored locally in MySQL and automatically eligible for cleanup after six months, with selective manual deletion.

## Current state

The repository contains a Laravel 13.8 base application. The first development milestone is to adopt the official Laravel React starter-kit foundation with Inertia 3, React 19, TypeScript, Tailwind 4, shadcn/ui, and Laravel authentication. Product functionality has not yet been implemented.

## Documentation

- [Product requirements](docs/01_PRODUCT_REQUIREMENTS.md)
- [Architecture](docs/02_ARCHITECTURE.md)
- [Data model](docs/03_DATA_MODEL.md)
- [Opportunity scoring](docs/04_SCORING_MODEL.md)
- [UI/UX specification](docs/05_UI_UX.md)
- [YouTube API integration](docs/06_YOUTUBE_API.md)
- [Local setup](docs/07_LOCAL_SETUP.md)
- [Implementation backlog](docs/08_BACKLOG.md)
- [Acceptance and testing](docs/09_ACCEPTANCE_AND_TESTING.md)
- [Decision log](docs/10_DECISIONS.md)

Codex must read `AGENTS.md` before implementation and follow the vertical-slice rule: every functional module includes backend, data, UI states, authorization, and tests.

## Local environment

- Project: `C:\laragon\www\NisheTube`
- PHP: 8.3.30
- Laravel: 13.8 or later compatible 13.x release
- Node.js: 22.22
- Composer: 2.9.4
- MySQL: 8.4.3
- Recommended local URL: `http://nishetube.test`

Do not commit `.env` or a YouTube API key.

