import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const [product, architecture, model, ui, api, acceptance, unified, decisions] =
    await Promise.all([
        readFile(
            new URL('../../docs/01_PRODUCT_REQUIREMENTS.md', import.meta.url),
            'utf8',
        ),
        readFile(
            new URL('../../docs/02_ARCHITECTURE.md', import.meta.url),
            'utf8',
        ),
        readFile(
            new URL('../../docs/03_DATA_MODEL.md', import.meta.url),
            'utf8',
        ),
        readFile(new URL('../../docs/05_UI_UX.md', import.meta.url), 'utf8'),
        readFile(
            new URL('../../docs/06_YOUTUBE_API.md', import.meta.url),
            'utf8',
        ),
        readFile(
            new URL('../../docs/09_ACCEPTANCE_AND_TESTING.md', import.meta.url),
            'utf8',
        ),
        readFile(
            new URL('../../docs/12_UNIFIED_ANALYZER_MODEL.md', import.meta.url),
            'utf8',
        ),
        readFile(
            new URL('../../docs/10_DECISIONS.md', import.meta.url),
            'utf8',
        ),
    ]);

test('manual transcript provider and optionality are documented across product boundaries', () => {
    assert.match(product, /optional user-provided transcript/);
    assert.match(architecture, /user_provided_transcript-v1/);
    assert.match(model, /transcript_documents/);
    assert.match(model, /transcript_deletion_audits/);
    assert.match(ui, /No transcript provided/);
    assert.match(api, /no YouTube request and consumes no quota/);
});

test('compliance, retention, authorization, and analysis independence are explicit', () => {
    assert.match(acceptance, /absence or deletion never changes/);
    assert.match(unified, /plain, bracket-timestamped, SRT, or VTT/);
    assert.match(decisions, /D-032/);
    assert.match(decisions, /no YouTube caption\/audio retrieval, scraping/);
});
