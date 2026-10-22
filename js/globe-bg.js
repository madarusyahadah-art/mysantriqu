// Background 3D: Cloudflare-style dot-matrix globe + "DARUSY SYAHADAH FOR ALL" orbit banner.
// Menggunakan Three.js r160 ES Module.
import * as THREE from 'three';
import { FontLoader } from 'three/addons/loaders/FontLoader.js';
import { TextGeometry } from 'three/addons/geometries/TextGeometry.js';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';

const BANNER_TEXT = 'DARUSY SYAHADAH FOR ALL';

/* ---------- KONSTANTA & PALET ---------- */
const R = 1;
const CYAN = new THREE.Color('#38bdf8');
const AMBER = new THREE.Color('#f59e0b');
const TILT = THREE.MathUtils.degToRad(23.5);
const isTouch = matchMedia('(pointer: coarse)').matches;

/* ---------- RENDERER / SCENE / CAMERA ---------- */
const container = document.getElementById('bg-canvas-container');
const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
renderer.setSize(innerWidth, innerHeight);
renderer.setClearColor(0x060810, 0);
renderer.toneMapping = THREE.ACESFilmicToneMapping;
renderer.toneMappingExposure = 1.05;
container.appendChild(renderer.domElement);

const scene = new THREE.Scene();
// Kamera telephoto (FOV 18) untuk mencegah distorsi elips/gepeng di sudut layar desktop (1366x840 dsb)
const FOV = 18;
const TARGET_VISH = 2 * Math.tan(THREE.MathUtils.degToRad(45 / 2)) * 4.4; // 3.645
const camDist = (TARGET_VISH / 2) / Math.tan(THREE.MathUtils.degToRad(FOV / 2)); // ~11.51
const camera = new THREE.PerspectiveCamera(FOV, innerWidth / innerHeight, 0.1, 200);
let zoomTarget = 1, zoom = 1; // skala globe dari scroll/pinch
camera.position.set(0, 0, camDist);

const pmrem = new THREE.PMREMGenerator(renderer);
scene.environment = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;

scene.add(new THREE.AmbientLight(0x8899bb, 0.5));
const keyLight = new THREE.DirectionalLight(0xffffff, 2.2);
keyLight.position.set(3, 2, 4); scene.add(keyLight);
const warmLight = new THREE.PointLight(0xf59e0b, 6, 12);
warmLight.position.set(-3, -1.5, 2.5); scene.add(warmLight);

/* ---------- HIERARKI ---------- */
const globeRoot = new THREE.Group(); scene.add(globeRoot);
const tiltGroup = new THREE.Group(); tiltGroup.rotation.z = -TILT; globeRoot.add(tiltGroup);
const spinGroup = new THREE.Group(); tiltGroup.add(spinGroup);
const textGroup = new THREE.Group();
textGroup.rotation.y = 2.4; // Posisi awal agar huruf awal (DARUSY) masuk terlebih dahulu
const bannerTilt = new THREE.Group();
bannerTilt.add(textGroup);
globeRoot.add(bannerTilt);

