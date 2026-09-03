<?php
http_response_code(403);

require dirname(dirname(__DIR__)) . '/assets/_layout.php';

new_render_header('403 - IHEARTCOMPUTER', 'IHEARTCOMPUTER - 403');
?>

      <section class="panel" style="max-width: 34rem; padding: 1.15rem 1.25rem;">
        <h1 class="h1" style="margin-bottom: 0.25rem;">403</h1>
        <p class="upper red" style="margin: 0 0 0.5rem; font-size: 0.8rem;">forbidden</p>
        <p class="muted" style="margin: 0 0 1rem;">you are not allowed to be here</p>
        <a class="upper blue" href="/" style="font-size: 0.85rem;">go home →</a>
      </section>

<?php new_render_footer(); ?>
