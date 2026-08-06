#!/usr/bin/env php
<?php

/**
 * Copy application rows from a SQLite file into the Postgres target
 * configured by Laravel .env DB_* keys.
 *
 * Not a migration. Assumes the target schema already exists
 * (`php artisan migrate --force` against pgsql).
 *
 * Usage:
 *   php scripts/sqlite-to-pgsql.php [--source=PATH] [--dry-run] [--verify] [--truncate] [--skip=a,b]
 *
 * Env loading mirrors scripts/db-backup.sh (DB_* only). Process env wins.
 */

declare(strict_types=1);

const ROOT = __DIR__.'/..';

/** Framework / ephemeral — skip by default. */
const DEFAULT_SKIP = [
    'migrations',
    'cache',
    'cache_locks',
    'jobs',
    'job_batches',
    'failed_jobs',
    'sessions',
    'password_reset_tokens',
];

/** Prefer spine / auth before dependents (cosmetic when replica role is on). */
const PREFERRED_ORDER = [
    'tenants',
    'users',
    'workspaces',
    'workspace_user',
    'company_profiles',
];

final class Cli
{
    public string $source;

    public bool $dryRun = false;

    public bool $verify = false;

    public bool $truncate = false;

    /** @var list<string> */
    public array $extraSkip = [];

    public static function parse(array $argv): self
    {
        $c = new self;
        $c->source = ROOT.'/database/database.sqlite';

        foreach (array_slice($argv, 1) as $arg) {
            if ($arg === '--dry-run') {
                $c->dryRun = true;

                continue;
            }
            if ($arg === '--verify') {
                $c->verify = true;

                continue;
            }
            if ($arg === '--truncate') {
                $c->truncate = true;

                continue;
            }
            if (str_starts_with($arg, '--source=')) {
                $path = substr($arg, 9);
                $c->source = str_starts_with($path, '/') ? $path : ROOT.'/'.ltrim($path, '/');

                continue;
            }
            if (str_starts_with($arg, '--skip=')) {
                $c->extraSkip = array_values(array_filter(array_map('trim', explode(',', substr($arg, 7)))));

                continue;
            }
            if ($arg === '-h' || $arg === '--help') {
                self::usage(0);
            }
            fwrite(STDERR, "error: unknown argument {$arg}\n");
            self::usage(1);
        }

        return $c;
    }

    public static function usage(int $code): never
    {
        $msg = <<<'TXT'
Usage: php scripts/sqlite-to-pgsql.php [options]

  --source=PATH   SQLite file (default: database/database.sqlite)
  --dry-run       List intersection tables + row counts; do not write
  --verify        After copy, compare COUNT(*) per table; exit 1 on mismatch
  --truncate      TRUNCATE … CASCADE all target tables before insert (re-runs)
  --skip=a,b      Extra tables to skip (added to framework defaults)

Target connection comes from .env DB_* (must be pgsql).

TXT;
        fwrite($code === 0 ? STDOUT : STDERR, $msg);
        exit($code);
    }
}

/**
 * @return array<string, string|null>
 */
function loadEnvDb(): array
{
    $envFile = ROOT.'/.env';
    $vars = [
        'DB_CONNECTION' => getenv('DB_CONNECTION') ?: null,
        'DB_HOST' => getenv('DB_HOST') ?: null,
        'DB_PORT' => getenv('DB_PORT') ?: null,
        'DB_DATABASE' => getenv('DB_DATABASE') ?: null,
        'DB_USERNAME' => getenv('DB_USERNAME') ?: null,
        'DB_PASSWORD' => getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : null,
    ];

    if (is_file($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES) ?: [];
        foreach ($lines as $line) {
            $line = rtrim($line, "\r");
            if (! preg_match('/^(DB_CONNECTION|DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|DB_PASSWORD)=(.*)$/', $line, $m)) {
                continue;
            }
            $val = $m[2];
            if (
                (str_starts_with($val, '"') && str_ends_with($val, '"'))
                || (str_starts_with($val, "'") && str_ends_with($val, "'"))
            ) {
                $val = substr($val, 1, -1);
            }
            // Process env wins (Compose / CI overrides).
            if ($vars[$m[1]] === null || $vars[$m[1]] === '') {
                $vars[$m[1]] = $val;
            }
        }
    }

    return $vars;
}