/* ---------- STARFIELD ---------- */
const starGroup = new THREE.Group(); scene.add(starGroup);
{
  const N = 2600;
  const pos = new Float32Array(N * 3), col = new Float32Array(N * 3), siz = new Float32Array(N);
  const c = new THREE.Color();
  for (let i = 0; i < N; i++) {
    const r = 18 + Math.random() * 55;
    const t = Math.random() * Math.PI * 2, p = Math.acos(2 * Math.random() - 1);
    pos[i * 3] = r * Math.sin(p) * Math.cos(t);
    pos[i * 3 + 1] = r * Math.cos(p);
    pos[i * 3 + 2] = r * Math.sin(p) * Math.sin(t);
    const pick = Math.random();
    c.set(pick < 0.72 ? 0xdbeafe : pick < 0.9 ? 0x7dd3fc : 0xfcd34d);
    c.multiplyScalar(0.45 + Math.random() * 0.55);
    col[i * 3] = c.r; col[i * 3 + 1] = c.g; col[i * 3 + 2] = c.b;
    siz[i] = 0.5 + Math.random() * 1.7;
  }
  const g = new THREE.BufferGeometry();
  g.setAttribute('position', new THREE.BufferAttribute(pos, 3));
  g.setAttribute('color', new THREE.BufferAttribute(col, 3));
  g.setAttribute('aSize', new THREE.BufferAttribute(siz, 1));
  const m = new THREE.ShaderMaterial({
    transparent: true, depthWrite: false, blending: THREE.AdditiveBlending,
    uniforms: { uTime: { value: 0 } },
    vertexShader: `
      attribute float aSize; varying vec3 vC; varying float vTw;
      uniform float uTime;
      void main(){
        vC = color;
        vTw = 0.65 + 0.35 * sin(uTime * 1.5 + position.x * 12.0 + position.y * 7.0);
        vec4 mv = modelViewMatrix * vec4(position, 1.0);
        gl_PointSize = aSize * (95.0 / -mv.z);
        gl_Position = projectionMatrix * mv;
      }`,
    fragmentShader: `
      varying vec3 vC; varying float vTw;
      void main(){
        float d = length(gl_PointCoord - 0.5);
        float a = smoothstep(0.5, 0.05, d);
        gl_FragColor = vec4(vC, a * vTw);
      }`,
    vertexColors: true
  });
  starGroup.add(new THREE.Points(g, m));
  starGroup.userData.mat = m;
}

/* ---------- INTI BOLA & ATMOSFER ---------- */
spinGroup.add(new THREE.Mesh(
  new THREE.SphereGeometry(R * 0.992, 64, 64),
  new THREE.MeshPhongMaterial({ color: 0x0a1226, emissive: 0x050b1c, shininess: 8, specular: 0x113355 })
));

tiltGroup.add(new THREE.Mesh(
  new THREE.SphereGeometry(R * 1.16, 64, 64),
  new THREE.ShaderMaterial({
    side: THREE.BackSide, transparent: true, depthWrite: false, blending: THREE.AdditiveBlending,
    vertexShader: `
      varying vec3 vN;
      void main(){ vN = normalize(normalMatrix * normal);
        gl_Position = projectionMatrix * modelViewMatrix * vec4(position,1.0); }`,
    fragmentShader: `
      varying vec3 vN;
      void main(){
        float i = pow(max(0.62 - dot(vN, vec3(0.,0.,1.)), 0.0), 3.2);
        vec3 col = mix(vec3(0.22,0.74,0.97), vec3(0.96,0.62,0.04), 0.18);
        gl_FragColor = vec4(col, 1.0) * i * 1.35;
      }`
  })
));

tiltGroup.add(new THREE.Mesh(
  new THREE.SphereGeometry(R * 1.004, 64, 64),
  new THREE.ShaderMaterial({
    transparent: true, depthWrite: false, blending: THREE.AdditiveBlending,
    vertexShader: `
      varying vec3 vN; varying vec3 vV;
      void main(){
        vN = normalize(normalMatrix * normal);
        vec4 mv = modelViewMatrix * vec4(position,1.0);
        vV = normalize(-mv.xyz);
        gl_Position = projectionMatrix * mv; }`,
    fragmentShader: `
      varying vec3 vN; varying vec3 vV;
      void main(){
        float f = pow(1.0 - abs(dot(vN, vV)), 3.5);
        gl_FragColor = vec4(0.22, 0.74, 0.97, f * 0.55);
      }`
  })
));

/* ---------- DOT-MATRIX WORLD MAP ---------- */
const MASK_URLS = [
  'js/earth_specular.jpg',
  'https://unpkg.com/three-globe@2.31.0/example/img/earth-water.png',
  'https://cdn.jsdelivr.net/npm/three@0.160.0/examples/textures/planets/earth_specular_2048.jpg'
];

function loadMask(index = 0) {
  return new Promise(resolve => {
    if (index >= MASK_URLS.length) return resolve(null);
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = () => {
      try {
        const w = 720, h = 360;
        const cv = document.createElement('canvas'); cv.width = w; cv.height = h;
        const cx = cv.getContext('2d', { willReadFrequently: true });
        cx.drawImage(img, 0, 0, w, h);
        resolve({ data: cx.getImageData(0, 0, w, h).data, w, h });
      } catch (e) { resolve(loadMask(index + 1)); }
    };
    img.onerror = () => resolve(loadMask(index + 1));
    img.src = MASK_URLS[index];
  });
}

