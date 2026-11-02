<?php

/**
 * Tiny hit logger for club PHP pages. Fail-open: never break the page.
 */

function hits_db_path(): string
{
    return dirname(__DIR__) . '/data/hits.sqlite';
}

/**
 * @return array{ok: bool, error: string, pdo: ?PDO}
 */
function hits_status(): array
{
    static $cached = null;

    if ($cached !== null) {
        return $cached;
    }

    if (!extension_loaded('pdo_sqlite')) {
        $cached = [
            'ok' => false,
            'error' => 'pdo_sqlite extension not loaded (restart php if you just installed it)',
            'pdo' => null,
        ];
        return $cached;
    }

    $path = hits_db_path();
    $dir = dirname($path);

    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        $cached = [
            'ok' => false,
            'error' => 'cannot create data/ directory',
            'pdo' => null,
        ];
        return $cached;
    }

    if (!is_writable($dir)) {
        $cached = [
            'ok' => false,
            'error' => 'data/ is not writable',
            'pdo' => null,
        ];
        return $cached;
    }

    try {
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS hits (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ts INTEGER NOT NULL,
                path TEXT NOT NULL,
                ip TEXT NOT NULL DEFAULT \'\',
                referrer TEXT NOT NULL DEFAULT \'\',
                ua TEXT NOT NULL DEFAULT \'\'
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS hits_ts ON hits(ts)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS hits_path_ts ON hits(path, ts)');
        $cached = ['ok' => true, 'error' => '', 'pdo' => $pdo];
    } catch (Throwable $e) {
        $cached = [
            'ok' => false,
            'error' => 'sqlite open failed: ' . $e->getMessage(),
            'pdo' => null,
        ];
    }

    return $cached;
}

function hits_db(): ?PDO
{
    $status = hits_status();
    return $status['pdo'];
}

function hits_normalize_path(?string $uri): string
{
    $uri = (string) $uri;
    $path = parse_url($uri, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        $path = '/';
    }

    // collapse // and drop trailing slash except root
    $path = '/' . ltrim($path, '/');
    if ($path !== '/' && str_ends_with($path, '/')) {
        $path = rtrim($path, '/');
    }

    return substr($path, 0, 200);
}

function hits_normalize_referrer(?string $ref): string
{
    $ref = trim((string) $ref);
    if ($ref === '') {
        return '';
    }

    $parts = parse_url($ref);
    if (!is_array($parts) || empty($parts['host'])) {
        return substr($ref, 0, 300);
    }

    $host = strtolower((string) $parts['host']);
    $path = (string) ($parts['path'] ?? '/');
    if ($path === '') {
        $path = '/';
    }

    return substr($host . $path, 0, 300);
}

function hits_client_ip(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
        return $ip;
    }
    return '';
}

function hits_should_skip(string $path): bool
{
    if ($path === '/admin' || str_starts_with($path, '/admin/')) {
        return true;
    }
    return false;
}

function hits_log(): void
{
    $path = hits_normalize_path($_SERVER['REQUEST_URI'] ?? '/');
    if (hits_should_skip($path)) {
        return;
    }

    $db = hits_db();
    if ($db === null) {
        return;
    }

    $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250);
    $ref = hits_normalize_referrer($_SERVER['HTTP_REFERER'] ?? '');

    // drop same-site referrers (internal nav noise)
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host !== '' && $ref !== '' && str_starts_with($ref, $host)) {
        $ref = '';
    }

    try {
        $stmt = $db->prepare(
            'INSERT INTO hits (ts, path, ip, referrer, ua) VALUES (:ts, :path, :ip, :referrer, :ua)'
        );
        $stmt->execute([
            ':ts' => time(),
            ':path' => $path,
            ':ip' => hits_client_ip(),
            ':referrer' => $ref,
            ':ua' => $ua,
        ]);
    } catch (Throwable $e) {
        // fail open
    }
}

/**
 * @return array{
 *   hits: int,
 *   uniques: int,
 *   top_paths: list<array{path: string, hits: int}>,
 *   top_referrers: list<array{referrer: string, hits: int}>,
 *   recent: list<array{ts: int, path: string, ip: string, referrer: string, ua: string}>
 * }|null
 */
function hits_summary(int $days = 7, int $limit = 15): ?array
{
    $db = hits_db();
    if ($db === null) {
        return null;
    }

    $days = max(1, min(90, $days));
    $limit = max(1, min(50, $limit));
    $since = time() - ($days * 86400);

    try {
        $totals = $db->prepare(
            'SELECT COUNT(*) AS hits, COUNT(DISTINCT ip) AS uniques
             FROM hits WHERE ts >= :since'
        );
        $totals->execute([':since' => $since]);
        $row = $totals->fetch() ?: ['hits' => 0, 'uniques' => 0];

        $paths = $db->prepare(
            'SELECT path, COUNT(*) AS hits
             FROM hits WHERE ts >= :since
             GROUP BY path ORDER BY hits DESC LIMIT :lim'
        );
        $paths->bindValue(':since', $since, PDO::PARAM_INT);
        $paths->bindValue(':lim', $limit, PDO::PARAM_INT);
        $paths->execute();

        $refs = $db->prepare(
            'SELECT referrer, COUNT(*) AS hits
             FROM hits
             WHERE ts >= :since AND referrer != \'\'
             GROUP BY referrer ORDER BY hits DESC LIMIT :lim'
        );
        $refs->bindValue(':since', $since, PDO::PARAM_INT);
        $refs->bindValue(':lim', $limit, PDO::PARAM_INT);
        $refs->execute();

        $recent = $db->prepare(
            'SELECT ts, path, ip, referrer, ua
             FROM hits ORDER BY id DESC LIMIT 25'
        );
        $recent->execute();

        return [
            'hits' => (int) $row['hits'],
            'uniques' => (int) $row['uniques'],
            'top_paths' => array_map(
                static fn(array $r): array => ['path' => (string) $r['path'], 'hits' => (int) $r['hits']],
                $paths->fetchAll()
            ),
            'top_referrers' => array_map(
                static fn(array $r): array => ['referrer' => (string) $r['referrer'], 'hits' => (int) $r['hits']],
                $refs->fetchAll()
            ),
            'recent' => array_map(
                static fn(array $r): array => [
                    'ts' => (int) $r['ts'],
                    'path' => (string) $r['path'],
                    'ip' => (string) $r['ip'],
                    'referrer' => (string) $r['referrer'],
                    'ua' => (string) $r['ua'],
                ],
                $recent->fetchAll()
            ),
        ];
    } catch (Throwable $e) {
        return null;
    }
}
