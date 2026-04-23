<?php
require_once '../helper.php';

$msg = '';

$data_file = __DIR__ . '/responses.csv';
$header = ['timestamp','name','interest','description','time','needs'];
ensure_csv_header($data_file, $header);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // honeypot
  if (!empty($_POST['website'])) exit;

  $name = get_post('name');
  $interest = get_post('interest');
  $description = get_post('description');
  $time = get_post('time');
  $needs = get_post('needs');

  if ($name && $interest) {
    if (write_csv($data_file, [
      date('c'),
      $name,
      $interest,
      $description,
      $time,
      $needs
    ])) {
      $msg = '<span style="color:green;">nice. you are in</span>';
    } else {
      $msg = '<span style="color:red;">something broke. tragic</span>';
    }
  } else {
    $msg = '<span style="color:blue;">fill the required stuff bro</span>';
  }
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>IHEARTCOMPUTER</title>
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

      <div class="large"><strong>final meeting presentation interest form (meeting 4/29/2026)</strong></div>
      <br>

      <div class="medium">
        for our last meeting it would be fun if everyone came up and talked about soemthing theyve been working on!
      </div>
      <div class="small">
        it can be anything, code, art, poetry, we just want to see what youve all been working on
      </div>

      <br>

      <div class="small">
        5 minutes is probably ideal presentation length but we are flexible depending on attendance
      </div>

      <hr class="break">

      <?= $msg ?>
      <br>

      <form method="POST">

        <!-- honeypot -->
        <input type="text" name="website" style="display:none">

        <div class="small">name*</div>
        <input class="input" name="name" required>
        <br><br>

        <div class="small">would you like to present?</div>
        <select class="input" name="interest">
          <option value="yes">yes</option>
          <option value="maybe">maybe</option>
          <option value="no">just watching</option>
        </select>
        <br><br>

        <div class="small">what will you talk about</div>
        <textarea class="input" name="description"></textarea>
        <br><br>

        <div class="small">how long</div>
        <select class="input" name="time">
          <option value="3">3 min</option>
          <option value="5">5 min</option>
          <option value="10">7 min</option>
        </select>
        <br><br>

        <div class="small">what will you need for your talk</div>
        <input class="input" name="needs" placeholder="slides / code / markers">
        <br><br>

        <button class="link" type="submit">submit</button>

      </form>

      <br>

      <div class="small">i would be really happy to help anyone put soemthing together, get me @ <u>ralport2005@gmail.com</u></div>
      <div class="small">if youve got a friend who is doing something cool bring them too! everyone is welcome</div>
      <br>
      <div class="small">your thing <i style="font-size: 18px;">doesnt have to be finished or even very good</i> but we want to hear about it</div>
      <br>
      <div class="small">thanks for the awesome semester everyone, see you wednesday april 29th!</div>

    </div>

    <hr class="break">

  </div>
</body>
</html>
