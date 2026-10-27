#!/usr/bin/env node
/**
 * Build images/fix-fanout-cascade.svg from measured serial vs batched traces.
 * Expects:
 *   data/trace-shape-serial.json
 *   data/trace-shape-batched.json
 */
import { readFileSync, writeFileSync, existsSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { normalize, renderCompareSvg } from "./cascade-lib.mjs";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const serialPath = join(root, "data/trace-shape-serial.json");
const batchedPath = join(root, "data/trace-shape-batched.json");

if (!existsSync(serialPath) || !existsSync(batchedPath)) {
  console.error("missing measured traces. run:");
  console.error(
    "  node /home/ralpo/code/whoscooking/scripts/trace-shape-compare.mjs http://127.0.0.1:3000",
    root + "/data"
  );
  process.exit(1);
}

const before = normalize(JSON.parse(readFileSync(serialPath, "utf8")));
const after = normalize(JSON.parse(readFileSync(batchedPath, "utf8")));
const out = join(root, "images/fix-fanout-cascade.svg");
writeFileSync(out, renderCompareSvg(before, after));
console.log("wrote", out);
console.log("before", {
  ttfb: before.ttfb,
  fn: before.fn,
  calls: before.callCount,
  sbWall: before.sbWall,
});
console.log("after", {
  ttfb: after.ttfb,
  fn: after.fn,
  calls: after.callCount,
  sbWall: after.sbWall,
});
