// Virtual Museum — procedural first-person 3D gallery with multi-room support.
// Exhibitions are chunked into connected rooms (~16 items each) joined by
// doorways along a straight spine. Loaded as an ES module; imports Three.js
// from the page import map (self-hosted).
import * as THREE from 'three';

const CFG = window.MUSEUM_CONFIG || {};

// ── Theme palettes ───────────────────────────────────────────────────────────
const THEMES = {
    classic: {
        wall: 0xe7ddc8, floor: 0x7a5c3c, ceiling: 0xf4efe4, frame: 0x2c2118,
        runner: 0x7c2d2d, trim: 0x3a2c1e, fixture: 0xfff6e2,
        hemiSky: 0xfff6e6, hemiGround: 0x9a8564, ambient: 1.6, hemiInt: 1.4,
        lamp: 0xfff1d4, fixtureInt: 30, exposure: 1.15,
        fog: 0xe7ddc8, fogNear: 16, banner: '#2c2118', bannerText: '#f4e9cf',
    },
    dark: {
        wall: 0x2b2c33, floor: 0x17181d, ceiling: 0x202128, frame: 0x0c0c0e,
        runner: 0x2a2d3a, trim: 0x080809, fixture: 0xe4edff,
        hemiSky: 0xaebfe0, hemiGround: 0x0f1014, ambient: 0.85, hemiInt: 0.9,
        lamp: 0xcfe0ff, fixtureInt: 55, exposure: 1.2,
        fog: 0x17181d, fogNear: 12, banner: '#0c0c0e', bannerText: '#cfe0ff',
    },
    white_cube: {
        wall: 0xf6f6f7, floor: 0xe4e6e9, ceiling: 0xffffff, frame: 0x1c1c1c,
        runner: 0xd7dade, trim: 0xc4c8cd, fixture: 0xffffff,
        hemiSky: 0xffffff, hemiGround: 0xd2d5d9, ambient: 2.2, hemiInt: 1.7,
        lamp: 0xffffff, fixtureInt: 24, exposure: 1.1,
        fog: 0xf1f2f3, fogNear: 20, banner: '#1c1c1c', bannerText: '#ffffff',
    },
};
const theme = THEMES[CFG.theme] || THEMES.classic;

// ── Constants ────────────────────────────────────────────────────────────────
const SPACING  = 4.0;    // metres between artwork centres along a wall
const HALL_W   = 8.0;    // room width
const WALL_H   = 5.0;    // wall height
const EYE_H    = 1.6;    // camera / artwork centre height
const PAD      = 0.7;    // how close the camera may get to a wall
const ROOM_CAP = 16;     // max artworks per room before a new room is added
const DOOR_W   = 1.8;    // doorway width
const DOOR_H   = 2.6;    // doorway height
const WALL_T   = 0.15;   // cross-wall thickness
const ART_DEPTH = 0.09;
const MAX_H = 2.3, MAX_W = 3.2, DEFAULT_RATIO = 1.4;
const LOAD_RADIUS = 15;   // load full-res art within this distance
const UNLOAD_RADIUS = 24; // free full-res art beyond this distance
const LIGHT_POOL = 8;     // constant number of live point lights

// ── DOM ──────────────────────────────────────────────────────────────────────
const canvas    = document.getElementById('museum-canvas');
const loaderEl  = document.getElementById('museum-loader');
const barFill   = document.getElementById('loader-bar-fill');
const statusEl  = document.getElementById('loader-status');
const hudEl     = document.getElementById('museum-hud');
const crosshair = document.getElementById('museum-crosshair');
const emptyEl   = document.getElementById('museum-empty');
const panelEl   = document.getElementById('museum-panel');

const isTouch = window.matchMedia('(pointer: coarse)').matches || ('ontouchstart' in window);
if (isTouch) document.body.classList.add('is-touch');

// ── Three.js core ────────────────────────────────────────────────────────────
const scene = new THREE.Scene();
scene.background = new THREE.Color(theme.fog);

