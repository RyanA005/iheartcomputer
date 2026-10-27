#!/usr/bin/env node
/**
 * Build images/fix-cache-cascade.svg from measured cache miss vs hit traces.
 */
import { existsSync, readFileSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { normalize, renderCompareSvg } from "./cascade-lib.mjs";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const missPath = join(root, "data/trace-cache-miss.json");
const hitPath = join(root, "data/trace-cache-hit.json");

if (!existsSync(missPath) || !existsSync(hitPath)) {
  console.error("missing measured traces. run:");
  console.error(
    "  node /home/ralpo/code/whoscooking/scripts/trace-cache-compare.mjs http://127.0.0.1:3000",
    root + "/data"
  );
  process.exit(1);
}

const miss = normalize(JSON.parse(readFileSync(missPath, "utf8")));
const hit = normalize(JSON.parse(readFileSync(hitPath, "utf8")));
const out = join(root, "images/fix-cache-cascade.svg");
writeFileSync(
  out,
  renderCompareSvg(miss, hit, {
    beforeTitle: "cache miss",
    afterTitle: "cache hit",
    svgTitle: "Cascade cache miss vs hit",
  })
);
console.log("wrote", out);
console.log("miss", { ttfb: miss.ttfb, fn: miss.fn, calls: miss.callCount, sbWall: miss.sbWall });
console.log("hit", { ttfb: hit.ttfb, fn: hit.fn, calls: hit.callCount, sbWall: hit.sbWall });
