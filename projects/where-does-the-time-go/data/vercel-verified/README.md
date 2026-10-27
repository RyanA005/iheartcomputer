# Chart verification standard

- **Vercel path charts**: measure on production (`https://whoscooking.live`) with **n hits** (default 15), report **p50**, draw the cascade from the sample nearest p50.
- **Shared baseline**: fix 1 `batched` and fix 2 `cache miss` are the **same sample pool** (identical URL/path).
- **Off-Vercel**: localhost sqlite multi-hit p50 for function time; paint **same-suite** vercel ping p50 (`connect` + `edge`) on both browser ends.

## Measure + rebuild

```bash
node /home/ralpo/code/whoscooking/scripts/trace-averaged-suite.mjs \
  https://whoscooking.live \
  /home/ralpo/code/iheartcomputer/projects/where-does-the-time-go/data/vercel-verified \
  15

node scripts/build-vercel-verified-charts.mjs
```

Originals under `images/*.svg` are never overwritten; verified copies are `*-vercel-verified.svg`.
