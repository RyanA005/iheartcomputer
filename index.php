<?php
$email_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
  $email = trim($_POST['email']);
  if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $line = date('c') . " " . $email . PHP_EOL;
    $file = __DIR__ . '/subscribers.txt';
    if (file_put_contents($file, $line, FILE_APPEND) !== false) {
      $email_msg = '<span style="color:green;">got it! thank you</span>';
    } else {
      $email_msg = '<span style="color:red;">error! probably already got it</span>';
    }
  } else {
    $email_msg = '<span style="color:blue;">actually enter your email jackass... think im a dummy?</span>';
  }
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="IHEARTCOMPUTER - A club for computer lovers at NJIT">
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

        <div class="large">Welcome!</div>
        <br>
        <div class="medium">you must be here for the computers...</div>    
        <br>
        <div class="medium">here is a <a class="link" href="https://discord.gg/JpRw84Ybwg">link to our discord</a>!</div>   
        <br>
        <div class="medium">here is a <a class="link" href="https://www.instagram.com/iheartcomputer.club/">link to our instagram</a>!</div>   
        <br>
        <div class="medium">next meeting March 4th... do not miss it</div>   
        <hr class="break">

<!--
	cool stuff coming soon
    <img src="/heart.gif" style="width:300px">
	cool stuff coming soon
-->
        <!--<canvas id="heart" style="border:1px solid #000000;"></canvas>-->
        <canvas id="heart" style=""></canvas>
        <script src="render.js"></script>

        <hr class="break">

        <div class="large">What do we actually do here?</div>
        <br>
        <div class="medium">we do whatever we want lol</div>    
        <br>
        <div class="small">for example:</div>    
        <div class="small">demonstrations and lectures on cool computing concepts</div>    
        <div class="small">get together to talk about projects and things we are working on</div>   
        <div class="small">career advice + resume review + workshops + networking</div>   
        <br> 
        <div class="medium">here are <a class="link" href="/projects">some of our projects</a></div>    
        <br> 
        <div class="small">its gonna be awesome you should join us</div>    
        <br>


    </div>

  </div>
</body>
</html>
