// Virtual Museum — procedural first-person 3D gallery with multi-room support.
// Exhibitions are chunked into connected rooms (~16 items each) joined by
// doorways along a straight spine. Loaded as an ES module; imports Three.js
// from the page import map (self-hosted).
import * as THREE from 'three';
import { buildMuseumLayout } from './layouts.js';
import { isInsideWalkable, resolveWalkMove } from './controls.js';
import { addFloorRect, addWallSegment, disposeScene } from './scene.js';
import { artworkDimensions } from './artwork.js';
import { escapeMuseumHtml, setPanelVisibility, showFallbackUI } from './ui.js';

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
const WALL_H   = 5.0;    // wall height
const EYE_H    = 1.6;    // camera / artwork centre height
const WALL_T   = 0.15;   // cross-wall thickness
const ART_DEPTH = 0.09;
const MAX_H = 2.3, MAX_W = 3.2, DEFAULT_RATIO = 1.4;
const LOAD_RADIUS = 15;   // load full-res art within this distance
const UNLOAD_RADIUS = 24; // free full-res art beyond this distance
const LIGHT_POOL = 8;     // constant number of live point lights
const WALK_SPEED = 3.2;   // metres per second
const RUN_SPEED  = 5.4;   // metres per second while Shift is held
const MOVE_SMOOTHING = 12; // velocity easing factor (higher = snappier)

// Centerpiece models available at a room's focal point. CFG.centerpiece picks
// one; 'auto' falls back to the layout's default, 'none' leaves the floor bare.
const CENTERPIECE_BUILDERS = {
    torus_knot:  () => ({ geometry: new THREE.TorusKnotGeometry(.48, .14, 80, 12), lift: .5,  spin: true }),
    icosahedron: () => ({ geometry: new THREE.IcosahedronGeometry(.62, 1),         lift: .62, spin: true }),
    globe:       () => ({ geometry: new THREE.SphereGeometry(.58, 32, 20),         lift: .58, spin: true }),
    obelisk:     () => ({ geometry: new THREE.CylinderGeometry(.14, .4, 2.2, 4),   lift: 1.1, spin: false }),
};

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

let renderer = null;
try {
    renderer = new THREE.WebGLRenderer({ canvas, antialias: true, powerPreference: 'high-performance' });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.setSize(window.innerWidth, window.innerHeight);
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = theme.exposure || 1.15;
} catch (error) {
    showWebglFallback();
}

const raycaster = new THREE.Raycaster();
const loader = new THREE.TextureLoader();
loader.crossOrigin = 'anonymous';

const artMeshes = [];   // meshes that can be inspected
const records   = [];   // per-artwork streaming records
const walkRects = [];   // walkable floor rectangles (rooms + doorways)
const fixturePoints = []; // ceiling fixture positions
const poolLights = [];  // reusable point lights, repositioned to nearest fixtures
const spinners  = [];   // centerpiece meshes given a slow idle rotation
let layoutDef = null;
let TOTAL_L = 8;        // spine length, set from the layout bounds
let placeholderTex = null;

// ── Control state ────────────────────────────────────────────────────────────
let yaw = Math.PI, pitch = 0;          // start facing +Z (into the hall)
let isLocked = false;
let panelOpen = false;
let running = false;
const move = { forward: false, back: false, left: false, right: false };
const velocity = new THREE.Vector3();  // smoothed walk velocity (m/s)


async function init() {
    if (!renderer) return;
    let items = Array.isArray(CFG.items) ? CFG.items : [];
    if (!items.length) {
        try {
            const res = await fetch(CFG.dataUrl, { credentials: 'same-origin' });
            const data = await res.json();
            if (data && data.success) items = data.items || [];
        } catch (e) {
            statusEl.textContent = 'Could not load exhibition data.';
            showWebglFallback();
            return;
        }
    }

    if (!items.length) {
        loaderEl.classList.add('fade');
        setTimeout(() => loaderEl.classList.add('hidden'), 600);
        emptyEl.classList.remove('hidden');
        return;
    }

    placeholderTex = makePlaceholderTexture();
    layoutDef = buildMuseumLayout(CFG.layout || 'grand_hall', items.length);
    buildShell();
    buildLighting();
    resetPosition();

    statusEl.textContent = 'Hanging the collection…';
    items.forEach((item, index) => buildArtwork(item, index, layoutDef.slots[index]));
    barFill.style.width = '100%';

    updateLights(true);
    bindControls();
    window.addEventListener('resize', onResize);
    window.addEventListener('beforeunload', disposeMuseum);
    animate();

    // Placeholders are up instantly; full-res streams in by proximity.
    reveal();
}

