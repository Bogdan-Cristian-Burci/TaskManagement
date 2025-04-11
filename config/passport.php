<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Passport Guard
    |--------------------------------------------------------------------------
    */
    'guard' => 'api',

    /*
    |--------------------------------------------------------------------------
    | Encryption Keys
    |--------------------------------------------------------------------------
    |
    | Passport uses encryption keys for generating secure access tokens.
    | Before using this feature, you need to install the passport keys
    | using the command: php artisan passport:keys
    |
    */

    'private_key' => env('PASSPORT_PRIVATE_KEY'),
    'public_key' => env('PASSPORT_PUBLIC_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Client UUIDs
    |--------------------------------------------------------------------------
    |
    | By default, Passport uses auto-incrementing primary keys when assigning
    | IDs to clients. For additional security, you can set this to true to
    | use UUIDs instead. You'll need to install the `ramsey/uuid` package.
    |
    */

    'client_uuids' => false,

    /*
    |--------------------------------------------------------------------------
    | Personal Access Client
    |--------------------------------------------------------------------------
    |
    | If you enable client hashing, you need to set the personal access client
    | ID and secret which is used when granting personal access tokens.
    | Generate these using php artisan passport:client --personal
    |
    */

    'personal_access_client' => [
        'id' => env('PASSPORT_PERSONAL_ACCESS_CLIENT_ID'),
        'secret' => env('PASSPORT_PERSONAL_ACCESS_CLIENT_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Passport Storage Driver
    |--------------------------------------------------------------------------
    |
    | This configuration option determines the storage driver that will be
    | used to store Passport's data. In addition to using Laravel's cache
    | system, you may use a Redis, database, or file based storage.
    |
    */

    'storage' => [
        'database' => [
            'connection' => env('DB_CONNECTION', 'mysql'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Lifetimes
    |--------------------------------------------------------------------------
    */
    'token_lifetimes' => [
        'access' => env('PASSPORT_ACCESS_TOKEN_TTL', 60), // 1 hour in minutes
        'refresh' => env('PASSPORT_REFRESH_TOKEN_TTL', 30 * 24 * 60), // 30 days in minutes
        'personal_access' => env('PASSPORT_PERSONAL_ACCESS_TOKEN_TTL', 365 * 24 * 60), // 1 year in minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Cookie Config
    |--------------------------------------------------------------------------
    */
    'cookie' => [
        'name' => env('PASSPORT_COOKIE_NAME', 'passport_token'),
        'expiration' => env('PASSPORT_COOKIE_EXPIRATION', 120),
        'path' => env('PASSPORT_COOKIE_PATH', '/'),
        'domain' => env('PASSPORT_COOKIE_DOMAIN', null),
        'secure' => env('PASSPORT_COOKIE_SECURE', true),
        'same_site' => env('PASSPORT_COOKIE_SAME_SITE', 'lax'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Grant Client
    |--------------------------------------------------------------------------
    */
    'password_client' => [
        'id' => env('PASSPORT_PASSWORD_CLIENT_ID'),
        'secret' => env('PASSPORT_PASSWORD_CLIENT_SECRET'),
    ],
];
