// Growing neural CA logo renderer (model + runtime live in /assets/nca).
import { createCA } from "./ca.js";

const MODEL_URL = new URL("./models/logo_regenerating.json", import.meta.url);
const ZOOM = 1.28; // crop in so the grown pattern fills most of the wrap
const SPEED = 2;
const BRUSH = 8;

async function main() {
  const canvas = document.getElementById("ca-canvas");
  if (!canvas) return;

  canvas.style.opacity = "0";
  canvas.style.background = "#f2f2f0";

  const payload = await fetch(MODEL_URL).then((r) => {
    if (!r.ok) throw new Error(`failed to load ${MODEL_URL}`);
    return r.json();
  });

  const layers = payload.layers || payload;
  const grid = payload.grid || [72, 70];
  const [logoW, logoH] = grid;

  // Distill createTensor(h,w) packs size=[w,h], so createCA([logoW,logoH])
  // runs in a swapped layout. Draw portrait, then CSS-orient to landscape.
  const simW = logoH;
  const simH = logoW;

  const scale = Math.max(4, Math.floor(520 / logoW));
  canvas.width = simW * scale;
  canvas.height = simH * scale;

  let wrap = canvas.closest(".ca-wrap");
  if (!wrap) {
    wrap = document.createElement("div");
    wrap.className = "ca-wrap";
    canvas.parentNode.insertBefore(wrap, canvas);
    wrap.appendChild(canvas);
  }

  wrap.style.maxWidth = "100%";
  wrap.style.position = "relative";
  wrap.style.display = "flex";
  wrap.style.alignItems = "center";
  wrap.style.justifyContent = "center";
  wrap.style.overflow = "hidden";
  wrap.style.background = "#f2f2f0";
  wrap.style.touchAction = "none";
  wrap.style.cursor = "crosshair";

  // Size pre-rotate so post-rotate AABB matches the wrap, then zoom in.
  canvas.style.position = "absolute";
  canvas.style.left = "50%";
  canvas.style.top = "50%";
  canvas.style.width = `${(logoH / logoW) * 100}%`;
  canvas.style.height = `${(logoW / logoH) * 100}%`;
  canvas.style.maxWidth = "none";
  canvas.style.aspectRatio = "auto";
  canvas.style.imageRendering = "pixelated";
  canvas.style.transform =
    `translate(-50%, -50%) scaleX(-1) rotate(90deg) scale(${ZOOM})`;
  canvas.style.transformOrigin = "center center";

  const gl = canvas.getContext("webgl", { alpha: false, antialias: false });
  if (!gl) return;

  gl.clearColor(242 / 255, 242 / 255, 240 / 255, 1);
  twgl.bindFramebufferInfo(gl);
  gl.clear(gl.COLOR_BUFFER_BIT);

  const ca = createCA(gl, layers, [simW, simH]);
  window.ca = ca;

  const seedYX = payload.seed_yx || [Math.floor(logoH / 2), Math.floor(logoW / 2)];
  ca.reset = () => {
    ca.paint(0, 0, 10000, "clear");
    ca.paint(seedYX[0], seedYX[1], 1, "seed");
  };
  ca.reset();

  function canvasToGrid(clientX, clientY) {
    // Undo zoom * scaleX(-1) * rotate(90deg) from landscape wrap coords.
    const rect = wrap.getBoundingClientRect();
    const vx = (clientX - rect.left) / rect.width;
    const vy = (clientY - rect.top) / rect.height;
    const cx = (vx - 0.5) / ZOOM + 0.5;
    const cy = (vy - 0.5) / ZOOM + 0.5;
    const simX = cy * simW;
    const simY = cx * simH;
    return [simX, simY];
  }

  function damageAt(clientX, clientY) {
    const [x, y] = canvasToGrid(clientX, clientY);
    ca.paint(x, y, BRUSH, "clear");
  }

  let drawing = false;
  wrap.addEventListener("pointerdown", (e) => {
    drawing = true;
    wrap.setPointerCapture(e.pointerId);
    damageAt(e.clientX, e.clientY);
  });
  wrap.addEventListener("pointermove", (e) => {
    if (drawing) damageAt(e.clientX, e.clientY);
  });
  wrap.addEventListener("pointerup", () => {
    drawing = false;
  });
  wrap.addEventListener("pointercancel", () => {
    drawing = false;
  });
  wrap.addEventListener("dblclick", (e) => {
    e.preventDefault();
    const [x, y] = canvasToGrid(e.clientX, e.clientY);
    ca.paint(x, y, 1, "seed");
  });

  let revealed = false;
  function frame() {
    for (let i = 0; i < SPEED; i++) ca.step();
    twgl.bindFramebufferInfo(gl);
    gl.clear(gl.COLOR_BUFFER_BIT);
    ca.draw();
    if (!revealed) {
      revealed = true;
      canvas.style.opacity = "1";
    }
    requestAnimationFrame(frame);
  }
  requestAnimationFrame(frame);
}

main().catch((err) => {
  console.error(err);
});
