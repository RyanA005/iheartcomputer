<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="IHEARTCOMPUTER - an unofficial computer club at NJIT">
  <title>IHEARTCOMPUTER</title>
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
      <section class="split" style="margin-bottom: 1.75rem;">
        <div style="display: flex; justify-content: center; align-items: center; width: 100%;">
          <div class="ca-wrap" style="width: min(100%, 32rem); aspect-ratio: 72 / 70; position: relative; overflow: hidden; background: #f2f2f0; touch-action: none; cursor: crosshair;">
            <canvas id="ca-canvas" aria-label="growing neural cellular automata" style="opacity: 0; background: #f2f2f0;"></canvas>
          </div>
        </div>
        <div>
          <h1 class="h1">Computer Club at NJIT</h1>
          <p class="muted" style="margin: 0 0 1.1rem;">Weekly projects, demos, workshops, and whatever else we feel like. Come make cool stuff with us!</p>
          <div class="row" style="justify-content: flex-start; gap: 1rem 1.25rem;">
            <a class="btn" href="https://discord.gg/JpRw84Ybwg">join discord →</a>
            <a class="upper blue" href="/new/about/" style="font-size: 0.85rem;">about us →</a>
          </div>
          <p class="nca-aside" style="margin: 1.1rem 0 0;">
            <a href="https://ryanhub.org/blogs/neural-cellular-automata.html" class="muted" style="font-size: 0.78rem; text-decoration: none;" target="_blank" rel="noopener noreferrer">← read more about neural cellular automata</a>
          </p>
        </div>
      </section>

      <a class="panel band" href="https://discord.gg/JpRw84Ybwg" style="padding: 1.1rem 1.1rem 1.1rem 1.25rem; margin-bottom: 2rem;">
        <div>
          <span class="upper red" style="display: block; font-size: 0.75rem; margin-bottom: 0.35rem;">next meeting</span>
          <strong class="upper" style="display: block; font-size: 1.35rem; line-height: 1.15;">Welcome back and how to start building real projects</strong>
        </div>
        <ul class="upper" style="list-style: none; margin: 0; padding: 0; font-size: 0.85rem;">
          <li style="margin: 0.15rem 0;">date/time: tba</li>
          <li style="margin: 0.15rem 0;">room: tba</li>
        </ul>
        <p style="margin: 0; font-size: 0.9rem; color: #333;">
          Kickoff for the year. Club intro, schedule, ideas, and a quick talk on how to start making things with some starter repos and inspiration included!
        </p>
        <p class="upper">
          DONT MISS IT...
        </p>
      </a>

      <section>
        <div class="row" style="align-items: baseline; margin-bottom: 1rem;">
          <h2 class="h2">some of our favorite projects</h2>
          <a class="upper blue" href="/new/projects/" style="font-size: 0.85rem;">see all projects →</a>
        </div>

        <ul class="grid">
          <li class="card" style="border-left-color: #e10600;">
            <a href="/projects/art-and-worlds/">
              <img src="/projects/art-and-worlds/images/voxel-terrain.png" alt="" loading="lazy" width="320" height="180">
              <div style="padding: 0.7rem 0.85rem 0.85rem;">
                <strong class="upper" style="display: block; font-size: 0.95rem; line-height: 1.2;">Procedural Art and Worlds</strong>
                <div class="row" style="font-size: 0.72rem; margin-top: 0.25rem;">
                  <span class="upper red">graphics / js</span>
                  <span class="muted">2026</span>
                </div>
                <p style="margin: 0.25rem 0 0; font-size: 0.82rem; color: #333;">procedural generation with five interactive demos</p>
              </div>
            </a>
          </li>
          <li class="card" style="border-left-color: #1a4fff;">
            <a href="/projects/not-a-virus/">
              <img src="/projects/not-a-virus/thumbnail.png" alt="" loading="lazy" width="320" height="180">
              <div style="padding: 0.7rem 0.85rem 0.85rem;">
                <strong class="upper" style="display: block; font-size: 0.95rem; line-height: 1.2;">NOT How To Write Computer Viruses</strong>
                <div class="row" style="font-size: 0.72rem; margin-top: 0.25rem;">
                  <span class="upper blue">security / malware</span>
                  <span class="muted">2026</span>
                </div>
                <p style="margin: 0.25rem 0 0; font-size: 0.82rem; color: #333;">safely examining how malware is built and spreads</p>
              </div>
            </a>
          </li>
          <li class="card" style="border-left-color: #1a9e4a;">
            <a href="/projects/baby-lm/">
              <img src="/projects/baby-lm/images/chad.png" alt="" loading="lazy" width="320" height="180">
              <div style="padding: 0.7rem 0.85rem 0.85rem;">
                <strong class="upper" style="display: block; font-size: 0.95rem; line-height: 1.2;">Foundations of Language Models</strong>
                <div class="row" style="font-size: 0.72rem; margin-top: 0.25rem;">
                  <span class="upper green">nlp / c</span>
                  <span class="muted">2026</span>
                </div>
                <p style="margin: 0.25rem 0 0; font-size: 0.82rem; color: #333;">LLM internals through a simple implementation in C</p>
              </div>
            </a>
          </li>
          <li class="card" style="border-left-color: #7a3cff;">
            <a href="/projects/box-physics/">
              <img src="/projects/box-physics/images/octree.png" alt="" loading="lazy" width="320" height="180">
              <div style="padding: 0.7rem 0.85rem 0.85rem;">
                <strong class="upper" style="display: block; font-size: 0.95rem; line-height: 1.2;">Physics Simulation</strong>
                <div class="row" style="font-size: 0.72rem; margin-top: 0.25rem;">
                  <span class="upper purple">physics / c</span>
                  <span class="muted">2026</span>
                </div>
                <p style="margin: 0.25rem 0 0; font-size: 0.82rem; color: #333;">a basic physics engine from scratch in C</p>
              </div>
            </a>
          </li>
        </ul>
      </section>

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

  <script src="/assets/nca/twgl.min.js"></script>
  <script type="module" src="/assets/nca/render.js"></script>
</body>
</html>
