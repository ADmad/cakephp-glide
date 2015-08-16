<?php
use Cake\Core\Configure;

Configure::write('Glide', [
    'serverConfig' => [
        'base_url' => '/images/',
        'source' => WWW_ROOT . 'uploads/',
        'cache' => WWW_ROOT . 'cache',
        'response' => new ADmad\Glide\Responses\CakeResponseFactory(),
    ],
    'secureUrls' => true,
    'headers' => [
        'Cache-Control' => 'max-age=31536000, public',
        'Expires' => true
    ]
]);
