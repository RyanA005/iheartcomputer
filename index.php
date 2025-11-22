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
  <link rel="icon" type="image/x-icon" href="logo.png">
  <style>
    body {
      display: flex;
      flex-direction: column;
      margin: 20px;
      border: 1px solid #333;
      padding: 10px;
    }
    .small { font-size: 1.0rem; align-self: center; }
    .medium { font-size: 1.5rem; align-self: center; }
    .large { font-size: 2.0rem; }
    .link { color: blue; }
    .break { width:100%;margin:20px auto; }
    .header {
      width: 100%;
      display: flex;
      flex-direction: row;
      align-items: center;
      justify-content: space-between;
    }
    .title {
      max-width: 60%;
      display: flex;
      flex-direction: column;
      flex: 1;
    }
    .nav {
      display: flex;
      flex-direction: row;
      gap: 15px;
      margin: 10px;
    }
    @media (max-width: 600px) {
      .nav {
        display: none;
      }
      .title {
        max-width: 100%;
      }
      .header {
        flex-direction: column;
      }
    }
    .content {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
    }
    button, input {
      background-color: whitesmoke;
      border: solid 1px lightgrey;
      border-radius: 5px;
      color: grey;
      padding: 5px;
    }
    button:hover, input:hover {
      border: solid 1px black;
    }

  </style>
</head>
<body>
  <div class="main">
    <header class="header">
      <div class="title">
        <strong class="large">I<span style="color: #e00;">♥</span>COMPUTER</strong>
      </div>
      <nav class="nav">
        <a class="link" href="index.php">home</a>
        <a class="link" href="https://discord.gg/JpRw84Ybwg" target="_blank">discord</a>
        <a class="link" href="">third link</a>
      </nav>
    </header>

    <hr class="break">

    <div class="content">

        <div class="large">Welcome!</div>
        <br>
        <div class="medium">you must be here for the computers...</div>    
        <br>
        <div class="medium"><a href="https://discord.gg/JpRw84Ybwg">here</a> is a link to our discord</div>   
        <br>
        <div class="medium">if you feel like a real champ type your email in this box</div>   
        <br>

        <form method="post" action="">
          <label class="small">email:
            <input class="small" type="email" name="email" required placeholder="you@email.com">
          </label>
          <button class="small" type="submit">submit</button>
        </form>
        <?php if ($email_msg): ?>
          <p class="small" role="status"><?php echo $email_msg; ?></p>
        <?php endif; ?>
        
        <hr class="break">

        cool stuff coming soon

        <hr class="break">

        <div class="medium">What do we actually do here?</div>
        <br>
        <div class="small">we do whatever we want lol</div>    
        <br>
        <div class="small">for example:</div>    
        <div class="small">> demonstrations and lectures on cool computing concepts</div>    
        <div class="small">> get together to talk about projects and things we are working on</div>   
        <div class="small">> career advice + resume review + workshops + networking</div>   
        <br> 
        <div class="small">its gonna be awesome you should join us</div>    
        <br>


    </div>

  </div>
</body>
</html>
