<?php

declare(strict_types=1);

use App\Helpers\Env;

return [
    'facebook' => [
        'graph_version' => (string) Env::get('FACEBOOK_GRAPH_VERSION', 'v24.0'),
        'pages' => [
            'vanassist' => ['page_id' => Env::get('FACEBOOK_VANASSIST_PAGE_ID', ''), 'access_token' => Env::get('FACEBOOK_VANASSIST_PAGE_ACCESS_TOKEN', '')],
            'towsmart' => ['page_id' => Env::get('FACEBOOK_TOWSMART_PAGE_ID', ''), 'access_token' => Env::get('FACEBOOK_TOWSMART_PAGE_ACCESS_TOKEN', '')],
            'trailerwise' => ['page_id' => Env::get('FACEBOOK_TRAILERWISE_PAGE_ID', ''), 'access_token' => Env::get('FACEBOOK_TRAILERWISE_PAGE_ACCESS_TOKEN', '')],
        ],
    ],
];
