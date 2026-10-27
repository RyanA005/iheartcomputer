/** Shared cascade SVG rendering from a trace-one-request style JSON packet. */

export const COLORS = {
  ink: "#171717",
  mute: "#5c5c5c",
  paper: "#ffffff",
  soft: "#e9ecef",
  waiting: "#adb5bd",
  waitingStroke: "#495057",
  working: "#0b57d0",
  codeWait: "#e8590c",
};

export function esc(s) {
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

export function normalize(data) {
  const full = data.full;
  const srv = full.server.server;
  const client = full.derived.client_ms;
  const split = full.derived.split_ms;
  const boardMs =
    srv.phases.find((p) => p.label === "board" || p.label === "cached_board")?.ms ?? 0;
  const badgeMs = srv.phases.find((p) => p.label === "badges")?.ms ?? 0;
  const fn = srv.function_ms;
  const ttfb = client.ttfb;
  const total = client.total;
  const fnStart = ttfb - fn;
  const floor = srv.supabase?.min_wait_ms ?? 0;

  const spans = (srv.spans ?? []).map((s, i) => {
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
      started_at_ms: s.started_at_ms,
    };
  });

  const raw = full.client;
  const connectDone = raw.time_appconnect > 0 ? raw.time_appconnect : raw.time_connect;
  const browserWorkStart = Math.max(0, raw.time_pretransfer - connectDone);
  const browserWorkEnd = client.download_body;

  return {
    shape: full.server.shape ?? data.shape ?? null,
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
    cpu: split["5_app_cpu_between_awaits"] ?? fn,
    edge: split["2_edge_queue_and_return_path_estimate"] ?? 0,
    sbWall: split["4_supabase_http_wall"] ?? 0,
    spans,
    callCount: srv.supabase?.calls ?? 0,
    payload: full.server.payload,
    browserWorkStart,
    browserWorkEnd,
  };
}

function waitSeg(x, y, w, h, tip) {
  return `<rect x="${x.toFixed(2)}" y="${y.toFixed(2)}" width="${Math.max(w, 1).toFixed(2)}" height="${h}" fill="url(#hatch-wait)" stroke="${COLORS.waitingStroke}" stroke-width="0.75"><title>${esc(tip)}</title></rect>`;
}

function codeWaitSeg(x, y, w, h, tip) {
  return `<rect x="${x.toFixed(2)}" y="${y.toFixed(2)}" width="${Math.max(w, 1).toFixed(2)}" height="${h}" fill="url(#hatch-code)" stroke="${COLORS.codeWait}" stroke-width="0.75"><title>${esc(tip)}</title></rect>`;
}

function workSeg(x, y, w, h, tip, fill = COLORS.working) {
  return `<rect x="${x.toFixed(2)}" y="${y.toFixed(2)}" width="${Math.max(w, 1).toFixed(2)}" height="${h}" fill="${fill}"><title>${esc(tip)}</title></rect>`;
}

/**
 * Render one cascade. Returns { parts, height, width }.
 * scaleMs: ms represented by full chartW (for before/after alignment).
 */
