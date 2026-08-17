import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');
const icon = read('../../resources/js/components/app-logo-icon.tsx');
const lockup = read('../../resources/js/components/app-logo.tsx');
const sidebar = read('../../resources/js/components/app-sidebar.tsx');
const authSimple = read(
    '../../resources/js/layouts/auth/auth-simple-layout.tsx',
);
const authCard = read('../../resources/js/layouts/auth/auth-card-layout.tsx');
const authSplit = read('../../resources/js/layouts/auth/auth-split-layout.tsx');
const favicon = read('../../public/favicon.svg');
const appView = read('../../resources/views/app.blade.php');

test('NisheTube mark combines video, research, and discovery cues in one scalable SVG', () => {
    assert.match(icon, /viewBox="0 0 48 48"/);
    assert.match(icon, /data-brand-mark="nishetube"/);
    assert.match(icon, /nishetube-mark-gradient/);
    assert.match(icon, /<circle[\s\S]*stroke="white"/);
    assert.match(icon, /m18\.6 16\.7 7\.8 4\.8/);
    assert.match(icon, /m29\.5 29\.5 7\.8 7\.8/);
    assert.match(icon, /fill="#A5F3FC"/);
    assert.match(icon, /aria-hidden="true"/);
    assert.match(icon, /focusable="false"/);
    assert.doesNotMatch(icon, /circle cx="10"/);
});

test('brand lockup stays compact and product naming supplies the accessible identity', () => {
    assert.match(lockup, /AppLogoIcon className="size-8/);
    assert.match(lockup, /drop-shadow/);
    assert.match(lockup, /\{name\}/);
    assert.match(lockup, /Research workspace/);
    assert.match(sidebar, /<AppLogo \/>/);
});

test('all authentication variants reuse the same code-native brand mark', () => {
    for (const source of [authSimple, authCard, authSplit]) {
        assert.match(source, /AppLogoIcon/);
    }

    assert.match(authSimple, /YouTube niche research/);
});

test('SVG favicon mirrors the application mark without a remote or raster dependency', () => {
    assert.match(favicon, /viewBox="0 0 48 48"/);
    assert.match(favicon, /nishetube-favicon-gradient/);
    assert.match(favicon, /<circle/);
    assert.match(favicon, /fill="#A5F3FC"/);
    assert.doesNotMatch(favicon, /FF2D20/);
    assert.match(appView, /href="\/favicon\.svg"/);
    assert.doesNotMatch(appView, /favicon\.ico|apple-touch-icon\.png/);
});
