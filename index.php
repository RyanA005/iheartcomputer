<?php

require __DIR__ . '/assets/_layout.php';

new_render_header('IHEARTCOMPUTER');
?>

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
            <a class="upper blue" href="/about/" style="font-size: 0.85rem;">about us →</a>
          </div>
          <p class="nca-aside" style="margin: 1.1rem 0 0;">
            <a href="https://ryanhub.org/blogs/neural-cellular-automata.html" class="muted" style="font-size: 0.78rem; text-decoration: none;" target="_blank" rel="noopener noreferrer">← read more about neural cellular automata</a>
          </p>
        </div>
      </section>

      <a class="panel band" href="https://discord.gg/JpRw84Ybwg" style="padding: 1.1rem 1.1rem 1.1rem 1.25rem; margin-bottom: 2rem;">
        <div>
          <span class="upper red" style="display: block; font-size: 0.75rem; margin-bottom: 0.35rem;">next meeting</span>
          <strong class="upper" style="display: block; font-size: 1.35rem; line-height: 1.15;">Topic to be announced!</strong>
        </div>
        <ul class="upper" style="list-style: none; margin: 0; padding: 0; font-size: 0.85rem;">
          <li style="margin: 0.15rem 0;">date/time: 9/30</li>
          <li style="margin: 0.15rem 0;">room: TBA</li>
        </ul>
        <p style="margin: 0; font-size: 0.9rem; color: #333;">
          Let us know if you have an idea or there is something you would like to see us explore.
        </p>
        <p class="upper">
          SEE YOU THERE...
        </p>
      </a>

      <section>
        <div class="row" style="align-items: baseline; margin-bottom: 1rem;">
          <h2 class="h2">some of our favorite projects</h2>
          <a class="upper blue" href="/projects/" style="font-size: 0.85rem;">see all projects →</a>
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

  <script src="/assets/nca/twgl.min.js"></script>
  <script type="module" src="/assets/nca/render.js"></script>

<?php new_render_footer(); ?>
