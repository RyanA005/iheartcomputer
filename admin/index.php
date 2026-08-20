<?php

session_start();

function load_dotenv(string $path): array {
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

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="IHEARTCOMPUTER - Admin">
  <title>Admin - IHEARTCOMPUTER</title>
  <link rel="icon" type="image/x-icon" href="/assets/logo.png">
  <link rel="stylesheet" href="/assets/styles.css">
</head>
<body>
  <div class="main">
    <header class="header">
      <div class="title">
        <strong class="large" style="align-self: flex-start;">I<span style="color: #e00;">♥</span>COMPUTER</strong>
      </div>
      <nav class="nav">
        <a class="link" href="/">home</a>
        <a class="link" href="https://discord.gg/JpRw84Ybwg" target="_blank">discord</a>
        <a class="link" href="/projects/">projects</a>
      </nav>
    </header>

    <hr class="break">

    <div class="content">
      <?php if (!$authed): ?>
        <div class="large">Admin</div>
        <br>
        <form method="post" action="/admin/" style="display:flex; flex-direction:column; gap:10px; align-items:center;">
          <label class="small" for="password">password</label>
          <input id="password" type="password" name="password" required autofocus>
          <button type="submit">login</button>
        </form>
        <?php if ($error !== ''): ?>
          <br>
          <div class="small" style="color:red;"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
      <?php else: ?>
        <div class="large">Admin</div>
        <br>
        <div class="medium">you are logged in</div>
        <br>
        <div class="small"><a class="link" href="/admin/?logout=1">logout</a></div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
