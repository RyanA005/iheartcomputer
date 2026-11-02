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
require_once dirname(__DIR__) . '/assets/ops.php';

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
    if (!$hideBots) {
        $q['hide_bots'] = 0;
    }
    return '/admin/?' . http_build_query($q);
}

function admin_hide_bots(): bool
{
    return (string) ($_GET['hide_bots'] ?? '1') !== '0';
}

function admin_row_page_hidden(int $index): string
{
    return $index >= hits_table_page_size() ? ' hidden' : '';
}

function admin_load_more(int $rowCount): void
{
    if ($rowCount <= hits_table_page_size()) {
        return;
    }
    ?>
              <div class="admin-more">
                <button type="button" class="chip js-table-more">load more</button>
              </div>
    <?php
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

function admin_view(): string
{
    $view = strtolower((string) ($_GET['view'] ?? 'stats'));
    return in_array($view, ['operations', 'ops'], true) ? 'operations' : 'stats';
}

function admin_csrf_token(): string
{
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function admin_csrf_ok(): bool
{
    $got = (string) ($_POST['csrf'] ?? '');
    $want = (string) ($_SESSION['csrf'] ?? '');
    return $got !== '' && $want !== '' && hash_equals($want, $got);
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
$authed = !empty($_SESSION['admin_authed']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'pull') {
        if (!$authed) {
            http_response_code(403);
            echo 'forbidden';
            exit;
        }
        if (!admin_csrf_ok()) {
            $_SESSION['ops_flash'] = [
                'ok' => false,
                'output' => 'csrf check failed; reload and try again.',
                'reason' => 'csrf',
                'from' => '',
                'to' => '',
            ];
            header('Location: /admin/?view=operations');
            exit;
        }
        $_SESSION['ops_flash'] = ops_pull();
        header('Location: /admin/?view=operations');
        exit;
    }

    $submitted = (string) ($_POST['password'] ?? '');
    if (hash_equals($password, $submitted)) {
        session_regenerate_id(true);
        $_SESSION['admin_authed'] = true;
        unset($_SESSION['csrf']);
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
                          <?php if (($row['kind'] ?? '') === 'scanner'): ?><span class="admin-tag">scanner</span>
                          <?php elseif (($row['kind'] ?? '') === 'crawler'): ?><span class="admin-tag">crawler</span><?php endif; ?>
                        </td>
                        <td><?= h($row['place'] !== '' ? $row['place'] : '—') ?></td>
                        <td class="ua" title="<?= h($row['ua']) ?>"><?= h($row['ua'] !== '' ? $row['ua'] : '—') ?></td>
                        <td class="ref"><?= h($row['referrer'] !== '' ? $row['referrer'] : '—') ?></td>
                      </tr>
    <?php
    return (string) ob_get_clean();
}

$authed = !empty($_SESSION['admin_authed']);
$view = $authed ? admin_view() : 'stats';
$range = '24h';
$hideBots = true;
$ops = null;
$opsFlash = null;
if ($authed) {
    $range = admin_requested_range();
    $hideBots = admin_hide_bots();
    if ($view === 'operations') {
        $ops = ops_status();
        if (isset($_SESSION['ops_flash']) && is_array($_SESSION['ops_flash'])) {
            $opsFlash = $_SESSION['ops_flash'];
            unset($_SESSION['ops_flash']);
        }
    }
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
    $page = hits_recent_page($range, $hideBots, $before, hits_table_page_size());
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

$hitsStatus = ($authed && $view === 'stats') ? hits_status() : null;
$summary = ($hitsStatus !== null && $hitsStatus['ok']) ? hits_dashboard($range, $hideBots) : null;

$extraHead = '<link rel="stylesheet" href="/assets/admin.css">';
if ($authed && $view === 'stats') {
    $extraHead = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">' . "\n  " . $extraHead;
}

$pageTitle = $view === 'operations' ? 'Operations - IHEARTCOMPUTER' : 'Stats - IHEARTCOMPUTER';
new_render_header($pageTitle, 'IHEARTCOMPUTER - Admin', $extraHead);

$hl = is_array($summary['headlines'] ?? null) ? $summary['headlines'] : null;
?>

      <div class="admin-head">
        <div class="row" style="align-items: baseline;">
          <?php if ($authed): ?>
            <h1 class="h1" style="margin: 0;">
              <?php if ($view === 'stats'): ?>
                <span class="admin-current">stats</span><span class="muted"> / </span><a class="admin-switch" href="/admin/?view=operations">operations</a>
              <?php else: ?>
                <a class="admin-switch" href="/admin/">stats</a><span class="muted"> / </span><span class="admin-current">operations</span>
              <?php endif; ?>
            </h1>
            <a class="upper blue" href="/admin/?logout=1" style="font-size: 0.85rem;">logout →</a>
          <?php else: ?>
            <h1 class="h1" style="margin: 0;">Admin</h1>
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
        <?php if ($view === 'operations'): ?>
          <?php
            $g = is_array($ops['git'] ?? null) ? $ops['git'] : [];
            $onMain = !empty($g['on_main']);
            $dirty = !empty($g['dirty']);
            $ahead = $g['ahead'];
            $behind = $g['behind'];
            $diverged = is_int($ahead) && is_int($behind) && $ahead > 0 && $behind > 0;
            $canPull = !empty($g['ok']) && $onMain && !$dirty && !$diverged;
            $pullBlock = '';
            if (!$onMain) {
                $pullBlock = 'HEAD is not main.';
            } elseif ($dirty) {
                $pullBlock = 'working tree has local changes.';
            } elseif ($diverged) {
                $pullBlock = 'local and origin/main have diverged; fast-forward is not possible.';
            }
          ?>

          <?php if (is_array($opsFlash)): ?>
            <div class="panel admin-panel <?= !empty($opsFlash['ok']) ? 'admin-flash-ok' : 'admin-flash-bad' ?>">
              <h2 class="upper"><?= !empty($opsFlash['ok']) ? 'pull ok' : 'pull failed' ?></h2>
              <?php if (!empty($opsFlash['reason'])): ?>
                <p style="margin: 0 0 0.7rem;"><?= h((string) $opsFlash['reason']) ?></p>
              <?php endif; ?>
              <?php
                $fromH = substr((string) ($opsFlash['from'] ?? ''), 0, 7);
                $toH = substr((string) ($opsFlash['to'] ?? ''), 0, 7);
              ?>
              <?php if ($fromH !== '' || $toH !== ''): ?>
                <p class="mono" style="margin: 0 0 0.7rem;">
                  <?= h($fromH !== '' ? $fromH : '?') ?>
                  →
                  <?php if ($toH !== '' && !empty($opsFlash['to'])): ?>
                    <a href="<?= h(ops_commit_url((string) $opsFlash['to'])) ?>"><?= h($toH) ?></a>
                  <?php else: ?>
                    <?= h($toH !== '' ? $toH : '?') ?>
                  <?php endif; ?>
                </p>
              <?php endif; ?>
              <?php if ((string) ($opsFlash['output'] ?? '') !== ''): ?>
                <pre class="admin-pre"><?= h((string) $opsFlash['output']) ?></pre>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <div class="admin-split">
            <div class="panel admin-panel" style="margin-bottom: 0;">
              <h2 class="upper">deployed commit</h2>
              <?php if (empty($g['ok'])): ?>
                <p class="admin-empty"><?= h((string) ($g['error'] ?? 'git status unavailable')) ?></p>
              <?php else: ?>
                <table class="admin-table">
                  <tbody>
                    <tr>
                      <td class="upper muted" style="width: 7.5rem;">commit</td>
                      <td class="mono">
                        <?php if (!empty($g['url'])): ?>
                          <a href="<?= h($g['url']) ?>"><?= h($g['hash']) ?></a>
                        <?php else: ?>
                          <?= h($g['hash']) ?>
                        <?php endif; ?>
                      </td>
                    </tr>
                    <tr>
                      <td class="upper muted">message</td>
                      <td><?= h($g['subject']) ?></td>
                    </tr>
                    <tr>
                      <td class="upper muted">author</td>
                      <td><?= h($g['author']) ?></td>
                    </tr>
                    <tr>
                      <td class="upper muted">date</td>
                      <td class="mono"><?= h($g['date']) ?></td>
                    </tr>
                    <tr>
                      <td class="upper muted">branch</td>
                      <td class="mono"><?= h($g['branch']) ?></td>
                    </tr>
                    <tr>
                      <td class="upper muted">tree</td>
                      <td><?= $dirty ? '<span class="red">dirty</span>' : 'clean' ?></td>
                    </tr>
                    <tr>
                      <td class="upper muted">vs origin</td>
                      <td>
                        <?php if (is_int($ahead) && is_int($behind)): ?>
                          <?= (int) $ahead ?> ahead · <?= (int) $behind ?> behind
                          <?= !empty($g['fetched']) ? '' : ' <span class="muted">(local origin/main)</span>' ?>
                        <?php else: ?>
                          <span class="muted">unknown</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  </tbody>
                </table>
                <?php if (!empty($g['fetch_error'])): ?>
                  <p class="admin-note"><?= h((string) $g['fetch_error']) ?></p>
                <?php endif; ?>
                <?php if ($dirty && $g['dirty_summary'] !== ''): ?>
                  <pre class="admin-pre" style="margin-top: 0.75rem;"><?= h((string) $g['dirty_summary']) ?></pre>
                <?php endif; ?>
              <?php endif; ?>
            </div>
            <div class="panel admin-panel" style="margin-bottom: 0;">
              <h2 class="upper">server</h2>
              <table class="admin-table">
                <tbody>
                  <tr>
                    <td class="upper muted" style="width: 7.5rem;">host</td>
                    <td class="mono"><?= h((string) ($ops['host'] ?? '')) ?></td>
                  </tr>
                  <tr>
                    <td class="upper muted">php user</td>
                    <td class="mono"><?= h((string) ($ops['user'] ?? '')) ?></td>
                  </tr>
                  <tr>
                    <td class="upper muted">php</td>
                    <td class="mono"><?= h((string) ($ops['php'] ?? '')) ?> · <?= h((string) ($ops['sapi'] ?? '')) ?></td>
                  </tr>
                  <tr>
                    <td class="upper muted">uptime</td>
                    <td><?= h((string) ($ops['uptime'] !== '' ? $ops['uptime'] : '—')) ?></td>
                  </tr>
                  <tr>
                    <td class="upper muted">load</td>
                    <td class="mono"><?= h((string) ($ops['load'] !== '' ? $ops['load'] : '—')) ?></td>
                  </tr>
                  <tr>
                    <td class="upper muted">disk free</td>
                    <td><?= h((string) ($ops['disk_free'] !== '' ? $ops['disk_free'] : '—')) ?>
                      <?php if (!empty($ops['disk_total'])): ?>
                        <span class="muted"> / <?= h((string) $ops['disk_total']) ?></span>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <tr>
                    <td class="upper muted">hits db</td>
                    <td><?= h((string) ($ops['hits_size'] !== '' ? $ops['hits_size'] : '—')) ?></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="panel admin-panel">
            <h2 class="upper">pull from github</h2>
            <p style="margin: 0 0 0.85rem; font-size: 0.9rem;">
              fetches origin and fast-forwards <span class="mono">main</span>.
            </p>
            <form method="post" action="/admin/?view=operations" id="ops-pull-form">
              <input type="hidden" name="csrf" value="<?= h(admin_csrf_token()) ?>">
              <input type="hidden" name="action" value="pull">
              <button class="btn" type="submit" id="ops-pull-btn" <?= $canPull ? '' : 'disabled' ?>>pull from github</button>
            </form>
            <script>
              (function () {
                var form = document.getElementById('ops-pull-form');
                var btn = document.getElementById('ops-pull-btn');
                if (!form || !btn || btn.disabled) return;
                var sending = false;
                form.addEventListener('submit', function (e) {
                  if (sending) {
                    e.preventDefault();
                    return;
                  }
                  sending = true;
                  btn.disabled = true;
                  btn.textContent = 'pulling...';
                });
              })();
            </script>
          </div>
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
          ?>
          <div class="panel admin-panel">
            <h2 class="upper">hits and unique visitors per <?= h($bucketWord) ?></h2>
            <?php if ($summary['daily'] === []): ?>
              <p class="admin-empty">no hits in this window</p>
            <?php else: ?>
              <div class="admin-chart admin-chart-wide">
                <canvas id="daily-chart" aria-label="hits and unique visitors per <?= h($bucketWord) ?>"></canvas>
              </div>
            <?php endif; ?>
          </div>

          <div class="admin-split admin-split-geo">
            <div class="panel admin-panel" style="margin-bottom: 0;">
              <h2 class="upper">hits by page</h2>
              <?php if ($summary['pages'] === []): ?>
                <p class="admin-empty">no hits yet</p>
              <?php else: ?>
                <div class="admin-pages">
                  <div class="admin-chart">
                    <canvas id="pages-chart" aria-label="hits by page"></canvas>
                  </div>
                  <div class="admin-page-legend-wrap">
                    <ul class="admin-page-legend" id="pages-legend"></ul>
                  </div>
                </div>
              <?php endif; ?>
            </div>
            <div class="panel admin-panel" style="margin-bottom: 0;">
              <h2 class="upper">where hits come from</h2>
              <div class="admin-geo">
                <div id="geo-map" class="admin-map" role="img" aria-label="world map of hits by country"></div>
                <div class="admin-legend">
                  <span><i style="background: #e8e8e4;"></i>no hits</span>
                  <span><i style="background: #e10600;"></i>more hits</span>
                  <span><i style="background: #1a4fff; border-radius: 50%;"></i>city</span>
                </div>
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
                    <?php foreach ($summary['asn_rows'] as $i => $row): ?>
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
                      <tr class="<?= $row['legacy'] ? 'legacy' : '' ?>"<?= admin_row_page_hidden((int) $i) ?>>
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
              <?php admin_load_more(count($summary['asn_rows'])); ?>
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
                      <?php foreach ($summary['top_paths'] as $i => $row): ?>
                        <tr<?= admin_row_page_hidden((int) $i) ?>>
                          <td class="path"><?= h($row['path']) ?></td>
                          <td class="num"><?= number_format((int) $row['hits']) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                <?php admin_load_more(count($summary['top_paths'])); ?>
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

          <?php
            $threats = is_array($summary['threats'] ?? null) ? $summary['threats'] : [];
            $threatHits = (int) ($threats['hits'] ?? 0);
          ?>
          <?php if ($threatHits > 0): ?>
            <div class="panel admin-panel">
              <h2 class="upper">threats / scanners</h2>
              <div class="admin-split admin-split-threats" style="margin-bottom: 0;">
                <div>
                  <?php if (empty($threats['top_ips'])): ?>
                    <p class="admin-empty">none</p>
                  <?php else: ?>
                    <div class="admin-table-wrap">
                      <table class="admin-table">
                        <thead>
                          <tr>
                            <th class="upper">IP / ASN</th>
                            <th class="upper">org</th>
                            <th class="upper num">hits</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($threats['top_ips'] as $i => $row): ?>
                            <?php
                              $ipAsn = $row['ip'];
                              if ($row['asn'] !== '') {
                                  $ipAsn .= ' · ' . $row['asn'];
                              }
                            ?>
                            <tr<?= admin_row_page_hidden((int) $i) ?>>
                              <td class="mono"><?= h($ipAsn) ?></td>
                              <td>
                                <?= h($row['org'] !== '' ? $row['org'] : '—') ?>
                                <?php if ($row['country_code'] !== ''): ?>
                                  <span class="muted"><?= h($row['country_code']) ?></span>
                                <?php endif; ?>
                              </td>
                              <td class="num"><?= number_format((int) $row['hits']) ?></td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                    <?php admin_load_more(count($threats['top_ips'])); ?>
                  <?php endif; ?>
                </div>
                <div>
                  <?php if (empty($threats['top_paths'])): ?>
                    <p class="admin-empty">none</p>
                  <?php else: ?>
                    <div class="admin-table-wrap">
                      <table class="admin-table">
                        <tbody>
                          <?php foreach ($threats['top_paths'] as $i => $row): ?>
                            <tr<?= admin_row_page_hidden((int) $i) ?>>
                              <td class="path"><?= h($row['path']) ?></td>
                              <td class="num"><?= number_format((int) $row['hits']) ?></td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                    <?php admin_load_more(count($threats['top_paths'])); ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <script type="application/json" id="stats-data"><?= json_encode(
              [
                  'bucket' => $summary['bucket'],
                  'daily' => $summary['daily'],
                  'pages' => $summary['pages'],
                  'pages_all' => $summary['pages_all'] ?? [],
                  'pages_other' => $summary['pages_other'] ?? [],
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
      <?php endif; ?>

<?php new_render_footer(); ?>
