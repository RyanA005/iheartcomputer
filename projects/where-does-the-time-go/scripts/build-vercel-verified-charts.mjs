#!/usr/bin/env node
/**
 * Rebuild *-vercel-verified.svg from averaged-suite outputs in data/vercel-verified/.
 * Cascades show the sample nearest p50; captions/notes carry the averaged stats.
 */
import { existsSync, readFileSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { normalize, renderCascadeSvg, renderCompareSvg, renderLadderSvg } from "./cascade-lib.mjs";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const vdir = join(root, "data/vercel-verified");
const img = join(root, "images");

function must(p) {
  if (!existsSync(p)) throw new Error(`missing ${p}`);
  return JSON.parse(readFileSync(p, "utf8"));
}

const summary = must(join(vdir, "averaged-suite-summary.json"));
const N = summary.n;

const serial = normalize(must(join(vdir, "trace-shape-serial.json")));
const batched = normalize(must(join(vdir, "trace-shape-batched.json")));
const miss = normalize(must(join(vdir, "trace-cache-miss.json")));
const hit = normalize(must(join(vdir, "trace-cache-hit.json")));
// Vercel+supabase row must continue the homepage story from fix 1/2 (batched),
// not a separate mode=board microbenchmark.
const vercelHome = batched;
const own = normalize(must(join(vdir, "trace-hw-sqlite-vercel-ping.json")));

// "follow a request" is the before story: serial awaits, no overlap.
writeFileSync(
  join(img, "request-cascade-vercel-verified.svg"),
  renderCascadeSvg(serial, {
    svgTitle: `Vercel-verified serial request (n=${N}, nearest p50)`,
    title: `serial homepage  p50 ttfb ${summary.serial.ttfb_p50} ms (n=${N})`,
    titleMs: null,
  })
);

writeFileSync(
  join(img, "fix-fanout-cascade-vercel-verified.svg"),
  renderCompareSvg(serial, batched, {
    beforeTitle: `serial  p50 ${summary.serial.ttfb_p50} ms (n=${N})`,
    afterTitle: `batched  p50 ${summary.batched.ttfb_p50} ms (n=${N})`,
    svgTitle: "Vercel-verified fanout (averaged)",
  })
);

writeFileSync(
  join(img, "fix-cache-cascade-vercel-verified.svg"),
  renderCompareSvg(miss, hit, {
    beforeTitle: `cache miss = batched  p50 ${summary.batched.ttfb_p50} ms (n=${N})`,
    afterTitle: `cache hit  p50 ${summary.cache_hit.ttfb_p50} ms (n=${N})`,
    svgTitle: "Vercel-verified cache (averaged, shared miss baseline)",
  })
);

const notes = [
  `vercel row = same suite batched homepage as fix 1 after / fix 2 miss (p50 ttfb ${summary.batched.ttfb_p50} ms, n=${N})`,
  `own row = localhost sqlite fn p50 ${summary.sqlite_local.function_p50} ms + same-suite vercel ping paint (connect ${summary.ping_p50.connect} ms, return ${summary.ping_p50.edge} ms)`,
  `cascades show sample nearest p50; not a separate mode=board number`,
];

writeFileSync(
  join(img, "fix-hw-compare-internet-vercel-verified.svg"),
  renderLadderSvg(
    [
      {
        title: `vercel + supabase homepage  p50 ${summary.batched.ttfb_p50} ms (n=${N})`,
        trace: vercelHome,
        processLabel: "vercel function",
        dbWallLabel: "supabase",
        callLabel: "supabase call",
      },
      {
        title: `own + sqlite + vercel ping paint  ~${Number(summary.sqlite_painted_ttfb).toFixed(0)} ms`,
        trace: own,
        processLabel: "always-on process",
        // Honest scale + callout: js+sqlite is real but tiny vs the painted RTT.
        flatLocal: true,
      },
    ],
    { svgTitle: "Vercel-verified hardware compare (averaged)", notes, labelW: 168 }
  )
);

const report = {
  measured_at: summary.measured_at,
  methodology: {
    n: N,
    vercel: "production whoscooking.live, p50 over N hits, cascade = sample nearest p50 ttfb",
    shared_baseline: "fix1 batched === fix2 cache_miss",
    off_vercel: "localhost sqlite function p50 + same-suite vercel ping p50 on both ends",
  },
  summary,
  charts: [
    "request-cascade-vercel-verified.svg",
    "fix-fanout-cascade-vercel-verified.svg",
    "fix-cache-cascade-vercel-verified.svg",
    "fix-hw-compare-internet-vercel-verified.svg",
  ],
};

writeFileSync(join(vdir, "discrepancy-report.json"), JSON.stringify(report, null, 2) + "\n");
console.log(JSON.stringify(summary, null, 2));
console.log("rebuilt verified charts from averaged suite");
