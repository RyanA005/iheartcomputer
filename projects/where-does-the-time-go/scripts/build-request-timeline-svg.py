#!/usr/bin/env python3
"""Build images/request-timeline.svg from data/trace-one-request.json."""
from __future__ import annotations

import html
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DATA = ROOT / "data" / "trace-one-request.json"
OUT = ROOT / "images" / "request-timeline.svg"


def main() -> None:
    d = json.loads(DATA.read_text())
    full = d["full"]
    srv = full["server"]["server"]
    spans = srv["spans"]
    phases = srv["phases"]
    client = full["derived"]["client_ms"]
    split = full["derived"]["split_ms"]
    rtt = srv["supabase"]["min_wait_ms"]

    board_ms = next(p["ms"] for p in phases if p["label"] == "board")
    badge_ms = next(p["ms"] for p in phases if p["label"] == "badges")
    fn = srv["function_ms"]
    ttfb = client["ttfb"]
    total = client["total"]
    edge = split["2_edge_queue_and_return_path_estimate"]
    cpu = split["5_app_cpu_between_awaits"]
    sb_wall = split["4_supabase_http_wall"]

    label_w = 220
    pad_l = 20
    pad_r = 180
    chart_w = 640
    width = pad_l + label_w + chart_w + pad_r
    row_h = 17
    gap = 3
    header_h = 100
    footer_h = 100

    rows: list[tuple[str, str, dict | None]] = [
        ("client", "client connect + TTFB + download", None),
        ("edge", "host overhead (est. residual)", None),
        ("function", "function wall (Node / our code)", None),
        ("phase", "phases: board then badges", None),
        ("header", "Supabase HTTP calls", None),
    ]
    for i, s in enumerate(spans):
        phase = "board" if s["started_at_ms"] < board_ms else "badges"
        name = s["name"].replace("table:", "").replace("rpc:", "rpc ")
        rows.append(("span", f"{i+1:02d}  {phase} · {name}", s))

    height = header_h + 56 + len(rows) * (row_h + gap) + footer_h + 20

    INK = "#171717"
    MUTE = "#5c5c5c"
    RULE = "#e5e5e5"
    PAPER = "#ffffff"
    C_CLIENT = "#495057"
    C_WAIT = "#dee2e6"
    C_EDGE = "#adb5bd"
    C_FN = "#0b57d0"
    C_BOARD = "#4c6ef5"
    C_BADGE = "#e8590c"
    C_NET = "#868e96"
    C_WORK = "#c2255c"
    C_BODY = "#12b886"

    def x_of(ms: float) -> float:
        return pad_l + label_w + (ms / total) * chart_w

    def w_of(ms: float) -> float:
        return max(1.2, (ms / total) * chart_w)

    esc = html.escape
    out: list[str] = []
    a = out.append
    a(
        f'''<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {width} {height}" width="100%" role="img" aria-labelledby="title desc">
<title id="title">Request timeline for whoscooking GET /api/trace?mode=full</title>
<desc id="desc">Client curl timings and server-side function and Supabase HTTP spans from a warm local measurement on 2026-10-26.</desc>
<style>
  .t {{ font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }}
  .title {{ font-size: 14px; font-weight: 600; fill: {INK}; }}
  .sub {{ font-size: 10.5px; fill: {MUTE}; }}
  .lbl {{ font-size: 10px; fill: {INK}; }}
  .meta {{ font-size: 9px; fill: {MUTE}; }}
  .tick {{ font-size: 9px; fill: {MUTE}; }}
  .onbar {{ font-size: 9px; fill: #fff; }}
</style>
<rect width="100%" height="100%" fill="{PAPER}"/>'''
    )

    a(f'<text class="t title" x="{pad_l}" y="24">Request timeline - GET /api/trace?mode=full</text>')
    a(
        f'<text class="t sub" x="{pad_l}" y="42">2026-10-26T17:28:04Z · client: WSL curl · app: Next.js 127.0.0.1:3000 · db: Supabase cloud</text>'
    )
    a(
        f'<text class="t sub" x="{pad_l}" y="56">result: {full["server"]["payload"]["entries"]} leaderboard entries, {full["server"]["payload"]["badgeUsers"]} badge users, date {full["server"]["payload"]["leaderboardDate"]}</text>'
    )

    ly = 78
    a(f'<text class="t meta" x="{pad_l}" y="{ly}">legend:</text>')
    leg = [
        (C_CLIENT, "connect"),
        (C_WAIT, "client wait"),
        (C_EDGE, "host overhead est."),
        (C_FN, "function"),
        (C_BOARD, "board phase"),
        (C_BADGE, "badges phase"),
        (C_NET, f"wait ≤ {rtt:.0f}ms floor"),
        (C_WORK, "wait above floor"),
        (C_BODY, "body read"),
    ]
    lx = pad_l + 52
    for c, lab in leg:
        a(f'<rect x="{lx}" y="{ly-9}" width="10" height="10" fill="{c}"/>')
        a(f'<text class="t meta" x="{lx+13}" y="{ly}">{esc(lab)}</text>')
        lx += 13 + 10 + len(lab) * 5.6 + 10

    axis_y = header_h + 8
    a(
        f'<line x1="{pad_l+label_w}" y1="{axis_y}" x2="{pad_l+label_w+chart_w}" y2="{axis_y}" stroke="{INK}" stroke-width="1"/>'
    )
    for t in range(0, int(total) + 1, 50):
        xx = x_of(t)
        a(f'<line x1="{xx}" y1="{axis_y}" x2="{xx}" y2="{axis_y+3}" stroke="{MUTE}"/>')
        a(f'<text class="t tick" x="{xx}" y="{axis_y+13}" text-anchor="middle">{t}</text>')
    a(
        f'<text class="t tick" x="{pad_l+label_w+chart_w+6}" y="{axis_y+13}">ms from client start</text>'
    )

    fn_start = ttfb - fn
    y = axis_y + 22

    def bar(x: float, yy: float, w: float, fill: str, title: str) -> None:
        a(
            f'<rect x="{x:.2f}" y="{yy:.2f}" width="{max(w,1):.2f}" height="{row_h}" fill="{fill}"><title>{esc(title)}</title></rect>'
        )

    for kind, label, span in rows:
        a(
            f'<text class="t lbl" x="{pad_l+label_w-10}" y="{y+row_h-4}" text-anchor="end">{esc(label)}</text>'
        )
        if kind == "client":
            c1 = client["dns"] + client["tcp"] + client["tls"]
            bar(x_of(0), y, w_of(max(c1, 0.4)), C_CLIENT, f"DNS+TCP+TLS {c1:.3f} ms")
            bar(x_of(c1), y, w_of(ttfb - c1), C_WAIT, f"wait to first byte {ttfb-c1:.2f} ms")
            bar(
                x_of(ttfb),
                y,
                w_of(max(client["download_body"], 0.5)),
                C_BODY,
                f'download {client["download_body"]:.2f} ms, {full["client"]["size_download"]} bytes',
            )
            a(
                f'<text class="t meta" x="{x_of(total)+6}" y="{y+row_h-4}">TTFB {ttfb:.2f} · total {total:.2f}</text>'
            )
        elif kind == "edge":
            bar(
                x_of(ttfb - edge),
                y,
                w_of(edge),
                C_EDGE,
                f"host overhead estimate {edge:.2f} ms = (TTFB after connect) - function_ms; includes admit and return path, not separated",
            )
            a(f'<text class="t meta" x="{x_of(total)+6}" y="{y+row_h-4}">{edge:.2f} ms est.</text>')
        elif kind == "function":
            bar(
                x_of(fn_start),
                y,
                w_of(fn),
                C_FN,
                f"function wall {fn:.2f} ms; app_cpu {cpu:.2f}; supabase_wall {sb_wall:.2f}",
            )
            a(
                f'<text class="t onbar" x="{x_of(fn_start)+6}" y="{y+row_h-4}">fn {fn:.1f} · cpu {cpu:.1f} · sb_wall {sb_wall:.1f}</text>'
            )
            a(f'<text class="t meta" x="{x_of(total)+6}" y="{y+row_h-4}">{fn:.2f} ms</text>')
        elif kind == "phase":
            bar(x_of(fn_start), y, w_of(board_ms), C_BOARD, f"getDailyLeaderboard {board_ms:.2f} ms")
            bar(
                x_of(fn_start + board_ms),
                y,
                w_of(badge_ms),
                C_BADGE,
                f"getLeaderboardBadgePinsByGithubIds {badge_ms:.2f} ms",
            )
            a(f'<text class="t onbar" x="{x_of(fn_start)+6}" y="{y+row_h-4}">board {board_ms:.0f}</text>')
            a(
                f'<text class="t onbar" x="{x_of(fn_start+board_ms)+6}" y="{y+row_h-4}">badges {badge_ms:.0f}</text>'
            )
        elif kind == "header":
            a(
                f'<text class="t meta" x="{pad_l+label_w+4}" y="{y+row_h-4}">wait = headers (network + PostgREST/Postgres); body = bytes copied into Node; floor = min wait across calls ({rtt:.2f} ms)</text>'
            )
        elif kind == "span" and span is not None:
            s = span
            t0 = fn_start + s["started_at_ms"]
            floor = min(s["wait_ms"], rtt)
            extra = max(0.0, s["wait_ms"] - rtt)
            path = s["url_path"]
            bar(x_of(t0), y, w_of(floor), C_NET, f'{s["method"]} {path}\nwait floor {floor:.2f} ms')
            if extra > 0:
                bar(x_of(t0 + floor), y, w_of(extra), C_WORK, f"wait above floor {extra:.2f} ms")
            bar(
                x_of(t0 + s["wait_ms"]),
                y,
                w_of(max(s["body_ms"], 0.7)),
                C_BODY,
                f'body {s["body_ms"]:.2f} ms · {s["bytes"]} bytes',
            )
            a(
                f'<text class="t meta" x="{pad_l+label_w+chart_w+6}" y="{y+row_h-4}">w {s["wait_ms"]:.1f} · b {s["body_ms"]:.1f} · {s["bytes"]}B</text>'
            )
        a(
            f'<line x1="{pad_l+label_w}" y1="{y+row_h+gap/2:.1f}" x2="{pad_l+label_w+chart_w}" y2="{y+row_h+gap/2:.1f}" stroke="{RULE}" stroke-width="0.5"/>'
        )
        y += row_h + gap

    fy = y + 18
    notes = [
        "Alignment: function bar ends at client TTFB. Span start times are offset by (TTFB − function_ms).",
        "Measured: curl time_namelookup/connect/appconnect/starttransfer/total; handler performance.now(); each fetch wait + body length.",
        "Estimated: host overhead = (TTFB after connect) − function_ms; “above floor” = call wait − min(wait). RTT floor is not a pure network measurement.",
        "Code paths: board = lib/leaderboard.ts getDailyLeaderboard; badges = lib/userBadges.ts getLeaderboardBadgePinsByGithubIds.",
        "Network path for spans: app host -> Supabase HTTPS (PostgREST) -> Postgres. Client<->app on this run is loopback (no TLS).",
        f'Source JSON: data/trace-one-request.json · URL {full["url"]}',
    ]
    for i, n in enumerate(notes):
        a(f'<text class="t meta" x="{pad_l}" y="{fy + i*13}">{esc(n)}</text>')

    a("</svg>")
    OUT.write_text("\n".join(out))
    print(f"wrote {OUT}")


if __name__ == "__main__":
    main()
