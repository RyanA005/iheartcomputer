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
        $pdo->exec('CREATE INDEX IF NOT EXISTS hits_ip ON hits(ip)');
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS ip_geo (
                ip TEXT PRIMARY KEY,
                looked_up_at INTEGER NOT NULL,
                status TEXT NOT NULL,
                country TEXT NOT NULL DEFAULT \'\',
                country_code TEXT NOT NULL DEFAULT \'\',
                city TEXT NOT NULL DEFAULT \'\',
                lat REAL,
                lon REAL,
                asn TEXT NOT NULL DEFAULT \'\',
                asn_org TEXT NOT NULL DEFAULT \'\',
                org TEXT NOT NULL DEFAULT \'\'
            )'
        );
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
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if ($remote === '' || !filter_var($remote, FILTER_VALIDATE_IP)) {
        return '';
    }

    // only trust forwarding headers from a loopback/private hop (nginx, cloudflared)
    $public = filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    if ($public !== false) {
        return $remote;
    }

    $xff = explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
    $candidates = [
        (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''),
        $xff[0],
        (string) ($_SERVER['HTTP_X_REAL_IP'] ?? ''),
    ];
    foreach ($candidates as $ip) {
        $ip = trim($ip);
        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    return $remote;
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

function hits_tz(): DateTimeZone
{
    static $tz = null;
    return $tz ??= new DateTimeZone('America/New_York');
}

function hits_format_ts(int $ts, string $format = 'Y-m-d H:i'): string
{
    return (new DateTimeImmutable('@' . $ts))->setTimezone(hits_tz())->format($format);
}

function hits_ip_is_public(string $ip): bool
{
    if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
        return false;
    }

    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
}

function hits_ua_is_scanner(string $ua): bool
{
    $ua = trim($ua);
    if ($ua === '') {
        return false;
    }

    return (bool) preg_match(
        '/nuclei|sqlmap|nmap(?:\/|\s)|wpscan|nikto|dirbuster|gobuster|feroxbuster|\bffuf\b|httpx(?:\/|\s)|masscan|zgrab|nessus|openvas|acunetix|burpsuite|owasp.?zap|\bw3af\b|havij|zmeu|libredtail|python-nmap|scanbot|wp.?vuln|masscan|zgrab2/i',
        $ua
    );
}

function hits_ua_is_crawler(string $ua): bool
{
    $ua = trim($ua);
    if ($ua === '') {
        return true;
    }
    if (hits_ua_is_scanner($ua)) {
        return false;
    }

    return (bool) preg_match(
        '/bot|crawler|crawl|spider|slurp|indexer|externalhit|externalagent|facebookcatalog|facebot|internetmeasurement|uptimerobot|pingdom|headless|curl\/|wget\/|python-requests|python-urllib|go-http-client|libwww|httpie\/|okhttp|scrapy|semrush|ahrefs|bytespider|chatgpt-user|gptbot|claude-user|claude-search|claudebot|amazonbot|ia_archiver|discordbot|slackbot|telegram|whatsapp|linkedinbot|applebot|bingpreview|duckduck|yandex|mj12bot|dotbot|petalbot|perplexity|meta-webindexer|meta-external|duckassist|oai-|youbot|hunyuan|grokbot|deepseek|qwenbot|moonshot|bravebot|adsbot|searchbot|preview/i',
        $ua
    );
}

function hits_ua_is_bot(string $ua): bool
{
    return hits_ua_is_crawler($ua) || hits_ua_is_scanner($ua);
}

function hits_path_is_site(string $path): bool
{
    if ($path === '/' || $path === '/style.css') {
        return true;
    }

    static $prefixes = ['/about', '/showcase', '/projects', '/sites', '/discord', '/instagram', '/assets', '/error', '/old'];
    foreach ($prefixes as $pre) {
        if ($path === $pre || str_starts_with($path, $pre . '/')) {
            return true;
        }
    }

    return false;
}

function hits_path_is_benign_unknown(string $path): bool
{
    $p = strtolower($path);
    if (
        $p === '/robots.txt'
        || $p === '/favicon.ico'
        || $p === '/sitemap.xml'
        || $p === '/sitemap_index.xml'
        || $p === '/ads.txt'
        || $p === '/humans.txt'
        || $p === '/security.txt'
    ) {
        return true;
    }
    if (str_starts_with($p, '/apple-touch-icon')) {
        return true;
    }
    if (str_starts_with($p, '/.well-known/')) {
        return true;
    }

    return false;
}

