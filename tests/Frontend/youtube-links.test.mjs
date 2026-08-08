import assert from 'node:assert/strict';
import test from 'node:test';
import {
    externalYouTubeLinkProps,
    videoPreviewAvailable,
    youtubeChannelUrl,
    youtubeVideoUrl,
} from '../../resources/js/features/research/analysis/youtube-links.ts';

test('builds canonical safe YouTube result links', () => {
    assert.equal(
        youtubeVideoUrl('video-safe_1'),
        'https://www.youtube.com/watch?v=video-safe_1',
    );
    assert.equal(
        youtubeChannelUrl('channel-safe_1'),
        'https://www.youtube.com/channel/channel-safe_1',
    );
    assert.deepEqual(externalYouTubeLinkProps, {
        target: '_blank',
        rel: 'noopener noreferrer',
    });
});

test('encodes provider identifiers before placing them in outbound URLs', () => {
    assert.equal(
        youtubeVideoUrl('unsafe&id'),
        'https://www.youtube.com/watch?v=unsafe%26id',
    );
    assert.equal(
        youtubeChannelUrl('unsafe/id'),
        'https://www.youtube.com/channel/unsafe%2Fid',
    );
});

test('uses the preview fallback for missing or failed thumbnails', () => {
    assert.equal(
        videoPreviewAvailable('https://images.example.test/video.jpg', false),
        true,
    );
    assert.equal(videoPreviewAvailable(null, false), false);
    assert.equal(
        videoPreviewAvailable('https://images.example.test/video.jpg', true),
        false,
    );
});
