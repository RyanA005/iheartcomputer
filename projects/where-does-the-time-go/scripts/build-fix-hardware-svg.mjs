#!/usr/bin/env node
/**
 * Build images/fix-hardware-cascade.svg from measured race ladder traces.
 * Expects data/trace-hw-{supabase,postgres,sqlite}.json and trace-hw-summary.json
 */
import { existsSync, readFileSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { normalize, renderLadderSvg } from "./cascade-lib.mjs";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const required = ["supabase", "sqlite", "summary", "cold"];
const paths = {
  supabase: join(root, "data/trace-hw-supabase.json"),
  postgres: join(root, "data/trace-hw-postgres.json"),
  sqlite: join(root, "data/trace-hw-sqlite.json"),
  summary: join(root, "data/trace-hw-summary.json"),
  cold: join(root, "data/trace-hw-cold-spawn.json"),
};

for (const k of required) {
  if (!existsSync(paths[k])) {
    console.error("missing", paths[k]);
    console.error(
      "run: node /home/ralpo/code/whoscooking/scripts/trace-race-ladder.mjs",
      join(root, "data")
    );
    process.exit(1);
  }
}

const supabase = normalize(JSON.parse(readFileSync(paths.supabase, "utf8")));
const sqlite = normalize(JSON.parse(readFileSync(paths.sqlite, "utf8")));
const summary = JSON.parse(readFileSync(paths.summary, "utf8"));
const cold = JSON.parse(readFileSync(paths.cold, "utf8"));
const hasPg = existsSync(paths.postgres) && summary.postgres;

const sb = summary.supabase.ttfb_ms;
const sq = summary.sqlite.ttfb_ms;
const coldP50 = cold.p50_ms;
const pg = hasPg ? summary.postgres.ttfb_ms : null;

const notes = [];
if (pg != null) {
  notes.push(
    `speedups (same GET /board JSON, localhost): supabase rest ${sb.toFixed(0)} ms  ->  postgres ${pg.toFixed(0)} ms  (${(sb / pg).toFixed(1)}x)  ->  sqlite ${sq.toFixed(1)} ms  (${(sb / sq).toFixed(0)}x)`
  );
} else {
  notes.push(
    `speedups (same GET /board JSON, localhost): supabase rest ${sb.toFixed(0)} ms  ->  local sqlite ${sq.toFixed(1)} ms  (${(sb / sq).toFixed(0)}x). direct postgres sits in between (drop postgrest, keep network).`
  );
}
notes.push(
  `always-on js/sqlite ~${sq.toFixed(1)} ms vs cold process spawn p50 ${coldP50.toFixed(1)} ms (~${(coldP50 / Math.max(sq, 0.1)).toFixed(0)}x tax for bring-up; vercel cold starts are the same idea)`
);
notes.push(
  `rewriting the query in c did not beat warm node+sqlite on this laptop; dropping remote rest + keeping a warm process did.`
);

const steps = [
  {
    title: "always-on node + supabase rest",
    trace: supabase,
    callLabel: "supabase call",
    dbWallLabel: "supabase",
  },
];
if (hasPg) {
  steps.push({
    title: "always-on node + postgres",
    trace: normalize(JSON.parse(readFileSync(paths.postgres, "utf8"))),
    callLabel: "postgres query",
    dbWallLabel: "postgres",
  });
}
steps.push({
  title: "always-on node + local sqlite",
  trace: sqlite,
  callLabel: "sqlite query",
  dbWallLabel: "sqlite",
});

const out = join(root, "images/fix-hardware-cascade.svg");
writeFileSync(
  out,
  renderLadderSvg(steps, {
    svgTitle: "Own hardware ladder cascade",
    processLabel: "always-on process",
    notes,
  })
);

console.log("wrote", out);
console.log({ supabase: sb, postgres: pg, sqlite: sq, cold_p50: coldP50 });
