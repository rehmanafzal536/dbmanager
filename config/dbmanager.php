<?php

return [
    'username'    => env('DBMANAGER_USERNAME', 'admin'),
    'password'    => env('DBMANAGER_PASSWORD', 'secret'),
    'prefix'      => 'dbmanager',
    'session_key' => 'dbmanager_authenticated',
];
