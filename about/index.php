<?php



function load_people(): array {

    $path = dirname(__DIR__) . '/people.json';

    if (!file_exists($path)) {

        return [];

    }



    $data = json_decode(file_get_contents($path), true);

    return is_array($data) ? $data : [];

}



function people_by_group(array $people, string $group): array {

    $filtered = [];

    foreach ($people as $id => $person) {

        $personGroup = $person['group'] ?? 'founding';

        if ($personGroup === $group) {

            $filtered[$id] = $person;

        }

    }

    return $filtered;

}



function render_contributor_list(array $people): void {

    ?>

    <ul class="contributor-list">

      <?php foreach ($people as $id => $person): ?>

        <li class="contributor" id="<?= htmlspecialchars($id) ?>">

          <?php if (!empty($person['photo'])): ?>

            <img

              class="contributor-photo"

              src="<?= htmlspecialchars($person['photo']) ?>"

              alt="<?= htmlspecialchars($person['name']) ?>"

              width="72"

              height="72"

              loading="lazy"

            >

          <?php endif; ?>

          <div class="contributor-body">

            <strong class="contributor-name"><?= htmlspecialchars($person['name']) ?></strong>

            <span class="contributor-detail"><?= htmlspecialchars($person['detail']) ?></span>

            <?php if (!empty($person['links'])): ?>

              <div class="contributor-links">

                <?php foreach ($person['links'] as $link): ?>

                  <a class="link" href="<?= htmlspecialchars($link['url']) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($link['label']) ?></a>

                <?php endforeach; ?>

              </div>

            <?php endif; ?>

          </div>

        </li>

      <?php endforeach; ?>

    </ul>

    <?php

}



$people = load_people();

$founding = people_by_group($people, 'founding');

$presenters = people_by_group($people, 'presenter');



?>

<!DOCTYPE html>

<html>

<head>

  <meta charset="UTF-8">

  <meta name="viewport" content="width=device-width, initial-scale=1">

  <meta name="description" content="IHEARTCOMPUTER - About the club and founding contributors">

  <title>About — IHEARTCOMPUTER</title>

  <link rel="icon" type="image/x-icon" href="/logo.png">

  <link rel="stylesheet" href="/styles.css">
  <style>
    .about-page { width:100%; max-width:640px; margin:0 auto; padding:0 10px 20px; align-items:stretch; text-align:left; }
    .contributor-list { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:18px; width:100%; }
    .contributor { display:flex; gap:14px; align-items:flex-start; padding:14px 16px; border:1px solid #ccc; border-radius:6px; scroll-margin-top:20px; }
    .contributor-photo { width:72px; height:72px; border-radius:50%; object-fit:cover; flex-shrink:0; border:1px solid #ddd; }
    .contributor-body { display:flex; flex-direction:column; gap:4px; flex:1; min-width:0; }
    .contributor-name { font-size:1.1rem; }
    .contributor-detail { font-size:.95rem; color:#555; line-height:1.4; }
    .contributor-links { display:flex; flex-wrap:wrap; gap:6px 14px; margin-top:4px; }
  </style>
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



    <div class="content about-page">

      <div class="large">About</div>

      <p class="small">IHEARTCOMPUTER is an unoffical club at NJIT open to all founded by Ryan Alport and his friends as a way to get together and present some of the cool projects we are working on.</p>

      <p class="small">Topics range from cyber security, game dev, AI, vibe coding, buisness, careers, programming workshops, you name it.</p>

      <p class="small">We love but are not limited to: weird implementations, hacking the world in ways you wouldn't expect: breaking things, doing it from scratch, or generally just having fun.</p>

      <p class="small">The idea is to create a weird space where we can show off the things we are building while also learning from eachother and getting exposed to some new areas of computing.</p>

      <p class="small">There are no rules, all are welcome to join, anyone can present, everyone is encouraged to ask questions or share their thoughts. Join our discord and say hi if you're interested!</p>

      <hr class="break">



      <div class="large">Founding Fathers</div>

      <br>

      <?php render_contributor_list($founding); ?>



      <?php if (!empty($presenters)): ?>

        <hr class="break">

        <div class="large">Presenters</div>

        <br>

        <?php render_contributor_list($presenters); ?>

      <?php endif; ?>

    </div>

  </div>

</body>

</html>

