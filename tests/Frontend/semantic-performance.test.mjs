import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const [component, analyzerPage, types] = await Promise.all([
    readFile(
        new URL(
            '../../resources/js/features/analyzer/topic-performance.tsx',
            import.meta.url,
        ),
        'utf8',
    ),
    readFile(
        new URL('../../resources/js/pages/analyzer/show.tsx', import.meta.url),
        'utf8',
    ),
    readFile(
        new URL('../../resources/js/types/analyzer.ts', import.meta.url),
        'utf8',
    ),
]);

test('Analyzer renders filtered accessible topic and title-pattern performance evidence', () => {
    assert.match(analyzerPage, /<TopicPerformance/);
    assert.match(component, /Calculating topic performance/);
    assert.match(component, /Topic performance unavailable/);
    assert.match(component, /Topic performance could not be calculated/);
    assert.match(component, /Insufficient topic performance sample/);
    assert.match(component, /Evidence grouping/);
    assert.match(component, /Editorial title patterns/);
    assert.match(component, /Observed association/);
    assert.match(component, /not evidence that a topic or title/);
    assert.match(component, /<caption className="sr-only">/);
    assert.match(component, /Median lifetime views\/day/);
    assert.match(component, /Breakout rate/);
    assert.match(component, /Queue CSV/);
    assert.match(component, /Queue XLSX/);
    assert.match(component, /performance-export/);
    assert.match(component, /Below minimum/);
    assert.match(component, /Unclassified/);
    assert.match(types, /AnalyzerTopicPerformanceAggregate/);
});