function hits_path_looks_like_exploit(string $path): bool
{
    $p = strtolower($path);

    return (bool) preg_match(
        '#(?:' .
            '(?:^|/)(?:wp-admin|wp-login(?:\.php)?|wp-content|wp-includes|wp-config|xmlrpc\.php|' .
            'phpmyadmin|pma(?:/|$)|adminer(?:/|$)|phpinfo(?:\.php)?|eval-stdin|' .
            'cgi-bin|vendor/phpunit|telescope|_debugbar|_profiler|server-status|' .
            'server-info|actuator|solr(?:/|$)|graphql(?:/|$)|phpmyadmin)' .
            '|' .
            '(?:^|/)\.(?:env(?:\.|$)|git(?:/|$)|svn(?:/|$)|aws(?:/|$)|htpasswd|htaccess|' .
            'ds_store|bash(?:rc|_history|_profile)|zshrc|npmrc|docker(?:/|$))' .
            '|' .
            '(?:aws.?credentials|\.aws/|service.?account|credentials\.json|secrets\.(?:env|json|ya?ml)|' .
            'docker-compose|\.tfvars|terraform\.tfstate|id_rsa|\.pem$|\.key$|proc/self|' .
            'kubernetes\.io|phpinfo|_environment)' .
            '|' .
            '(?:^|/)(?:\.env|config\.env|config\.py|settings\.py|local_settings\.py|' .
            'web\.config|wp-config)' .
        ')#',
        $p
    );
}

function hits_path_is_probe(string $path): bool
{
    if ($path === '') {
        return false;
    }
    if (hits_path_looks_like_exploit($path)) {
        return true;
    }
    if (hits_path_is_site($path) || hits_path_is_benign_unknown($path)) {
        return false;
    }

    return true;
}

/**
 * @param array<string, true> $scannerIps
 * @param array<string, true> $trustedIps
 * @return 'human'|'crawler'|'scanner'
 */
function hits_hit_kind(string $ua, string $path, string $ip, array $scannerIps, array $trustedIps = []): string
{
    if ($ip !== '' && isset($trustedIps[$ip])) {
        return 'human';
    }
    if ($ip !== '' && isset($scannerIps[$ip])) {
        return 'scanner';
    }
    if (hits_path_looks_like_exploit($path) || hits_ua_is_scanner($ua)) {
        return 'scanner';
    }
    if (hits_ua_is_crawler($ua)) {
        return 'crawler';
    }

    return 'human';
}

/**
 * IPs to treat as human (the logged-in admin viewing stats).
 *
 * @return array<string, true>
 */
function hits_trusted_admin_ips(): array
{
    $ip = hits_client_ip();
    if ($ip === '') {
        return [];
    }
    return [$ip => true];
}

/**
 * IPs that hit an exploit/probe-pattern path or used a scanner UA, all-time.
 * A one-off unknown 404 does not flag the whole IP.
 *
 * @return array<string, true>
 */
function hits_scanner_ips(?PDO $db = null): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $cached = [];
    $db = $db ?? hits_db();
    if ($db === null) {
        return $cached;
    }

    try {
        $q = $db->query('SELECT DISTINCT ip, path, ua FROM hits');
        foreach ($q as $r) {
            $ip = (string) ($r['ip'] ?? '');
            if ($ip === '') {
                continue;
            }
            if (hits_path_looks_like_exploit((string) ($r['path'] ?? '')) || hits_ua_is_scanner((string) ($r['ua'] ?? ''))) {
                $cached[$ip] = true;
            }
        }
    } catch (Throwable $e) {
        return $cached;
    }

    return $cached;
}

/**
 * @return array{asn: string, asn_org: string}
 */
function hits_parse_as(string $as): array
{
    $as = trim($as);
    if ($as === '') {
        return ['asn' => '', 'asn_org' => ''];
    }
    if (preg_match('/^(AS\d+)\s*(.*)$/i', $as, $m) === 1) {
        return ['asn' => strtoupper($m[1]), 'asn_org' => trim($m[2])];
    }
    return ['asn' => '', 'asn_org' => $as];
}

/**
 * @return array<string, array{
 *   ip: string,
 *   looked_up_at: int,
 *   status: string,
 *   country: string,
 *   country_code: string,
 *   city: string,
 *   lat: ?float,
 *   lon: ?float,
 *   asn: string,
 *   asn_org: string,
 *   org: string
 * }>
 */