// ── Shell geometry (floor, walls, doorways, trim, fixtures) ──────────────────
function buildShell() {
    const wallMat = new THREE.MeshStandardMaterial({ color: theme.wall, roughness: .92 });
    const floorMat = new THREE.MeshStandardMaterial({ color: theme.floor, roughness: .78, metalness: .04 });
    const ceilingMat = new THREE.MeshStandardMaterial({ color: theme.ceiling, roughness: 1 });
    const trimMat = new THREE.MeshStandardMaterial({ color: theme.trim, roughness: .65, metalness: .12 });
    const bounds = layoutDef.bounds;
    TOTAL_L = Math.max(bounds.maxX - bounds.minX, bounds.maxZ - bounds.minZ);
    scene.fog = new THREE.Fog(theme.fog, theme.fogNear || 16, Math.min(TOTAL_L * 1.55, 145));

    layoutDef.floorRects.forEach(rect => {
        const width = rect.x1 - rect.x0, length = rect.z1 - rect.z0;
        const cx = (rect.x0 + rect.x1) / 2, cz = (rect.z0 + rect.z1) / 2;
        addFloorRect(scene, rect, 0, floorMat, false);
        addFloorRect(scene, rect, WALL_H, ceilingMat, true);
    });

    layoutDef.walls.forEach(wall => {
        const dx = wall.x2 - wall.x1, dz = wall.z2 - wall.z1;
        const length = Math.hypot(dx, dz);
        const mesh = addWallSegment(scene, wall, WALL_H, WALL_T, wallMat);
        const trim = new THREE.Mesh(new THREE.BoxGeometry(length, .16, WALL_T + .04), trimMat);
        trim.position.set(mesh.position.x, .09, mesh.position.z); trim.rotation.y = mesh.rotation.y; scene.add(trim);
    });

    layoutDef.walkRects.forEach(rect => walkRects.push(rect));
    layoutDef.fixtures.forEach(point => {
        const fixture = new THREE.Mesh(
            new THREE.CircleGeometry(.58, 20),
            new THREE.MeshBasicMaterial({ color: theme.fixture, toneMapped: false })
        );
        fixture.rotation.x = Math.PI / 2; fixture.position.set(point.x, WALL_H - .025, point.z); scene.add(fixture);
        fixturePoints.push(point);
    });
    buildFocalPoints();
    placeBanner(CFG.title || '', bounds.maxZ - .12);
}

/** Which centerpiece model to build: honour CFG, fall back per layout. */
function resolveCenterpiece(layoutDefault) {
    const choice = CFG.centerpiece || 'auto';
    if (choice === 'none') return null;
    if (CENTERPIECE_BUILDERS[choice]) return choice;
    return layoutDefault; // 'auto' or anything unrecognised
}

function addCenterpiece(kind, x, baseY, z, scale) {
    const { geometry, lift, spin } = CENTERPIECE_BUILDERS[kind]();
    const mesh = new THREE.Mesh(geometry, new THREE.MeshStandardMaterial({ color: theme.frame, metalness: .5, roughness: .32 }));
    mesh.scale.setScalar(scale);
    mesh.position.set(x, baseY + lift * scale, z);
    if (kind === 'obelisk') mesh.rotation.y = Math.PI / 4; // face a flat side forward
    scene.add(mesh);
    if (spin) spinners.push(mesh);
}