function fail(string $msg, int $code = 1): never
{
    fwrite(STDERR, "error: {$msg}\n");
    exit($code);
}

function connectSqlite(string $path): PDO
{
    if (! is_file($path)) {
        fail("sqlite source not found: {$path}");
    }
    $pdo = new PDO('sqlite:'.$path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = OFF');

    return $pdo;
}

/**
 * @param  array<string, string|null>  $env
 */
function connectPgsql(array $env): PDO
{
    $driver = strtolower((string) ($env['DB_CONNECTION'] ?? ''));
    if (! in_array($driver, ['pgsql', 'postgres', 'postgresql'], true)) {
        fail('DB_CONNECTION must be pgsql (got: '.($env['DB_CONNECTION'] ?? 'null').'). Point .env at Postgres before running.');
    }

    $host = $env['DB_HOST'] ?? '127.0.0.1';
    $port = $env['DB_PORT'] ?? '5432';
    $db = $env['DB_DATABASE'] ?? '';
    $user = $env['DB_USERNAME'] ?? '';
    $pass = $env['DB_PASSWORD'] ?? '';

    if ($db === '' || $user === '') {
        fail('DB_DATABASE and DB_USERNAME are required for pgsql');
    }

    $dsn = "pgsql:host={$host};port={$port};dbname={$db}";

    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

/** @return list<string> */
function sqliteTables(PDO $sqlite): array
{
    $rows = $sqlite->query(
        "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
    )->fetchAll(PDO::FETCH_COLUMN);

    return array_map('strval', $rows);
}

/** @return list<string> */
function pgsqlTables(PDO $pg): array
{
    $stmt = $pg->query(
        "SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename"
    );

    return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/** @return list<string> */
function orderTables(array $tables): array
{
    $set = array_fill_keys($tables, true);
    $out = [];
    foreach (PREFERRED_ORDER as $t) {
        if (isset($set[$t])) {
            $out[] = $t;
            unset($set[$t]);
        }
    }
    $rest = array_keys($set);
    sort($rest);

    return array_merge($out, $rest);
}

/** @return list<string> */
function sqliteColumns(PDO $sqlite, string $table): array
{
    $stmt = $sqlite->query('PRAGMA table_info('.quoteIdentSqlite($table).')');
    $cols = [];
    foreach ($stmt->fetchAll() as $row) {
        $cols[] = (string) $row['name'];
    }

    return $cols;
}

/**
 * @return array{columns: list<string>, types: array<string, string>}
 */
function pgsqlColumns(PDO $pg, string $table): array
{
    $stmt = $pg->prepare(
        "SELECT column_name, data_type, udt_name
         FROM information_schema.columns
         WHERE table_schema = 'public' AND table_name = :t
         ORDER BY ordinal_position"
    );
    $stmt->execute(['t' => $table]);
    $columns = [];
    $types = [];
    foreach ($stmt->fetchAll() as $row) {
        $name = (string) $row['column_name'];
        $columns[] = $name;
        $types[$name] = (string) $row['data_type'];
        if ($row['data_type'] === 'USER-DEFINED') {
            $types[$name] = (string) $row['udt_name'];
        }
    }

    return ['columns' => $columns, 'types' => $types];
}

function quoteIdentSqlite(string $ident): string
{
    return '"'.str_replace('"', '""', $ident).'"';
}

function quoteIdentPg(string $ident): string
{
    return '"'.str_replace('"', '""', $ident).'"';
}

function countTable(PDO $pdo, string $table, bool $pg): int
{
    $q = $pg ? quoteIdentPg($table) : quoteIdentSqlite($table);

    return (int) $pdo->query("SELECT COUNT(*) FROM {$q}")->fetchColumn();
}

function tableHasColumn(PDO $pg, string $table, string $column): bool
{
    $stmt = $pg->prepare(
        "SELECT 1 FROM information_schema.columns
         WHERE table_schema = 'public' AND table_name = :t AND column_name = :c"
    );
    $stmt->execute(['t' => $table, 'c' => $column]);

    return (bool) $stmt->fetchColumn();
}

/**
 * Coerce a SQLite cell into a Postgres-friendly PHP value.
 */
function coerce(mixed $value, string $pgType): mixed
{
    if ($value === null) {
        return null;
    }

    $pgType = strtolower($pgType);

    if ($pgType === 'boolean') {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return ((int) $value) !== 0;
        }
        $s = strtolower(trim((string) $value));
        if (in_array($s, ['1', 't', 'true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($s, ['0', 'f', 'false', 'no', 'off', ''], true)) {
            return false;
        }
        fail('cannot coerce boolean from '.var_export($value, true));
    }

    if ($pgType === 'json' || $pgType === 'jsonb') {
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
        $s = (string) $value;
        if (trim($s) === '') {
            return null;
        }
        json_decode($s, true, 512, JSON_THROW_ON_ERROR);

        return $s;
    }

    return $value;
}

function resetSequences(PDO $pg, string $table): void
{
    $stmt = $pg->prepare(
        "SELECT c.data_type
         FROM information_schema.columns c
         WHERE c.table_schema = 'public' AND c.table_name = :t AND c.column_name = 'id'"
    );
    $stmt->execute(['t' => $table]);
    $type = $stmt->fetchColumn();
    if ($type === false || ! in_array($type, ['bigint', 'integer', 'smallint'], true)) {
        return;
    }

    $seqStmt = $pg->prepare('SELECT pg_get_serial_sequence(:t, \'id\')');
    $seqStmt->execute(['t' => $table]);
    $seq = $seqStmt->fetchColumn();
    if ($seq === false || $seq === null || $seq === '') {
        return;
    }

    $qt = quoteIdentPg($table);
    // Sequence name comes from Postgres itself (pg_get_serial_sequence), not user input.
    $pg->exec(
        'SELECT setval('.
        $pg->quote((string) $seq).'::regclass, '.
        "COALESCE((SELECT MAX(id) FROM {$qt}), 1), ".
        "(SELECT MAX(id) FROM {$qt}) IS NOT NULL)"
    );
}

function copyTable(PDO $sqlite, PDO $pg, string $table): int
{
    $srcCols = sqliteColumns($sqlite, $table);
    $pgMeta = pgsqlColumns($pg, $table);
    $dstCols = $pgMeta['columns'];
    $types = $pgMeta['types'];

    $cols = array_values(array_intersect($srcCols, $dstCols));
    if ($cols === []) {
        fail("no overlapping columns for table {$table}");
    }

    $srcOnly = array_diff($srcCols, $dstCols);
    $dstOnly = array_diff($dstCols, $srcCols);
    if ($srcOnly !== []) {
        fwrite(STDERR, "warn: {$table}: sqlite-only columns skipped: ".implode(',', $srcOnly)."\n");
    }
    if ($dstOnly !== []) {
        fwrite(STDERR, "warn: {$table}: pgsql-only columns left default/null: ".implode(',', $dstOnly)."\n");
    }

    $qt = quoteIdentPg($table);
    $colList = implode(', ', array_map('quoteIdentPg', $cols));
    $placeholders = implode(', ', array_fill(0, count($cols), '?'));
    $insert = $pg->prepare("INSERT INTO {$qt} ({$colList}) VALUES ({$placeholders})");

    $qs = quoteIdentSqlite($table);
    $selectList = implode(', ', array_map('quoteIdentSqlite', $cols));
    $rows = $sqlite->query("SELECT {$selectList} FROM {$qs}");

    $n = 0;
    while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
        $params = [];
        foreach ($cols as $c) {
            $params[] = coerce($row[$c] ?? null, $types[$c] ?? 'text');
        }
        $insert->execute($params);
        $n++;
    }

    return $n;
}

// ---------------------------------------------------------------------------
// main
// ---------------------------------------------------------------------------

$cli = Cli::parse($argv);
$env = loadEnvDb();
$skip = array_values(array_unique(array_merge(DEFAULT_SKIP, $cli->extraSkip)));

$sqlite = connectSqlite($cli->source);
$pg = connectPgsql($env);

$src = sqliteTables($sqlite);
$dst = pgsqlTables($pg);
$both = array_values(array_intersect($src, $dst));
$tables = array_values(array_filter($both, static fn (string $t): bool => ! in_array($t, $skip, true)));
$tables = orderTables($tables);

$srcOnly = array_values(array_diff($src, $dst, $skip));
$dstOnly = array_values(array_diff($dst, $src, $skip));

echo "source: {$cli->source}\n";
echo 'target: '.($env['DB_HOST'] ?? '').'/'.($env['DB_DATABASE'] ?? '')."\n";
echo 'tables to copy: '.count($tables)."\n";
if ($srcOnly !== []) {
    echo 'sqlite-only (ignored): '.implode(', ', $srcOnly)."\n";
}
if ($dstOnly !== []) {
    echo 'pgsql-only (ignored): '.implode(', ', $dstOnly)."\n";
}
echo 'skipped: '.implode(', ', $skip)."\n\n";

$counts = [];
foreach ($tables as $t) {
    $sc = countTable($sqlite, $t, false);
    $pc = countTable($pg, $t, true);
    $counts[$t] = ['sqlite' => $sc, 'pgsql' => $pc];
    printf("%-32s  sqlite=%-6d  pgsql=%-6d\n", $t, $sc, $pc);
}

if ($cli->dryRun) {
    echo "\ndry-run complete; no writes.\n";
    exit(0);
}

if (! $cli->truncate) {
    foreach ($counts as $t => $c) {
        if ($c['pgsql'] > 0 && $c['sqlite'] > 0) {
            fail("target table {$t} already has {$c['pgsql']} rows. Re-run with --truncate, or use a fresh database.");
        }
    }
}

echo "\ncopying…\n";
$pg->exec('SET session_replication_role = replica');

$copied = 0;
try {
    if ($cli->truncate && $tables !== []) {
        $list = implode(', ', array_map('quoteIdentPg', $tables));
        $pg->exec("TRUNCATE TABLE {$list} CASCADE");
        echo "  truncated ".count($tables)." tables\n";
    }

    foreach ($tables as $t) {
        $n = copyTable($sqlite, $pg, $t);
        printf("  %-32s  inserted=%d\n", $t, $n);
        $copied += $n;
    }

    echo "resetting sequences…\n";
    foreach ($tables as $t) {
        resetSequences($pg, $t);
    }
} catch (Throwable $e) {
    try {
        $pg->exec('SET session_replication_role = DEFAULT');
    } catch (Throwable) {
        // ignore
    }
    fail($e->getMessage());
}

$pg->exec('SET session_replication_role = DEFAULT');
echo "copied rows total: {$copied}\n";

if ($cli->verify) {
    echo "verifying counts…\n";
    $bad = 0;
    foreach ($tables as $t) {
        $sc = countTable($sqlite, $t, false);
        $pc = countTable($pg, $t, true);
        $ok = $sc === $pc ? 'OK' : 'MISMATCH';
        printf("  %-32s  sqlite=%-6d  pgsql=%-6d  %s\n", $t, $sc, $pc, $ok);
        if ($sc !== $pc) {
            $bad++;
        }
    }
    foreach (['users', 'events', 'tenants'] as $spot) {
        if (! in_array($spot, $tables, true)) {
            continue;
        }
        if ($spot !== 'tenants' && tableHasColumn($pg, $spot, 'tenant_id')) {
            $nullTenants = (int) $pg->query(
                'SELECT COUNT(*) FROM '.quoteIdentPg($spot).' WHERE tenant_id IS NULL'
            )->fetchColumn();
            echo "  spot: {$spot}.tenant_id IS NULL → {$nullTenants}\n";
        }
    }
    if ($bad > 0) {
        fail("verify failed: {$bad} table(s) with count mismatch");
    }
    echo "verify OK\n";
}

echo "done.\n";
exit(0);
