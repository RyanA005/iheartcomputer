<?php
require dirname(__DIR__) . '/assets/_layout.php';

$sites = json_decode(
    file_get_contents(__DIR__ . '/../assets/sites.json'),
    true
) ?: [];

$escape = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

$grouped = [];
foreach ($sites as $site) {
    $url = $site['url'] ?? '';
    $scheme = parse_url($url, PHP_URL_SCHEME);

    if (!filter_var($url, FILTER_VALIDATE_URL) ||
        !in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
        continue;
    }

    $tag = trim((string) ($site['tag'] ?? ''));
    if ($tag === '') {
        $tag = 'other';
    }

    $key = strtolower($tag);
    if (!isset($grouped[$key])) {
        $grouped[$key] = [
            'label' => $tag,
            'sites' => [],
        ];
    }
    $grouped[$key]['sites'][] = $site;
}

$preferred = ['personal', 'project', 'business'];
$ordered = [];

foreach ($preferred as $key) {
    if (isset($grouped[$key])) {
        $ordered[$key] = $grouped[$key];
        unset($grouped[$key]);
    }
}

ksort($grouped, SORT_NATURAL | SORT_FLAG_CASE);
foreach ($grouped as $key => $group) {
    $ordered[$key] = $group;
}

new_render_header('Sites - IHEARTCOMPUTER');
?>

<h1 class="h1">websites</h1>
<p>created by our club members and friends</p>

<hr>

<?php foreach ($ordered as $group): ?>
  <h2 class="h2" style="margin-bottom: 0.75rem;"><?= $escape($group['label']) ?></h2>
  <ul>
  <?php foreach ($group['sites'] as $site):
      $url = $site['url'];
      $host = parse_url($url, PHP_URL_HOST) ?: $url;
      $name = $site['name'] ?? '';
      $description = $site['description'] ?? '';
  ?>
    <li>
        <a href="<?= $escape($url) ?>" target="_blank" rel="noopener noreferrer"><?= $escape($host) ?></a><?php
        if ($name !== '') {
            echo ', by ' . $escape($name);
        }
        if ($description !== '') {
            echo ' - ' . $escape($description);
        }
        ?>
    </li>
  <?php endforeach; ?>
  </ul>
  <hr>
<?php endforeach; ?>

<p>want to submit your own? submit a PR <a href="https://github.com/RyanA005/iheartcomputer">here</a> or reach out directly and ask!</p>

<?php new_render_footer(); ?>
