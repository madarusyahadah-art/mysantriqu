// Background 3D: Cloudflare-style dot-matrix globe + "DS FOR ALL" banner.
// Diadaptasi dari globe-cf-1.html (tanpa rel emas atas/bawah, tanpa interaksi drag).
import * as THREE from 'three';
import { FontLoader } from 'three/addons/loaders/FontLoader.js';
import { TextGeometry } from 'three/addons/geometries/TextGeometry.js';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';

const BANNER_TEXT = 'DS FOR ALL';

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
const canvas = renderer.domElement;

const scene = new THREE.Scene();
const camera = new THREE.PerspectiveCamera(45, innerWidth / innerHeight, 0.1, 200);
let zoomTarget = 1, zoom = 1;          // skala globe dari scroll/pinch
const camDist = 4.4;
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
  const phi = THREE.MathUtils.degToRad(90 - lat);
  const theta = THREE.MathUtils.degToRad(lon + 180);
  return new THREE.Vector3(
    -r * Math.sin(phi) * Math.cos(theta),
    r * Math.cos(phi),
    r * Math.sin(phi) * Math.sin(theta)
  );
}

function buildDots(mask) {
  const positions = [], colors = [], sizes = [];
  const cTmp = new THREE.Color();
  const step = 1.55;
  for (let lat = -84; lat <= 84; lat += step) {
    const cosLat = Math.cos(THREE.MathUtils.degToRad(lat));
    const lonStep = step / Math.max(cosLat, 0.12);
    for (let lon = -180; lon < 180; lon += lonStep) {
      let land = true;
      if (mask) {
        const u = Math.floor(((lon + 180) / 360) * mask.w) % mask.w;
        const v = Math.min(mask.h - 1, Math.floor(((90 - lat) / 180) * mask.h));
        land = mask.data[(v * mask.w + u) * 4] < 110;
      }
      if (!land) continue;
      const p = latLonToVec3(lat, lon, R * 1.001);
      positions.push(p.x, p.y, p.z);
      const wave = 0.5 + 0.5 * Math.sin(lon * 0.045 + lat * 0.09);
      const mixv = THREE.MathUtils.clamp(Math.pow(wave, 2.4) * 0.85 + Math.random() * 0.28 - 0.12, 0, 1);
      cTmp.copy(CYAN).lerp(AMBER, mixv).multiplyScalar(0.7 + Math.random() * 0.5);
      colors.push(cTmp.r, cTmp.g, cTmp.b);
      sizes.push(0.85 + Math.random() * 0.75 + mixv * 0.35);
    }
  }
  const g = new THREE.BufferGeometry();
  g.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
  g.setAttribute('color', new THREE.Float32BufferAttribute(colors, 3));
  g.setAttribute('aSize', new THREE.Float32BufferAttribute(sizes, 1));
  const m = new THREE.ShaderMaterial({
    transparent: true, depthWrite: false, blending: THREE.AdditiveBlending, vertexColors: true,
    uniforms: { uScale: { value: innerHeight * 0.5 } },
    vertexShader: `
      attribute float aSize; varying vec3 vC; uniform float uScale;
      void main(){
        vC = color;
        vec4 mv = modelViewMatrix * vec4(position, 1.0);
        gl_PointSize = aSize * 0.0135 * (uScale / -mv.z);
        gl_Position = projectionMatrix * mv;
      }`,
    fragmentShader: `
      varying vec3 vC;
      void main(){
        float d = length(gl_PointCoord - 0.5);
        float a = smoothstep(0.5, 0.18, d);
        if (a < 0.02) discard;
        gl_FragColor = vec4(vC, a);
      }`
  });
  spinGroup.add(new THREE.Points(g, m));
  return m;
}

/* ---------- KOTA, BUSUR, ROKET DATA ---------- */
const CITIES = {
  solo:      { lat: -7.57,  lon: 110.82 },
  makkah:    { lat: 21.42,  lon: 39.83  },
  tokyo:     { lat: 35.68,  lon: 139.69 },
  london:    { lat: 51.50,  lon: -0.12  },
  newyork:   { lat: 40.71,  lon: -74.00 },
  sydney:    { lat: -33.87, lon: 151.21 },
  sf:        { lat: 37.77,  lon: -122.42 },
  singapore: { lat: 1.35,   lon: 103.82 }
};
const ROUTES = [
  ['solo', 'makkah'], ['solo', 'tokyo'], ['solo', 'sydney'], ['solo', 'singapore'],
  ['makkah', 'london'], ['london', 'newyork'], ['newyork', 'sf'],
  ['sf', 'tokyo'], ['tokyo', 'sydney'], ['london', 'singapore'], ['newyork', 'solo']
];