function latLonToVec3(lat, lon, r) {
  const phi = (90 - lat) * (Math.PI / 180);
  const theta = (lon + 180) * (Math.PI / 180);
  return new THREE.Vector3(
    -r * Math.sin(phi) * Math.cos(theta),
    r * Math.cos(phi),
    r * Math.sin(phi) * Math.sin(theta)
  );
}

function buildDots(mask) {
  const N_LAT = 150;
  const positions = [], colors = [], scales = [];
  const cCyan = CYAN.clone(), cAmber = AMBER.clone(), cDim = new THREE.Color(0x0f223f);
  const c = new THREE.Color();

  for (let i = 0; i < N_LAT; i++) {
    const lat = 90 - (180 * (i + 0.5)) / N_LAT;
    const circ = 2 * Math.PI * R * Math.cos(lat * Math.PI / 180);
    const count = Math.max(1, Math.round(circ * (N_LAT / Math.PI)));
    for (let j = 0; j < count; j++) {
      const lon = -180 + (360 * j) / count;
      let isLand = false;
      if (mask) {
        const u = ((lon + 180) / 360) * mask.w;
        const v = ((90 - lat) / 180) * mask.h;
        const idx = (Math.floor(v) * mask.w + Math.floor(u)) * 4;
        const lum = (mask.data[idx] + mask.data[idx + 1] + mask.data[idx + 2]) / 3;
        isLand = lum < 128;
      } else {
        const a = Math.sin(lat * 0.1) * Math.cos(lon * 0.1);
        isLand = a > 0.05;
      }
      if (!isLand && Math.random() > 0.035) continue;

      const p = latLonToVec3(lat, lon, R * 1.001);
      positions.push(p.x, p.y, p.z);

      if (isLand) {
        const pick = Math.random();
        if (pick < 0.82) c.copy(cCyan).multiplyScalar(0.75 + Math.random() * 0.35);
        else if (pick < 0.94) c.copy(cAmber).multiplyScalar(0.9 + Math.random() * 0.3);
        else c.set(0xffffff);
        scales.push(1.0 + Math.random() * 0.5);
      } else {
        c.copy(cDim);
        scales.push(0.45);
      }
      colors.push(c.r, c.g, c.b);
    }
  }

  const g = new THREE.BufferGeometry();
  g.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
  g.setAttribute('color', new THREE.Float32BufferAttribute(colors, 3));
  g.setAttribute('aScale', new THREE.Float32BufferAttribute(scales, 1));

  const mat = new THREE.ShaderMaterial({
    transparent: true, depthWrite: false, blending: THREE.AdditiveBlending,
    uniforms: { uTime: { value: 0 }, uScale: { value: 600 } },
    vertexShader: `
      attribute float aScale; varying vec3 vC; uniform float uTime; uniform float uScale;
      void main(){
        vC = color;
        vec4 mv = modelViewMatrix * vec4(position, 1.0);
        float tw = 0.85 + 0.15 * sin(uTime * 2.0 + position.x * 20.0 + position.y * 30.0);
        gl_PointSize = aScale * tw * (uScale / -mv.z) * 0.0055;
        gl_Position = projectionMatrix * mv;
      }`,
    fragmentShader: `
      varying vec3 vC;
      void main(){
        float d = length(gl_PointCoord - 0.5);
        if (d > 0.5) discard;
        float a = smoothstep(0.5, 0.15, d);
        gl_FragColor = vec4(vC, a * 0.9);
      }`,
    vertexColors: true
  });
  spinGroup.add(new THREE.Points(g, mat));
  return mat;
}

/* ---------- KOTA / NODE & NETWORK ARCS ---------- */
const NODES = {
  solo:     { lat: -7.56, lon: 110.82, col: AMBER, name: 'Darusy Syahadah / Solo' },
  jakarta:  { lat: -6.20, lon: 106.84, col: CYAN,  name: 'Jakarta' },
  mecca:    { lat: 21.42, lon:  39.82, col: AMBER, name: 'Mecca' },
  tokyo:    { lat: 35.67, lon: 139.65, col: CYAN,  name: 'Tokyo' },
  london:   { lat: 51.50, lon:  -0.12, col: CYAN,  name: 'London' },
  newyork:  { lat: 40.71, lon: -74.00, col: CYAN,  name: 'New York' },
  sydney:   { lat:-33.86, lon: 151.20, col: CYAN,  name: 'Sydney' },
  cairo:    { lat: 30.04, lon:  31.23, col: AMBER, name: 'Cairo' }
};

