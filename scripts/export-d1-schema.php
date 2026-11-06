<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$output = $argv[1] ?? null;

if (! $output) {
    fwrite(STDERR, "Usage: php scripts/export-d1-schema.php <output.sql>\n");
    exit(2);
}

$projectRoot = dirname(__DIR__);
$outputPath = $output;

if (! str_starts_with($outputPath, DIRECTORY_SEPARATOR) && ! preg_match('/^[A-Za-z]:[\\\\\/]/', $outputPath)) {
    $outputPath = $projectRoot.DIRECTORY_SEPARATOR.$outputPath;
}

$temporaryDatabase = tempnam(sys_get_temp_dir(), 'pos-d1-schema-');

if ($temporaryDatabase === false) {
    throw new RuntimeException('Unable to create the temporary SQLite database.');
}

register_shutdown_function(static function () use ($temporaryDatabase): void {
    if (file_exists($temporaryDatabase)) {
        unlink($temporaryDatabase);
    }
});

foreach ([
    'APP_ENV' => 'local',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => $temporaryDatabase,
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require $projectRoot.'/vendor/autoload.php';

$app = require $projectRoot.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$exitCode = $kernel->call('migrate', [
    '--database' => 'sqlite',
    '--force' => true,
    '--no-interaction' => true,
]);

if ($exitCode !== 0) {
    throw new RuntimeException($kernel->output() ?: 'Laravel migrations failed.');
}

$database = new PDO('sqlite:'.$temporaryDatabase);
$schema = $database->query(
    "SELECT type, name, sql
     FROM sqlite_master
     WHERE sql IS NOT NULL
       AND name NOT LIKE 'sqlite_%'
       AND type IN ('table', 'index', 'trigger', 'view')
     ORDER BY CASE type
        WHEN 'table' THEN 0
        WHEN 'index' THEN 1
        WHEN 'trigger' THEN 2
        ELSE 3
     END, name",
)->fetchAll(PDO::FETCH_ASSOC);

$sql = [
    '-- Generated from the current Laravel migrations using a fresh temporary SQLite database.',
    '-- No application database or customer data is read or exported.',
];

foreach ($schema as $object) {
    $sql[] = $object['sql'].';';
}

foreach (['migrations', 'setup_state'] as $table) {
    $rows = $database->query('SELECT * FROM "'.$table.'"')->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $columns = array_map(static fn (string $column): string => '"'.$column.'"', array_keys($row));
        $values = array_map(
            static fn (mixed $value): string => $value === null ? 'NULL' : $database->quote((string) $value),
            array_values($row),
        );

        $sql[] = 'INSERT INTO "'.$table.'" ('.implode(', ', $columns).') VALUES ('.implode(', ', $values).');';
    }
}

$directory = dirname($outputPath);

if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
    throw new RuntimeException('Unable to create the output directory.');
}

if (file_put_contents($outputPath, implode("\n\n", $sql)."\n") === false) {
    throw new RuntimeException('Unable to write the D1 schema file.');
}

DB::purge('sqlite');
$database = null;

fwrite(STDOUT, 'Wrote '.count($schema).' schema objects to '.$outputPath.PHP_EOL);
