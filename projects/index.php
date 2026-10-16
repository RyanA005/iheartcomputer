<?php

require dirname(__DIR__) . '/assets/_layout.php';

function load_people(): array
{
    $path = dirname(__DIR__) . '/assets/people.json';
    if (!file_exists($path)) {
        return [];
    }

    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function load_project_metadata(string $dir): ?array
{
    $path = $dir . '/metadata.json';
    if (!file_exists($path)) {
        return null;
    }

    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

function format_project_date(?string $date): string
{
    if (!$date) {
        return '';
    }

    $timestamp = strtotime($date);
    return $timestamp ? date('M j, Y', $timestamp) : $date;
}

function project_thumbnail_src(string $slug, ?array $meta): string
{
    $thumbnail = $meta['thumbnail'] ?? null;
    if (!$thumbnail) {
        return '/assets/logo.png';
    }

    if (str_starts_with($thumbnail, '/')) {
        return $thumbnail;
    }

    return '/projects/' . $slug . '/' . $thumbnail;
}

function is_http_url(string $url): bool
{
    return (bool) preg_match('#^https?://#i', $url);
}

function resolve_author(string|array|null $author, array $people): ?array
{
    if ($author === null || $author === '') {
        return null;
    }

    if (is_array($author)) {
        $name = is_string($author['name'] ?? null) ? trim($author['name']) : '';
        if ($name === '') {
            return null;
        }

        $url = is_string($author['url'] ?? null) ? trim($author['url']) : '';
        if ($url !== '' && !is_http_url($url)) {
            $url = '';
        }

        return [
            'id' => null,
            'name' => $name,
            'profile_url' => $url !== '' ? $url : null,
            'external' => $url !== '',
        ];
    }

    if ($author === 'everyone') {
        return [
            'id' => 'everyone',
            'name' => 'everyone',
            'profile_url' => '/about/#',
            'external' => false,
        ];
    }

    if (!isset($people[$author])) {
        return null;
    }

    return [
        'id' => $author,
        'name' => $people[$author]['name'],
        'profile_url' => '/about/#' . $author,
        'external' => false,
    ];
}

function resolve_project_url(string $slug, ?array $meta): array
{
    $url = is_string($meta['url'] ?? null) ? trim($meta['url']) : '';
    if ($url !== '' && is_http_url($url)) {
        return [
            'href' => $url,
            'external' => true,
        ];
    }

    return [
        'href' => '/projects/' . $slug . '/',
        'external' => false,
    ];
}

function project_bucket(array $topics): string
{
    $joined = strtolower(implode(' ', $topics));
    if (preg_match('/\b(ai|llm|llms|language|nlp|machine learning|autoencoder|agent|agents|vibe)\b/', $joined)) {
        return 'ai';
    }
    if (preg_match('/\b(graphics|procedural|javascript)\b/', $joined)) {
        return 'graphics';
    }
    if (preg_match('/\b(security|malware)\b/', $joined)) {
        return 'security';
    }
    if (preg_match('/\b(physics|simulation|network|protocol|system|systems)\b/', $joined)) {
        return 'systems';
    }
    return 'misc';
}

function project_tag_label(array $topics): string
{
    if ($topics === []) {
        return 'project';
    }
    $slice = array_slice($topics, 0, 2);
    return strtolower(implode(' / ', $slice));
}

function project_tag_class(string $bucket): string
{
    return match ($bucket) {
        'ai' => 'red',
        'graphics' => 'green',
        'security' => 'blue',
        'systems' => 'purple',
        default => 'muted',
    };
}

$root = dirname(__DIR__) . '/projects';
$people = load_people();
$archive = ['vim-demo', 'iheartcomputer-template'];
$projects = [];

foreach (scandir($root) as $file) {
    if ($file === '.' || $file === '..' || !is_dir($root . '/' . $file) || str_starts_with($file, '.')) {
        continue;
    }

    if (in_array($file, $archive, true)) {
        continue;
    }

    $meta = load_project_metadata($root . '/' . $file);
    if ($meta && array_key_exists('listed', $meta) && $meta['listed'] === false) {
        continue;
    }

    $authorMeta = $meta['author'] ?? null;
    if (!is_string($authorMeta) && !is_array($authorMeta)) {
        $authorMeta = null;
    }
    $topics = $meta['topics'] ?? [];
    $bucket = project_bucket($topics);
    $link = resolve_project_url($file, $meta);

    $projects[] = [
        'slug' => $file,
        'title' => $meta['title'] ?? str_replace('-', ' ', $file),
        'subtitle' => $meta['subtitle'] ?? '',
        'topics' => $topics,
        'bucket' => $bucket,
        'tag' => project_tag_label($topics),
        'tag_class' => project_tag_class($bucket),
        'author' => resolve_author($authorMeta, $people),
        'date' => $meta['date'] ?? null,
        'thumbnail' => project_thumbnail_src($file, $meta),
        'video' => $meta['video'] ?? null,
        'href' => $link['href'],
        'external' => $link['external'],
    ];
}

usort($projects, function (array $a, array $b): int {
    return strcmp($b['date'] ?? '', $a['date'] ?? '');
});

$filters = [
    'all' => 'all',
    'ai' => 'ai',
    'graphics' => 'graphics',
    'security' => 'security',
    'systems' => 'systems',
    'misc' => 'misc',
];

new_render_header('Projects - IHEARTCOMPUTER', 'IHEARTCOMPUTER - Public Projects Page');
?>

      <div class="row" style="align-items: baseline; margin-bottom: 0.35rem;">
        <h1 class="h1" style="margin: 0;">Projects</h1>
      </div>
      <p class="muted" style="margin: 0 0 1.25rem;">
        most recent <?= count($projects) ?> projects
      </p>

      <div class="row" style="justify-content: flex-start; gap: 0.35rem 0.5rem; margin-bottom: 1rem;" id="filters">
        <span class="upper muted" style="font-size: 0.75rem; margin-right: 0.25rem;">filter:</span>
        <?php foreach ($filters as $key => $label): ?>
          <button
            type="button"
            class="chip<?= $key === 'all' ? ' chip-on' : '' ?>"
            data-filter="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
          ><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></button>
        <?php endforeach; ?>
      </div>

      <ul class="list" id="project-list">
        <?php foreach ($projects as $i => $project): ?>
          <?php
            $num = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
          ?>
          <?php
            $projectAttrs = $project['external']
              ? ' target="_blank" rel="noopener noreferrer"'
              : '';
            $authorUrl = $project['author']['profile_url'] ?? null;
            $authorAttrs = !empty($project['author']['external'])
              ? ' target="_blank" rel="noopener noreferrer"'
              : '';
          ?>
          <li data-bucket="<?= htmlspecialchars($project['bucket'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="list-row">
              <a href="<?= htmlspecialchars($project['href'], ENT_QUOTES, 'UTF-8') ?>"<?= $projectAttrs ?>>
                <img
                  src="<?= htmlspecialchars($project['thumbnail'], ENT_QUOTES, 'UTF-8') ?>"
                  alt=""
                  loading="lazy"
                  width="160"
                  height="100"
                >
              </a>
              <span class="list-num upper" style="font-size: 1.35rem;"><?= htmlspecialchars($num, ENT_QUOTES, 'UTF-8') ?></span>
              <strong class="list-title upper" style="display: block; font-size: 1.15rem; line-height: 1.25;">
                <a href="<?= htmlspecialchars($project['href'], ENT_QUOTES, 'UTF-8') ?>" style="color: inherit; text-decoration: none;"<?= $projectAttrs ?>>
                  <?= htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8') ?>
                </a>
              </strong>
              <span class="list-body">
                <?php if ($project['subtitle'] !== ''): ?>
                  <span class="muted" style="display: block; font-size: 0.95rem; line-height: 1.4;">
                    <?= htmlspecialchars($project['subtitle'], ENT_QUOTES, 'UTF-8') ?>
                  </span>
                <?php endif; ?>
                <span class="muted" style="display: block; margin-top: 0.35rem; font-size: 0.88rem;">
                  <?php if (!empty($project['author']['name'])): ?>
                    <?php if ($authorUrl): ?>
                      <a href="<?= htmlspecialchars($authorUrl, ENT_QUOTES, 'UTF-8') ?>"<?= $authorAttrs ?>>
                        <?= htmlspecialchars($project['author']['name'], ENT_QUOTES, 'UTF-8') ?>
                      </a>
                    <?php else: ?>
                      <?= htmlspecialchars($project['author']['name'], ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                  <?php endif; ?>
                  <?php if ($project['date']): ?>
                    <?= !empty($project['author']['name']) ? ' · ' : '' ?>
                    <?= htmlspecialchars(format_project_date($project['date']), ENT_QUOTES, 'UTF-8') ?>
                  <?php endif; ?>
                  <?php if (!empty($project['video'])): ?>
                    <?= (!empty($project['author']['name']) || $project['date']) ? ' · ' : '' ?>
                    <a href="<?= htmlspecialchars($project['video'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">recording</a>
                  <?php endif; ?>
                </span>
              </span>
              <span class="list-tag tag <?= htmlspecialchars($project['tag_class'], ENT_QUOTES, 'UTF-8') ?>" style="font-size: 0.78rem; padding: 0.3rem 0.6rem;">
                <?= htmlspecialchars($project['tag'], ENT_QUOTES, 'UTF-8') ?>
              </span>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>

      <script>
        (function () {
          var filters = document.getElementById('filters');
          var list = document.getElementById('project-list');
          if (!filters || !list) return;

          filters.addEventListener('click', function (event) {
            var button = event.target.closest('[data-filter]');
            if (!button) return;

            var value = button.getAttribute('data-filter');
            filters.querySelectorAll('[data-filter]').forEach(function (chip) {
              chip.classList.toggle('chip-on', chip === button);
            });

            list.querySelectorAll('[data-bucket]').forEach(function (item) {
              var show = value === 'all' || item.getAttribute('data-bucket') === value;
              item.hidden = !show;
            });
          });
        })();
      </script>

<?php new_render_footer(); ?>
