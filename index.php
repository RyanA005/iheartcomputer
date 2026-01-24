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
        <div class="medium"><a class="link" href="https://discord.gg/JpRw84Ybwg">here</a> is a link to our discord</div>   
        <br>
        <div class="medium">if you are really really interested, type your email in this box</div>   
        <br>

        <form method="post" action="">
          <label class="small">email:
            <input class="small" type="email" name="email" required placeholder="you@email.com">
          </label>
	  <button type="submit" class="small">submit</button>
        </form>
        <?php if ($email_msg): ?>
          <p class="small" role="status"><?php echo $email_msg; ?></p>
        <?php endif; ?>
        
        <hr class="break">
	cool stuff coming soon
    <img src="/heart.gif" style="width:300px">
	cool stuff coming soon
	<!--
        <canvas width="500" height="500" id="a"></canvas>
        <script src="/heart.js"></script>
	-->
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
        <div class="medium"><a class="link" href="/projects">here</a> are some of our projects</div>    
        <br> 
        <div class="small">its gonna be awesome you should join us</div>    
        <br>


    </div>

  </div>
</body>
</html>
