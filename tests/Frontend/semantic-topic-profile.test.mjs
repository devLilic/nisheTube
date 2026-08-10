import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const [
    analyzerProfile,
    profile,
    analyzerPage,
    research,
    discovery,
    explore,
    workspace,
] = await Promise.all([
    readFile(
        new URL(
            '../../resources/js/features/analyzer/analyzer-profile.tsx',
            import.meta.url,
        ),
        'utf8',
    ),
    readFile(
        new URL(
            '../../resources/js/features/analyzer/topic-profile.tsx',
            import.meta.url,
        ),
        'utf8',
    ),
    readFile(
        new URL('../../resources/js/pages/analyzer/show.tsx', import.meta.url),
        'utf8',
    ),
    readFile(
        new URL(
            '../../resources/js/features/research/analysis/analysis-tables.tsx',
            import.meta.url,
        ),
        'utf8',
    ),
    readFile(
        new URL(
            '../../resources/js/features/discovery/candidate-list.tsx',
            import.meta.url,
        ),
        'utf8',
    ),
    readFile(
        new URL('../../resources/js/pages/explore/index.tsx', import.meta.url),
        'utf8',
    ),
    readFile(
        new URL('../../resources/js/pages/topics/show.tsx', import.meta.url),
        'utf8',
    ),
]);

test('Analyzer renders a versioned inferred Topic Profile with every state', () => {
    assert.match(analyzerPage, /<TopicProfile/);
    assert.match(profile, /Detecting topic profile/);
    assert.match(profile, /Topic profile unavailable/);
    assert.match(profile, /Topic profile could not be calculated/);
    assert.match(profile, /Partial inferred classification/);
    assert.match(profile, /Detected niche/);
    assert.match(profile, /Content pillars/);
    assert.match(profile, /not a YouTube API fact or/);
    assert.match(profile, /algorithm_version/);
    assert.match(profile, /Language:/);
});

test('semantic evidence is visibly integrated without replacing official category or validation', () => {
    assert.match(research, /detected_topic_profile/);
    assert.match(discovery, /Inferred Analyzer topics/);
    assert.match(discovery, /Validation Search is still\s+required/);
    assert.match(explore, /detected_topic_profile/);
    assert.match(workspace, /detected_topic_profile/);
    assert.match(profile, /Inferred/);
    assert.match(analyzerProfile, /Official category/);
});