export function renderCascadeParts(t, opts = {}) {
  const padL = opts.padL ?? 12;
  const labelW = opts.labelW ?? 148;
  const chartW = opts.chartW ?? 760;
  const padR = opts.padR ?? 56;
  const width = padL + labelW + chartW + padR;
  const barH = 34;
  const callH = 16;
  const callGap = 2;
  const maxCalls = opts.maxCalls ?? 10;
  const scaleMs = opts.scaleMs ?? t.total;
  const y0 = opts.y0 ?? 0;
  const title = opts.title ?? null;
  const showLegend = opts.showLegend !== false;
  const parts = [];

  const toHost = t.split["1_computer_to_vercel_tcp_tls"];
  const appWork = t.fn;
  const pingBackEst = t.edge;
  const jsWork = t.cpu;
  const sbWall = t.sbWall;
  const floor = t.floor;
  const half = floor / 2;

  const callRows = [...t.spans].sort((a, b) => a.start - b.start || a.i - b.i);
  const allCalls = callRows.map((s) => {
    let to = half;
    let back = half;
    let work = Math.max(0, s.wait - floor);
    if (s.wait < floor) {
      to = s.wait / 2;
      back = s.wait / 2;
      work = 0;
    }
    return { s, to, work, back, total: to + work + back };
  });
  const calls = allCalls.slice(0, maxCalls);
  const callSum = calls.reduce((a, c) => a + c.total, 0) || 1;

  const browserParts = [
    { id: "b0", short: "work", ms: t.browserWorkStart, kind: "work" },
    { id: "to", short: "to", ms: toHost, kind: "net" },
    { id: "app", short: "app", ms: appWork, kind: "code" },
    { id: "back", short: "back", ms: pingBackEst, kind: "net" },
    { id: "b1", short: "work", ms: t.browserWorkEnd, kind: "work" },
  ];
  const processLabel = opts.processLabel ?? "vercel function";
  const dbWallLabel = opts.dbWallLabel ?? "supabase";
  const callLabel = opts.callLabel ?? "supabase call";
  // Own-hardware / embedded DB: treat function time as local work (blue), not
  // remote code-wait, and skip the broken "zoom a 0.3ms parent to 140px" path.
  const flatLocal = opts.flatLocal === true;
  const jsMs = flatLocal
    ? Math.max(appWork, jsWork + sbWall, 0.01)
    : jsWork || (sbWall > 0 ? 0 : appWork);
  const appParts = flatLocal
    ? [{ id: "js", short: "js+sqlite", ms: jsMs, kind: "work" }]
    : [
        { id: "js", short: "js", ms: jsMs, kind: "work" },
        { id: "sb", short: dbWallLabel, ms: sbWall, kind: "code" },
      ];
  if (flatLocal) {
    // Browser "app" should read as work, not orange code-wait.
    const appPart = browserParts.find((p) => p.id === "app");
    if (appPart) appPart.kind = "work";
  }

  const MIN_PX = 6;
  const pxPerMs = chartW / scaleMs;

  function layoutAbsolute(segments, x0) {
    const usable = segments.filter((s) => s.ms > 0);
    let x = x0;
    return usable.map((s) => {
      const w = Math.max(MIN_PX, s.ms * pxPerMs);
      const out = { ...s, x, w };
      x += w;
      return out;
    });
  }

  function layoutInBand(segments, x0, bandW) {
    const usable = segments.filter((s) => s.ms > 0);
    const sum = usable.reduce((a, s) => a + s.ms, 0) || 1;
    let items = usable.map((s) => ({ ...s, w: (s.ms / sum) * bandW, expanded: false }));
    for (const it of items) {
      if (it.w < MIN_PX) {
        it.w = MIN_PX;
        it.expanded = true;
      }
    }
    let totalW = items.reduce((a, s) => a + s.w, 0);
    if (totalW > bandW) {
      const big = items.filter((s) => !s.expanded);
      const bigSum = big.reduce((a, s) => a + s.w, 0);
      const overflow = totalW - bandW;
      if (bigSum > overflow) for (const s of big) s.w -= (s.w / bigSum) * overflow;
    } else if (totalW < bandW) {
      const big = items.filter((s) => !s.expanded);
      const bigSum = big.reduce((a, s) => a + s.w, 0) || 1;
      for (const s of big) s.w += (s.w / bigSum) * (bandW - totalW);
    }
    let x = x0;
    return items.map((it) => {
      const out = { ...it, x, w: Math.max(1, it.w) };
      x += out.w;
      return out;
    });
  }

  function drawSeg(it, y, h) {
    const tip = `${it.short} ${it.ms.toFixed(2)} ms`;
    if (it.kind === "net" || it.kind === "wait") parts.push(waitSeg(it.x, y, it.w, h, tip));
    else if (it.kind === "code") parts.push(codeWaitSeg(it.x, y, it.w, h, tip));
    else parts.push(workSeg(it.x, y, it.w, h, tip, COLORS.working));
  }

  function label(text, y, h) {
    parts.push(
      `<text class="lbl" x="${padL + labelW - 8}" y="${y + h - 10}" text-anchor="end">${esc(text)}</text>`
    );
  }

  function guides(x0, x1, yA, yB) {
    parts.push(
      `<rect x="${x0}" y="${yA}" width="${x1 - x0}" height="${yB - yA}" fill="${COLORS.working}" opacity="0.05"/>`,
      `<line x1="${x0}" y1="${yA}" x2="${x0}" y2="${yB}" stroke="${COLORS.ink}" stroke-width="1" stroke-dasharray="3 3"/>`,
      `<line x1="${x1}" y1="${yA}" x2="${x1}" y2="${yB}" stroke="${COLORS.ink}" stroke-width="1" stroke-dasharray="3 3"/>`
    );
  }

  function brace(x0, x1, yTop, yBot) {
    const mid = (yTop + yBot) / 2;
    parts.push(
      `<path d="M ${x0} ${yTop} V ${mid} H ${x1} V ${yTop}" fill="none" stroke="${COLORS.mute}" stroke-width="1.2"/>`,
      `<path d="M ${(x0 + x1) / 2} ${mid} V ${yBot}" fill="none" stroke="${COLORS.mute}" stroke-width="1.2"/>`
    );
  }

  let y = y0 + 18;

  if (title) {
    const heading = opts.titleMs != null ? `${title}  ${Number(opts.titleMs).toFixed(0)} ms` : title;
    parts.push(`<text class="lbl" x="${padL}" y="${y}">${esc(heading)}</text>`);
    y += 14;
  }

  if (showLegend) {
    parts.push(waitSeg(padL, y, 12, 10, "network wait"));
    parts.push(`<text class="meta" x="${padL + 16}" y="${y + 9}">network wait</text>`);
    parts.push(codeWaitSeg(padL + 120, y, 12, 10, "code wait"));
    parts.push(`<text class="meta" x="${padL + 136}" y="${y + 9}">code wait</text>`);
    parts.push(workSeg(padL + 230, y, 12, 10, "work", COLORS.working));
    parts.push(`<text class="meta" x="${padL + 246}" y="${y + 9}">work</text>`);
    y += 22;
  }

  const chartX = padL + labelW;

  // soft full-scale track so shorter after bars read against the same ruler
  parts.push(
    `<rect x="${chartX}" y="${y}" width="${chartW}" height="${barH}" fill="${COLORS.soft}" opacity="0.55"/>`
  );

  label("your browser", y, barH);
  const browserLayout = layoutAbsolute(browserParts, chartX);
  for (const it of browserLayout) drawSeg(it, y, barH);
  const appSeg = browserLayout.find((s) => s.id === "app");
  const y0b = y + barH;
  y += barH + 4;

  if (!appSeg) {
    return { parts, height: y + 12 - y0, width, yEnd: y + 12 };
  }

  // Flat local own-hardware: keep honest browser proportions (network dominates).
  // Draw a normal braced process row at true scale, plus a readable text callout
  // because js+sqlite is sub-pixel on the shared vercel homepage scale.
  if (flatLocal) {
    const g1 = 18;
    brace(appSeg.x, appSeg.x + appSeg.w, y0b, y + g1);
    guides(appSeg.x, appSeg.x + appSeg.w, y0b, y + g1 + barH + 4);
    y += g1;
    label(processLabel, y, barH);
    const appLayout = layoutInBand(appParts, appSeg.x, Math.max(appSeg.w, MIN_PX));
    for (const it of appLayout) drawSeg(it, y, barH);
    const localMs = jsMs;
    parts.push(
      `<text class="meta" x="${Math.min(appSeg.x + Math.max(appSeg.w, MIN_PX) + 8, chartX + chartW - 200)}" y="${y + barH - 12}">${esc(`js+sqlite ${localMs.toFixed(2)} ms (sliver at this scale)`)}</text>`
    );
    y += barH + 12;
    return { parts, height: y + 12 - y0, width, yEnd: y + 12 };
  }

  const g1 = 18;
  brace(appSeg.x, appSeg.x + appSeg.w, y0b, y + g1);
  guides(appSeg.x, appSeg.x + appSeg.w, y0b, y + g1 + barH + 4);
  y += g1;
  label(processLabel, y, barH);
  const appLayout = layoutInBand(appParts, appSeg.x, appSeg.w);
  for (const it of appLayout) drawSeg(it, y, barH);
  const sbSeg = appLayout.find((s) => s.id === "sb");
  const y1b = y + barH;
  y += barH + 4;

  if (!sbSeg || calls.length === 0) {
    return { parts, height: y + 12 - y0, width, yEnd: y + 12 };
  }

  const g2 = 16;
  const blockH = calls.length * (callH + callGap) + 4;
  brace(sbSeg.x, sbSeg.x + sbSeg.w, y1b, y + g2);
  guides(sbSeg.x, sbSeg.x + sbSeg.w, y1b, y + g2 + blockH);
  y += g2;

  // Place calls on a real timeline inside the db wall band so overlap is visible.
  const callT0 = Math.min(...calls.map((c) => c.s.started_at_ms));
  const callT1 = Math.max(...calls.map((c) => c.s.started_at_ms + c.total));
  const callWindow = Math.max(callT1 - callT0, 1);

  for (const c of calls) {
    const rel = c.s.started_at_ms - callT0;
    const xCall = sbSeg.x + (rel / callWindow) * sbSeg.w;
    const wCall = Math.max(1, (c.total / callWindow) * sbSeg.w);
    const segs = layoutInBand(
      [
        { short: "to", ms: c.to, kind: "net" },
        { short: "work", ms: c.work, kind: "work" },
        { short: "back", ms: c.back, kind: "net" },
      ],
      xCall,
      wCall
    );
    label(callLabel, y, callH);
    parts.push(
      `<rect x="${sbSeg.x}" y="${y}" width="${sbSeg.w}" height="${callH}" fill="${COLORS.soft}"/>`
    );
    for (const it of segs) drawSeg(it, y, callH);
    y += callH + callGap;
  }

  return { parts, height: y + 12 - y0, width, yEnd: y + 12 };
}

