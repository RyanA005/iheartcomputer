(() => {
  const hostCanvas = document.getElementById('demo-host');
  const clientCanvas = document.getElementById('demo-client');
  if (!hostCanvas || !clientCanvas) return;

  const hostCtx = hostCanvas.getContext('2d');
  const clientCtx = clientCanvas.getContext('2d');

  const W = 640;
  const H = 360;
  for (const c of [hostCanvas, clientCanvas]) {
    c.width = W;
    c.height = H;
  }

  const controls = {
    latency: document.getElementById('lag-latency'),
    jitter: document.getElementById('lag-jitter'),
    loss: document.getElementById('lag-loss'),
    rate: document.getElementById('lag-rate'),
    mode: document.getElementById('lag-mode'),
  };
  const stats = document.getElementById('lag-stats');

  function readout(input) {
    const span = document.querySelector(`[data-for="${input.id}"]`);
    if (span) span.textContent = input.value + (input.dataset.unit || '');
  }
  for (const key of ['latency', 'jitter', 'loss', 'rate']) {
    controls[key].addEventListener('input', () => readout(controls[key]));
    readout(controls[key]);
  }

  const keys = new Set();
  let manual = false;
  window.addEventListener('keydown', (e) => {
    if (!hostCanvas.matches(':hover')) return;
    const k = e.key.toLowerCase();
    if (['w', 'a', 's', 'd'].includes(k)) {
      keys.add(k);
      manual = true;
      e.preventDefault();
    }
  });
  window.addEventListener('keyup', (e) => keys.delete(e.key.toLowerCase()));
  hostCanvas.addEventListener('mouseleave', () => {
    keys.clear();
    manual = false;
  });

  const RADIUS = 14;
  const SPEED = 260;
  let pos = { x: W / 2, y: H / 2 };
  let autoT = 0;

  // packets: { sendTime, arriveTime, x, y }
  let inFlight = [];
  let received = [];
  let lastSend = 0;
  let sent = 0;
  let lost = 0;
  let outOfOrder = 0;
  let newestSendTime = -Infinity;
  let shown = { x: pos.x, y: pos.y };

  function stepAuthority(dt) {
    if (manual) {
      let dx = 0, dy = 0;
      if (keys.has('a')) dx -= 1;
      if (keys.has('d')) dx += 1;
      if (keys.has('w')) dy -= 1;
      if (keys.has('s')) dy += 1;
      const len = Math.hypot(dx, dy) || 1;
      pos.x = Math.min(W - RADIUS, Math.max(RADIUS, pos.x + (dx / len) * SPEED * dt));
      pos.y = Math.min(H - RADIUS, Math.max(RADIUS, pos.y + (dy / len) * SPEED * dt));
    } else {
      autoT += dt * 0.9;
      pos.x = W / 2 + Math.sin(autoT) * (W * 0.38);
      pos.y = H / 2 + Math.sin(autoT * 2) * (H * 0.3);
    }
  }

  function sendUpdates(now) {
    const interval = 1000 / Number(controls.rate.value);
    if (now - lastSend < interval) return;
    lastSend = now;
    sent++;
    if (Math.random() * 100 < Number(controls.loss.value)) {
      lost++;
      return;
    }
    const latency = Number(controls.latency.value);
    const jitter = Number(controls.jitter.value);
    const delay = Math.max(0, latency + (Math.random() * 2 - 1) * jitter);
    inFlight.push({ sendTime: now, arriveTime: now + delay, x: pos.x, y: pos.y });
  }

  function deliver(now) {
    const still = [];
    for (const p of inFlight) {
      if (p.arriveTime <= now) {
        if (p.sendTime < newestSendTime) outOfOrder++;
        newestSendTime = Math.max(newestSendTime, p.sendTime);
        received.push(p);
      } else {
        still.push(p);
      }
    }
    inFlight = still;
    received.sort((a, b) => a.sendTime - b.sendTime);
    if (received.length > 120) received = received.slice(-120);
  }

  function lerp(a, b, t) {
    return a + (b - a) * t;
  }

  function computeShown(now) {
    if (received.length === 0) return;
    const mode = controls.mode.value;
    const newest = received[received.length - 1];

    if (mode === 'snap') {
      shown = { x: newest.x, y: newest.y };
      return;
    }

    if (mode === 'extrapolate') {
      if (received.length < 2) {
        shown = { x: newest.x, y: newest.y };
        return;
      }
      const prev = received[received.length - 2];
      const span = newest.sendTime - prev.sendTime || 1;
      const vx = (newest.x - prev.x) / span;
      const vy = (newest.y - prev.y) / span;
      const ahead = Math.min(now - newest.sendTime, 500);
      shown = { x: newest.x + vx * ahead, y: newest.y + vy * ahead };
      return;
    }

    // interpolate: render slightly in the past, between two known snapshots
    const bufferMs = 2 * (1000 / Number(controls.rate.value));
    const renderTime = now - Number(controls.latency.value) - bufferMs;
    for (let i = received.length - 1; i > 0; i--) {
      const a = received[i - 1];
      const b = received[i];
      if (a.sendTime <= renderTime && renderTime <= b.sendTime) {
        const t = (renderTime - a.sendTime) / (b.sendTime - a.sendTime || 1);
        shown = { x: lerp(a.x, b.x, t), y: lerp(a.y, b.y, t) };
        return;
      }
    }
    // buffer ran dry (heavy loss): hold the newest known position
    shown = { x: newest.x, y: newest.y };
  }

  function drawGrid(ctx) {
    ctx.fillStyle = '#111';
    ctx.fillRect(0, 0, W, H);
    ctx.strokeStyle = '#222';
    ctx.lineWidth = 1;
    for (let x = 0; x <= W; x += 40) {
      ctx.beginPath();
      ctx.moveTo(x, 0);
      ctx.lineTo(x, H);
      ctx.stroke();
    }
    for (let y = 0; y <= H; y += 40) {
      ctx.beginPath();
      ctx.moveTo(0, y);
      ctx.lineTo(W, y);
      ctx.stroke();
    }
  }

  function drawPlayer(ctx, p, fill, stroke, dashed) {
    ctx.beginPath();
    ctx.arc(p.x, p.y, RADIUS, 0, Math.PI * 2);
    if (fill) {
      ctx.fillStyle = fill;
      ctx.fill();
    }
    if (stroke) {
      ctx.setLineDash(dashed ? [4, 4] : []);
      ctx.strokeStyle = stroke;
      ctx.lineWidth = 2;
      ctx.stroke();
      ctx.setLineDash([]);
    }
  }

  function drawLabel(ctx, text) {
    ctx.fillStyle = '#bbb';
    ctx.font = '14px monospace';
    ctx.fillText(text, 10, 20);
  }

  function draw(now) {
    drawGrid(hostCtx);
    drawPlayer(hostCtx, pos, '#3a8cf2', null, false);
    drawLabel(hostCtx, manual ? 'authority (WASD)' : 'authority (hover + WASD to drive)');

    drawGrid(clientCtx);
    for (const p of received.slice(-30)) {
      clientCtx.fillStyle = 'rgba(255,255,255,0.18)';
      clientCtx.fillRect(p.x - 2, p.y - 2, 4, 4);
    }
    drawPlayer(clientCtx, pos, null, 'rgba(255,255,255,0.35)', true);
    drawPlayer(clientCtx, shown, '#e05a2a', null, false);
    drawLabel(clientCtx, 'remote view (dashed = true position)');

    const error = Math.hypot(pos.x - shown.x, pos.y - shown.y);
    stats.textContent =
      `sent ${sent}  lost ${lost}  arrived out of order ${outOfOrder}  ` +
      `in flight ${inFlight.length}  error ${error.toFixed(0)}px`;
  }

  let last = performance.now();
  function frame(now) {
    const dt = Math.min((now - last) / 1000, 0.05);
    last = now;
    stepAuthority(dt);
    sendUpdates(now);
    deliver(now);
    computeShown(now);
    draw(now);
    requestAnimationFrame(frame);
  }
  requestAnimationFrame(frame);
})();