const camera = new THREE.PerspectiveCamera(70, window.innerWidth / window.innerHeight, 0.1, 250);
camera.rotation.order = 'YXZ';

const renderer = new THREE.WebGLRenderer({ canvas, antialias: true });
renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
renderer.setSize(window.innerWidth, window.innerHeight);
renderer.outputColorSpace = THREE.SRGBColorSpace;
renderer.toneMapping = THREE.ACESFilmicToneMapping;
renderer.toneMappingExposure = theme.exposure || 1.15;

const raycaster = new THREE.Raycaster();
const loader = new THREE.TextureLoader();
loader.crossOrigin = 'anonymous';

const artMeshes = [];   // meshes that can be inspected
const records   = [];   // per-artwork streaming records
const walkRects = [];   // walkable floor rectangles (rooms + doorways)
const fixtureZs = [];   // ceiling fixture Z positions (all at x=0)
const poolLights = [];  // reusable point lights, repositioned to nearest fixtures
let roomDefs = [];      // {items, z0, z1, first, last}
let TOTAL_L = 8;        // spine length, set once rooms are chunked
let placeholderTex = null;

// ── Control state ────────────────────────────────────────────────────────────
let yaw = Math.PI, pitch = 0;          // start facing +Z (into the hall)
let isLocked = false;
let panelOpen = false;
const move = { forward: false, back: false, left: false, right: false };

// ── Boot ─────────────────────────────────────────────────────────────────────
init();

async function init() {
    let items = [];
    try {
        const res = await fetch(CFG.dataUrl, { credentials: 'same-origin' });
        const data = await res.json();
        if (data && data.success) items = data.items || [];
    } catch (e) {
        statusEl.textContent = 'Could not load exhibition data.';
        return;
    }

    if (!items.length) {
        loaderEl.classList.add('fade');
        setTimeout(() => loaderEl.classList.add('hidden'), 600);
        emptyEl.classList.remove('hidden');
        return;
    }

    placeholderTex = makePlaceholderTexture();
    roomDefs = chunkRooms(items);
    buildShell();
    buildLighting();
    camera.position.set(0, EYE_H, 1.2);

    statusEl.textContent = 'Hanging the collection…';
    roomDefs.forEach((room) => {
        room.items.forEach((item, j) => buildArtwork(item, j, room));
    });
    barFill.style.width = '100%';

    updateLights(true);
    bindControls();
    window.addEventListener('resize', onResize);
    animate();

    // Placeholders are up instantly; full-res streams in by proximity.
    reveal();
}

// ── Room chunking ────────────────────────────────────────────────────────────
function chunkRooms(items) {
    const rooms = [];
    let z = 0;
    for (let i = 0; i < items.length; i += ROOM_CAP) {
        const slice = items.slice(i, i + ROOM_CAP);
        const perSide = Math.ceil(slice.length / 2);
        const len = (perSide + 1) * SPACING;
        rooms.push({ items: slice, z0: z, z1: z + len, first: i + 1, last: i + slice.length });
        z += len;
    }
    TOTAL_L = z;
    return rooms;
}

