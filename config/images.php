<?php

return [
    'optimizer' => env('IMAGE_OPTIMIZER', 'none'),
    'cloudflare' => [
        'proxy_domain' => env('CLOUDFLARE_IMAGE_PROXY_DOMAIN'),
    ],
    'imagekit' => [
        'url_endpoint' => env('IMAGEKIT_URL_ENDPOINT'),
    ],
];
