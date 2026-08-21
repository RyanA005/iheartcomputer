<?php

session_start();

require dirname(__DIR__) . '/_layout.php';

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

$env = load_dotenv(dirname(__DIR__, 2) . '/.env');
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
    header('Location: /new/admin/');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted = (string) ($_POST['password'] ?? '');
    if (hash_equals($password, $submitted)) {
        session_regenerate_id(true);
        $_SESSION['admin_authed'] = true;
        header('Location: /new/admin/');
        exit;
    }
    $error = 'wrong password';
}

$authed = !empty($_SESSION['admin_authed']);

new_render_header('Admin - IHEARTCOMPUTER', 'IHEARTCOMPUTER - Admin');
?>

      <h1 class="h1">Admin</h1>

      <?php if (!$authed): ?>
        <form method="post" action="/new/admin/" class="panel" style="max-width: 22rem; padding: 1.1rem 1.25rem;">
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
        <div class="panel" style="max-width: 28rem; padding: 1.1rem 1.25rem;">
          <p class="upper" style="margin: 0 0 0.75rem;">you are logged in</p>
          <a class="upper blue" href="/new/admin/?logout=1" style="font-size: 0.85rem;">logout →</a>
        </div>
      <?php endif; ?>

<?php new_render_footer(); ?>
