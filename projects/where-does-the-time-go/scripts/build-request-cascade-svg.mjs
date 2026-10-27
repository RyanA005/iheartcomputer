#!/usr/bin/env node
/**
 * Build images/request-cascade.svg for "follow a request".
 * Must be the SERIAL path (no overlapping supabase calls) — that sets up fix 1.
 */
import { readFileSync, writeFileSync, existsSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { normalize, renderCascadeSvg } from "./cascade-lib.mjs";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const serialPath = join(root, "data/trace-shape-serial.json");
const legacyPath = join(root, "data/trace-one-request.json");
const src = existsSync(serialPath) ? serialPath : legacyPath;
const data = JSON.parse(readFileSync(src, "utf8"));
const out = join(root, "images/request-cascade.svg");
writeFileSync(
  out,
  renderCascadeSvg(normalize(data), {
    svgTitle: "Serial request cascade (no overlapping supabase calls)",
  })
);
console.log("wrote", out, "from", src);
