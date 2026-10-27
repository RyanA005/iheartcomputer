/**
 * Fix 3 demos: same delay, escalating perceived-latency UI (mini whoscooking.live).
 * Modes: blank → blank+spinner → skeleton → skeleton+spinner → stream → progressive
 */
(() => {
  const DELAY_MS = 900;
  const ROWS = [
    { rank: 1, delta: 2, user: "alice", repo: "alice/oven", net: 4821, add: 5102, del: 281, commits: 18, badges: 2 },
    { rank: 2, delta: 0, user: "bob", repo: "bob/skillet", net: 3102, add: 3400, del: 298, commits: 12, badges: 1 },
    { rank: 3, delta: -1, user: "cara", repo: "cara/mise", net: 2874, add: 3010, del: 136, commits: 9, badges: 2 },
    { rank: 4, delta: 3, user: "dev", repo: "dev/pan", net: 1901, add: 2204, del: 303, commits: 14, badges: 0 },
    { rank: 5, delta: -2, user: "erin", repo: "erin/stock", net: 1540, add: 1688, del: 148, commits: 7, badges: 1 },
  ];

  const root = document.querySelector("[data-feel-demo]");
  if (!root) return;

  const stage = root.querySelector("[data-feel-stage]");
  const modeButtons = [...root.querySelectorAll("[data-mode]")];

  let mode = "blank";
  let runId = 0;
  let timers = [];

  function clearTimers() {
    for (const t of timers) clearTimeout(t);
    timers = [];
  }

  function later(ms, fn) {
    timers.push(setTimeout(fn, ms));
  }

  function setActiveMode(next) {
    mode = next;
    for (const btn of modeButtons) {
      const on = btn.getAttribute("data-mode") === mode;
      btn.classList.toggle("is-active", on);
      btn.setAttribute("aria-pressed", on ? "true" : "false");
    }
  }

  function deltaHtml(delta) {
    if (delta > 0) {
      return `<span class="wc-delta wc-delta-up" title="up ${delta}">▲${delta}</span>`;
    }
    if (delta < 0) {
      return `<span class="wc-delta wc-delta-down" title="down ${Math.abs(delta)}">▼${Math.abs(delta)}</span>`;
    }
    return `<span class="wc-delta wc-delta-flat" title="no change">–</span>`;
  }

  function avatarHtml(user) {
    return `<span class="wc-avatar" aria-hidden="true">${user.slice(0, 1).toUpperCase()}</span>`;
  }

  function badgePinsHtml(count, visible) {
    if (!count) {
      return `<span class="wc-badge-pins is-empty" aria-hidden="true"></span>`;
    }
    const pins = Array.from({ length: count }, () => `<span class="wc-badge-pin" aria-hidden="true"></span>`).join("");
    return `<span class="wc-badge-pins${visible ? " is-in" : " is-empty"}" aria-hidden="true">${visible ? pins : ""}</span>`;
  }

  function rowHtml(row) {
    return `<article class="wc-row">
      <div class="wc-left">
        ${deltaHtml(row.delta)}
        <span class="wc-rank">#${String(row.rank).padStart(2, "0")}</span>
        ${avatarHtml(row.user)}
        <div class="wc-who">
          <div class="wc-username-row">
            <div class="wc-username">${row.user}</div>
            ${badgePinsHtml(row.badges, true)}
          </div>
          <div class="wc-meta">top: <span class="wc-repo">${row.repo}</span></div>
        </div>
      </div>
      <div class="wc-right">
        <div class="wc-net">${row.net.toLocaleString()} net</div>
        <div class="wc-breakdown">+${row.add.toLocaleString()} / -${row.del.toLocaleString()} / ${row.commits} commits</div>
      </div>
    </article>`;
  }

  /** Stats-first row: numbers visible; username+repo held behind skeleton overlays. */
  function progressiveRowHtml(row, index) {
    return `<article class="wc-row wc-row-stream" style="animation-delay:${index * 55}ms">
      <div class="wc-left">
        ${deltaHtml(row.delta)}
        <span class="wc-rank">#${String(row.rank).padStart(2, "0")}</span>
        ${avatarHtml(row.user)}
        <div class="wc-who">
          <div class="wc-username-row">
            <span class="wc-detail-slot">
              <span class="sk sk-name" data-feel-sk aria-hidden="true"></span>
              <div class="wc-username" data-feel-detail style="visibility:hidden">${row.user}</div>
            </span>
            <span class="wc-badge-pins is-empty" data-feel-badges aria-hidden="true"></span>
          </div>
          <div class="wc-meta">
            <span class="wc-detail-slot">
              <span class="sk sk-meta" data-feel-sk aria-hidden="true"></span>
              <span data-feel-detail style="visibility:hidden">top: <span class="wc-repo">${row.repo}</span></span>
            </span>
          </div>
        </div>
      </div>
      <div class="wc-right">
        <div class="wc-net">${row.net.toLocaleString()} net</div>
        <div class="wc-breakdown">+${row.add.toLocaleString()} / -${row.del.toLocaleString()} / ${row.commits} commits</div>
      </div>
    </article>`;
  }

  function skeletonRowsHtml() {
    return ROWS.map(
      () => `<article class="wc-row wc-skel" aria-hidden="true">
        <div class="wc-left">
          <span class="sk sk-sm"></span>
          <span class="sk sk-rank"></span>
          <span class="sk sk-av"></span>
          <div class="wc-who">
            <span class="sk sk-name"></span>
            <span class="sk sk-meta"></span>
          </div>
        </div>
        <div class="wc-right">
          <span class="sk sk-net"></span>
          <span class="sk sk-break"></span>
        </div>
      </article>`
    ).join("");
  }

  function boardShell(rowsInner, { cardClass = "", headRight = "Output" } = {}) {
    return `<div class="wc-stage">
      <header class="wc-header">
        <div>
          <h3 class="wc-title">who's cooking leaderboard</h3>
          <p class="wc-sub">daily ranking by net lines.</p>
        </div>
        <span class="wc-pill">INSTALL THE APP</span>
      </header>
      <div class="wc-summary">
        <div class="wc-summary-top">
          <strong>Today</strong>
          <span>Mon, Oct 26 · ET</span>
        </div>
        <div class="wc-metrics">
          <div><span>Net lines</span><strong>14,238</strong></div>
          <div><span>Cooks</span><strong>5</strong></div>
          <div><span>Commits</span><strong>60</strong></div>
        </div>
      </div>
      <div class="wc-card${cardClass ? ` ${cardClass}` : ""}">
        <div class="wc-table-head">
          <span>1d · Rank / Dev</span>
          <span class="wc-head-right">${headRight}</span>
        </div>
        <div data-feel-rows>${rowsInner}</div>
      </div>
    </div>`;
  }

  function inlineLoading() {
    return `<span class="wc-inline-load" role="status">
      <span class="wc-spinner" aria-hidden="true"></span>loading
    </span>`;
  }

  function spinnerChip(label = "loading board…") {
    return `<div class="wc-spinner-chip" role="status">
      <span class="wc-spinner" aria-hidden="true"></span>
      <span>${label}</span>
    </div>`;
  }

  function blankOverlay(withSpinner) {
    return `<div class="wc-overlay${withSpinner ? "" : " wc-blank"}" role="status">
      ${withSpinner ? spinnerChip() : `<span class="wc-blank-mark">· · ·</span>`}
    </div>`;
  }

  function showFinalBoard({ fade = false } = {}) {
    stage.innerHTML = boardShell(ROWS.map(rowHtml).join(""), {
      cardClass: fade ? "is-fading" : "",
    });
    if (fade) {
      void stage.offsetWidth;
      stage.querySelector(".wc-card")?.classList.add("is-in");
    }
  }

  function revealProgressiveDetails(id) {
    if (id !== runId) return;
    for (const sk of stage.querySelectorAll("[data-feel-sk]")) sk.remove();
    for (const el of stage.querySelectorAll("[data-feel-detail]")) {
      el.style.visibility = "visible";
      el.classList.add("is-fade-in");
    }
    const head = stage.querySelector(".wc-head-right");
    if (head) head.textContent = "Output";
  }

  function revealProgressiveBadges(id) {
    if (id !== runId) return;
    stage.querySelectorAll("[data-feel-badges]").forEach((el, i) => {
      const count = ROWS[i]?.badges ?? 0;
      if (!count) return;
      el.className = "wc-badge-pins is-in";
      el.innerHTML = Array.from(
        { length: count },
        () => `<span class="wc-badge-pin" aria-hidden="true"></span>`
      ).join("");
    });
  }

  function run() {
    clearTimers();
    const id = ++runId;
    if (mode === "blank") {
      stage.innerHTML = `<div class="wc-stage wc-stage-empty">${blankOverlay(false)}</div>`;
      later(DELAY_MS, () => {
        if (id !== runId) return;
        showFinalBoard();
      });
      return;
    }

    if (mode === "blank-spinner") {
      stage.innerHTML = `<div class="wc-stage wc-stage-empty">${blankOverlay(true)}</div>`;
      later(DELAY_MS, () => {
        if (id !== runId) return;
        showFinalBoard({ fade: true });
      });
      return;
    }

    if (mode === "skeleton") {
      stage.innerHTML = boardShell(skeletonRowsHtml());
      later(DELAY_MS, () => {
        if (id !== runId) return;
        showFinalBoard({ fade: true });
      });
      return;
    }

    if (mode === "skeleton-spinner") {
      stage.innerHTML = boardShell(skeletonRowsHtml(), { headRight: inlineLoading() });
      later(DELAY_MS, () => {
        if (id !== runId) return;
        showFinalBoard({ fade: true });
      });
      return;
    }

    if (mode === "progressive") {
      // Stats-first paint (rank/avatar/net), username+repo skeleton until "enrich", then badges.
      stage.innerHTML = boardShell(ROWS.map((row, i) => progressiveRowHtml(row, i)).join(""), {
        headRight: inlineLoading(),
      });
      const enrichAt = Math.round(DELAY_MS * 0.45);
      const badgesAt = Math.round(DELAY_MS * 0.78);
      later(enrichAt, () => revealProgressiveDetails(id));
      later(badgesAt, () => revealProgressiveBadges(id));
      return;
    }

    // stream: skeleton chrome immediately; rows replace within the same delay budget
    stage.innerHTML = boardShell(skeletonRowsHtml());
    const slot = stage.querySelector("[data-feel-rows]");
    const firstAt = Math.round(DELAY_MS / 3);
    const step = Math.round((DELAY_MS - firstAt) / Math.max(ROWS.length - 1, 1));

    later(firstAt, () => {
      if (id !== runId) return;
      slot.innerHTML = "";
      ROWS.forEach((row, i) => {
        later(i * step, () => {
          if (id !== runId) return;
          const wrap = document.createElement("div");
          wrap.innerHTML = rowHtml(row);
          const node = wrap.firstElementChild;
          node.classList.add("is-arriving");
          slot.appendChild(node);
          void node.offsetWidth;
          node.classList.add("is-in");
        });
      });
    });
  }

  for (const btn of modeButtons) {
    btn.addEventListener("click", () => {
      setActiveMode(btn.getAttribute("data-mode"));
      run();
    });
  }

  setActiveMode("blank");
  run();
})();
