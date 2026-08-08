<?php

return [
    'disk' => env('EXPORT_DISK', 'local'),
    'expiry_days' => (int) env('EXPORT_EXPIRY_DAYS', 7),
    'max_research_runs' => (int) env('EXPORT_MAX_RESEARCH_RUNS', 100),
];