// ── Shell geometry (floor, walls, doorways, trim, fixtures) ──────────────────
function buildShell() {
    scene.fog = new THREE.Fog(theme.fog, theme.fogNear || 16, Math.min(TOTAL_L * 1.3, 120));

    const midZ = TOTAL_L / 2;
    const wallMat    = new THREE.MeshStandardMaterial({ color: theme.wall, roughness: 0.95 });
    const floorMat   = new THREE.MeshStandardMaterial({ color: theme.floor, roughness: 0.8, metalness: 0.05 });
    const ceilingMat = new THREE.MeshStandardMaterial({ color: theme.ceiling, roughness: 1.0 });
    const trimMat    = new THREE.MeshStandardMaterial({ color: theme.trim, roughness: 0.7, metalness: 0.1 });

    // Continuous floor, ceiling, runner
    const floor = new THREE.Mesh(new THREE.PlaneGeometry(HALL_W, TOTAL_L), floorMat);
    floor.rotation.x = -Math.PI / 2;
    floor.position.set(0, 0, midZ);
    scene.add(floor);

    const ceiling = new THREE.Mesh(new THREE.PlaneGeometry(HALL_W, TOTAL_L), ceilingMat);
    ceiling.rotation.x = Math.PI / 2;
    ceiling.position.set(0, WALL_H, midZ);
    scene.add(ceiling);

    const runner = new THREE.Mesh(
        new THREE.PlaneGeometry(2.4, TOTAL_L * 0.96),
        new THREE.MeshStandardMaterial({ color: theme.runner, roughness: 1.0 })
    );
    runner.rotation.x = -Math.PI / 2;
    runner.position.set(0, 0.02, midZ);
    scene.add(runner);

    // Side walls: one segment per room, alternating subtle tint
    const baseWall = new THREE.Color(theme.wall);
    roomDefs.forEach((room, k) => {
        const len = room.z1 - room.z0;
        const cz  = (room.z0 + room.z1) / 2;
        const tinted = baseWall.clone().offsetHSL(0, 0, k % 2 ? -0.022 : 0.014);
        const mat = new THREE.MeshStandardMaterial({ color: tinted, roughness: 0.95 });

        const left = new THREE.Mesh(new THREE.PlaneGeometry(len, WALL_H), mat);
        left.rotation.y = Math.PI / 2;
        left.position.set(-HALL_W / 2, WALL_H / 2, cz);
        scene.add(left);

        const right = new THREE.Mesh(new THREE.PlaneGeometry(len, WALL_H), mat);
        right.rotation.y = -Math.PI / 2;
        right.position.set(HALL_W / 2, WALL_H / 2, cz);
        scene.add(right);
    });

    // Solid end walls
    const front = new THREE.Mesh(new THREE.PlaneGeometry(HALL_W, WALL_H), wallMat);
    front.position.set(0, WALL_H / 2, 0);
    scene.add(front);

    const back = new THREE.Mesh(new THREE.PlaneGeometry(HALL_W, WALL_H), wallMat);
    back.rotation.y = Math.PI;
    back.position.set(0, WALL_H / 2, TOTAL_L);
    scene.add(back);

    // Cross walls with doorways between rooms; signage on the entrance face
    const panelW = (HALL_W - DOOR_W) / 2;
    for (let d = 0; d < roomDefs.length - 1; d++) {
        const zb = roomDefs[d].z1;

        const leftPanel = new THREE.Mesh(new THREE.BoxGeometry(panelW, WALL_H, WALL_T), wallMat);
        leftPanel.position.set(-(DOOR_W / 2 + panelW / 2), WALL_H / 2, zb);
        scene.add(leftPanel);

        const rightPanel = new THREE.Mesh(new THREE.BoxGeometry(panelW, WALL_H, WALL_T), wallMat);
        rightPanel.position.set(DOOR_W / 2 + panelW / 2, WALL_H / 2, zb);
        scene.add(rightPanel);

        const lintel = new THREE.Mesh(new THREE.BoxGeometry(DOOR_W, WALL_H - DOOR_H, WALL_T), wallMat);
        lintel.position.set(0, DOOR_H + (WALL_H - DOOR_H) / 2, zb);
        scene.add(lintel);

        const threshold = new THREE.Mesh(new THREE.BoxGeometry(DOOR_W + 0.3, 0.03, 0.5), trimMat);
        threshold.position.set(0, 0.035, zb);
        scene.add(threshold);

        // First doorway carries the exhibition banner; the rest get room signs.
        const faceZ = zb - WALL_T / 2 - 0.015;
        if (d === 0) {
            placeBanner(CFG.title || '', faceZ);
        } else {
            const next = roomDefs[d + 1];
            placeSign(`Room ${d + 2}`, `Items ${next.first}–${next.last}`, faceZ);
        }
    }

    // Single-room exhibitions keep the banner on the far wall.
    if (roomDefs.length === 1) {
        placeBanner(CFG.title || '', TOTAL_L - 0.06);
    }

    // Baseboards + crown moulding along both side walls (full length)
    const trimBar = (x, y) => {
        const bar = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.18, TOTAL_L), trimMat);
        bar.position.set(x, y, midZ);
        scene.add(bar);
    };
    trimBar(-HALL_W / 2 + 0.04, 0.09);
    trimBar( HALL_W / 2 - 0.04, 0.09);
    trimBar(-HALL_W / 2 + 0.04, WALL_H - 0.14);
    trimBar( HALL_W / 2 - 0.04, WALL_H - 0.14);

    // Ceiling light fixtures: emissive panels along each room; the light pool
    // attaches point lights to the nearest few as the visitor moves.
    const fixtureMat = new THREE.MeshBasicMaterial({ color: theme.fixture, toneMapped: false });
    roomDefs.forEach((room) => {
        for (let z = room.z0 + SPACING; z < room.z1 - 1; z += SPACING * 1.5) {
            const panel = new THREE.Mesh(new THREE.PlaneGeometry(1.7, 1.2), fixtureMat);
            panel.rotation.x = Math.PI / 2;   // face downward
            panel.position.set(0, WALL_H - 0.03, z);
            scene.add(panel);
            fixtureZs.push(z);
        }
    });

    // Walkable rectangles: one per room + one per doorway (overlapping both rooms)
    roomDefs.forEach((room) => {
        walkRects.push({ x0: -HALL_W / 2 + PAD, x1: HALL_W / 2 - PAD, z0: room.z0 + PAD, z1: room.z1 - PAD });
    });
    for (let d = 0; d < roomDefs.length - 1; d++) {
        const zb = roomDefs[d].z1;
        walkRects.push({ x0: -DOOR_W / 2 + 0.3, x1: DOOR_W / 2 - 0.3, z0: zb - PAD - 0.15, z1: zb + PAD + 0.15 });
    }
}

