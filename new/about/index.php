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

new_render_header('About - IHEARTCOMPUTER', 'IHEARTCOMPUTER - About the club and contributors');
?>

      <section style="margin-bottom: 2.25rem;">
        <h1 class="h1" style="margin-bottom: 0.2rem;">About</h1>
        <p style="margin: 0 0 0.85rem; font-size: 1.05rem; line-height: 1.55; color: #333;">
          We're a student run club at NJIT for people who like computers and
          want to do more with them. We meet weekly for a lecture, demo, or workshop
          led by one of our members.
        </p>
        <p style="margin: 0 0 0.85rem; font-size: 1.05rem; line-height: 1.55; color: #333;">
          Topics range from security, to graphics, AI, systems, games,
          startups, careers, and whatever else we feel like.
          We like to do things from scratch and show off how they really work.
        </p>
        <p style="margin: 0 0 1.35rem; font-size: 1.05rem; line-height: 1.55; color: #333;">
          All experience levels. All majors. Anyone can join, anyone can
          present, we got no rules.
        </p>
        <a class="upper blue" href="https://discord.gg/JpRw84Ybwg" style="font-size: 0.85rem;">come to a meeting →</a>
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
