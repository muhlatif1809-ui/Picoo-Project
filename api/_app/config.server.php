<?php
// config.server.php : koneksi database untuk server Nivor.
// Isi DB_PASSWORD dengan password BARU dari Nivor (Databases > Reset password).
// File ini berisi rahasia: repo harus PRIVATE. Jangan bagikan isinya.
// Kalau password mengandung tanda ' atau \, tambahkan \ di depannya.
return [
    'DB_CONNECTION' => 'pgsql',
    'DB_HOST'       => 'nivor-postgres',
    'DB_PORT'       => '5432',
    'DB_DATABASE'   => 'db_pico',
    'DB_USERNAME'   => 'latif',
    'DB_PASSWORD'   => ';7v_N',
];