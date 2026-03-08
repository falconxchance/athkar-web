<?php
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'athkar_app',
        'user' => 'athkar_user',
        'pass' => 'CHANGE_ME',
        'charset' => 'utf8mb4',
    ],
    'admin' => [
        'username' => 'admin',
        // Bootstrap admin (used ONLY until you create the first DB admin user).
        // Once DB users exist, logins come from the database.
        // Generate your own with: php -r "echo password_hash('yourpassword', PASSWORD_BCRYPT);"
        'password_hash' => 'CHANGE_ME_BCRYPT_HASH',
    ],
];
