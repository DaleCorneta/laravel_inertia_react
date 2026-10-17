<?php

return [
    'disabled_events' => array_values(array_filter(array_map('trim', explode(',', (string) env('AUDIT_DISABLED_EVENTS', ''))))),
];
