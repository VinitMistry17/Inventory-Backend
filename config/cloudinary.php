<?php

return [
    'cloud' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
    ],
    'api' => [
        'key' => env('CLOUDINARY_API_KEY'),
        'secret' => env('CLOUDINARY_API_SECRET'),
    ],
    'secure' => true,
    'url' => [
        'secure' => true,
    ],
];
