import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const [product, architecture, data, ui, acceptance, decisions, model, backlog] =
    await Promise.all(
        [
            '01_PRODUCT_REQUIREMENTS.md',
            '02_ARCHITECTURE.md',
            '03_DATA_MODEL.md',
            '05_UI_UX.md',
            '09_ACCEPTANCE_AND_TESTING.md',
            '10_DECISIONS.md',
            '12_UNIFIED_ANALYZER_MODEL.md',
            '08_BACKLOG.md',
        ].map((file) =>
            readFile(new URL(`../../docs/${file}`, import.meta.url), 'utf8'),
        ),
    );

test('cross-channel comparison scope and safety agree across canonical documents', () => {
    assert.match(
        product,
        /two or three owner-scoped completed channel Analyzer attempts/,
    );
    assert.match(
        architecture,
        /24 recent channels with five attempts per channel/,
    );
    assert.match(data, /Cross-channel comparison adds no persistence/);
    assert.match(
        ui,
        /not an opportunity score, causal finding, or channel recommendation/,
    );
    assert.match(acceptance, /capped at 100 rows per evidence family/);
    assert.match(acceptance, /accepts two or three different completed/);
    assert.match(decisions, /D-035 — Cross-channel comparison/);
    assert.match(model, /GET \/analyzer\/compare/);
    assert.match(backlog, /XCMP-01 — Cross-channel topic comparison/);
});