function glowTexture(inner = 'rgba(255,255,255,1)', outer = 'rgba(255,255,255,0)') {
  const s = 64, cv = document.createElement('canvas'); cv.width = cv.height = s;
  const cx = cv.getContext('2d');
  const gr = cx.createRadialGradient(s / 2, s / 2, 0, s / 2, s / 2, s / 2);
  gr.addColorStop(0, inner); gr.addColorStop(0.25, inner); gr.addColorStop(1, outer);
  cx.fillStyle = gr; cx.fillRect(0, 0, s, s);
  const t = new THREE.CanvasTexture(cv); t.colorSpace = THREE.SRGBColorSpace;
  return t;
}
const glowTex = glowTexture();

for (const k in CITIES) {
  const p = latLonToVec3(CITIES[k].lat, CITIES[k].lon, R * 1.004);
  const spr = new THREE.Sprite(new THREE.SpriteMaterial({
    map: glowTex, color: AMBER, transparent: true, depthWrite: false,
    blending: THREE.AdditiveBlending, opacity: 0.95
  }));
  spr.position.copy(p); spr.scale.setScalar(0.055);
  spinGroup.add(spr);
  const core = new THREE.Mesh(new THREE.SphereGeometry(0.008, 8, 8),
    new THREE.MeshBasicMaterial({ color: 0xfff7e0 }));
  core.position.copy(p); spinGroup.add(core);
}

const ripples = [];
const rippleGeo = new THREE.RingGeometry(0.86, 1, 48);
function spawnRipple(posLocal, color) {
  let r = ripples.find(r => !r.active);
  if (!r) {
    r = {
      mesh: new THREE.Mesh(rippleGeo, new THREE.MeshBasicMaterial({
        transparent: true, depthWrite: false, side: THREE.DoubleSide, blending: THREE.AdditiveBlending
      })),
      t: 0, active: false
    };
    spinGroup.add(r.mesh); ripples.push(r);
  }
  r.active = true; r.t = 0;
  r.mesh.material.color.copy(color);
  r.mesh.position.copy(posLocal).multiplyScalar(1.003);
  r.mesh.quaternion.setFromUnitVectors(new THREE.Vector3(0, 0, 1), posLocal.clone().normalize());
  r.mesh.visible = true;
}
function updateRipples(dt) {
  for (const r of ripples) {
    if (!r.active) continue;
    r.t += dt / 1.1;
    if (r.t >= 1) { r.active = false; r.mesh.visible = false; continue; }
    const s = 0.02 + r.t * 0.16;
    r.mesh.scale.setScalar(s);
    r.mesh.material.opacity = (1 - r.t) * 0.9;
  }
}

const arcs = [];
ROUTES.forEach(([a, b], i) => {
  const pa = latLonToVec3(CITIES[a].lat, CITIES[a].lon, R * 1.005);
  const pb = latLonToVec3(CITIES[b].lat, CITIES[b].lon, R * 1.005);
  const dist = pa.distanceTo(pb);
  const mid = pa.clone().add(pb).multiplyScalar(0.5).normalize()
    .multiplyScalar(R * (1 + 0.18 + dist * 0.28));
  const curve = new THREE.QuadraticBezierCurve3(pa, mid, pb);

  const SEG = 90;
  const pts = curve.getPoints(SEG);
  const g = new THREE.BufferGeometry().setFromPoints(pts);
  const tArr = new Float32Array(SEG + 1);
  for (let j = 0; j <= SEG; j++) tArr[j] = j / SEG;
  g.setAttribute('aT', new THREE.BufferAttribute(tArr, 1));

  const color = i % 3 === 0 ? AMBER.clone() : CYAN.clone();
  const mat = new THREE.ShaderMaterial({
    transparent: true, depthWrite: false, blending: THREE.AdditiveBlending,
    uniforms: {
      uProgress: { value: -0.5 },
      uColor: { value: color },
      uTail: { value: 0.22 }
    },
    vertexShader: `
      attribute float aT; varying float vT;
      void main(){ vT = aT;
        gl_Position = projectionMatrix * modelViewMatrix * vec4(position,1.0); }`,
    fragmentShader: `
      uniform float uProgress; uniform vec3 uColor; uniform float uTail;
      varying float vT;
      void main(){
        float d = uProgress - vT;
        float tail = (d >= 0.0) ? smoothstep(uTail, 0.0, d) : 0.0;
        float head = (d >= 0.0) ? smoothstep(0.02, 0.0, d) : 0.0;
        float base = 0.09;
        float a = base + tail * 0.85 + head * 0.6;
        vec3 col = uColor * (0.55 + tail * 1.8) + vec3(1.0) * head * 0.8;
        gl_FragColor = vec4(col, a);
      }`
  });
  spinGroup.add(new THREE.Line(g, mat));

  const head = new THREE.Sprite(new THREE.SpriteMaterial({
    map: glowTex, color: color.clone().lerp(new THREE.Color(0xffffff), 0.5),
    transparent: true, depthWrite: false, blending: THREE.AdditiveBlending
  }));
  head.scale.setScalar(0.075); head.visible = false;
  spinGroup.add(head);

  arcs.push({
    curve, mat, head, dest: pb, color,
    progress: -Math.random() * 2.5,
    speed: 0.28 + Math.random() * 0.22,
    pause: 0
  });
});

