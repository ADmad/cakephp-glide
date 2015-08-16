<?php
use Cake\Core\Configure;

Configure::write('Glide', [
    'serverConfig' => [
        'source' => WWW_ROOT,
        'cache' => WWW_ROOT . 'cache',
        'response' => new ADmad\Glide\Responses\CakeResponseFactory(),
    ],
    'secureUrls' => true,
    'headers' => [
        'Cache-Control' => 'max-age=31536000, public',
        'Expires' => true
    ]
]);
