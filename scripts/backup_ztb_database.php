<?php

// One-time production backup helper. Run on the application server before migration.
$project = '/var/www/ztb';
$target = $argv[1] ?? null;
if (! is_string($target) || ! str_starts_with($target, '/var/backups/ztb-') || ! str_ends_with($target, '.sql.gz')) {
    fwrite(STDERR, "Invalid backup target.\n");
    exit(1);
}

require $project.'/vendor/autoload.php';
$app = require $project.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$pdo = Illuminate\Support\Facades\DB::connection()->getPdo();
$output = gzopen($target, 'wb9');
if ($output === false) {
    throw new RuntimeException('Unable to create database backup.');
}

try {
    gzwrite($output, "SET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n");
    $tables = $pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);
    foreach ($tables as $tableInfo) {
        $table = (string) $tableInfo[0];
        if (($tableInfo[1] ?? 'BASE TABLE') !== 'BASE TABLE') {
            continue;
        }
        $quotedTable = '`'.str_replace('`', '``', $table).'`';
        $create = $pdo->query('SHOW CREATE TABLE '.$quotedTable)->fetch(PDO::FETCH_NUM);
        gzwrite($output, "DROP TABLE IF EXISTS {$quotedTable};\n{$create[1]};\n");
        $rows = $pdo->query('SELECT * FROM '.$quotedTable);
        while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
            $columns = array_map(fn ($column) => '`'.str_replace('`', '``', $column).'`', array_keys($row));
            $values = array_map(fn ($value) => $value === null ? 'NULL' : $pdo->quote((string) $value), array_values($row));
            gzwrite($output, 'INSERT INTO '.$quotedTable.' ('.implode(', ', $columns).') VALUES ('.implode(', ', $values).");\n");
        }
        $rows->closeCursor();
    }
    gzwrite($output, "SET FOREIGN_KEY_CHECKS=1;\n");
} finally {
    gzclose($output);
}

echo $target.' '.filesize($target)." bytes\n";