function buildFocalPoints() {
    const stone = new THREE.MeshStandardMaterial({ color: theme.trim, roughness: .72 });
    for (const point of layoutDef.focalPoints || []) {
        if (point.type === 'courtyard') {
            // The planted bed always exists; only the sculpture is configurable.
            const bed = new THREE.Mesh(new THREE.BoxGeometry(point.width, .34, point.length), new THREE.MeshStandardMaterial({ color: 0x263d35, roughness: 1 }));
            bed.position.set(point.x, .17, point.z); scene.add(bed);
            const kind = resolveCenterpiece('icosahedron');
            if (kind) addCenterpiece(kind, point.x, .34, point.z, 1.8);
        } else {
            const kind = resolveCenterpiece(point.type === 'sculpture' ? 'icosahedron' : 'torus_knot');
            if (!kind) continue;
            const plinth = new THREE.Mesh(new THREE.CylinderGeometry(.85, 1.05, 1.05, 32), stone);
            plinth.position.set(point.x, .525, point.z); scene.add(plinth);
            addCenterpiece(kind, point.x, 1.05, point.z, 1);
        }
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
    if (!fixturePoints.length) return;
    const cx = camera.position.x, cz = camera.position.z;
    const nearest = fixturePoints
        .map((point) => ({ ...point, d: Math.hypot(point.x - cx, point.z - cz) }))
        .sort((a, b) => a.d - b.d)
        .slice(0, LIGHT_POOL);
    poolLights.forEach((lamp, i) => {
        if (nearest[i]) {
            lamp.visible = true;
            lamp.position.set(nearest[i].x, WALL_H - 0.5, nearest[i].z);
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

// ── Artworks (placeholder now, full-res streamed by proximity) ───────────────
function buildArtwork(item, index, slot) {
    if (!slot) return;
    const ratio = item.ratio || null;
    const dims = artworkDims(ratio || DEFAULT_RATIO);
    const holder = new THREE.Group();
    holder.position.set(slot.x, EYE_H, slot.z);
    holder.rotation.y = slot.rotY;

    const frame = new THREE.Mesh(
        new THREE.BoxGeometry(dims.w + .2, dims.h + .2, ART_DEPTH),
        new THREE.MeshStandardMaterial({ color: theme.frame, roughness: .52, metalness: .25 })
    );
    holder.add(frame);
    const art = new THREE.Mesh(new THREE.PlaneGeometry(dims.w, dims.h), new THREE.MeshBasicMaterial({ map: placeholderTex, toneMapped: false }));
    art.position.z = ART_DEPTH / 2 + .002; holder.add(art);
    const plaque = makePlaque(item); plaque.position.set(0, -(dims.h / 2) - .42, ART_DEPTH / 2 + .02); holder.add(plaque);
    for (const mesh of [art, frame, plaque]) { mesh.userData.item = item; artMeshes.push(mesh); }
    scene.add(holder);
    records.push({ item, x: slot.x, z: slot.z, zone: slot.zone, holder, art, frame, plaque, ratio, tex: null, loaded: false, loading: false });
}

function artworkDims(ratio) {
    return artworkDimensions(ratio, { maxHeight: MAX_H, maxWidth: MAX_W });
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
    const welcome = document.getElementById('museum-welcome');
    if (welcome) welcome.classList.remove('hidden');
    if (isTouch) crosshair.classList.add('active');
}

// ── Controls ─────────────────────────────────────────────────────────────────
function bindControls() {
    document.getElementById('museum-start')?.addEventListener('click', () => {
        document.getElementById('museum-welcome')?.classList.add('hidden');
        canvas.focus();
        if (!isTouch) canvas.requestPointerLock?.();
    });
    document.getElementById('museum-reset')?.addEventListener('click', resetPosition);
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
        case 'ShiftLeft': case 'ShiftRight': running = val; break;
        case 'Enter': if (val && !panelOpen) inspectAtNDC(0, 0); break;
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

    setPanelVisibility(panelEl, true);
    panelOpen = true;
    if (document.pointerLockElement) document.exitPointerLock();
    document.getElementById('panel-close').focus();
}

function closePanel() {
    setPanelVisibility(panelEl, false);
    panelOpen = false;
    canvas.focus();
}

function escapeHtml(s) {
    return escapeMuseumHtml(s);
}

// ── Collision ────────────────────────────────────────────────────────────────
function insideAny(x, z) {
    return isInsideWalkable(x, z, walkRects);
}

function resetPosition() {
    if (!layoutDef) return;
    camera.position.set(layoutDef.spawn.x, EYE_H, layoutDef.spawn.z);
    yaw = layoutDef.spawn.yaw;
    pitch = 0;
}

function showWebglFallback() {
    showFallbackUI();
}

function disposeMuseum() {
    disposeScene(scene, renderer, [placeholderTex, ...records.map(rec => rec.tex)]);
}

// ── Loop ─────────────────────────────────────────────────────────────────────
const clock = new THREE.Clock();
const fwd = new THREE.Vector3();
const rgt = new THREE.Vector3();
const dir = new THREE.Vector3();
let progressTick = 0;

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

    // Ease the velocity toward the input direction so walking starts and stops
    // smoothly instead of snapping — reads as gliding, not teleporting.
    if (dir.lengthSq() > 0) dir.normalize().multiplyScalar(running ? RUN_SPEED : WALK_SPEED);
    const blend = Math.min(1, MOVE_SMOOTHING * dt);
    velocity.x += (dir.x - velocity.x) * blend;
    velocity.z += (dir.z - velocity.z) * blend;

    if (velocity.lengthSq() > 1e-6) {
        const next = resolveWalkMove(
            { x: camera.position.x, z: camera.position.z },
            { x: velocity.x * dt, z: velocity.z * dt },
            walkRects
        );
        camera.position.x = next.x;
        camera.position.z = next.z;
    }
    camera.position.y = EYE_H;

    for (const mesh of spinners) mesh.rotation.y += dt * 0.25;

    updateStreaming();
    updateLights(false);

    if ((++progressTick % 30) === 0 && records.length) {
        const nearest = records.reduce((best, rec) => {
            const distance = Math.hypot(rec.x - camera.position.x, rec.z - camera.position.z);
            return !best || distance < best.distance ? { rec, distance } : best;
        }, null);
        const progress = document.getElementById('museum-progress');
        if (progress && nearest) progress.textContent = `${nearest.rec.zone || 'Gallery'} · ${records.length} objects`;
    }

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

// ── Boot ─────────────────────────────────────────────────────────────────────
init();
document.getElementById('museum-retry')?.addEventListener('click', () => location.reload());
