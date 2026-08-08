<?php

return [
    'months' => max(1, (int) env('RETENTION_MONTHS', 6)),
];