function buildLighting() {
    scene.add(new THREE.HemisphereLight(theme.hemiSky, theme.hemiGround, theme.hemiInt));
    scene.add(new THREE.AmbientLight(0xffffff, theme.ambient));

    // Grazing wall-wash from each end so walls and art never fall to black
    const wash1 = new THREE.DirectionalLight(theme.lamp, 0.6);
    wash1.position.set(0, WALL_H, 0);
    wash1.target.position.set(0, EYE_H, TOTAL_L);
    scene.add(wash1, wash1.target);
    const wash2 = new THREE.DirectionalLight(theme.lamp, 0.6);
    wash2.position.set(0, WALL_H, TOTAL_L);
    wash2.target.position.set(0, EYE_H, 0);
    scene.add(wash2, wash2.target);

    // Constant-size pool of point lights → no shader recompiles, bounded cost
    for (let i = 0; i < LIGHT_POOL; i++) {
        const lamp = new THREE.PointLight(theme.lamp, theme.fixtureInt, 24, 2);
        lamp.position.set(0, WALL_H - 0.5, 0);
        scene.add(lamp);
        poolLights.push(lamp);
    }
}

/** Attach the pooled lights to the fixtures nearest the camera. */
let lightTick = 0;
function updateLights(force) {
    if (!force && (++lightTick % 20) !== 0) return;
    if (!fixtureZs.length) return;
    const cz = camera.position.z;
    const nearest = fixtureZs
        .map((z) => ({ z, d: Math.abs(z - cz) }))
        .sort((a, b) => a.d - b.d)
        .slice(0, LIGHT_POOL);
    poolLights.forEach((lamp, i) => {
        if (nearest[i]) {
            lamp.visible = true;
            lamp.position.set(0, WALL_H - 0.5, nearest[i].z);
        } else {
            lamp.visible = false;
        }
    });
}

