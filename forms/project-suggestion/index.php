<?php
require_once '../helper.php';

$msg = '';

$data_file = __DIR__ . '/responses.csv';
$header = ['timestamp', 'name', 'email', 'topic', 'description', 'present'];
ensure_csv_header($data_file, $header);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!empty($_POST['website'])) {
    exit;
  }

  $name = get_post('name');
  $email = get_post('email');
  $topic = get_post('topic');
  $description = get_post('description');
  $present = get_post('present');

  if ($name && $topic) {
    if (write_csv($data_file, [
      date('c'),
      $name,
      $email,
      $topic,
      $description,
      $present,
    ])) {
      $msg = '<span style="color:green;">got it - thanks for the suggestion!</span>';
    } else {
      $msg = '<span style="color:red;">something broke. try again<./span>';
    }
  } else {
    $msg = '<span style="color:blue;">fill the required fields please.</span>';
  }
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Project Suggestion - IHEARTCOMPUTER</title>
  <link rel="icon" type="image/x-icon" href="/logo.png">
  <link rel="stylesheet" href="/styles.css">
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
      <div class="large"><strong>project suggestion / request form</strong></div>
      <br>

      <div class="medium">
        want to hear a talk on something? got a project idea we should cover?
      </div>
      <div class="small">
        tell us what you want to see at or pitch something you'd like to present yourself!
      </div>

      <hr class="break">

      <?= $msg ?>
      <br>

      <form method="POST">
        <input type="text" name="website" style="display:none">

        <div class="small">name*</div>
        <input class="input" name="name" required>
        <br><br>

        <div class="small">email (optional, if we need to follow up)</div>
        <input class="input" name="email" type="email">
        <br><br>

        <div class="small">topic or project idea*</div>
        <input class="input" name="topic" placeholder="e.g. writing a raycaster, local LLMs, web scraping..." required>
        <br><br>

        <div class="small">why should we do this / what would you want to learn?</div>
        <textarea class="input" name="description"></textarea>
        <br><br>

        <div class="small">would you want to present this?</div>
        <select class="input" name="present">
          <option value="suggest-only">just a suggestion</option>
          <option value="yes">yes, I'd present it</option>
          <option value="maybe">maybe, with some help</option>
          <option value="no">no, someone else should</option>
        </select>
        <br><br>

        <button class="link" type="submit">submit</button>
      </form>

      <br>

      <div class="small">questions? reach out @ <u>ralport2005@gmail.com</u> or say hi in the discord</div>
      <br>
      <div class="small">see <a class="link" href="/projects/">past projects</a> for the kind of stuff we usually do</div>
    </div>

    <hr class="break">
  </div>
</body>
</html>