function glowTexture(inner = 'rgba(255,255,255,1)', outer = 'rgba(255,255,255,0)') {
  const cv = document.createElement('canvas'); cv.width = cv.height = 128;
  const cx = cv.getContext('2d');
  const g = cx.createRadialGradient(64, 64, 0, 64, 64, 64);
  g.addColorStop(0, inner); g.addColorStop(0.35, inner); g.addColorStop(1, outer);
  cx.fillStyle = g; cx.fillRect(0, 0, 128, 128);
  const tex = new THREE.CanvasTexture(cv);
  tex.colorSpace = THREE.SRGBColorSpace;
  return tex;
}
const glowTex = glowTexture('rgba(255,255,255,1)', 'rgba(56,189,248,0)');
const amberGlowTex = glowTexture('rgba(255,255,255,1)', 'rgba(245,158,11,0)');

const ripples = [];
function spawnRipple(posLocal, color) {
  const geom = new THREE.RingGeometry(0.005, 0.015, 32);
  const mat = new THREE.MeshBasicMaterial({
    color, transparent: true, opacity: 0.95, side: THREE.DoubleSide, depthWrite: false,
    blending: THREE.AdditiveBlending
  });
  const mesh = new THREE.Mesh(geom, mat);
  mesh.position.copy(posLocal);
  mesh.quaternion.setFromUnitVectors(new THREE.Vector3(0, 0, 1), posLocal.clone().normalize());
  spinGroup.add(mesh);
  ripples.push({ mesh, t: 0, life: 1.2 });
}

function updateRipples(dt) {
  for (let i = ripples.length - 1; i >= 0; i--) {
    const r = ripples[i];
    r.t += dt / r.life;
    if (r.t >= 1) {
      spinGroup.remove(r.mesh);
      r.mesh.geometry.dispose(); r.mesh.material.dispose();
      ripples.splice(i, 1);
      continue;
    }
    const s = 0.02 + r.t * 0.16;
    r.mesh.scale.setScalar(s);
    r.mesh.material.opacity = (1 - r.t) * 0.9;
  }
}

for (const k in NODES) {
  const n = NODES[k];
  const p = latLonToVec3(n.lat, n.lon, R * 1.002);
  n.pos = p;

  const spr = new THREE.Sprite(new THREE.SpriteMaterial({
    map: n.col === AMBER ? amberGlowTex : glowTex,
    color: n.col, transparent: true, blending: THREE.AdditiveBlending, depthWrite: false
  }));
  spr.position.copy(p); spr.scale.setScalar(0.055);
  spinGroup.add(spr);
  const core = new THREE.Mesh(new THREE.SphereGeometry(0.008, 8, 8),
    new THREE.MeshBasicMaterial({ color: 0xfff7e0 }));
  core.position.copy(p); spinGroup.add(core);
}

const ROUTES = [
  ['solo', 'jakarta', AMBER, 0.55],
  ['solo', 'mecca',   AMBER, 0.40],
  ['mecca', 'cairo',  AMBER, 0.50],
  ['cairo', 'london', CYAN,  0.45],
  ['london', 'newyork', CYAN, 0.45],
  ['solo', 'tokyo',   CYAN,  0.45],
  ['tokyo', 'newyork',CYAN,  0.35],
  ['solo', 'sydney',  CYAN,  0.45]
];

const arcs = [];
const ARC_PTS = 64;

