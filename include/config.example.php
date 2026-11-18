<?php

// Copy to config.local.php; never commit the local file.
return [
    'DB_HOST' => 'localhost',
    'DB_PORT' => '3306',
    'DB_NAME' => 'cinescope',
    'DB_USER' => 'root',
    'DB_PASSWORD' => '',
    'OMDB_API_KEY' => '',
    // Optional: fuer Trailer und den TMDb-Import (https://www.themoviedb.org/settings/api)
    'TMDB_API_KEY' => '',
    'ADMIN_USERNAME' => 'admin',
    'ADMIN_PASSWORD_HASH' => '',
];
