import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const [architecture, model, ui, api, acceptance] = await Promise.all([
    readFile(new URL('../../docs/02_ARCHITECTURE.md', import.meta.url), 'utf8'),
    readFile(new URL('../../docs/03_DATA_MODEL.md', import.meta.url), 'utf8'),
    readFile(new URL('../../docs/05_UI_UX.md', import.meta.url), 'utf8'),
    readFile(new URL('../../docs/06_YOUTUBE_API.md', import.meta.url), 'utf8'),
    readFile(
        new URL('../../docs/09_ACCEPTANCE_AND_TESTING.md', import.meta.url),
        'utf8',
    ),
]);

test('transcript structure boundaries and acceptance are documented', () => {
    assert.match(architecture, /TranscriptStructureProvider/);
    assert.match(architecture, /transcript-structure-v1/);
    assert.match(model, /transcript_structure_profiles/);
    assert.match(model, /transcript_structure_insights/);
    assert.match(model, /start\/end character offsets/);
    assert.match(
        ui,
        /Transcript Structure follows the original transcript viewer/,
    );
    assert.match(
        ui,
        /Not-analyzed, analyzing, insufficient, partial, failed, and complete/,
    );
    assert.match(api, /performs no YouTube or network request/);
    assert.match(api, /cannot be presented as YouTube-supplied metadata/);
    assert.match(acceptance, /all eight required output kinds/);
    assert.match(
        acceptance,
        /Original text and inferred output remain structurally and visually separate/,
    );
});