// ── Banner & signage ─────────────────────────────────────────────────────────
function placeBanner(title, z) {
    if (!title) return;
    const cw = 1024, ch = 256;
    const c = document.createElement('canvas');
    c.width = cw; c.height = ch;
    const g = c.getContext('2d');
    g.fillStyle = theme.banner;
    g.fillRect(0, 0, cw, ch);
    g.fillStyle = theme.bannerText;
    g.textAlign = 'center';
    g.textBaseline = 'middle';
    let fs = 96;
    do { g.font = `bold ${fs}px Georgia, serif`; fs -= 4; }
    while (g.measureText(title).width > cw - 80 && fs > 28);
    g.fillText(title, cw / 2, ch / 2 + 6);

    const tex = new THREE.CanvasTexture(c);
    tex.colorSpace = THREE.SRGBColorSpace;
    const bw = 5, bh = bw * (ch / cw);
    const banner = new THREE.Mesh(
        new THREE.PlaneGeometry(bw, bh),
        new THREE.MeshBasicMaterial({ map: tex, toneMapped: false })
    );
    banner.position.set(0, 3.55, z);
    banner.rotation.y = Math.PI; // face back toward the entrance
    scene.add(banner);
}

function placeSign(title, sub, z) {
    const cw = 512, ch = 192;
    const c = document.createElement('canvas');
    c.width = cw; c.height = ch;
    const g = c.getContext('2d');
    g.fillStyle = theme.banner;
    g.fillRect(0, 0, cw, ch);
    g.fillStyle = theme.bannerText;
    g.textAlign = 'center';
    g.font = 'bold 64px Georgia, serif';
    g.fillText(title, cw / 2, 86);
    g.globalAlpha = 0.75;
    g.font = '34px Georgia, serif';
    g.fillText(sub, cw / 2, 148);
    g.globalAlpha = 1;

    const tex = new THREE.CanvasTexture(c);
    tex.colorSpace = THREE.SRGBColorSpace;
    const sw = 2.2, sh = sw * (ch / cw);
    const sign = new THREE.Mesh(
        new THREE.PlaneGeometry(sw, sh),
        new THREE.MeshBasicMaterial({ map: tex, toneMapped: false })
    );
    sign.position.set(0, 3.5, z);
    sign.rotation.y = Math.PI;
    scene.add(sign);
}

// ── Artworks (placeholder now, full-res streamed by proximity) ───────────────
function buildArtwork(item, j, room) {
    const side = j % 2;                 // 0 = left wall, 1 = right wall
    const idx  = Math.floor(j / 2);
    const z    = room.z0 + SPACING * (idx + 1);
    const x    = side === 0 ? -HALL_W / 2 + 0.06 : HALL_W / 2 - 0.06;
    const rotY = side === 0 ? Math.PI / 2 : -Math.PI / 2;

    const ratio = item.ratio || null;
    const dims = artworkDims(ratio || DEFAULT_RATIO);

    const holder = new THREE.Group();
    holder.position.set(x, EYE_H, z);
    holder.rotation.y = rotY;

    const frame = new THREE.Mesh(
        new THREE.BoxGeometry(dims.w + 0.2, dims.h + 0.2, ART_DEPTH),
        new THREE.MeshStandardMaterial({ color: theme.frame, roughness: 0.55, metalness: 0.25 })
    );
    holder.add(frame);

    const art = new THREE.Mesh(
        new THREE.PlaneGeometry(dims.w, dims.h),
        new THREE.MeshBasicMaterial({ map: placeholderTex, toneMapped: false })
    );
    art.position.z = ART_DEPTH / 2 + 0.002;
    holder.add(art);

    const plaque = makePlaque(item);
    plaque.position.set(0, -(dims.h / 2) - 0.42, ART_DEPTH / 2 + 0.02);
    holder.add(plaque);

    for (const m of [art, frame, plaque]) {
        m.userData.item = item;
        artMeshes.push(m);
    }
    scene.add(holder);

    records.push({ item, x, z, art, frame, plaque, ratio, tex: null, loaded: false, loading: false });
}

