<?php
return [
    'environment' => env('MIDTRANS_ENVIRONMENT', 'sandbox'),
    'production_enabled' => env('MIDTRANS_PRODUCTION_ENABLED', false),
    // SHA-256 of a randomly generated device token. Never store the plaintext token here.
    'device_token_hash' => env('BOOTH_DEVICE_TOKEN_HASH', ''),
    'midtrans_server_key' => env('MIDTRANS_SERVER_KEY', ''),
    'packages' => [
        ['id'=>'basic', 'name'=>'Paket Basic', 'description'=>'Momen manis untuk diri sendiri', 'price'=>25000, 'photo_count'=>4, 'print_copies'=>1, 'allow_retake'=>true],
        ['id'=>'double', 'name'=>'Paket Double', 'description'=>'Satu untukmu, satu untuk teman', 'price'=>40000, 'photo_count'=>4, 'print_copies'=>2, 'allow_retake'=>true],
        ['id'=>'express', 'name'=>'Paket Express', 'description'=>'Singkat, spontan, berkesan', 'price'=>20000, 'photo_count'=>3, 'print_copies'=>1, 'allow_retake'=>false],
    ],
];
