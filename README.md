# NisheTube

NisheTube is a local YouTube niche-research application. It searches and samples public YouTube data, stores timestamped research snapshots, compares results over time, and produces an explainable opportunity score.

## Product scope

- English application interface.
- Multiple local users with login and registration.
- Three research markets: Global/English, Romania/Romanian, and Russia/Russian.
- Keyword search, canonical video/channel Analyzer, stored-evidence Explore, Watchlist, Topic Workspaces, niche discovery and validation, semantic/audience/transcript/thumbnail evidence, history and comparisons, projects and favorites, CSV/Excel export, API/quota settings, and snapshot retention.
- Data stored locally in MySQL and automatically eligible for cleanup after six months, with selective manual deletion.

## Current state

The integrated local research expansion is implemented through its final release-hardening gate. The live status and focused verification evidence are maintained in `docs/TASK_STATUS.md`.

## Documentation

- [Product requirements](docs/01_PRODUCT_REQUIREMENTS.md)
- [Architecture](docs/02_ARCHITECTURE.md)
- [Data model](docs/03_DATA_MODEL.md)
- [Opportunity scoring](docs/04_SCORING_MODEL.md)
- [UI/UX specification](docs/05_UI_UX.md)
- [YouTube API integration](docs/06_YOUTUBE_API.md)
- [Local setup](docs/07_LOCAL_SETUP.md)
- [Backup, restore, and troubleshooting](docs/11_BACKUP_AND_RECOVERY.md)
- [Implementation backlog](docs/08_BACKLOG.md)
- [Live task status](docs/TASK_STATUS.md)
- [Acceptance and testing](docs/09_ACCEPTANCE_AND_TESTING.md)
- [Decision log](docs/10_DECISIONS.md)

Codex must read `AGENTS.md` before implementation and follow the vertical-slice rule: every functional module includes backend, data, UI states, authorization, and tests.

## Local environment

- Project: `<Laragon root>\www\NisheTube` (normally `C:\laragon\www\NisheTube`; use the actual checkout path)
- PHP: 8.3.30
- Laravel: 13.8 or later compatible 13.x release
- Node.js: 22.22
- Composer: 2.9.4
- MySQL: 8.4.3
- Recommended local URL: `http://nishetube.test`

Do not commit `.env` or a YouTube API key.
