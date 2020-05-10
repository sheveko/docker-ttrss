#!/usr/bin/env php
<?php

include '/srv/ttrss-utils.php';

if (!env('TTRSS_PATH', ''))
    $confpath = '/var/www/ttrss/';
$conffile = $confpath . 'config.php';

$db_type = env('DB_TYPE', 'pgsql');
$db_host = env('DB_HOST', 'DB');

$db_port = getenv('DB_PORT');
if ($db_port === null && $db_type == 'pgsql') {
    $db_port = 5432;
} elseif ($db_port === null && $db_type == 'mysql') {
    $db_port = 3306;
}

// database credentials for this instance
//   database name (DB_NAME) can be supplied or defaults to "ttrss"
//   database user (DB_USER) can be supplied or defaults to "ttrss"
//   database pass (DB_PASS) can be supplied or defaults to "ttrss"
$db_super_user = env('DB_SUPER_USER', 'postgres');
$db_super_pass = env('DB_SUPER_PASS', 'postgres');
$db_name = env('DB_NAME', 'ttrss');
$db_user = env('DB_USER', 'ttrss');
$db_pass = env('DB_PASS', 'ttrss');

echo 'Configuring database for: ' . $conffile . PHP_EOL;

$config = array();
$config['DB_TYPE'] = $db_type;
$config['DB_HOST'] = $db_host;
$config['DB_PORT'] = $db_port;
$config['DB_NAME'] = $db_name;
$config['DB_USER'] = $db_user;
$config['DB_PASS'] = $db_pass;

// path to ttrss
$config['SELF_URL_PATH'] = env('SELF_URL_PATH', 'http://localhost');

if (!dbcheck($config)) {
    echo 'Database login failed, trying to create ...' . PHP_EOL;
    // superuser account to create new database and corresponding user account
    //   username (SU_USER) can be supplied or defaults to "docker"
    //   password (SU_PASS) can be supplied or defaults to username

    $super = $config;

    $super['DB_NAME'] = null;
    $super['DB_USER'] = $db_super_user;
    $super['DB_PASS'] = $db_super_pass;

    $pdo = dbconnect($super);
    $pdo->exec('CREATE ROLE ' . ($config['DB_USER']) . ' WITH LOGIN PASSWORD ' . $pdo->quote($config['DB_PASS']));
    $pdo->exec('CREATE DATABASE ' . ($config['DB_NAME']) . ' WITH OWNER ' . ($config['DB_USER']));
    unset($pdo);

    if (dbcheck($config)) {
        echo 'Database login created and confirmed' . PHP_EOL;
    } else {
        error('Database login failed, trying to create login failed as well');
    }
}

$pdo = dbconnect($config);
try {
    $pdo->query('SELECT 1 FROM ttrss_feeds');
    echo 'Connection to database successful' . PHP_EOL;
    // reached this point => table found, assume db is complete
}
catch (PDOException $e) {
    echo 'Database table not found, applying schema... ' . PHP_EOL;
    $schema = file_get_contents($confpath . 'schema/ttrss_schema_' . $config['DB_TYPE'] . '.sql');
    $schema = preg_replace('/--(.*?);/', '', $schema);
    $schema = preg_replace('/[\r\n]/', ' ', $schema);
    $schema = trim($schema, ' ;');
    foreach (explode(';', $schema) as $stm) {
        $pdo->exec($stm);
    }
    unset($pdo);
}

$contents = file_get_contents($conffile);
foreach ($config as $name => $value) {
    $contents = preg_replace('/(define\s*\(\'' . $name . '\',\s*)(.*)(\);)/', '$1"' . $value . '"$3', $contents);
}
file_put_contents($conffile, $contents);