function hits_geo_all(PDO $db): array
{
    $out = [];
    try {
        foreach ($db->query('SELECT * FROM ip_geo') as $row) {
            $ip = (string) $row['ip'];
            $out[$ip] = [
                'ip' => $ip,
                'looked_up_at' => (int) $row['looked_up_at'],
                'status' => (string) $row['status'],
                'country' => (string) $row['country'],
                'country_code' => (string) $row['country_code'],
                'city' => (string) $row['city'],
                'lat' => $row['lat'] !== null && $row['lat'] !== '' ? (float) $row['lat'] : null,
                'lon' => $row['lon'] !== null && $row['lon'] !== '' ? (float) $row['lon'] : null,
                'asn' => (string) $row['asn'],
                'asn_org' => (string) $row['asn_org'],
                'org' => (string) $row['org'],
            ];
        }
    } catch (Throwable $e) {
        return [];
    }
    return $out;
}

/**
 * @param array{
 *   status: string,
 *   country?: string,
 *   country_code?: string,
 *   city?: string,
 *   lat?: ?float,
 *   lon?: ?float,
 *   asn?: string,
 *   asn_org?: string,
 *   org?: string
 * } $geo
 */
function hits_geo_upsert(PDO $db, string $ip, array $geo): void
{
    $stmt = $db->prepare(
        'INSERT INTO ip_geo (ip, looked_up_at, status, country, country_code, city, lat, lon, asn, asn_org, org)
         VALUES (:ip, :looked_up_at, :status, :country, :country_code, :city, :lat, :lon, :asn, :asn_org, :org)
         ON CONFLICT(ip) DO UPDATE SET
            looked_up_at = excluded.looked_up_at,
            status = excluded.status,
            country = excluded.country,
            country_code = excluded.country_code,
            city = excluded.city,
            lat = excluded.lat,
            lon = excluded.lon,
            asn = excluded.asn,
            asn_org = excluded.asn_org,
            org = excluded.org'
    );
    $stmt->execute([
        ':ip' => $ip,
        ':looked_up_at' => time(),
        ':status' => $geo['status'],
        ':country' => $geo['country'] ?? '',
        ':country_code' => $geo['country_code'] ?? '',
        ':city' => $geo['city'] ?? '',
        ':lat' => $geo['lat'] ?? null,
        ':lon' => $geo['lon'] ?? null,
        ':asn' => $geo['asn'] ?? '',
        ':asn_org' => $geo['asn_org'] ?? '',
        ':org' => $geo['org'] ?? '',
    ]);
}

/**
 * POST a batch to ip-api.com (HTTP, no key). Returns a list of result rows.
 *
 * @param list<string> $ips
 * @return list<array<string, mixed>>
 */
function hits_geo_fetch_ip_api(array $ips): array
{
    if ($ips === []) {
        return [];
    }

    $fields = 'status,message,country,countryCode,city,lat,lon,org,as,asname,query';
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nUser-Agent: iheartcomputer.club-admin/1.0\r\n",
            'content' => json_encode(array_values($ips), JSON_UNESCAPED_SLASHES),
            'timeout' => 8,
            'ignore_errors' => true,
        ],
    ]);
    $raw = @file_get_contents('http://ip-api.com/batch?fields=' . $fields, false, $ctx);
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * HTTPS fallback, still no API key. One IP per request.
 *
 * @return array<string, mixed>|null
 */
