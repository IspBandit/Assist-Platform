<?php

declare(strict_types=1);

/**
 * Provider promotion / ad graphic specifications.
 */
return [
    'desktop' => [
        'width'  => 1200,
        'height' => 400,
        'label'  => 'Desktop & tablet (1200×400)',
    ],
    'mobile' => [
        'width'  => 800,
        'height' => 450,
        'label'  => 'Mobile (800×450)',
    ],
    /** Matches the picture/source breakpoint in partials.provider-promotion-ad. */
    'mobile_max_width_px' => 719,
];
