<?php

return [
    'initial_admin' => [
        'name' => env('BUKO_ADMIN_NAME', 'Administrador BUKO'),
        'email' => env('BUKO_ADMIN_EMAIL', 'admin@buko.local'),
        'password' => env('BUKO_ADMIN_PASSWORD'),
    ],
    'show_local_credentials' => env('BUKO_SHOW_LOCAL_CREDENTIALS', false),
];
