import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');
const css = read('../../resources/css/app.css');
const primitives = read('../../resources/js/components/liquid-glass.tsx');
const showcase = read('../../resources/js/pages/design-system.tsx');
const appearance = read('../../resources/js/hooks/use-appearance.tsx');
const routes = read('../../routes/web.php');

test('Liquid Glass foundation defines constrained materials and accessible fallbacks', () => {
    for (const token of [
        '--lg-canvas',
        '--lg-violet',
        '--lg-focus',
        '--lg-border-soft',
        '--lg-shadow-command',
    ]) {
        assert.match(css, new RegExp(token));
    }

    assert.match(css, /@media \(prefers-reduced-motion: reduce\)/);
    assert.match(css, /@media \(forced-colors: active\)/);
    assert.match(css, /lg-material-command/);
    assert.match(css, /backdrop-filter: none/);
    assert.doesNotMatch(css, /\.dark\s*\{/);
});

test('showcase exercises every named reusable Liquid Glass primitive and all state grammar', () => {
    for (const primitive of [
        'AppCanvas',
        'GlassNavigationRail',
        'GlassCommandBar',
        'GlassCapsule',
        'GlassPanel',
        'GlassPopover',
        'GlassSheet',
        'ContentPanel',
        'MetricTile',
        'EvidenceRow',
        'DataTableFrame',
        'InspectorPanel',
        'ChartFrame',
        'AsyncSection',
    ]) {
        assert.match(primitives, new RegExp(`(?:function|const) ${primitive}`));
        assert.match(showcase, new RegExp(`<${primitive}`));
    }

    assert.match(
        showcase,
        /Loading, empty, partial, success, and error\s+grammar/,
    );
    assert.match(showcase, /Cum am construit un studio YouTube/);
    assert.match(showcase, /Полный разбор компактной камеры/);
});

test('design-system route remains local and verified while appearance is forced light', () => {
    assert.match(routes, /app\(\)->environment\('local'\)/);
    assert.match(routes, /Route::inertia\('design-system', 'design-system'\)/);
    assert.match(routes, /Route::middleware\(\['auth', 'verified'\]\)/);
    assert.match(appearance, /currentAppearance: Appearance = 'light'/);
    assert.match(appearance, /classList\.remove\('dark'\)/);
    assert.match(appearance, /colorScheme = 'light'/);
});
