<?php

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
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
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

$authed = !empty($_SESSION['admin_authed']);
$days = 7;
if ($authed) {
    $requested = (int) ($_GET['days'] ?? 7);
    if (in_array($requested, [7, 30], true)) {
        $days = $requested;
    }
}
$hitsStatus = $authed ? hits_status() : null;
$summary = ($hitsStatus !== null && $hitsStatus['ok']) ? hits_summary($days) : null;

new_render_header('Admin - IHEARTCOMPUTER', 'IHEARTCOMPUTER - Admin');
?>

      <h1 class="h1">Admin</h1>

      <?php if (!$authed): ?>
        <form method="post" action="/admin/" class="panel" style="max-width: 22rem; padding: 1.1rem 1.25rem;">
          <label class="upper" for="password" style="display: block; font-size: 0.75rem; margin-bottom: 0.45rem;">password</label>
          <input
            id="password"
            type="password"
            name="password"
            required
            autofocus
            style="display: block; width: 100%; box-sizing: border-box; margin-bottom: 0.85rem; padding: 0.55rem 0.65rem; border: 1px solid #ccc; background: #fff; font: inherit;"
          >
          <button class="btn" type="submit" style="border: none; cursor: pointer;">login</button>
          <?php if ($error !== ''): ?>
            <p class="red" style="margin: 0.85rem 0 0; font-size: 0.9rem;"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
          <?php endif; ?>
        </form>
      <?php else: ?>
        <div class="row" style="margin-bottom: 1.25rem;">
          <p class="upper" style="margin: 0;">you are logged in</p>
          <a class="upper blue" href="/admin/?logout=1" style="font-size: 0.85rem;">logout →</a>
        </div>

        <?php if ($summary === null): ?>
          <div class="panel" style="padding: 1.1rem 1.25rem;">
            <p class="muted" style="margin: 0;">hit log unavailable: <?= htmlspecialchars((string) ($hitsStatus['error'] ?? 'unknown'), ENT_QUOTES, 'UTF-8') ?></p>
          </div>
        <?php else: ?>
          <div class="row" style="margin-bottom: 1rem; justify-content: flex-start; gap: 0.75rem;">
            <a class="upper<?= $days === 7 ? ' red' : '' ?>" href="/admin/?days=7" style="font-size: 0.8rem;">last 7 days</a>
            <a class="upper<?= $days === 30 ? ' red' : '' ?>" href="/admin/?days=30" style="font-size: 0.8rem;">last 30 days</a>
          </div>

          <div class="grid" style="margin-bottom: 1.75rem;">
            <div class="panel" style="padding: 1rem 1.15rem;">
              <span class="upper muted" style="display: block; font-size: 0.7rem; margin-bottom: 0.35rem;">hits</span>
              <strong style="font-size: 1.6rem;"><?= (int) $summary['hits'] ?></strong>
            </div>
            <div class="panel" style="padding: 1rem 1.15rem;">
              <span class="upper muted" style="display: block; font-size: 0.7rem; margin-bottom: 0.35rem;">unique ips</span>
              <strong style="font-size: 1.6rem;"><?= (int) $summary['uniques'] ?></strong>
            </div>
          </div>

          <div class="split" style="align-items: start; margin-bottom: 1.75rem;">
            <div>
              <h2 class="upper" style="font-size: 0.85rem; margin: 0 0 0.75rem;">top pages</h2>
              <?php if ($summary['top_paths'] === []): ?>
                <p class="muted" style="margin: 0;">no hits yet</p>
              <?php else: ?>
                <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                  <tbody>
                    <?php foreach ($summary['top_paths'] as $row): ?>
                      <tr style="border-top: 1px solid #ddd;">
                        <td style="padding: 0.45rem 0.5rem 0.45rem 0; word-break: break-all;"><?= htmlspecialchars($row['path'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td style="padding: 0.45rem 0; text-align: right; white-space: nowrap;"><?= (int) $row['hits'] ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php endif; ?>
            </div>
            <div>
              <h2 class="upper" style="font-size: 0.85rem; margin: 0 0 0.75rem;">top referrers</h2>
              <?php if ($summary['top_referrers'] === []): ?>
                <p class="muted" style="margin: 0;">none yet (same-site refs are dropped)</p>
              <?php else: ?>
                <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                  <tbody>
                    <?php foreach ($summary['top_referrers'] as $row): ?>
                      <tr style="border-top: 1px solid #ddd;">
                        <td style="padding: 0.45rem 0.5rem 0.45rem 0; word-break: break-all;"><?= htmlspecialchars($row['referrer'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td style="padding: 0.45rem 0; text-align: right; white-space: nowrap;"><?= (int) $row['hits'] ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php endif; ?>
            </div>
          </div>

          <h2 class="upper" style="font-size: 0.85rem; margin: 0 0 0.75rem;">recent hits</h2>
          <?php if ($summary['recent'] === []): ?>
            <p class="muted" style="margin: 0;">no hits yet</p>
          <?php else: ?>
            <div style="overflow-x: auto;">
              <table style="width: 100%; border-collapse: collapse; font-size: 0.8rem;">
                <thead>
                  <tr style="border-bottom: 1px solid #111; text-align: left;">
                    <th class="upper" style="padding: 0.35rem 0.5rem 0.35rem 0; font-weight: 700;">when</th>
                    <th class="upper" style="padding: 0.35rem 0.5rem; font-weight: 700;">path</th>
                    <th class="upper" style="padding: 0.35rem 0.5rem; font-weight: 700;">ip</th>
                    <th class="upper" style="padding: 0.35rem 0; font-weight: 700;">referrer</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($summary['recent'] as $row): ?>
                    <tr style="border-top: 1px solid #ddd; vertical-align: top;">
                      <td style="padding: 0.4rem 0.5rem 0.4rem 0; white-space: nowrap;"><?= htmlspecialchars(gmdate('Y-m-d H:i', $row['ts']) . 'Z', ENT_QUOTES, 'UTF-8') ?></td>
                      <td style="padding: 0.4rem 0.5rem; word-break: break-all;"><?= htmlspecialchars($row['path'], ENT_QUOTES, 'UTF-8') ?></td>
                      <td style="padding: 0.4rem 0.5rem; white-space: nowrap;"><?= htmlspecialchars($row['ip'], ENT_QUOTES, 'UTF-8') ?></td>
                      <td style="padding: 0.4rem 0; word-break: break-all;"><?= htmlspecialchars($row['referrer'] !== '' ? $row['referrer'] : '-', ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      <?php endif; ?>

<?php new_render_footer(); ?>
