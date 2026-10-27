#!/usr/bin/env node
/**
 * One chart: vercel+supabase vs own-hardware+sqlite for the same GET /board,
 * with identical synthetic internet turnarounds painted on both browser ends
 * so the visual difference is execution (remote PostgREST vs embedded sqlite).
 *
 * Sources (localhost measures):
 *   data/trace-hw-supabase.json
 *   data/trace-hw-sqlite.json
 *
 * Out:
 *   data/trace-hw-compare-internet.json
 *   images/fix-hw-compare-internet.svg
 *
 * Env: RTT_MS (default 70), TLS_MS (default 12)
 */
import { existsSync, readFileSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { normalize, renderLadderSvg } from "./cascade-lib.mjs";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const supabasePath = join(root, "data/trace-hw-supabase.json");
const sqlitePath = join(root, "data/trace-hw-sqlite.json");
for (const p of [supabasePath, sqlitePath]) {
  if (!existsSync(p)) {
    console.error("missing", p);
    process.exit(1);
  }
}

const RTT_MS = Number(process.env.RTT_MS || 70);
const TLS_MS = Number(process.env.TLS_MS || 12);
const oneWay = RTT_MS / 2;
const dns = 8;
const tcp = oneWay;
const tls = TLS_MS;
const connectDone = dns + tcp + tls;
const pretransfer = connectDone + 0.3;
const bodyTail = oneWay * 0.15;

function paintInternet(src, { label, url, processKind }) {
  const srv = structuredClone(src.full.server.server);
  const fn = srv.function_ms;
  const bodyMs = 0.4;
  const ttfb = pretransfer + oneWay + fn;
  const total = ttfb + bodyTail + bodyMs;
  const waitAfterTls = ttfb - connectDone;
  const edge = Math.max(0, waitAfterTls - fn);

  const client = {
    http_code: 200,
    time_namelookup: dns,
    time_connect: dns + tcp,
    time_appconnect: connectDone,
    time_pretransfer: pretransfer,
    time_starttransfer: ttfb,
    time_total: total,
    size_download: src.full.client.size_download,
  };

  return {
    base: "synthetic-internet",
    label,
    measured_at: new Date().toISOString(),
    rtt_ms: RTT_MS,
    tls_ms: TLS_MS,
    process_kind: processKind,
    source: src.label || src.full?.server?.shape || "trace",
    full: {
      url,
      client,
      server: {
        ...src.full.server,
        shape: label,
        measured_at: new Date().toISOString(),
        region_hint: "synthetic-internet",
        server: srv,
      },
      derived: {
        client_ms: {
          dns,
          tcp,
          tls,
          wait_after_tls: waitAfterTls,
          ttfb,
          download_body: Math.max(0, total - ttfb),
          total,
        },
        split_ms: {
          "1_computer_to_vercel_tcp_tls": connectDone,
          "2_edge_queue_and_return_path_estimate": edge,
          "3_function_runtime": fn,
          "4_supabase_http_wall": srv.supabase.wall_ms,
          "4b_supabase_http_sum_parallel": srv.supabase.total_ms_sum,
          "5_app_cpu_between_awaits": srv.app_cpu_ms,
          note: `same synthetic ${RTT_MS} ms RTT (+${TLS_MS} ms TLS) on both ends; server timings from localhost measure`,
        },
      },
    },
  };
}

const supabaseSrc = JSON.parse(readFileSync(supabasePath, "utf8"));
const sqliteSrc = JSON.parse(readFileSync(sqlitePath, "utf8"));

// Cloud path: same /board work measured via Supabase REST. Labeled as a Vercel
// function because that is the deployed shape; cold-start is NOT added so the
// browser turnarounds stay identical to the own-hardware row.
const cloudPkt = paintInternet(supabaseSrc, {
  label: "vercel-supabase",
  url: "https://whoscooking.live/board",
  processKind: "vercel",
});
const ownPkt = paintInternet(sqliteSrc, {
  label: "own-sqlite",
  url: "https://own-box.example/board",
  processKind: "always-on",
});

const cloud = normalize(cloudPkt);
const own = normalize(ownPkt);

const bundle = {
  measured_at: new Date().toISOString(),
  rtt_ms: RTT_MS,
  tls_ms: TLS_MS,
  note: "Identical client turnarounds; compare function/db execution only.",
  cloud: {
    ttfb_ms: cloud.ttfb,
    function_ms: cloud.fn,
    db_wall_ms: cloud.sbWall,
  },
  own: {
    ttfb_ms: own.ttfb,
    function_ms: own.fn,
    db_wall_ms: own.sbWall,
  },
  speedup_ttfb: cloud.ttfb / own.ttfb,
  speedup_function: cloud.fn / Math.max(own.fn, 0.001),
  packets: { cloud: cloudPkt, own: ownPkt },
};

writeFileSync(join(root, "data/trace-hw-compare-internet.json"), JSON.stringify(bundle, null, 2) + "\n");

const notes = [
  `same synthetic internet on both ends: ~${RTT_MS} ms RTT + ${TLS_MS} ms TLS (to/back identical)`,
  `execution: vercel+supabase function ${cloud.fn.toFixed(0)} ms (almost all remote rest) vs own+sqlite ${own.fn.toFixed(2)} ms`,
  `ttfb ${cloud.ttfb.toFixed(0)} ms -> ${own.ttfb.toFixed(0)} ms (~${(cloud.ttfb / own.ttfb).toFixed(1)}x); gap is server work, not the browser hop`,
];

const svg = renderLadderSvg(
  [
    {
      title: "vercel + supabase rest",
      trace: cloud,
      processLabel: "vercel function",
      dbWallLabel: "supabase",
      callLabel: "supabase call",
    },
    {
      title: "own hardware + embedded sqlite",
      trace: own,
      processLabel: "always-on process",
      dbWallLabel: "sqlite",
      callLabel: "sqlite query",
    },
  ],
  {
    svgTitle: "Same internet RTT: vercel+supabase vs own+sqlite",
    notes,
    labelW: 168,
  }
);

const out = join(root, "images/fix-hw-compare-internet.svg");
writeFileSync(out, svg);
console.log("wrote", out);
console.log({
  rtt_ms: RTT_MS,
  cloud_ttfb: Number(cloud.ttfb.toFixed(2)),
  own_ttfb: Number(own.ttfb.toFixed(2)),
  cloud_fn: cloud.fn,
  own_fn: own.fn,
  speedup_ttfb: Number((cloud.ttfb / own.ttfb).toFixed(2)),
});
