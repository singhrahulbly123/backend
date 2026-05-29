<?php

return [
    'provider' => env('CDN_PROVIDER', 'cloudflare'),

    'cloudflare' => [
        'zone_id' => env('CLOUDFLARE_ZONE_ID'),
    ],
];