export function svgShell(width, height, title, body) {
  return `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${width} ${height}" width="100%" role="img">
<title>${esc(title)}</title>
<style>
  .lbl { font: 500 11px ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; fill: ${COLORS.ink}; }
  .meta { font: 400 10px ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; fill: ${COLORS.mute}; }
</style>
<defs>
  <pattern id="hatch-wait" patternUnits="userSpaceOnUse" width="8" height="8" patternTransform="rotate(45)">
    <rect width="8" height="8" fill="${COLORS.waiting}"/>
    <line x1="0" y1="0" x2="0" y2="8" stroke="${COLORS.waitingStroke}" stroke-width="3"/>
  </pattern>
  <pattern id="hatch-code" patternUnits="userSpaceOnUse" width="8" height="8" patternTransform="rotate(-45)">
    <rect width="8" height="8" fill="#ffd8a8"/>
    <line x1="0" y1="0" x2="0" y2="8" stroke="${COLORS.codeWait}" stroke-width="3"/>
  </pattern>
</defs>
<rect width="100%" height="100%" fill="${COLORS.paper}"/>
${body}
</svg>`;
}

export function renderCascadeSvg(t, opts = {}) {
  const { parts, height, width } = renderCascadeParts(t, opts);
  return svgShell(width, height, opts.svgTitle ?? "Cascade", parts.join("\n"));
}

