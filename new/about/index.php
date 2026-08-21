<?php

require dirname(__DIR__) . '/_layout.php';

function load_people(): array
{
    $path = dirname(__DIR__, 2) . '/assets/people.json';
    if (!file_exists($path)) {
        return [];
    }

    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function people_by_group(array $people, string $group): array
{
    $filtered = [];
    foreach ($people as $id => $person) {
        $personGroup = $person['group'] ?? 'founding';
        if ($personGroup === $group) {
            $filtered[$id] = $person;
        }
    }
    return $filtered;
}

$people = load_people();
$founding = people_by_group($people, 'founding');
$presenters = people_by_group($people, 'presenter');
$allPeople = $founding + $presenters;
$accents = ['#e10600', '#1a4fff', '#1a9e4a', '#7a3cff'];

$things = [
    [
        'title' => 'code',
        'text' => 'Build real things with computers.',
        'color' => '#e10600',
        'icon' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 18l-6-6 6-6M15 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="square"/></svg>',
    ],
    [
        'title' => 'systems',
        'text' => 'Learn how things work from the ground up.',
        'color' => '#1a4fff',
        'icon' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3l8 4.5v9L12 21l-8-4.5v-9L12 3z" stroke="currentColor" stroke-width="2"/><path d="M12 12l8-4.5M12 12v9M12 12L4 7.5" stroke="currentColor" stroke-width="2"/></svg>',
    ],
    [
        'title' => 'curiosity',
        'text' => 'Learn about unique and interesting areas of computing.',
        'color' => '#1a9e4a',
        'icon' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M20 20l-3.5-3.5M11 8v3l2 2" stroke="currentColor" stroke-width="2" stroke-linecap="square"/></svg>',
    ],
    [
        'title' => 'community',
        'text' => 'Show up, learn from others and share what you know.',
        'color' => '#7a3cff',
        'icon' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="2"/><circle cx="16" cy="9" r="2.5" stroke="currentColor" stroke-width="2"/><path d="M3 19c0-3 2.5-5 6-5s6 2 6 5M14 19c0-2 1.5-3.5 4-3.5s3.5 1 4 3.5" stroke="currentColor" stroke-width="2"/></svg>',
    ],
    [
        'title' => 'opportunity',
        'text' => 'Doing and being part of cool shit opens doors.',
        'color' => '#e67e22',
        'icon' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3l2.2 6.8H21l-5.5 4 2.1 6.7L12 16.8 6.4 20.5l2.1-6.7L3 9.8h6.8L12 3z" stroke="currentColor" stroke-width="2" stroke-linejoin="miter"/></svg>',
    ],
];

new_render_header('About - IHEARTCOMPUTER', 'IHEARTCOMPUTER - About the club and contributors');
?>

      <section class="split" style="margin-bottom: 2.25rem; align-items: start;">
        <div>
          <h1 class="h1" style="margin-bottom: 0.2rem;">About</h1>
          <p class="upper muted" style="margin: 0 0 1.1rem; font-size: 0.9rem; letter-spacing: 0.08em;">computer club</p>
          <p style="margin: 0 0 0.85rem; font-size: 1.05rem; line-height: 1.55; color: #333; max-width: 30rem;">
            We're a student run club at NJIT for people who like computers and
            want to do more with them. We meet weekly for a lecture, demo, or workshop
            led by one of our members.
          </p>
          <p style="margin: 0 0 0.85rem; font-size: 1.05rem; line-height: 1.55; color: #333; max-width: 30rem;">
            Topics range from security, to graphics, AI, systems, games,
            startups, careers, and whatever else we feel like.
            We like to do things from scratch and show off how they really work.
          </p>
          <p style="margin: 0 0 1.35rem; font-size: 1.05rem; line-height: 1.55; color: #333; max-width: 30rem;">
            All experience levels. All majors. Anyone can join, anyone can
            present, we got no rules.
          </p>
          <a class="upper blue" href="https://discord.gg/JpRw84Ybwg" style="font-size: 0.85rem;">come to a meeting →</a>
        </div>

        <div class="split-aside">
          <ul style="list-style: none; margin: 0; padding: 0;">
            <?php foreach ($things as $i => $thing): ?>
              <li style="display: flex; gap: 0.9rem; align-items: flex-start; padding: 0.9rem 0;<?= $i < count($things) - 1 ? ' border-bottom: 1px solid #e5e5e5;' : '' ?>">
                <span style="color: <?= htmlspecialchars($thing['color'], ENT_QUOTES, 'UTF-8') ?>; flex-shrink: 0; margin-top: 0.1rem;"><?= $thing['icon'] ?></span>
                <span>
                  <strong class="upper" style="display: block; font-size: 0.95rem; margin-bottom: 0.2rem;">
                    <?= htmlspecialchars($thing['title'], ENT_QUOTES, 'UTF-8') ?>
                  </strong>
                  <span style="display: block; font-size: 0.95rem; line-height: 1.4; color: #333;">
                    <?= htmlspecialchars($thing['text'], ENT_QUOTES, 'UTF-8') ?>
                  </span>
                </span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </section>

      <hr>

      <div class="row" style="align-items: baseline; margin-bottom: 1rem;">
        <h2 class="h2">people</h2>
      </div>

      <ul class="list">
        <?php $i = 0; foreach ($allPeople as $id => $person): ?>
          <?php
            $num = str_pad((string) (++$i), 2, '0', STR_PAD_LEFT);
            $accent = $accents[($i - 1) % count($accents)];
          ?>
          <li id="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>" style="scroll-margin-top: 1rem;">
            <div class="list-row list-row-people" style="border-left: 4px solid <?= htmlspecialchars($accent, ENT_QUOTES, 'UTF-8') ?>;">
              <?php if (!empty($person['photo'])): ?>
                <img
                  src="<?= htmlspecialchars($person['photo'], ENT_QUOTES, 'UTF-8') ?>"
                  alt="<?= htmlspecialchars($person['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                  width="104"
                  height="104"
                  loading="lazy"
                >
              <?php else: ?>
                <span></span>
              <?php endif; ?>
              <span class="list-num upper" style="font-size: 1.35rem;"><?= htmlspecialchars($num, ENT_QUOTES, 'UTF-8') ?></span>
              <strong class="list-title upper" style="display: block; font-size: 1.15rem; line-height: 1.25;">
                <?= htmlspecialchars($person['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
              </strong>
              <span class="list-body" style="min-width: 0;">
                <?php if (!empty($person['detail'])): ?>
                  <span style="display: block; font-size: 0.95rem; line-height: 1.45; color: #333;">
                    <?= htmlspecialchars($person['detail'], ENT_QUOTES, 'UTF-8') ?>
                  </span>
                <?php endif; ?>
                <?php if (!empty($person['links']) && is_array($person['links'])): ?>
                  <span class="row" style="justify-content: flex-start; gap: 0.45rem 1rem; margin-top: 0.55rem; font-size: 0.88rem;">
                    <?php foreach ($person['links'] as $link): ?>
                      <a href="<?= htmlspecialchars($link['url'] ?? '#', ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($link['label'] ?? 'link', ENT_QUOTES, 'UTF-8') ?></a>
                    <?php endforeach; ?>
                  </span>
                <?php endif; ?>
              </span>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>

<?php new_render_footer(); ?>
