<?php

return [
    'max_comments' => (int) env('YOUTUBE_COMMENT_MAX_RESULTS', 200),
    'page_size' => (int) env('YOUTUBE_COMMENT_PAGE_SIZE', 100),
];
