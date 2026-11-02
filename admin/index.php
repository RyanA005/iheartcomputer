<?php

function admin_request_is_https(): bool
{
    $https = $_SERVER['HTTPS'] ?? '';
    if ($https !== '' && $https !== 'off' && $https !== '0') {
        return true;
    }
    $fwd = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if ($fwd === 'https') {
        return true;
    }
    $cf = (string) ($_SERVER['HTTP_CF_VISITOR'] ?? '');
    return $cf !== '' && str_contains($cf, 'https');
}

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/admin',
    'secure' => admin_request_is_https(),
    'httponly' => true,
    'samesite' => 'Lax',
]);
ini_set('session.use_strict_mode', '1');
session_start();

require dirname(__DIR__) . '/assets/_layout.php';
require_once dirname(__DIR__) . '/assets/hits.php';

function load_dotenv(string $path): array
{
    if (!is_readable($path)) {
        return [];
    }

    $vars = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return [];
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if ($key !== '') {
            $vars[$key] = $value;
        }
    }

    return $vars;
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function admin_url(string $range, bool $hideBots): string
{
    $q = ['range' => $range];
    if ($hideBots) {
        $q['hide_bots'] = 1;
    }
    return '/admin/?' . http_build_query($q);
}

function admin_requested_range(): string
{
    $range = (string) ($_GET['range'] ?? '');
    if ($range === '' && isset($_GET['days'])) {
        $map = [1 => '24h', 7 => '7d', 30 => '30d', 365 => '365d', 0 => 'all'];
        $range = $map[(int) $_GET['days']] ?? '';
    }
    return hits_normalize_range($range !== '' ? $range : '24h');
}

$env = load_dotenv(dirname(__DIR__) . '/.env');
$password = $env['PASSWORD'] ?? '';

if ($password === '') {
    http_response_code(500);
    echo 'admin is not configured (missing PASSWORD in .env)';
    exit;
}

if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        $samesite = $params['samesite'] ?? '';
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => (bool) $params['secure'],
            'httponly' => (bool) $params['httponly'],
            'samesite' => is_string($samesite) && $samesite !== '' ? $samesite : 'Lax',
        ]);
    }
    session_destroy();
    header('Location: /admin/');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted = (string) ($_POST['password'] ?? '');
    if (hash_equals($password, $submitted)) {
        session_regenerate_id(true);
        $_SESSION['admin_authed'] = true;
        header('Location: /admin/');
        exit;
    }
    $error = 'wrong password';
}

function admin_recent_row_html(array $row): string
{
    ob_start();
    ?>
                      <tr class="<?= !empty($row['legacy']) ? 'legacy' : '' ?>">
                        <td class="mono"><?= h($row['when']) ?></td>
                        <td class="path"><?= h($row['path']) ?></td>
                        <td class="mono">
                          <?= h($row['ip']) ?>
                          <?php if (!empty($row['legacy'])): ?><span class="admin-tag">legacy</span><?php endif; ?>
                          <?php if (!empty($row['bot'])): ?><span class="admin-tag">bot</span><?php endif; ?>
                        </td>
                        <td><?= h($row['place'] !== '' ? $row['place'] : '—') ?></td>
                        <td class="ua" title="<?= h($row['ua']) ?>"><?= h($row['ua'] !== '' ? $row['ua'] : '—') ?></td>
                        <td class="ref"><?= h($row['referrer'] !== '' ? $row['referrer'] : '—') ?></td>
                      </tr>
    <?php
    return (string) ob_get_clean();
}

$authed = !empty($_SESSION['admin_authed']);
$range = '24h';
$hideBots = false;
if ($authed) {
    $range = admin_requested_range();
    $hideBots = (string) ($_GET['hide_bots'] ?? '') === '1';
}