for (let i = 0; i < ROUTES.length; i++) {
  const [k1, k2, col, speed] = ROUTES[i];
  const p1 = NODES[k1].pos, p2 = NODES[k2].pos;

  const mid = p1.clone().add(p2).multiplyScalar(0.5);
  const d = p1.distanceTo(p2);
  const alt = R + d * 0.34;
  mid.normalize().multiplyScalar(alt);

  const curve = new THREE.QuadraticBezierCurve3(p1, mid, p2);
  const pts = curve.getPoints(ARC_PTS);

  const geom = new THREE.BufferGeometry().setFromPoints(pts);
  const baseLine = new THREE.Line(geom, new THREE.LineBasicMaterial({
    color: col, transparent: true, opacity: 0.22, blending: THREE.AdditiveBlending, depthWrite: false
  }));
  spinGroup.add(baseLine);

  const TRAIL_LEN = 12;
  const trailGeom = new THREE.BufferGeometry();
  const trailPositions = new Float32Array(TRAIL_LEN * 3);
  const trailColors = new Float32Array(TRAIL_LEN * 3);
  for (let k = 0; k < TRAIL_LEN; k++) {
    const alpha = (k / (TRAIL_LEN - 1));
    const c = col.clone().lerp(new THREE.Color(0xffffff), alpha * 0.8);
    trailColors[k * 3] = c.r; trailColors[k * 3 + 1] = c.g; trailColors[k * 3 + 2] = c.b;
  }
  trailGeom.setAttribute('position', new THREE.BufferAttribute(trailPositions, 3));
  trailGeom.setAttribute('color', new THREE.BufferAttribute(trailColors, 3));
  const trailLine = new THREE.Line(trailGeom, new THREE.LineBasicMaterial({
    vertexColors: true, transparent: true, opacity: 0.95, blending: THREE.AdditiveBlending, depthWrite: false
  }));
  spinGroup.add(trailLine);

  const head = new THREE.Sprite(new THREE.SpriteMaterial({
    map: glowTex, color: 0xffffff, transparent: true, blending: THREE.AdditiveBlending, depthWrite: false
  }));
  head.scale.setScalar(0.075); head.visible = false;
  spinGroup.add(head);

  arcs.push({
    curve, trailLine, head, targetPos: p2, col,
    progress: (i / ROUTES.length),
    speed: speed * 0.22,
    trailLen: TRAIL_LEN
  });
}

function updateArcs(dt) {
  for (const a of arcs) {
    const prev = a.progress;
    a.progress += dt * a.speed;
    if (prev < 1 && a.progress >= 1) {
      spawnRipple(a.targetPos, a.col);
    }
    if (a.progress > 1.25) {
      a.progress = 0;
    }
    if (a.progress >= 0 && a.progress <= 1) {
      a.head.visible = true;
      const cur = a.curve.getPoint(a.progress);
      a.head.position.copy(cur);
      const pos = a.trailLine.geometry.attributes.position.array;
      for (let k = 0; k < a.trailLen; k++) {
        const t = Math.max(0, a.progress - (a.trailLen - 1 - k) * 0.012);
        const p = a.curve.getPoint(t);
        pos[k * 3] = p.x; pos[k * 3 + 1] = p.y; pos[k * 3 + 2] = p.z;
      }
      a.trailLine.geometry.attributes.position.needsUpdate = true;
      a.trailLine.visible = true;
      const s = 0.06 + 0.025 * Math.sin(a.progress * Math.PI);
      a.head.scale.setScalar(s);
    } else a.head.visible = false;
  }
}

/* ---------- TEKS 3D "DARUSY SYAHADAH FOR ALL" (ORBIT RING) ---------- */
const TEXT_R = R * 1.35;
const frontMat = new THREE.MeshStandardMaterial({ color: 0xffffff, roughness: 0.28, metalness: 0.15 });
const bevelMat = new THREE.MeshStandardMaterial({ color: 0xf59e0b, roughness: 0.22, metalness: 0.95, emissive: 0xb45309, emissiveIntensity: 0.12 });

function placeOnArc(mesh, theta) {
  mesh.position.set(Math.sin(theta) * TEXT_R, 0, Math.cos(theta) * TEXT_R);
  mesh.rotation.y = theta;
}

