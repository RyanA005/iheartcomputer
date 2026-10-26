/** Render several request-timeline diagram types from data/trace-one-request.json */

const COLORS = {
  ink: "#171717",
  mute: "#5c5c5c",
  rule: "#e5e5e5",
  paper: "#ffffff",
  connect: "#495057",
  wait: "#ced4da",
  waitDark: "#868e96",
  body: "#12b886",
  edge: "#adb5bd",
  fn: "#0b57d0",
  board: "#4c6ef5",
  badges: "#e8590c",
  critical: "#171717",
  soft: "#e9ecef",
  laneClient: "#dee2e6",
  laneApp: "#d0ebff",
  laneSb: "#ffe8cc",
};

function esc(s) {
  return String(s)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

function shortPath(urlPath) {
  try {
    const u = new URL(urlPath, "https://x");
    const table = u.pathname.replace(/^\/rest\/v1\//, "");
    const q = u.searchParams;
    const bits = [];
    const date = q.get("date") || "";
    if (date.startsWith("eq.")) bits.push(date.slice(3));
    else if (date.startsWith("in.")) bits.push("multi-date");
    else if (date) bits.push(date);
    if (q.has("github_id")) bits.push("by github_id");
    if (q.get("limit")) bits.push(`limit=${q.get("limit")}`);
    const hint = bits.slice(0, 2).join(", ");
    return hint ? `${table} (${hint})` : table;
  } catch {
    return urlPath.slice(0, 48);
  }
}

function normalize(data) {
  const full = data.full;
  const srv = full.server.server;
  const client = full.derived.client_ms;
  const split = full.derived.split_ms;
  const boardMs = srv.phases.find((p) => p.label === "board").ms;
  const badgeMs = srv.phases.find((p) => p.label === "badges").ms;
  const fn = srv.function_ms;
  const ttfb = client.ttfb;
  const total = client.total;
  const fnStart = ttfb - fn;
  const floor = srv.supabase.min_wait_ms;

  const spans = srv.spans.map((s, i) => {
    const phase = s.started_at_ms < boardMs ? "board" : "badges";
    const start = fnStart + s.started_at_ms;
    return {
      i: i + 1,
      phase,
      name: s.name.replace("table:", "").replace("rpc:", "rpc "),
      method: s.method,
      path: s.url_path,
      label: shortPath(s.url_path),
      start,
      wait: s.wait_ms,
      body: s.body_ms,
      bytes: s.bytes,
      end: start + s.wait_ms + s.body_ms,
      aboveFloor: Math.max(0, s.wait_ms - floor),
      floorPart: Math.min(s.wait_ms, floor),
    };
  });

  return {
    measuredAt: full.server.measured_at,
    url: full.url,
    client,
    split,
    fn,
    ttfb,
    total,
    fnStart,
    boardMs,
    badgeMs,
    floor,
    cpu: split["5_app_cpu_between_awaits"],
    edge: split["2_edge_queue_and_return_path_estimate"],
    sbWall: split["4_supabase_http_wall"],
    spans,
    payload: full.server.payload,
  };
}

function axis(svgParts, { padL, labelW, chartW, y, total }) {
  const x0 = padL + labelW;
  svgParts.push(
    `<line x1="${x0}" y1="${y}" x2="${x0 + chartW}" y2="${y}" stroke="${COLORS.ink}" stroke-width="1"/>`
  );
  for (let t = 0; t <= total; t += 50) {
    const x = x0 + (t / total) * chartW;
    svgParts.push(
      `<line x1="${x}" y1="${y}" x2="${x}" y2="${y + 4}" stroke="${COLORS.mute}"/>`,
      `<text class="tick" x="${x}" y="${y + 14}" text-anchor="middle">${t}</text>`
    );
  }
  svgParts.push(
    `<text class="tick" x="${x0 + chartW + 8}" y="${y + 14}">ms</text>`
  );
}

function svgShell(width, height, title, body) {
  return `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${width} ${height}" width="100%" role="img">
<title>${esc(title)}</title>
<style>
  .lbl { font: 500 11px ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; fill: ${COLORS.ink}; }
  .meta { font: 400 10px ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; fill: ${COLORS.mute}; }
  .tick { font: 400 9px ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; fill: ${COLORS.mute}; }
  .on { font: 500 10px ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; fill: #fff; }
  .title { font: 600 13px ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; fill: ${COLORS.ink}; }
</style>
<rect width="100%" height="100%" fill="${COLORS.paper}"/>
${body}
</svg>`;
}

function xOf(ms, total, padL, labelW, chartW) {
  return padL + labelW + (ms / total) * chartW;
}
function wOf(ms, total, chartW) {
  return Math.max(1.2, (ms / total) * chartW);
}

function bar(x, y, w, h, fill, title) {
  return `<rect x="${x.toFixed(2)}" y="${y.toFixed(2)}" width="${Math.max(w, 1).toFixed(2)}" height="${h}" fill="${fill}"><title>${esc(title)}</title></rect>`;
}

/** 1. Chrome-style flat waterfall of outbound calls + client row */
function renderChrome(t) {
  const padL = 12;
  const labelW = 260;
  const chartW = 620;
  const padR = 100;
  const width = padL + labelW + chartW + padR;
  const rowH = 18;
  const gap = 2;
  const rows = [
    { kind: "client", label: "GET /api/trace?mode=full (client)" },
    ...t.spans.map((s) => ({
      kind: "span",
      label: `${s.method} ${s.label}`,
      s,
    })),
  ];
  const height = 40 + 20 + rows.length * (rowH + gap) + 28;
  const parts = [];
  parts.push(`<text class="title" x="${padL}" y="18">Chrome-style network waterfall</text>`);
  parts.push(`<text class="meta" x="${padL}" y="34">light = wait (TTFB of that call) · dark = content download · overlapping = parallel</text>`);
  axis(parts, { padL, labelW, chartW, y: 44, total: t.total });

  let y = 56;
  for (const row of rows) {
    parts.push(
      `<text class="lbl" x="${padL + labelW - 8}" y="${y + rowH - 5}" text-anchor="end">${esc(row.label)}</text>`
    );
    if (row.kind === "client") {
      const c = t.client.dns + t.client.tcp + t.client.tls;
      parts.push(
        bar(xOf(0, t.total, padL, labelW, chartW), y, wOf(Math.max(c, 0.3), t.total, chartW), rowH, COLORS.connect, `connect ${c.toFixed(2)} ms`),
        bar(xOf(c, t.total, padL, labelW, chartW), y, wOf(t.ttfb - c, t.total, chartW), rowH, COLORS.wait, `wait ${ (t.ttfb - c).toFixed(2)} ms`),
        bar(xOf(t.ttfb, t.total, padL, labelW, chartW), y, wOf(Math.max(t.client.download_body, 0.4), t.total, chartW), rowH, COLORS.body, `download ${t.client.download_body.toFixed(2)} ms`)
      );
      parts.push(`<text class="meta" x="${padL + labelW + chartW + 6}" y="${y + rowH - 5}">${t.ttfb.toFixed(0)} ms</text>`);
    } else {
      const s = row.s;
      parts.push(
        bar(xOf(s.start, t.total, padL, labelW, chartW), y, wOf(s.wait, t.total, chartW), rowH, COLORS.wait, `wait ${s.wait.toFixed(2)} ms`),
        bar(xOf(s.start + s.wait, t.total, padL, labelW, chartW), y, wOf(Math.max(s.body, 0.5), t.total, chartW), rowH, COLORS.waitDark, `body ${s.body.toFixed(2)} ms · ${s.bytes} B`)
      );
      parts.push(`<text class="meta" x="${padL + labelW + chartW + 6}" y="${y + rowH - 5}">${(s.wait + s.body).toFixed(0)}</text>`);
    }
    y += rowH + gap;
  }
  return svgShell(width, height, "Chrome-style waterfall", parts.join("\n"));
}

/** 2. Nested span tree waterfall */
function renderNested(t) {
  const padL = 12;
  const labelW = 280;
  const chartW = 600;
  const padR = 90;
  const width = padL + labelW + chartW + padR;
  const rowH = 17;
  const gap = 2;

  const tree = [
    { depth: 0, label: "client curl", start: 0, dur: t.total, color: COLORS.connect, tip: "client wall" },
    { depth: 1, label: "host overhead (est.)", start: t.ttfb - t.edge, dur: t.edge, color: COLORS.edge, tip: "TTFB - function" },
    { depth: 1, label: "function handler", start: t.fnStart, dur: t.fn, color: COLORS.fn, tip: `function_ms ${t.fn}` },
    { depth: 2, label: "getDailyLeaderboard", start: t.fnStart, dur: t.boardMs, color: COLORS.board, tip: "board phase" },
    ...t.spans
      .filter((s) => s.phase === "board")
      .map((s) => ({
        depth: 3,
        label: `${s.i.toString().padStart(2, "0")} ${s.name}`,
        start: s.start,
        dur: s.wait + s.body,
        color: COLORS.waitDark,
        tip: `${s.method} ${s.path}`,
        wait: s.wait,
        body: s.body,
      })),
    {
      depth: 2,
      label: "getLeaderboardBadgePinsByGithubIds",
      start: t.fnStart + t.boardMs,
      dur: t.badgeMs,
      color: COLORS.badges,
      tip: "badges phase",
    },
    ...t.spans
      .filter((s) => s.phase === "badges")
      .map((s) => ({
        depth: 3,
        label: `${s.i.toString().padStart(2, "0")} ${s.name}`,
        start: s.start,
        dur: s.wait + s.body,
        color: COLORS.waitDark,
        tip: `${s.method} ${s.path}`,
        wait: s.wait,
        body: s.body,
      })),
  ];

  const height = 40 + 20 + tree.length * (rowH + gap) + 24;
  const parts = [];
  parts.push(`<text class="title" x="${padL}" y="18">Nested span waterfall</text>`);
  parts.push(`<text class="meta" x="${padL}" y="34">indent = parent/child · like Jaeger / Grafana / Honeycomb</text>`);
  axis(parts, { padL, labelW, chartW, y: 44, total: t.total });

  let y = 56;
  for (const n of tree) {
    const prefix = n.depth === 0 ? "" : `${"| ".repeat(n.depth)}`;
    parts.push(
      `<text class="lbl" x="${padL + labelW - 8}" y="${y + rowH - 5}" text-anchor="end">${esc(prefix + n.label)}</text>`
    );
    parts.push(
      `<circle cx="${padL + 6 + n.depth * 10}" cy="${y + rowH / 2}" r="2.5" fill="${n.color}"/>`
    );

    if (n.wait != null) {
      parts.push(
        bar(xOf(n.start, t.total, padL, labelW, chartW), y, wOf(n.wait, t.total, chartW), rowH, COLORS.wait, n.tip),
        bar(xOf(n.start + n.wait, t.total, padL, labelW, chartW), y, wOf(Math.max(n.body, 0.5), t.total, chartW), rowH, n.color, `body ${n.body}`)
      );
    } else {
      parts.push(bar(xOf(n.start, t.total, padL, labelW, chartW), y, wOf(n.dur, t.total, chartW), rowH, n.color, n.tip));
    }
    parts.push(`<text class="meta" x="${padL + labelW + chartW + 6}" y="${y + rowH - 5}">${n.dur.toFixed(0)}</text>`);
    y += rowH + gap;
  }
  return svgShell(width, height, "Nested span waterfall", parts.join("\n"));
}

/** 3. Swimlanes by machine */
function renderSwim(t) {
  const padL = 12;
  const labelW = 120;
  const chartW = 720;
  const padR = 40;
  const width = padL + labelW + chartW + padR;
  const laneH = 88;
  const lanes = [
    { id: "client", label: "laptop\n(client)", fill: COLORS.laneClient },
    { id: "app", label: "app host\n(Node)", fill: COLORS.laneApp },
    { id: "sb", label: "Supabase\n(PostgREST)", fill: COLORS.laneSb },
  ];
  const height = 44 + lanes.length * laneH + 36;
  const parts = [];
  parts.push(`<text class="title" x="${padL}" y="18">Machine swimlanes</text>`);
  parts.push(`<text class="meta" x="${padL}" y="34">one lane per machine · bars are work that machine owns</text>`);
  axis(parts, { padL, labelW, chartW, y: 44, total: t.total });

  lanes.forEach((lane, i) => {
    const y = 56 + i * laneH;
    parts.push(
      `<rect x="${padL + labelW}" y="${y}" width="${chartW}" height="${laneH - 8}" fill="${lane.fill}" opacity="0.45"/>`,
      `<text class="lbl" x="${padL + labelW - 8}" y="${y + 28}" text-anchor="end">${esc(lane.label.split("\n")[0])}</text>`,
      `<text class="meta" x="${padL + labelW - 8}" y="${y + 42}" text-anchor="end">${esc(lane.label.split("\n")[1] || "")}</text>`
    );

    if (lane.id === "client") {
      const c = t.client.dns + t.client.tcp;
      parts.push(
        bar(xOf(0, t.total, padL, labelW, chartW), y + 18, wOf(Math.max(c, 0.4), t.total, chartW), 16, COLORS.connect, "connect"),
        bar(xOf(c, t.total, padL, labelW, chartW), y + 18, wOf(t.ttfb - c, t.total, chartW), 16, COLORS.wait, "waiting for first byte"),
        bar(xOf(t.ttfb, t.total, padL, labelW, chartW), y + 18, wOf(Math.max(t.client.download_body, 0.5), t.total, chartW), 16, COLORS.body, "download")
      );
      parts.push(`<text class="meta" x="${xOf(c + 8, t.total, padL, labelW, chartW)}" y="${y + 50}">waiting on app...</text>`);
    }
    if (lane.id === "app") {
      parts.push(
        bar(xOf(t.ttfb - t.edge, t.total, padL, labelW, chartW), y + 10, wOf(t.edge, t.total, chartW), 14, COLORS.edge, `host overhead est ${t.edge}`),
        bar(xOf(t.fnStart, t.total, padL, labelW, chartW), y + 28, wOf(t.boardMs, t.total, chartW), 18, COLORS.board, `board ${t.boardMs}`),
        bar(xOf(t.fnStart + t.boardMs, t.total, padL, labelW, chartW), y + 28, wOf(t.badgeMs, t.total, chartW), 18, COLORS.badges, `badges ${t.badgeMs}`)
      );
      parts.push(
        `<text class="on" x="${xOf(t.fnStart, t.total, padL, labelW, chartW) + 6}" y="${y + 41}">board</text>`,
        `<text class="on" x="${xOf(t.fnStart + t.boardMs, t.total, padL, labelW, chartW) + 6}" y="${y + 41}">badges</text>`,
        `<text class="meta" x="${padL + labelW + 8}" y="${y + 68}">app CPU between awaits ≈ ${t.cpu.toFixed(0)} ms (not drawn as contiguous)</text>`
      );
    }
    if (lane.id === "sb") {
      // pack spans into sub-rows by overlap
      const subRows = [];
      for (const s of t.spans) {
        let placed = false;
        for (const row of subRows) {
          if (row.every((o) => s.end <= o.start || s.start >= o.end)) {
            row.push(s);
            placed = true;
            break;
          }
        }
        if (!placed) subRows.push([s]);
      }
      const subH = Math.min(14, (laneH - 20) / Math.max(subRows.length, 1));
      subRows.forEach((row, ri) => {
        const yy = y + 8 + ri * (subH + 2);
        for (const s of row) {
          parts.push(
            bar(xOf(s.start, t.total, padL, labelW, chartW), yy, wOf(s.wait, t.total, chartW), subH, COLORS.waitDark, `${s.label} wait ${s.wait}`),
            bar(xOf(s.start + s.wait, t.total, padL, labelW, chartW), yy, wOf(Math.max(s.body, 0.4), t.total, chartW), subH, COLORS.body, `body ${s.body}`)
          );
        }
      });
    }
  });
  return svgShell(width, height, "Machine swimlanes", parts.join("\n"));
}

/** 4. Flame / icicle chart */
function renderFlame(t) {
  const padL = 12;
  const padR = 12;
  const chartW = 900;
  const width = padL + chartW + padR;
  const rowH = 28;
  const levels = [
    [{ label: `client ${t.total.toFixed(0)} ms`, start: 0, dur: t.total, color: COLORS.connect }],
    [
      { label: `connect`, start: 0, dur: Math.max(t.client.dns + t.client.tcp, 0.5), color: COLORS.connect },
      { label: `wait to TTFB`, start: t.client.dns + t.client.tcp, dur: t.ttfb - (t.client.dns + t.client.tcp), color: COLORS.wait },
      { label: `dl`, start: t.ttfb, dur: Math.max(t.client.download_body, 0.5), color: COLORS.body },
    ],
    [
      { label: `overhead est ${t.edge.toFixed(0)}`, start: t.ttfb - t.edge, dur: t.edge, color: COLORS.edge },
      { label: `function ${t.fn.toFixed(0)}`, start: t.fnStart, dur: t.fn, color: COLORS.fn },
    ],
    [
      { label: `board ${t.boardMs.toFixed(0)}`, start: t.fnStart, dur: t.boardMs, color: COLORS.board },
      { label: `badges ${t.badgeMs.toFixed(0)}`, start: t.fnStart + t.boardMs, dur: t.badgeMs, color: COLORS.badges },
    ],
    t.spans.map((s) => ({
      label: s.name.slice(0, 18),
      start: s.start,
      dur: s.wait + s.body,
      color: s.phase === "board" ? COLORS.board : COLORS.badges,
      tip: `${s.method} ${s.path}`,
    })),
  ];
  const height = 36 + levels.length * (rowH + 4) + 20;
  const parts = [];
  parts.push(`<text class="title" x="${padL}" y="18">Flame chart (icicle)</text>`);
  parts.push(`<text class="meta" x="${padL}" y="32">depth = nesting · width = duration · inspired by APM flame views</text>`);

  levels.forEach((level, li) => {
    const y = 44 + li * (rowH + 4);
    for (const n of level) {
      const x = padL + (n.start / t.total) * chartW;
      const w = Math.max(2, (n.dur / t.total) * chartW);
      parts.push(bar(x, y, w, rowH, n.color, n.tip || n.label));
      if (w > 48) {
        parts.push(`<text class="on" x="${x + 4}" y="${y + rowH - 9}">${esc(n.label)}</text>`);
      }
    }
  });
  return svgShell(width, height, "Flame chart", parts.join("\n"));
}

/** 5. Composition stacked bar + table */
function renderStack(t) {
  const buckets = [
    { label: "client connect (DNS+TCP)", ms: t.split["1_computer_to_vercel_tcp_tls"], color: COLORS.connect, note: "measured" },
    { label: "host overhead (est.)", ms: t.edge, color: COLORS.edge, note: "TTFB - function" },
    { label: "app CPU between awaits", ms: t.cpu, color: COLORS.fn, note: "function - supabase wall" },
    { label: "Supabase HTTP wall", ms: t.sbWall, color: COLORS.badges, note: "merged busy of 11 calls" },
    { label: "client download", ms: t.client.download_body, color: COLORS.body, note: "measured" },
  ];
  const sum = buckets.reduce((a, b) => a + b.ms, 0);
  const padL = 12;
  const chartW = 880;
  const width = padL + chartW + 12;
  const height = 160;
  const parts = [];
  parts.push(`<text class="title" x="${padL}" y="18">Exclusive-ish composition (not a timeline)</text>`);
  parts.push(`<text class="meta" x="${padL}" y="34">sums to ~TTFB accounting · parallel Supabase collapsed into wall · order is accounting not chronology</text>`);

  let x = padL;
  const y = 52;
  const h = 36;
  for (const b of buckets) {
    const w = (b.ms / sum) * chartW;
    parts.push(bar(x, y, w, h, b.color, `${b.label}: ${b.ms.toFixed(2)} ms`));
    if (w > 56) {
      parts.push(`<text class="on" x="${x + 4}" y="${y + 22}">${b.ms.toFixed(0)}</text>`);
    }
    x += w;
  }

  // legend
  let lx = padL;
  const ly = 108;
  for (const b of buckets) {
    parts.push(
      `<rect x="${lx}" y="${ly}" width="10" height="10" fill="${b.color}"/>`,
      `<text class="meta" x="${lx + 14}" y="${ly + 9}">${esc(b.label)} (${b.ms.toFixed(1)})</text>`
    );
    lx += 14 + b.label.length * 6.2 + 28;
    if (lx > padL + chartW - 80) {
      lx = padL;
    }
  }

  const svg = svgShell(width, height, "Composition", parts.join("\n"));
  const table = `<table class="stack-table"><thead><tr><th>bucket</th><th>ms</th><th>%</th><th>notes</th></tr></thead><tbody>${buckets
    .map(
      (b) =>
        `<tr><td>${esc(b.label)}</td><td>${b.ms.toFixed(2)}</td><td>${((100 * b.ms) / sum).toFixed(1)}%</td><td>${esc(b.note)}</td></tr>`
    )
    .join("")}<tr><td><strong>sum</strong></td><td>${sum.toFixed(2)}</td><td>100%</td><td>compare to TTFB ${t.ttfb.toFixed(2)}</td></tr></tbody></table>`;
  return { svg, html: svg + table };
}

/** 6. Critical path Gantt */
function criticalPath(t) {
  // serial chain: client connect -> (then function starts) -> longest dependency chain through phases
  // Simple critical path through board then badges wall, picking the span that extends furthest in each serial segment.
  const path = [
    { label: "client connect", start: 0, dur: t.client.dns + t.client.tcp, color: COLORS.connect },
  ];
  // After connect, client waits; critical server work is function
  path.push({ label: "function (server)", start: t.fnStart, dur: t.fn, color: COLORS.fn });

  // Within board: span with max end among board, then within badges
  const boardSpans = t.spans.filter((s) => s.phase === "board");
  const badgeSpans = t.spans.filter((s) => s.phase === "badges");
  const critBoard = boardSpans.reduce((a, b) => (a.end >= b.end ? a : b));
  const critBadge = badgeSpans.reduce((a, b) => (a.end >= b.end ? a : b));
  path.push({
    label: `board critical: ${critBoard.name}`,
    start: critBoard.start,
    dur: critBoard.wait + critBoard.body,
    color: COLORS.board,
  });
  path.push({
    label: `badges critical: ${critBadge.name}`,
    start: critBadge.start,
    dur: critBadge.wait + critBadge.body,
    color: COLORS.badges,
  });
  path.push({
    label: "host overhead + return (est.)",
    start: t.ttfb - t.edge,
    dur: t.edge,
    color: COLORS.edge,
  });
  return { path, critIds: new Set([critBoard.i, critBadge.i]) };
}

function renderGantt(t) {
  const { path, critIds } = criticalPath(t);
  const padL = 12;
  const labelW = 260;
  const chartW = 620;
  const padR = 90;
  const width = padL + labelW + chartW + padR;
  const rowH = 16;
  const gap = 2;
  const rows = [
    { label: "GET /api/trace (client)", start: 0, dur: t.total, mute: true, color: COLORS.soft },
    { label: "function", start: t.fnStart, dur: t.fn, mute: true, color: COLORS.soft },
    ...t.spans.map((s) => ({
      label: `${s.i.toString().padStart(2, "0")} ${s.phase} · ${s.name}`,
      start: s.start,
      dur: s.wait + s.body,
      critical: critIds.has(s.i),
      color: critIds.has(s.i) ? COLORS.critical : COLORS.soft,
      mute: !critIds.has(s.i),
    })),
  ];
  const height = 56 + (rows.length + path.length + 2) * (rowH + gap) + 40;
  const parts = [];
  parts.push(`<text class="title" x="${padL}" y="18">Critical-path Gantt</text>`);
  parts.push(
    `<text class="meta" x="${padL}" y="34">mute = all spans · ink = spans that extend the serial board then badges chain · plus estimated host overhead</text>`
  );
  axis(parts, { padL, labelW, chartW, y: 44, total: t.total });

  let y = 56;
  parts.push(`<text class="meta" x="${padL}" y="${y + 10}">all spans</text>`);
  y += 16;
  for (const r of rows) {
    parts.push(
      `<text class="lbl" x="${padL + labelW - 8}" y="${y + rowH - 4}" text-anchor="end">${esc(r.label)}</text>`,
      bar(
        xOf(r.start, t.total, padL, labelW, chartW),
        y,
        wOf(r.dur, t.total, chartW),
        rowH,
        r.color,
        r.label
      )
    );
    y += rowH + gap;
  }
  y += 10;
  parts.push(`<text class="meta" x="${padL}" y="${y + 10}">highlighted critical pieces</text>`);
  y += 16;
  for (const p of path) {
    parts.push(
      `<text class="lbl" x="${padL + labelW - 8}" y="${y + rowH - 4}" text-anchor="end">${esc(p.label)}</text>`,
      bar(xOf(p.start, t.total, padL, labelW, chartW), y, wOf(p.dur, t.total, chartW), rowH, p.color, p.label),
      `<text class="meta" x="${padL + labelW + chartW + 6}" y="${y + rowH - 4}">${p.dur.toFixed(0)}</text>`
    );
    y += rowH + gap;
  }
  return svgShell(width, y + 16, "Critical-path Gantt", parts.join("\n"));
}

const RENDERERS = {
  chrome: (t, el) => {
    el.innerHTML = renderChrome(t);
  },
  nested: (t, el) => {
    el.innerHTML = renderNested(t);
  },
  swim: (t, el) => {
    el.innerHTML = renderSwim(t);
  },
  flame: (t, el) => {
    el.innerHTML = renderFlame(t);
  },
  stack: (t, el) => {
    const { html } = renderStack(t);
    el.innerHTML = html;
  },
  gantt: (t, el) => {
    el.innerHTML = renderGantt(t);
  },
};

async function main() {
  const frames = [...document.querySelectorAll("[data-viz]")];
  let data;
  try {
    const res = await fetch("data/trace-one-request.json");
    if (!res.ok) throw new Error(`${res.status} ${res.statusText}`);
    data = await res.json();
  } catch (err) {
    for (const el of frames) {
      el.innerHTML = `<p class="err">could not load data/trace-one-request.json: ${esc(err.message)}. serve this folder over HTTP (not file://).</p>`;
    }
    return;
  }
  const t = normalize(data);
  for (const el of frames) {
    const kind = el.getAttribute("data-viz");
    try {
      RENDERERS[kind](t, el);
    } catch (err) {
      el.innerHTML = `<p class="err">${esc(kind)}: ${esc(err.message)}</p>`;
      console.error(kind, err);
    }
  }
}

main();
