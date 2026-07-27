<?php

return [
    'offer_stale_after_hours' => max(
        1,
        (int) env('OFFER_STALE_AFTER_HOURS', 168),
    ),
];