function artworkDims(ratio) {
    let h = MAX_H, w = MAX_H * ratio;
    if (w > MAX_W) { w = MAX_W; h = MAX_W / ratio; }
    return { w, h };
}

// ── Proximity texture streaming ──────────────────────────────────────────────
let streamTick = 0;
function updateStreaming() {
    if ((++streamTick % 8) !== 0) return;
    const cx = camera.position.x, cz = camera.position.z;
    for (const rec of records) {
        const d = Math.hypot(rec.x - cx, rec.z - cz);
        if (d < LOAD_RADIUS && !rec.loaded && !rec.loading) loadFull(rec);
        else if (d > UNLOAD_RADIUS && rec.loaded) unloadFull(rec);
    }
}

function loadFull(rec) {
    const url = rec.item.display || rec.item.thumb;
    if (!url) return;
    rec.loading = true;
    loader.load(
        url,
        (tex) => {
            tex.colorSpace = THREE.SRGBColorSpace;
            if (!rec.ratio && tex.image && tex.image.height) {
                resizeArtwork(rec, tex.image.width / tex.image.height);
                rec.ratio = tex.image.width / tex.image.height;
            }
            rec.art.material.map = tex;
            rec.art.material.needsUpdate = true;
            rec.tex = tex;
            rec.loaded = true;
            rec.loading = false;
        },
        undefined,
        () => { rec.loading = false; }
    );
}

function unloadFull(rec) {
    if (!rec.tex) return;
    rec.art.material.map = placeholderTex;
    rec.art.material.needsUpdate = true;
    rec.tex.dispose();
    rec.tex = null;
    rec.loaded = false;
}

function resizeArtwork(rec, ratio) {
    const d = artworkDims(ratio);
    rec.art.geometry.dispose();
    rec.art.geometry = new THREE.PlaneGeometry(d.w, d.h);
    rec.frame.geometry.dispose();
    rec.frame.geometry = new THREE.BoxGeometry(d.w + 0.2, d.h + 0.2, ART_DEPTH);
    rec.plaque.position.y = -(d.h / 2) - 0.42;
}

function makePlaceholderTexture() {
    const c = document.createElement('canvas');
    c.width = 32; c.height = 24;
    const g = c.getContext('2d');
    g.fillStyle = '#00000018';
    g.fillRect(0, 0, 32, 24);
    const tex = new THREE.CanvasTexture(c);
    tex.colorSpace = THREE.SRGBColorSpace;
    return tex;
}

// ── Plaque canvas ────────────────────────────────────────────────────────────
function makePlaque(item) {
    const cw = 512, ch = 160;
    const c = document.createElement('canvas');
    c.width = cw; c.height = ch;
    const g = c.getContext('2d');

    g.fillStyle = '#f4f1ea';
    g.fillRect(0, 0, cw, ch);
    g.fillStyle = '#c9a24a';
    g.fillRect(0, 0, cw, 8);

    g.fillStyle = '#1c1c1c';
    g.font = 'bold 30px Georgia, serif';
    wrapText(g, item.title || 'Untitled', 26, 58, cw - 52, 34, 2);

    g.fillStyle = '#6b6b6b';
    g.font = '22px Georgia, serif';
    const meta = [item.date, item.reg].filter(Boolean).join('  ·  ');
    if (meta) g.fillText(truncate(g, meta, cw - 52), 26, ch - 34);

    const tex = new THREE.CanvasTexture(c);
    tex.colorSpace = THREE.SRGBColorSpace;
    const pw = 0.7, ph = pw * (ch / cw);
    return new THREE.Mesh(
        new THREE.PlaneGeometry(pw, ph),
        new THREE.MeshBasicMaterial({ map: tex, transparent: true, toneMapped: false })
    );
}

