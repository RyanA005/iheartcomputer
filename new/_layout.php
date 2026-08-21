<?php

function new_render_header(string $title, string $description = 'IHEARTCOMPUTER - an unofficial computer club at NJIT'): void
{
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="<?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="icon" type="image/x-icon" href="/assets/logo.png">
  <link rel="stylesheet" href="/new/style.css">
</head>
<body>
  <div class="wrap">
    <header class="row" style="padding: 1.25rem 0; border-bottom: 1px solid #ccc;">
      <div class="row" style="gap: 0.75rem; justify-content: flex-start;">
        <a href="/new/" class="upper" style="color: #111; font-size: clamp(1.75rem, 4vw, 2.5rem); letter-spacing: 0.02em; line-height: 1; text-decoration: none;">
          I<span class="red">♥</span>COMPUTER
        </a>
        <span class="badge" aria-hidden="true" style="margin-top: 0.35rem;">new stylesheet!</span>
      </div>
      <nav class="nav" aria-label="primary">
        <a href="/new/projects/">projects</a>
        <a href="/new/about/">about</a>
        <a href="https://discord.gg/JpRw84Ybwg">discord</a>
        <a href="https://www.instagram.com/iheartcomputer.club/">instagram</a>
      </nav>
    </header>
    <main style="padding-top: 1.5rem;">
    <?php
}

function new_render_footer(): void
{
    ?>
      <hr>
      <footer class="row" style="align-items: baseline;">
        <p style="margin: 0; max-width: 34rem;">
          "Computer club is fun pull up" -
          <a href="/new/about/#ryan-alport">Ryan Alport</a>
        </p>
        <nav class="nav" aria-label="footer">
          <a href="/new/projects/">projects</a>
          <a href="/new/about/">about</a>
          <a href="https://discord.gg/JpRw84Ybwg">discord</a>
          <a href="https://www.instagram.com/iheartcomputer.club/">instagram</a>
        </nav>
      </footer>
    </main>
  </div>
</body>
</html>
    <?php
}
