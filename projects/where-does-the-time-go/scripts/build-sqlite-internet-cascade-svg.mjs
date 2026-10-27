#!/usr/bin/env node
/**
 * Single-request cascade: measured always-on node + embedded sqlite,
 * with synthetic internet latency on the client->server and return path.
 *
 * Source: data/trace-hw-sqlite.json (localhost measure)
 * Out:    images/fix-sqlite-internet-cascade.svg
 *
 * Env:
 *   RTT_MS   full round-trip to paint (default 70, ~US regional / similar to
 *            the supabase network floor we saw on the same board query)
 */
import { existsSync, readFileSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { normalize, renderCascadeSvg } from "./cascade-lib.mjs";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const srcPath = join(root, "data/trace-hw-sqlite.json");
if (!existsSync(srcPath)) {
  console.error("missing", srcPath);
  process.exit(1);
}

const RTT_MS = Number(process.env.RTT_MS || 70);
const oneWay = RTT_MS / 2;
const tlsExtra = Number(process.env.TLS_MS || 12); // warm-ish TLS on top of TCP

const src = JSON.parse(readFileSync(srcPath, "utf8"));
const srv = src.full.server.server;
const fn = srv.function_ms;
const bodyMs = 0.4; // small JSON body over the wire

// Rebuild client curl timings as if the same process answered over the public internet.
const dns = 8;
const tcp = oneWay; // SYN/ACK ≈ one-way
const tls = tlsExtra;
const connectDone = dns + tcp + tls; // treated as time_appconnect
const pretransfer = connectDone + 0.3;
const ttfb = pretransfer + oneWay + fn; // request flight + server + first byte
const total = ttfb + oneWay * 0.15 + bodyMs; // remaining body + last ACK-ish

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

const waitAfterTls = ttfb - connectDone;
const edge = Math.max(0, waitAfterTls - fn);

const packet = {
  base: "synthetic-internet",
  label: "node-sqlite-internet",
  measured_at: new Date().toISOString(),
  note: `localhost sqlite measure with synthetic ${RTT_MS} ms RTT (+${tlsExtra} ms TLS) painted on both ends`,
  rtt_ms: RTT_MS,
  tls_ms: tlsExtra,
  source: "data/trace-hw-sqlite.json",
  full: {
    url: "https://own-box.example/board",
    client,
    server: {
      ...src.full.server,
      shape: "node-sqlite-internet",
      measured_at: new Date().toISOString(),
      region_hint: "synthetic-internet",
      server: {
        ...srv,
        // keep measured spans; embedded sqlite has no remote db RTT
      },
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
        note: `synthetic internet: ~${RTT_MS} ms RTT painted as tcp/tls + return path; server/sqlite timings from localhost measure`,
      },
    },
  },
};

const dataOut = join(root, "data/trace-hw-sqlite-internet.json");
writeFileSync(dataOut, JSON.stringify(packet, null, 2) + "\n");

const trace = normalize(packet);
const svg = renderCascadeSvg(trace, {
  svgTitle: "Single request: own hardware + sqlite over the internet",
  title: "own box + embedded sqlite (synthetic internet rtt)",
  titleMs: trace.ttfb,
  processLabel: "always-on process",
  dbWallLabel: "sqlite",
  callLabel: "sqlite query",
  labelW: 168,
});
const svgOut = join(root, "images/fix-sqlite-internet-cascade.svg");
writeFileSync(svgOut, svg);

console.log("wrote", dataOut);
console.log("wrote", svgOut);
console.log({
  rtt_ms: RTT_MS,
  ttfb: Number(ttfb.toFixed(2)),
  total: Number(total.toFixed(2)),
  function_ms: fn,
  sqlite_wall_ms: srv.supabase.wall_ms,
  tcp_tls_ms: Number(connectDone.toFixed(2)),
  return_path_ms: Number(edge.toFixed(2)),
});