if ((string) ($_GET['load_recent'] ?? '') === '1') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (!$authed) {
        http_response_code(403);
        echo '{"error":"auth"}';
        exit;
    }
    $before = (int) ($_GET['before'] ?? 0);
    $page = hits_recent_page($range, $hideBots, $before, 25);
    $html = '';
    foreach ($page['rows'] as $row) {
        $html .= admin_recent_row_html($row);
    }
    $lastId = 0;
    if ($page['rows'] !== []) {
        $lastId = (int) $page['rows'][count($page['rows']) - 1]['id'];
    }
    echo json_encode(
        [
            'html' => $html,
            'has_more' => $page['has_more'],
            'before' => $lastId,
        ],
        JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS
    );
    exit;
}

$hitsStatus = $authed ? hits_status() : null;
$summary = ($hitsStatus !== null && $hitsStatus['ok']) ? hits_dashboard($range, $hideBots) : null;

$extraHead = '<link rel="stylesheet" href="/assets/admin.css">';
if ($authed) {
    $extraHead = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">' . "\n  " . $extraHead;
}

new_render_header('Stats - IHEARTCOMPUTER', 'IHEARTCOMPUTER - Admin', $extraHead);

$hl = is_array($summary['headlines'] ?? null) ? $summary['headlines'] : null;
?>

      <div class="admin-head">
        <div class="row" style="align-items: baseline;">
          <h1 class="h1" style="margin: 0;">Stats</h1>
          <?php if ($authed): ?>
            <a class="upper blue" href="/admin/?logout=1" style="font-size: 0.85rem;">logout →</a>
          <?php endif; ?>
        </div>
      </div>

      <?php if (!$authed): ?>
        <form method="post" action="/admin/" class="panel admin-login">
          <label class="upper" for="password">password</label>
          <input
            id="password"
            type="password"
            name="password"
            required
            autofocus
            autocomplete="current-password"
          >
          <button class="btn" type="submit">login</button>
          <?php if ($error !== ''): ?>
            <p class="red" style="margin: 0.85rem 0 0; font-size: 0.9rem;"><?= h($error) ?></p>
          <?php endif; ?>
        </form>
      <?php else: ?>
        <div class="admin-controls" style="margin-bottom: 1.15rem;">
          <?php foreach (hits_ranges() as $key => $info): ?>
            <a class="chip<?= $range === $key ? ' chip-on' : '' ?>" href="<?= h(admin_url($key, $hideBots)) ?>"><?= h($info['label']) ?></a>
          <?php endforeach; ?>
          <a class="chip<?= $hideBots ? ' chip-on' : '' ?>" href="<?= h(admin_url($range, !$hideBots)) ?>"><?= $hideBots ? 'showing humans' : 'hide bots' ?></a>
        </div>

        <?php if ($summary === null): ?>
          <div class="panel admin-panel">
            <p class="muted" style="margin: 0;">hit log unavailable: <?= h((string) ($hitsStatus['error'] ?? 'unknown')) ?></p>
          </div>
        <?php else: ?>
          <div class="admin-stats">
            <div class="panel admin-stat">
              <span class="upper muted admin-stat-label"><?= h($summary['range_label']) ?> hits</span>
              <strong class="admin-stat-value"><?= number_format((int) $hl['hits']) ?></strong>
            </div>
            <div class="panel admin-stat">
              <span class="upper muted admin-stat-label">unique visitors</span>
              <strong class="admin-stat-value"><?= number_format((int) $hl['uniques']) ?></strong>
              <span class="admin-stat-sub">public IPs</span>
            </div>
            <div class="panel admin-stat">
              <span class="upper muted admin-stat-label">today</span>
              <strong class="admin-stat-value"><?= number_format((int) $hl['hits_today']) ?></strong>
              <span class="admin-stat-sub"><?= number_format((int) $hl['uniques_today']) ?> unique · ET</span>
            </div>
            <div class="panel admin-stat">
              <span class="upper muted admin-stat-label">top page</span>
              <strong class="admin-stat-value" style="font-size: 1.15rem;">
                <?= $hl['top_page'] ? h($hl['top_page']['path']) : '—' ?>
              </strong>
              <span class="admin-stat-sub">
                <?= $hl['top_page'] ? number_format((int) $hl['top_page']['hits']) . ' hits' : 'no hits yet' ?>
              </span>
            </div>
            <div class="panel admin-stat">
              <span class="upper muted admin-stat-label">top country</span>
              <strong class="admin-stat-value" style="font-size: 1.15rem;">
                <?= $hl['top_country'] ? h($hl['top_country']['name']) : '—' ?>
              </strong>
              <span class="admin-stat-sub">
                <?= $hl['top_country'] ? number_format((int) $hl['top_country']['hits']) . ' hits' : 'no public geo yet' ?>
              </span>
            </div>
          </div>

          <?php
            $bucketWord = ($summary['bucket'] ?? 'day') === 'hour' ? 'hour' : 'day';
            $chartNote = ($summary['bucket'] ?? 'day') === 'hour'
                ? 'rolling 24 hours, hourly buckets in America/New_York. unique visitors skip loopback/private IPs.'
                : $summary['range_label'] . ', by America/New_York calendar day. unique visitors skip loopback/private IPs.';
          ?>
          <div class="panel admin-panel">
            <h2 class="upper">hits and unique visitors per <?= h($bucketWord) ?></h2>
            <?php if ($summary['daily'] === []): ?>
              <p class="admin-empty">no hits in this window</p>
            <?php else: ?>
              <div class="admin-chart admin-chart-wide">
                <canvas id="daily-chart" aria-label="hits and unique visitors per <?= h($bucketWord) ?>"></canvas>
              </div>
              <p class="admin-note"><?= h($chartNote) ?></p>
            <?php endif; ?>
          </div>

          <div class="admin-split">
            <div class="panel admin-panel" style="margin-bottom: 0;">
              <h2 class="upper">hits by page</h2>
              <?php if ($summary['pages'] === []): ?>
                <p class="admin-empty">no hits yet</p>
              <?php else: ?>
                <div class="admin-chart">
                  <canvas id="pages-chart" aria-label="hits by page"></canvas>
                </div>
              <?php endif; ?>
            </div>
            <div class="panel admin-panel" style="margin-bottom: 0;">
              <h2 class="upper">where hits come from</h2>
              <div id="geo-map" class="admin-map" role="img" aria-label="world map of hits by country"></div>
              <div class="admin-legend">
                <span><i style="background: #e8e8e4;"></i>no hits</span>
                <span><i style="background: #e10600;"></i>more hits</span>
                <span><i style="background: #1a4fff; border-radius: 50%;"></i>city</span>
              </div>
            </div>
          </div>

          <div class="panel admin-panel">
            <div class="admin-toolbar">
              <h2 class="upper" style="margin: 0;">IP / ASN lookup</h2>
              <input class="admin-search" id="asn-search" type="search" placeholder="search IP, ASN, org, city..." autocomplete="off">
            </div>
            <?php if ($summary['asn_rows'] === []): ?>
              <p class="admin-empty">no hits yet</p>
            <?php else: ?>
              <div class="admin-table-wrap">
                <table class="admin-table" id="asn-table">
                  <thead>
                    <tr>
                      <th class="upper sortable">IP / ASN</th>
                      <th class="upper sortable">org</th>
                      <th class="upper sortable">country</th>
                      <th class="upper sortable">city</th>
                      <th class="upper sortable num" data-type="num" aria-sort="descending">hits</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($summary['asn_rows'] as $row): ?>
                      <?php
                        $ipAsn = $row['ip'];
                        if ($row['asn'] !== '') {
                            $ipAsn .= ' · ' . $row['asn'];
                        }
                        $city = (string) $row['city'];
                        if ($row['status'] === 'pending') {
                            $city = $city !== '' ? $city : 'pending';
                        } elseif ($row['status'] === 'fail' && $city === '') {
                            $city = 'lookup failed';
                        }
                      ?>
                      <tr class="<?= $row['legacy'] ? 'legacy' : '' ?>">
                        <td class="mono" data-sort="<?= h($row['ip']) ?>">
                          <?= h($ipAsn) ?>
                          <?php if ($row['legacy']): ?><span class="admin-tag">legacy</span><?php endif; ?>
                        </td>
                        <td data-sort="<?= h($row['org']) ?>"><?= h($row['org'] !== '' ? $row['org'] : '—') ?></td>
                        <td data-sort="<?= h($row['country_code'] !== '' ? $row['country_code'] : $row['country']) ?>">
                          <?= h($row['country'] !== '' ? $row['country'] : '—') ?>
                          <?php if ($row['country_code'] !== ''): ?>
                            <span class="muted"><?= h($row['country_code']) ?></span>
                          <?php endif; ?>
                        </td>
                        <td data-sort="<?= h($city) ?>"><?= h($city !== '' ? $city : '—') ?></td>
                        <td class="num" data-sort="<?= (int) $row['hits'] ?>"><?= number_format((int) $row['hits']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>

          <div class="admin-split">
            <div class="panel admin-panel" style="margin-bottom: 0;">
              <h2 class="upper">top pages</h2>
              <?php if ($summary['top_paths'] === []): ?>
                <p class="admin-empty">no hits yet</p>
              <?php else: ?>
                <div class="admin-table-wrap">
                  <table class="admin-table">
                    <tbody>
                      <?php foreach ($summary['top_paths'] as $row): ?>
                        <tr>
                          <td class="path"><?= h($row['path']) ?></td>
                          <td class="num"><?= number_format((int) $row['hits']) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>
            <div class="panel admin-panel" style="margin-bottom: 0;">
              <h2 class="upper">top referrers</h2>
              <?php if ($summary['top_referrers'] === []): ?>
                <p class="admin-empty">none yet (same-site refs are dropped)</p>
              <?php else: ?>
                <div class="admin-table-wrap">
                  <table class="admin-table">
                    <tbody>
                      <?php foreach ($summary['top_referrers'] as $row): ?>
                        <tr>
                          <td class="ref"><?= h($row['referrer']) ?></td>
                          <td class="num"><?= number_format((int) $row['hits']) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <div class="panel admin-panel">
            <h2 class="upper">recent hits</h2>
            <?php if ($summary['recent'] === []): ?>
              <p class="admin-empty">no hits in this window</p>
            <?php else: ?>
              <div class="admin-table-wrap">
                <table class="admin-table">
                  <thead>
                    <tr>
                      <th class="upper">when (ET)</th>
                      <th class="upper">path</th>
                      <th class="upper">ip</th>
                      <th class="upper">place</th>
                      <th class="upper">ua</th>
                      <th class="upper">referrer</th>
                    </tr>
                  </thead>
                  <tbody id="recent-body">
                    <?php foreach ($summary['recent'] as $row): ?>
                      <?= admin_recent_row_html($row) ?>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <?php if (!empty($summary['recent_has_more'])): ?>
                <?php
                  $lastRecent = $summary['recent'][count($summary['recent']) - 1];
                ?>
                <div class="admin-more">
                  <button
                    type="button"
                    class="chip"
                    id="recent-more"
                    data-range="<?= h($summary['range']) ?>"
                    data-hide-bots="<?= $hideBots ? '1' : '0' ?>"
                    data-before="<?= (int) $lastRecent['id'] ?>"
                  >load more</button>
                </div>
              <?php endif; ?>
            <?php endif; ?>
          </div>

          <script type="application/json" id="stats-data"><?= json_encode(
              [
                  'bucket' => $summary['bucket'],
                  'daily' => $summary['daily'],
                  'pages' => $summary['pages'],
                  'countries' => $summary['countries'],
                  'map_points' => $summary['map_points'],
              ],
              JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS
          ) ?></script>
          <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
          <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
          <script src="/assets/admin-stats.js"></script>
        <?php endif; ?>
      <?php endif; ?>

<?php new_render_footer(); ?>