function hits_geo_fetch_ipwho(string $ip): ?array
{
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: iheartcomputer.club-admin/1.0\r\nAccept: application/json\r\n",
            'timeout' => 6,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $raw = @file_get_contents('https://ipwho.is/' . rawurlencode($ip), false, $ctx);
    if (!is_string($raw) || $raw === '') {
        return null;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

/**
 * @param array<string, mixed> $row
 * @return array{status: string, country: string, country_code: string, city: string, lat: ?float, lon: ?float, asn: string, asn_org: string, org: string}
 */
function hits_geo_from_ip_api_row(array $row): array
{
    if (($row['status'] ?? '') !== 'success') {
        return [
            'status' => 'fail',
            'country' => '',
            'country_code' => '',
            'city' => '',
            'lat' => null,
            'lon' => null,
            'asn' => '',
            'asn_org' => '',
            'org' => '',
        ];
    }

    $parsed = hits_parse_as((string) ($row['as'] ?? ''));
    $lat = isset($row['lat']) && is_numeric($row['lat']) ? (float) $row['lat'] : null;
    $lon = isset($row['lon']) && is_numeric($row['lon']) ? (float) $row['lon'] : null;

    return [
        'status' => 'ok',
        'country' => (string) ($row['country'] ?? ''),
        'country_code' => strtoupper((string) ($row['countryCode'] ?? '')),
        'city' => (string) ($row['city'] ?? ''),
        'lat' => $lat,
        'lon' => $lon,
        'asn' => $parsed['asn'],
        'asn_org' => $parsed['asn_org'] !== '' ? $parsed['asn_org'] : (string) ($row['asname'] ?? ''),
        'org' => (string) ($row['org'] ?? ''),
    ];
}

/**
 * @param array<string, mixed> $row
 * @return array{status: string, country: string, country_code: string, city: string, lat: ?float, lon: ?float, asn: string, asn_org: string, org: string}
 */
function hits_geo_from_ipwho_row(array $row): array
{
    if (empty($row['success'])) {
        return [
            'status' => 'fail',
            'country' => '',
            'country_code' => '',
            'city' => '',
            'lat' => null,
            'lon' => null,
            'asn' => '',
            'asn_org' => '',
            'org' => '',
        ];
    }

    $conn = is_array($row['connection'] ?? null) ? $row['connection'] : [];
    $asnNum = $conn['asn'] ?? '';
    $asn = $asnNum !== '' && $asnNum !== null ? 'AS' . $asnNum : '';
    $lat = isset($row['latitude']) && is_numeric($row['latitude']) ? (float) $row['latitude'] : null;
    $lon = isset($row['longitude']) && is_numeric($row['longitude']) ? (float) $row['longitude'] : null;

    return [
        'status' => 'ok',
        'country' => (string) ($row['country'] ?? ''),
        'country_code' => strtoupper((string) ($row['country_code'] ?? '')),
        'city' => (string) ($row['city'] ?? ''),
        'lat' => $lat,
        'lon' => $lon,
        'asn' => $asn,
        'asn_org' => (string) ($conn['org'] ?? $conn['isp'] ?? ''),
        'org' => (string) ($conn['org'] ?? $conn['isp'] ?? ''),
    ];
}

/**
 * Look up unknown public IPs and cache them. Private/loopback IPs are labeled
 * without a network call. Caps work per request so we stay under ip-api's 45/min.
 *
 * @return array{looked_up: int, pending: int}
 */
function hits_geo_refresh(PDO $db, int $maxLookups = 40): array
{
    $maxLookups = max(0, min(40, $maxLookups));
    $now = time();
    $lookedUp = 0;
    $pending = 0;

    try {
        $ips = $db->query('SELECT DISTINCT ip FROM hits')->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        return ['looked_up' => 0, 'pending' => 0];
    }

    $cached = hits_geo_all($db);
    $needApi = [];

    foreach ($ips as $ip) {
        $ip = (string) $ip;
        if ($ip === '') {
            continue;
        }

        if (!hits_ip_is_public($ip)) {
            $existing = $cached[$ip] ?? null;
            if ($existing === null || $existing['status'] !== 'private') {
                try {
                    hits_geo_upsert($db, $ip, [
                        'status' => 'private',
                        'country' => '',
                        'country_code' => '',
                        'city' => '',
                        'lat' => null,
                        'lon' => null,
                        'asn' => '',
                        'asn_org' => '',
                        'org' => '',
                    ]);
                } catch (Throwable $e) {
                    // fail open
                }
            }
            continue;
        }

        $existing = $cached[$ip] ?? null;
        if ($existing !== null && $existing['status'] === 'ok') {
            continue;
        }
        $staleFail = $existing !== null
            && $existing['status'] === 'fail'
            && ($now - $existing['looked_up_at']) > 86400;
        if ($existing !== null && $existing['status'] === 'fail' && !$staleFail) {
            continue;
        }
        $needApi[] = $ip;
    }

    $pending = count($needApi);
    if ($maxLookups === 0 || $needApi === []) {
        return ['looked_up' => 0, 'pending' => $pending];
    }

    $batch = array_slice($needApi, 0, $maxLookups);
    $results = hits_geo_fetch_ip_api($batch);
    $byQuery = [];
    foreach ($results as $row) {
        if (!is_array($row)) {
            continue;
        }
        $query = (string) ($row['query'] ?? '');
        if ($query !== '') {
            $byQuery[$query] = $row;
        }
    }

    foreach ($batch as $ip) {
        $geo = null;
        if (isset($byQuery[$ip])) {
            $geo = hits_geo_from_ip_api_row($byQuery[$ip]);
        } else {
            $who = hits_geo_fetch_ipwho($ip);
            if ($who !== null) {
                $geo = hits_geo_from_ipwho_row($who);
            }
        }
        if ($geo === null) {
            $geo = [
                'status' => 'fail',
                'country' => '',
                'country_code' => '',
                'city' => '',
                'lat' => null,
                'lon' => null,
                'asn' => '',
                'asn_org' => '',
                'org' => '',
            ];
        }
        try {
            hits_geo_upsert($db, $ip, $geo);
            $lookedUp++;
            $pending--;
        } catch (Throwable $e) {
            // fail open
        }
    }

    return ['looked_up' => $lookedUp, 'pending' => max(0, $pending)];
}

/**
 * @return array<string, array{label: string, seconds: ?int}>
 */
function hits_ranges(): array
{
    return [
        '24h' => ['label' => '24 hours', 'seconds' => 86400],
        '7d' => ['label' => '7 days', 'seconds' => 7 * 86400],
        '30d' => ['label' => '30 days', 'seconds' => 30 * 86400],
        '365d' => ['label' => '365 days', 'seconds' => 365 * 86400],
        'all' => ['label' => 'all time', 'seconds' => null],
    ];
}

function hits_normalize_range(string $range): string
{
    return array_key_exists($range, hits_ranges()) ? $range : '24h';
}

function hits_range_label(string $range): string
{
    $range = hits_normalize_range($range);
    return hits_ranges()[$range]['label'];
}

function hits_range_since(string $range, int $nowTs): ?int
{
    $range = hits_normalize_range($range);
    $seconds = hits_ranges()[$range]['seconds'];
    return $seconds === null ? null : $nowTs - $seconds;
}

/**
 * @param array<string, mixed> $r
 * @param array<string, array<string, mixed>> $geoByIp
 * @param array<string, true> $scannerIps
 * @return array<string, mixed>
 */
function hits_format_recent_row(array $r, array $geoByIp, array $scannerIps = [], array $trustedIps = []): array
{
    $ip = (string) ($r['ip'] ?? '');
    $path = (string) ($r['path'] ?? '');
    $ua = (string) ($r['ua'] ?? '');
    $isPublic = hits_ip_is_public($ip);
    $geo = $geoByIp[$ip] ?? null;
    $legacy = !$isPublic;
    $place = '';
    if ($legacy) {
        $place = 'legacy';
    } elseif ($geo !== null && $geo['status'] === 'ok') {
        $bits = array_filter([$geo['city'], $geo['country_code'] !== '' ? $geo['country_code'] : $geo['country']]);
        $place = implode(', ', $bits);
    } elseif ($geo !== null && $geo['status'] === 'fail') {
        $place = 'lookup failed';
    } else {
        $place = 'pending';
    }

    $kind = hits_hit_kind($ua, $path, $ip, $scannerIps, $trustedIps);

    return [
        'id' => (int) ($r['id'] ?? 0),
        'ts' => (int) ($r['ts'] ?? 0),
        'when' => hits_format_ts((int) ($r['ts'] ?? 0)),
        'path' => $path,
        'ip' => $ip,
        'referrer' => (string) ($r['referrer'] ?? ''),
        'ua' => $ua,
        'kind' => $kind,
        'bot' => $kind !== 'human',
        'probe' => hits_path_is_probe($path),
        'legacy' => $legacy,
        'place' => $place,
    ];
}

function hits_table_page_size(): int
{
    return 20;
}

/**
 * Newest-first page of hits in the selected range. $beforeId is the oldest id
 * already shown; 0 means the first page.
 *
 * @return array{rows: list<array<string, mixed>>, has_more: bool}
 */
function hits_recent_page(string $range, bool $hideBots, int $beforeId = 0, int $limit = 0): array
{
    $empty = ['rows' => [], 'has_more' => false];
    $db = hits_db();
    if ($db === null) {
        return $empty;
    }

    $range = hits_normalize_range($range);
    if ($limit <= 0) {
        $limit = hits_table_page_size();
    }
    $limit = max(1, min(100, $limit));
    $since = hits_range_since($range, time());
    $geoByIp = hits_geo_all($db);

    $sql = 'SELECT id, ts, path, ip, referrer, ua FROM hits';
    $where = [];
    $params = [];
    if ($since !== null) {
        $where[] = 'ts >= :since';
        $params[':since'] = $since;
    }
    if ($beforeId > 0) {
        $where[] = 'id < :before';
        $params[':before'] = $beforeId;
    }
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY id DESC';

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    } catch (Throwable $e) {
        return $empty;
    }

    $scannerIps = hits_scanner_ips($db);
    $trustedIps = hits_trusted_admin_ips();
    $rows = [];
    $hasMore = false;
    while ($r = $stmt->fetch()) {
        $kind = hits_hit_kind(
            (string) ($r['ua'] ?? ''),
            (string) ($r['path'] ?? ''),
            (string) ($r['ip'] ?? ''),
            $scannerIps,
            $trustedIps
        );
        if ($hideBots && $kind !== 'human') {
            continue;
        }
        if (count($rows) >= $limit) {
            $hasMore = true;
            break;
        }
        $rows[] = hits_format_recent_row($r, $geoByIp, $scannerIps, $trustedIps);
    }

    return ['rows' => $rows, 'has_more' => $hasMore];
}

/**
 * @return array<string, mixed>|null
 */
function hits_dashboard(string $range = '24h', bool $hideBots = false): ?array
{
    $db = hits_db();
    if ($db === null) {
        return null;
    }

    $range = hits_normalize_range($range);
    $hourly = $range === '24h';
    $tz = hits_tz();
    $now = new DateTimeImmutable('now', $tz);
    $todayStart = $now->setTime(0, 0, 0)->getTimestamp();
    $nowTs = time();
    $since = hits_range_since($range, $nowTs);

    $geoRefresh = ['looked_up' => 0, 'pending' => 0];
    try {
        $geoRefresh = hits_geo_refresh($db);
    } catch (Throwable $e) {
        $geoRefresh = ['looked_up' => 0, 'pending' => 0];
    }
    $geoByIp = hits_geo_all($db);

    try {
        $rows = $db->query(
            'SELECT ts, path, ip, referrer, ua FROM hits ORDER BY id ASC'
        )->fetchAll();
    } catch (Throwable $e) {
        return null;
    }

    $scannerIps = hits_scanner_ips($db);
    $trustedIps = hits_trusted_admin_ips();

    $hitsAll = 0;
    $hitsToday = 0;
    $legacyHits = 0;
    $crawlerHits = 0;
    $scannerHits = 0;
    $botHidden = 0;
    $uniqAll = [];
    $uniqToday = [];
    $byPath = [];
    $byProbePath = [];
    $byRef = [];
    $byBucketHits = [];
    $byBucketUniq = [];
    $byCountry = [];
    $byCity = [];
    $byIp = [];
    $threatIps = [];

    foreach ($rows as $r) {
        $ts = (int) $r['ts'];
        if ($since !== null && $ts < $since) {
            continue;
        }

        $path = (string) $r['path'];
        $ip = (string) $r['ip'];
        $ref = (string) $r['referrer'];
        $ua = (string) $r['ua'];
        $kind = hits_hit_kind($ua, $path, $ip, $scannerIps, $trustedIps);
        $isProbe = hits_path_is_probe($path);
        $isPublic = hits_ip_is_public($ip);

        if ($kind === 'crawler') {
            $crawlerHits++;
        } elseif ($kind === 'scanner') {
            $scannerHits++;
            if ($ip !== '') {
                $threatIps[$ip] = ($threatIps[$ip] ?? 0) + 1;
            }
            if ($isProbe) {
                $byProbePath[$path] = ($byProbePath[$path] ?? 0) + 1;
            }
        }

        if ($hideBots && $kind !== 'human') {
            $botHidden++;
            continue;
        }

        $hitsAll++;
        if (!$isPublic) {
            $legacyHits++;
        }
        if ($ts >= $todayStart) {
            $hitsToday++;
        }

        $dt = (new DateTimeImmutable('@' . $ts))->setTimezone($tz);
        $bucket = $hourly ? $dt->format('Y-m-d H:00') : $dt->format('Y-m-d');
        $byBucketHits[$bucket] = ($byBucketHits[$bucket] ?? 0) + 1;

        if ($isPublic) {
            $uniqAll[$ip] = true;
            $byBucketUniq[$bucket][$ip] = true;
            if ($ts >= $todayStart) {
                $uniqToday[$ip] = true;
            }
        }

        $byPath[$path] = ($byPath[$path] ?? 0) + 1;
        if ($ref !== '') {
            $byRef[$ref] = ($byRef[$ref] ?? 0) + 1;
        }

        if (!isset($byIp[$ip])) {
            $byIp[$ip] = 0;
        }
        $byIp[$ip]++;

        $geo = $geoByIp[$ip] ?? null;
        if ($isPublic && $geo !== null && $geo['status'] === 'ok' && $geo['country_code'] !== '') {
            $cc = $geo['country_code'];
            if (!isset($byCountry[$cc])) {
                $byCountry[$cc] = [
                    'code' => $cc,
                    'name' => $geo['country'] !== '' ? $geo['country'] : $cc,
                    'hits' => 0,
                    'uniques' => [],
                ];
            }
            $byCountry[$cc]['hits']++;
            $byCountry[$cc]['uniques'][$ip] = true;

            $cityKey = $cc . "\n" . $geo['city'];
            if (!isset($byCity[$cityKey])) {
                $byCity[$cityKey] = [
                    'city' => $geo['city'] !== '' ? $geo['city'] : 'unknown',
                    'country' => $geo['country'],
                    'country_code' => $cc,
                    'lat' => $geo['lat'],
                    'lon' => $geo['lon'],
                    'hits' => 0,
                ];
            }
            $byCity[$cityKey]['hits']++;
            if ($byCity[$cityKey]['lat'] === null && $geo['lat'] !== null) {
                $byCity[$cityKey]['lat'] = $geo['lat'];
                $byCity[$cityKey]['lon'] = $geo['lon'];
            }
        }

    }

    arsort($byPath);
    $topPaths = [];
    foreach ($byPath as $path => $count) {
        $topPaths[] = ['path' => (string) $path, 'hits' => (int) $count];
    }
    $topPage = $topPaths[0] ?? null;

    $sliceLimit = 12;
    $pagesForChart = [];
    foreach (array_slice($topPaths, 0, $sliceLimit) as $row) {
        $pagesForChart[] = [
            'path' => $row['path'],
            'hits' => $row['hits'],
            'kind' => 'page',
        ];
    }
    $pagesOther = [];
    $otherHits = 0;
    foreach (array_slice($topPaths, $sliceLimit) as $row) {
        $pagesOther[] = $row;
        $otherHits += $row['hits'];
    }
    if ($otherHits > 0) {
        $pagesForChart[] = [
            'path' => 'other',
            'hits' => $otherHits,
            'kind' => 'other',
        ];
    }

    arsort($byProbePath);
    $probePaths = [];
    $probeHits = 0;
    foreach ($byProbePath as $path => $count) {
        $probePaths[] = ['path' => (string) $path, 'hits' => (int) $count];
        $probeHits += (int) $count;
    }

    $threatIpRows = [];
    foreach ($threatIps as $ip => $count) {
        $geo = $geoByIp[$ip] ?? null;
        $org = '';
        $asn = '';
        if (is_array($geo)) {
            $asn = (string) ($geo['asn'] ?? '');
            $org = $geo['org'] !== '' ? (string) $geo['org'] : (string) ($geo['asn_org'] ?? '');
        }
        $threatIpRows[] = [
            'ip' => (string) $ip,
            'hits' => (int) $count,
            'asn' => $asn,
            'org' => $org,
            'country' => is_array($geo) ? (string) ($geo['country'] ?? '') : '',
            'country_code' => is_array($geo) ? (string) ($geo['country_code'] ?? '') : '',
        ];
    }
    usort($threatIpRows, static fn(array $a, array $b): int => $b['hits'] <=> $a['hits']);

    arsort($byRef);
    $topReferrers = [];
    $n = 0;
    foreach ($byRef as $referrer => $count) {
        $topReferrers[] = ['referrer' => (string) $referrer, 'hits' => (int) $count];
        if (++$n >= 15) {
            break;
        }
    }

    $countries = [];
    foreach ($byCountry as $row) {
        $countries[] = [
            'code' => $row['code'],
            'name' => $row['name'],
            'hits' => (int) $row['hits'],
            'uniques' => count($row['uniques']),
        ];
    }
    usort($countries, static fn(array $a, array $b): int => $b['hits'] <=> $a['hits']);
    $topCountry = $countries[0] ?? null;

    $mapPoints = [];
    foreach ($byCity as $row) {
        if ($row['lat'] === null || $row['lon'] === null) {
            continue;
        }
        $mapPoints[] = [
            'city' => $row['city'],
            'country' => $row['country'],
            'country_code' => $row['country_code'],
            'lat' => $row['lat'],
            'lon' => $row['lon'],
            'hits' => (int) $row['hits'],
        ];
    }
    usort($mapPoints, static fn(array $a, array $b): int => $b['hits'] <=> $a['hits']);

    $series = [];
    try {
        if ($hourly) {
            $cursor = (new DateTimeImmutable('@' . ($since ?? ($nowTs - 86400))))->setTimezone($tz);
            $cursor = $cursor->setTime((int) $cursor->format('H'), 0, 0);
            $endHour = $now->setTime((int) $now->format('H'), 0, 0);
            while ($cursor <= $endHour) {
                $key = $cursor->format('Y-m-d H:00');
                $series[] = [
                    'day' => $key,
                    'label' => $cursor->format('n/j H:00'),
                    'hits' => (int) ($byBucketHits[$key] ?? 0),
                    'uniques' => isset($byBucketUniq[$key]) ? count($byBucketUniq[$key]) : 0,
                ];
                $cursor = $cursor->modify('+1 hour');
            }
        } else {
            if ($since !== null) {
                $cursor = (new DateTimeImmutable('@' . $since))->setTimezone($tz)->setTime(0, 0, 0);
            } elseif ($byBucketHits !== []) {
                $first = min(array_keys($byBucketHits));
                $cursor = new DateTimeImmutable($first, $tz);
            } else {
                $cursor = null;
            }
            $end = $now->setTime(0, 0, 0);
            if ($cursor !== null) {
                while ($cursor <= $end) {
                    $key = $cursor->format('Y-m-d');
                    $series[] = [
                        'day' => $key,
                        'label' => $key,
                        'hits' => (int) ($byBucketHits[$key] ?? 0),
                        'uniques' => isset($byBucketUniq[$key]) ? count($byBucketUniq[$key]) : 0,
                    ];
                    $cursor = $cursor->modify('+1 day');
                }
            }
        }
    } catch (Throwable $e) {
        $series = [];
    }

    $asnRows = [];
    foreach ($byIp as $ip => $count) {
        $geo = $geoByIp[$ip] ?? null;
        $isPublic = hits_ip_is_public((string) $ip);
        $status = $geo['status'] ?? ($isPublic ? 'pending' : 'private');
        $org = '';
        if ($geo !== null) {
            $org = $geo['org'] !== '' ? $geo['org'] : $geo['asn_org'];
        }
        $asnRows[] = [
            'ip' => (string) $ip,
            'asn' => $geo['asn'] ?? '',
            'org' => $org,
            'country' => $geo['country'] ?? '',
            'country_code' => $geo['country_code'] ?? '',
            'city' => $isPublic ? ($geo['city'] ?? '') : 'legacy',
            'hits' => (int) $count,
            'legacy' => !$isPublic,
            'status' => $status,
        ];
    }
    usort($asnRows, static fn(array $a, array $b): int => $b['hits'] <=> $a['hits']);

    $recentPage = hits_recent_page($range, $hideBots, 0, hits_table_page_size());

    return [
        'range' => $range,
        'range_label' => hits_range_label($range),
        'bucket' => $hourly ? 'hour' : 'day',
        'hide_bots' => $hideBots,
        'geo_looked_up' => (int) $geoRefresh['looked_up'],
        'geo_pending' => (int) $geoRefresh['pending'],
        'headlines' => [
            'hits' => $hitsAll,
            'uniques' => count($uniqAll),
            'hits_today' => $hitsToday,
            'uniques_today' => count($uniqToday),
            'top_page' => $topPage,
            'top_country' => $topCountry,
            'legacy_hits' => $legacyHits,
            'crawler_hits' => $crawlerHits,
            'scanner_hits' => $scannerHits,
            'bot_hits' => $crawlerHits + $scannerHits,
            'bot_hidden' => $botHidden,
        ],
        'daily' => $series,
        'pages' => $pagesForChart,
        'pages_all' => $topPaths,
        'pages_other' => $pagesOther,
        'top_paths' => $topPaths,
        'top_referrers' => $topReferrers,
        'threats' => [
            'hits' => $scannerHits,
            'ips' => count($threatIps),
            'probe_hits' => $probeHits,
            'probe_paths' => count($probePaths),
            'top_ips' => $threatIpRows,
            'top_paths' => $probePaths,
        ],
        'countries' => $countries,
        'map_points' => $mapPoints,
        'asn_rows' => $asnRows,
        'recent' => $recentPage['rows'],
        'recent_has_more' => $recentPage['has_more'],
    ];
}
