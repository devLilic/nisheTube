import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');
const landing = read('../../resources/js/pages/welcome.tsx');

test('landing page provides responsive, keyboard-accessible authentication calls to action', () => {
    assert.match(landing, /aria-label="Authentication actions"/);
    assert.match(landing, /focus-visible:ring-2/);
    assert.match(landing, /data-test="landing-register"/);
    assert.match(landing, /data-test="landing-sign-in"/);
    assert.match(landing, /sm:flex-row/);
    assert.match(landing, /md:grid-cols-3/);
});

test('landing copy describes stored evidence without a search-volume claim', () => {
    assert.match(landing, /returned YouTube videos and\s+channels/);
    assert.match(landing, /observed demand from those results/);
    assert.match(landing, /does not measure YouTube search volume/);
    assert.match(
        landing,
        /Global\/English, Romania\/Romanian, and Russia\/Russian/,
    );
    assert.match(landing, /Account data stays in this\s+installation/);
});