function buildText(font) {
  const chars = BANNER_TEXT.split('');
  // Ukuran font tebal besar melingkari globe seperti orbit
  const size = 0.28, gap = 0.045;
  const items = [];
  let totalW = 0;
  for (const ch of chars) {
    if (ch === ' ') { items.push({ w: size * 0.5 }); totalW += size * 0.5 + gap; continue; }
    const geo = new TextGeometry(ch, {
      font, size, height: 0.07, curveSegments: 8,
      bevelEnabled: true, bevelThickness: 0.016, bevelSize: 0.010, bevelSegments: 3
    });
    geo.computeBoundingBox();
    const bb = geo.boundingBox;
    const w = bb.max.x - bb.min.x, h = bb.max.y - bb.min.y;
    geo.translate(-bb.min.x - w / 2, -bb.min.y - h / 2, -0.035);
    items.push({ mesh: new THREE.Mesh(geo, [frontMat, bevelMat]), w });
    totalW += w + gap;
  }
  totalW -= gap;
  let cursor = -totalW / 2;
  for (const it of items) {
    const center = cursor + it.w / 2;
    if (it.mesh) { placeOnArc(it.mesh, center / TEXT_R); textGroup.add(it.mesh); }
    cursor += it.w + gap;
  }
}

function buildTextFallback() {
  const chars = BANNER_TEXT.split('');
  const wUnit = 0.24, gap = 0.035;
  const total = chars.length * (wUnit + gap) - gap;
  let cursor = -total / 2;
  for (const ch of chars) {
    if (ch !== ' ') {
      const cv = document.createElement('canvas'); cv.width = 128; cv.height = 160;
      const cx = cv.getContext('2d');
      cx.font = '900 120px Arial, sans-serif';
      cx.textAlign = 'center'; cx.textBaseline = 'middle';
      cx.lineWidth = 10; cx.strokeStyle = '#b45309'; cx.strokeText(ch, 64, 84);
      cx.fillStyle = '#ffffff'; cx.fillText(ch, 64, 84);
      const tex = new THREE.CanvasTexture(cv); tex.colorSpace = THREE.SRGBColorSpace;
      const mesh = new THREE.Mesh(new THREE.PlaneGeometry(wUnit, wUnit * 1.25),
        new THREE.MeshBasicMaterial({ map: tex, transparent: true, side: THREE.DoubleSide }));
      placeOnArc(mesh, (cursor + wUnit / 2) / TEXT_R);
      textGroup.add(mesh);
    }
    cursor += wUnit + gap;
  }
}

const FONT_URL = 'https://cdn.jsdelivr.net/npm/three@0.160.0/examples/fonts/helvetiker_bold.typeface.json';
new FontLoader().load(FONT_URL, buildText, undefined, buildTextFallback);

/* ---------- PARALLAX, DRAG & ZOOM ---------- */
const pointer = { x: 0, y: 0 };
if (!isTouch) {
  addEventListener('pointermove', e => {
    pointer.x = (e.clientX / innerWidth) * 2 - 1;
    pointer.y = (e.clientY / innerHeight) * 2 - 1;
  }, { passive: true });
}

// rotasi manual (drag) + inersia
let dragging = false, lastX = 0, lastY = 0;
let dragRotY = 0, dragRotX = 0, velY = 0, velX = 0;

const interactiveTarget = t =>
  t instanceof Element && t.closest('a, button, input, textarea, select, .code-box');

addEventListener('pointerdown', e => {
  if (e.pointerType === 'touch' || e.button !== 0 || interactiveTarget(e.target)) return;
  dragging = true; lastX = e.clientX; lastY = e.clientY;
  velY = velX = 0;
}, { passive: true });

addEventListener('pointermove', e => {
  if (!dragging) return;
  const dx = e.clientX - lastX, dy = e.clientY - lastY;
  lastX = e.clientX; lastY = e.clientY;
  velY = dx * 0.005; velX = dy * 0.003;
  dragRotY += velY;
  dragRotX = THREE.MathUtils.clamp(dragRotX + velX, -0.5, 0.5);
}, { passive: true });

const endDrag = () => { dragging = false; };
addEventListener('pointerup', endDrag);
addEventListener('pointercancel', endDrag);

// scroll = zoom (halaman tetap bisa di-scroll bila memang panjang)
addEventListener('wheel', e => {
  const pageScrolls = document.documentElement.scrollHeight > innerHeight + 8;
  if (pageScrolls && interactiveTarget(e.target)) return;
  e.preventDefault();
  zoomTarget = THREE.MathUtils.clamp(zoomTarget * (1 - e.deltaY * 0.0012), 0.45, 2.2);
}, { passive: false });

