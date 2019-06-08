#!/usr/bin/env php
<?php

include '/srv/ttrss-utils.php';

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


$config = array();
$config['DB_TYPE'] = $db_type;
$config['DB_HOST'] = $db_host;
$config['DB_PORT'] = $db_port;
$config['DB_NAME'] = $db_name;
$config['DB_USER'] = $db_user;
$config['DB_PASS'] = $db_pass;

$pdo = dbconnect($config);
try {
    $pdo->query('SELECT 1 FROM plugin_mobilize_feeds');
    // reached this point => table found, assume db is complete
}
catch (PDOException $e) {
    echo 'Database table for mobilize plugin not found, applying schema... ' . PHP_EOL;
    $schema = file_get_contents('/srv/ttrss-plugin-mobilize.'.$db_type);
    $schema = preg_replace('/--(.*?);/', '', $schema);
    $schema = preg_replace('/[\r\n]/', ' ', $schema);
    $schema = trim($schema, ' ;');
    foreach (explode(';', $schema) as $stm) {
        $pdo->exec($stm);
    }
    unset($pdo);
}

$contents = file_get_contents($confpath);
foreach ($config as $name => $value) {
    $contents = preg_replace('/(define\s*\(\'' . $name . '\',\s*)(.*)(\);)/', '$1"' . $value . '"$3', $contents);
}
file_put_contents($confpath, $contents);