export function renderCompareSvg(before, after, opts = {}) {
  const scaleMs = Math.max(before.total, after.total) * 1.02;
  const a = renderCascadeParts(before, {
    scaleMs,
    y0: 0,
    title: opts.beforeTitle ?? "before",
    titleMs: before.ttfb,
    showLegend: true,
    processLabel: opts.processLabel,
    dbWallLabel: opts.dbWallLabel,
    callLabel: opts.callLabel,
    ...(opts.beforeOpts ?? {}),
  });
  const b = renderCascadeParts(after, {
    scaleMs,
    y0: a.yEnd + 8,
    title: opts.afterTitle ?? "after",
    titleMs: after.ttfb,
    showLegend: false,
    processLabel: opts.processLabel,
    dbWallLabel: opts.dbWallLabel,
    callLabel: opts.callLabel,
    ...(opts.afterOpts ?? {}),
  });
  const width = a.width;
  const height = b.yEnd;
  const body = [...a.parts, ...b.parts].join("\n");
  return svgShell(width, height, opts.svgTitle ?? "Cascade before vs after", body);
}

/** Stack N cascades on one shared ms scale (for hardware ladder slides). */
export function renderLadderSvg(steps, opts = {}) {
  if (!steps.length) throw new Error("renderLadderSvg: empty steps");
  const scaleMs = Math.max(...steps.map((s) => s.trace.total)) * 1.02;
  let y0 = 0;
  const parts = [];
  let width = 0;
  for (let i = 0; i < steps.length; i++) {
    const step = steps[i];
    const rendered = renderCascadeParts(step.trace, {
      scaleMs,
      y0,
      labelW: opts.labelW ?? 168,
      title: step.title,
      titleMs: step.trace.ttfb,
      showLegend: i === 0,
      processLabel: step.processLabel ?? opts.processLabel ?? "always-on process",
      dbWallLabel: step.dbWallLabel ?? opts.dbWallLabel ?? "db wait",
      callLabel: step.callLabel ?? opts.callLabel ?? "db call",
      flatLocal: step.flatLocal ?? opts.flatLocal,
      minAppBandPx: step.minAppBandPx ?? opts.minAppBandPx,
      ...(step.opts ?? {}),
    });
    parts.push(...rendered.parts);
    width = rendered.width;
    y0 = rendered.yEnd + 10;
  }
  if (opts.notes?.length) {
    let y = y0 + 6;
    for (const line of opts.notes) {
      parts.push(
        `<text class="meta" x="12" y="${y}">${esc(line)}</text>`
      );
      y += 14;
    }
    y0 = y + 8;
  }
  return svgShell(width, y0, opts.svgTitle ?? "Hardware ladder cascade", parts.join("\n"));
}