// pinch = zoom (touch)
let pinchStart = null;
addEventListener('touchstart', e => {
  if (e.touches.length !== 2) return;
  pinchStart = {
    dist: Math.hypot(e.touches[0].clientX - e.touches[1].clientX, e.touches[0].clientY - e.touches[1].clientY),
    zoom: zoomTarget
  };
}, { passive: true });
addEventListener('touchmove', e => {
  if (!pinchStart || e.touches.length !== 2) return;
  const d = Math.hypot(e.touches[0].clientX - e.touches[1].clientX, e.touches[0].clientY - e.touches[1].clientY);
  zoomTarget = THREE.MathUtils.clamp(pinchStart.zoom * (d / Math.max(pinchStart.dist, 1)), 0.45, 2.2);
}, { passive: true });

/* ---------- LAYOUT ---------- */
let dotsMat = null;
let baseScale = 1;
const wrapper = document.querySelector('.wrapper');

function layout() {
  const w = innerWidth, h = innerHeight;
  renderer.setSize(w, h);
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  camera.aspect = w / h;
  camera.updateProjectionMatrix();

  const visH = 2 * Math.tan(THREE.MathUtils.degToRad(camera.fov / 2)) * camDist;
  const visW = visH * camera.aspect;
  const pxToWorld = visH / h;

  const desktop = w >= 900 && w > h;

  if (desktop) {
    // pojok kanan atas card; diperbesar (Zoom In) agar terlihat megah dan bulat sempurna
    const rect = wrapper
      ? wrapper.getBoundingClientRect()
      : { top: h * 0.5 - 300, right: w * 0.5 + 390 };
    const targetX = Math.min(rect.right + 60, w - 160);
    const targetY = Math.max(rect.top + 120, 120);
    globeRoot.position.set((targetX - w / 2) * pxToWorld, -(targetY - h / 2) * pxToWorld, 0);
    baseScale = Math.min(1.05, Math.max(0.72, Math.min(w, h) / 950));
    bannerTilt.rotation.z = -0.18;
  } else {
    // setengah atas layar; banner dimiringkan 45°
    globeRoot.position.set(0, h * 0.5 * pxToWorld * 0.52, 0);
    baseScale = Math.min(0.62, Math.max(0.34, visW * 0.42));
    bannerTilt.rotation.z = -Math.PI / 4;
  }
  if (dotsMat) dotsMat.uniforms.uScale.value = h * 0.5;
}
addEventListener('resize', layout);

/* ---------- RENDER LOOP ---------- */
const clock = new THREE.Clock();
let elapsed = 0;

function animate() {
  requestAnimationFrame(animate);
  const dt = Math.min(clock.getDelta(), 0.05);
  elapsed += dt;

  // 1. Bumi berputar dari kiri ke kanan
  spinGroup.rotation.y -= dt * 0.055;

  // 2. Teks berputar dari kanan ke kiri sehingga huruf kiri (DARUSY) terbaca terlebih dahulu
  textGroup.rotation.y -= dt * 0.11;

  // inersia drag
  if (!dragging) {
    dragRotY += velY;
    dragRotX += velX;
    velY *= 0.94;
    velX *= 0.94;
  }
  tiltGroup.rotation.y = dragRotY;
  tiltGroup.rotation.x = dragRotX;

  // bintang twinkling & rotasi lembut
  if (starGroup.userData.mat) starGroup.userData.mat.uniforms.uTime.value = elapsed;
  starGroup.rotation.y = elapsed * 0.005;

  if (dotsMat) dotsMat.uniforms.uTime.value = elapsed;

  // zoom smoothing
  zoom += (zoomTarget - zoom) * 0.12;
  globeRoot.scale.setScalar(baseScale * zoom);

  camera.position.x += (pointer.x * 0.12 - camera.position.x) * 0.05;
  camera.position.y += (-pointer.y * 0.08 - camera.position.y) * 0.05;
  camera.position.z = camDist;
  camera.lookAt(0, 0, 0);

  updateArcs(dt);
  updateRipples(dt);

  renderer.render(scene, camera);
}

/* ---------- BOOT ---------- */
(async function init() {
  dotsMat = buildDots(await loadMask());
  layout();
  animate();
})();