function updateArcs(dt) {
  for (const a of arcs) {
    if (a.pause > 0) { a.pause -= dt; a.head.visible = false; a.mat.uniforms.uProgress.value = -1; continue; }
    a.progress += dt * a.speed;
    if (a.progress >= 1) {
      spawnRipple(a.dest, a.color);
      a.progress = 0; a.pause = 0.6 + Math.random() * 2.2;
    }
    a.mat.uniforms.uProgress.value = a.progress;
    if (a.progress >= 0 && a.progress <= 1) {
      a.head.visible = true;
      a.head.position.copy(a.curve.getPoint(a.progress));
      const s = 0.06 + 0.025 * Math.sin(a.progress * Math.PI);
      a.head.scale.setScalar(s);
    } else a.head.visible = false;
  }
}

/* ---------- TEKS 3D "DS FOR ALL" ---------- */
const TEXT_R = R * 1.34;
const frontMat = new THREE.MeshStandardMaterial({ color: 0xffffff, roughness: 0.28, metalness: 0.15 });
const bevelMat = new THREE.MeshStandardMaterial({ color: 0xf59e0b, roughness: 0.22, metalness: 0.95, emissive: 0xb45309, emissiveIntensity: 0.12 });

function placeOnArc(mesh, theta) {
  mesh.position.set(Math.sin(theta) * TEXT_R, 0, Math.cos(theta) * TEXT_R);
  mesh.rotation.y = theta;
}

function buildText(font) {
  const chars = BANNER_TEXT.split('');
  const size = 0.30, gap = 0.055;
  const items = [];
  let totalW = 0;
  for (const ch of chars) {
    if (ch === ' ') { items.push({ w: size * 0.5 }); totalW += size * 0.5 + gap; continue; }
    const geo = new TextGeometry(ch, {
      font, size, height: 0.075, curveSegments: 8,
      bevelEnabled: true, bevelThickness: 0.018, bevelSize: 0.011, bevelSegments: 3
    });
    geo.computeBoundingBox();
    const bb = geo.boundingBox;
    const w = bb.max.x - bb.min.x, h = bb.max.y - bb.min.y;
    geo.translate(-bb.min.x - w / 2, -bb.min.y - h / 2, -0.0375);
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
  const wUnit = 0.26, gap = 0.03;
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
addEventListener('touchend', e => { if (e.touches.length < 2) pinchStart = null; }, { passive: true });

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
    // pojok kanan atas card
    const rect = wrapper
      ? wrapper.getBoundingClientRect()
      : { top: h * 0.5 - 300, right: w * 0.5 + 390 };
    const targetX = Math.min(rect.right + 20, w - 120);
    const targetY = Math.max(rect.top + 120, 120);
    globeRoot.position.set((targetX - w / 2) * pxToWorld, -(targetY - h / 2) * pxToWorld, 0);
    baseScale = Math.min(0.72, Math.max(0.42, Math.min(w, h) / 1500));
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

  spinGroup.rotation.y -= dt * 0.055;
  textGroup.rotation.y += dt * 0.14;

  // inersia drag
  if (!dragging) {
    dragRotY += velY; velY *= 0.94;
    dragRotX = THREE.MathUtils.clamp(dragRotX + velX, -0.5, 0.5); velX *= 0.9;
  }
  globeRoot.rotation.y += (dragRotY - globeRoot.rotation.y) * 0.12;
  globeRoot.rotation.x += (dragRotX - globeRoot.rotation.x) * 0.12;

  zoom += (zoomTarget - zoom) * 0.12;
  globeRoot.scale.setScalar(baseScale * zoom);

  camera.position.x += (pointer.x * 0.12 - camera.position.x) * 0.05;
  camera.position.y += (-pointer.y * 0.08 - camera.position.y) * 0.05;
  camera.position.z = camDist;
  camera.lookAt(0, 0, 0);

  updateArcs(dt);
  updateRipples(dt);
  starGroup.rotation.y += dt * 0.004;
  starGroup.userData.mat.uniforms.uTime.value = elapsed;

  renderer.render(scene, camera);
}

/* ---------- BOOT ---------- */
(async () => {
  dotsMat = buildDots(await loadMask());
  layout();
  animate();
})();
