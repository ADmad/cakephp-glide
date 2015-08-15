<?php
return [
    'Glide' => [
        'serverConfig' => [
            'cache' => TMP . 'glide',
            'response' => new ADmad\Glide\Responses\CakeResponseFactory(),
        ],
        'signedUrls' => true,
        'headers' => [
        	'Cache-Control' => 'max-age=31536000, public',
        	'Expires' => true
        ]
    ]
];