function wrapText(g, text, x, y, maxW, lineH, maxLines) {
    const words = String(text).split(/\s+/);
    let line = '', lines = 0;
    for (let i = 0; i < words.length; i++) {
        const test = line ? line + ' ' + words[i] : words[i];
        if (g.measureText(test).width > maxW && line) {
            g.fillText(line, x, y);
            line = words[i]; y += lineH; lines++;
            if (lines >= maxLines - 1) {
                g.fillText(truncate(g, (line + ' ' + words.slice(i + 1).join(' ')).trim(), maxW), x, y);
                return;
            }
        } else {
            line = test;
        }
    }
    g.fillText(line, x, y);
}

function truncate(g, text, maxW) {
    let t = String(text);
    if (g.measureText(t).width <= maxW) return t;
    while (t.length > 1 && g.measureText(t + '…').width > maxW) t = t.slice(0, -1);
    return t + '…';
}

function reveal() {
    loaderEl.classList.add('fade');
    setTimeout(() => loaderEl.classList.add('hidden'), 600);
    hudEl.classList.remove('hidden');
    if (isTouch) crosshair.classList.add('active');
}

// ── Controls ─────────────────────────────────────────────────────────────────
function bindControls() {
    if (!isTouch) {
        canvas.addEventListener('click', () => {
            if (panelOpen) return;
            if (isLocked) inspectAtNDC(0, 0);
            else canvas.requestPointerLock();
        });
        document.addEventListener('pointerlockchange', () => {
            isLocked = (document.pointerLockElement === canvas);
            crosshair.classList.toggle('active', isLocked);
        });
        document.addEventListener('mousemove', (e) => {
            if (!isLocked) return;
            yaw   -= e.movementX * 0.0024;
            pitch -= e.movementY * 0.0024;
            clampPitch();
        });
    }

    document.addEventListener('keydown', (e) => setKey(e.code, true));
    document.addEventListener('keyup',   (e) => setKey(e.code, false));

    let tId = null, lx = 0, ly = 0, moved = 0;
    canvas.addEventListener('touchstart', (e) => {
        const t = e.changedTouches[0];
        tId = t.identifier; lx = t.clientX; ly = t.clientY; moved = 0;
    }, { passive: true });
    canvas.addEventListener('touchmove', (e) => {
        for (const t of e.changedTouches) {
            if (t.identifier !== tId) continue;
            const dx = t.clientX - lx, dy = t.clientY - ly;
            yaw   -= dx * 0.005;
            pitch -= dy * 0.005;
            clampPitch();
            moved += Math.abs(dx) + Math.abs(dy);
            lx = t.clientX; ly = t.clientY;
        }
    }, { passive: true });
    canvas.addEventListener('touchend', (e) => {
        for (const t of e.changedTouches) {
            if (t.identifier !== tId) continue;
            if (moved < 10 && !panelOpen) inspectAtClient(t.clientX, t.clientY);
            tId = null;
        }
    }, { passive: true });

    document.querySelectorAll('#touch-pad .pad-btn').forEach((btn) => {
        const dir = btn.dataset.dir;
        const on  = (e) => { e.preventDefault(); e.stopPropagation(); move[dir] = true; };
        const off = (e) => { e.preventDefault(); e.stopPropagation(); move[dir] = false; };
        btn.addEventListener('touchstart', on, { passive: false });
        btn.addEventListener('touchend',   off, { passive: false });
        btn.addEventListener('touchcancel', off, { passive: false });
        btn.addEventListener('mousedown', on);
        btn.addEventListener('mouseup',   off);
        btn.addEventListener('mouseleave', off);
    });

    document.getElementById('panel-close').addEventListener('click', closePanel);
}

function setKey(code, val) {
    switch (code) {
        case 'KeyW': case 'ArrowUp':    move.forward = val; break;
        case 'KeyS': case 'ArrowDown':  move.back    = val; break;
        case 'KeyA': case 'ArrowLeft':  move.left    = val; break;
        case 'KeyD': case 'ArrowRight': move.right   = val; break;
        case 'Escape': if (val && panelOpen) closePanel(); break;
    }
}

