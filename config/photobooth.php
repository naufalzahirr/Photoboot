<?php
return [
    'environment' => env('MIDTRANS_ENVIRONMENT', 'sandbox'),
    'production_enabled' => env('MIDTRANS_PRODUCTION_ENABLED', false),
    // SHA-256 of a randomly generated device token. Never store the plaintext token here.
    'device_token_hash' => env('BOOTH_DEVICE_TOKEN_HASH', ''),
    'midtrans_server_key' => env('MIDTRANS_SERVER_KEY', ''),
    'packages' => [
        ['id'=>'basic', 'name'=>'Cetak Biasa', 'description'=>'6 foto · 1 lembar 4R · 2 strip', 'price'=>15000, 'photo_count'=>6, 'print_copies'=>1, 'allow_retake'=>true],
        ['id'=>'double', 'name'=>'Cetak Double', 'description'=>'6 foto · 2 lembar 4R · 4 strip', 'price'=>25000, 'photo_count'=>6, 'print_copies'=>2, 'allow_retake'=>true],
        ['id'=>'triple', 'name'=>'Cetak Triple', 'description'=>'6 foto · 3 lembar 4R · 6 strip', 'price'=>40000, 'photo_count'=>6, 'print_copies'=>3, 'allow_retake'=>true],
    ],
];
