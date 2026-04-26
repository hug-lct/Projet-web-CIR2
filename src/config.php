<?php

declare(strict_types=1);

return [
    'db_host' => getenv('DB_HOST') !== false ? (string) getenv('DB_HOST') : 'localhost',
    'db_port' => getenv('DB_PORT') !== false ? (int) getenv('DB_PORT') : 8889,
    'db_name' => getenv('DB_NAME') !== false ? (string) getenv('DB_NAME') : 'junia_toilettes',
    'db_user' => getenv('DB_USER') !== false ? (string) getenv('DB_USER') : 'root',
    'db_pass' => getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : 'root',
];