function clampPitch() {
    const lim = Math.PI / 2 - 0.05;
    pitch = Math.max(-lim, Math.min(lim, pitch));
}

// ── Interaction ──────────────────────────────────────────────────────────────
function inspectAtNDC(nx, ny) {
    raycaster.setFromCamera(new THREE.Vector2(nx, ny), camera);
    const hits = raycaster.intersectObjects(artMeshes, false);
    if (hits.length) openPanel(hits[0].object.userData.item);
}
function inspectAtClient(cx, cy) {
    const nx = (cx / window.innerWidth) * 2 - 1;
    const ny = -(cy / window.innerHeight) * 2 + 1;
    inspectAtNDC(nx, ny);
}

function openPanel(item) {
    if (!item) return;
    document.getElementById('panel-img').src = item.display || item.thumb || '';
    document.getElementById('panel-title').textContent = item.title || 'Untitled';
    const meta = [];
    if (item.date)   meta.push(`<span>${escapeHtml(item.date)}</span>`);
    if (item.reg)    meta.push(`<span>${escapeHtml(item.reg)}</span>`);
    if (item.credit) meta.push(`<span>${escapeHtml(item.credit)}</span>`);
    document.getElementById('panel-meta').innerHTML = meta.join('');
    document.getElementById('panel-desc').textContent = item.desc || '';
    document.getElementById('panel-link').href = item.url || '#';

    panelEl.classList.remove('hidden');
    requestAnimationFrame(() => panelEl.classList.add('open'));
    panelOpen = true;
    if (document.pointerLockElement) document.exitPointerLock();
}

function closePanel() {
    panelEl.classList.remove('open');
    panelOpen = false;
    setTimeout(() => panelEl.classList.add('hidden'), 400);
}

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (c) => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));
}

// ── Collision ────────────────────────────────────────────────────────────────
function insideAny(x, z) {
    for (const r of walkRects) {
        if (x >= r.x0 && x <= r.x1 && z >= r.z0 && z <= r.z1) return true;
    }
    return false;
}

// ── Loop ─────────────────────────────────────────────────────────────────────
const clock = new THREE.Clock();
const fwd = new THREE.Vector3();
const rgt = new THREE.Vector3();
const dir = new THREE.Vector3();

function animate() {
    requestAnimationFrame(animate);
    const dt = Math.min(clock.getDelta(), 0.1);

    camera.rotation.set(pitch, yaw, 0);

    fwd.set(-Math.sin(yaw), 0, -Math.cos(yaw));
    rgt.set(Math.cos(yaw), 0, -Math.sin(yaw));
    dir.set(0, 0, 0);
    if (move.forward) dir.add(fwd);
    if (move.back)    dir.sub(fwd);
    if (move.right)   dir.add(rgt);
    if (move.left)    dir.sub(rgt);

    if (dir.lengthSq() > 0) {
        dir.normalize().multiplyScalar(3.2 * dt);
        const ox = camera.position.x, oz = camera.position.z;
        const nx = ox + dir.x, nz = oz + dir.z;
        // Axis-separated sliding: try full move, then each axis alone
        if (insideAny(nx, nz)) {
            camera.position.x = nx; camera.position.z = nz;
        } else if (insideAny(nx, oz)) {
            camera.position.x = nx;
        } else if (insideAny(ox, nz)) {
            camera.position.z = nz;
        }
    }
    camera.position.y = EYE_H;

    updateStreaming();
    updateLights(false);

    if (isLocked && crosshair.classList.contains('active')) {
        raycaster.setFromCamera(new THREE.Vector2(0, 0), camera);
        const hot = raycaster.intersectObjects(artMeshes, false).length > 0;
        crosshair.classList.toggle('hot', hot);
    }

    renderer.render(scene, camera);
}

function onResize() {
    camera.aspect = window.innerWidth / window.innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(window.innerWidth, window.innerHeight);
}
