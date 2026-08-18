const SPACING = 3.8;
const HALF_WIDTH = 4.5;
const PAD = 0.7;

export function buildMuseumLayout(requested, itemCount) {
    const id = ['grand_hall', 'l_gallery', 'courtyard_loop'].includes(requested) ? requested : 'grand_hall';
    const count = Math.max(0, Math.min(100, Number(itemCount) || 0));
    if (id === 'l_gallery') return buildLGallery(count);
    if (id === 'courtyard_loop') return buildCourtyard(count);
    return buildGrandHall(count);
}

function buildGrandHall(count) {
    const perWall = Math.max(1, Math.ceil(count / 2));
    const length = Math.max(16, (perWall + 1) * SPACING);
    const walls = rectangleWalls(-HALF_WIDTH, HALF_WIDTH, 0, length);
    const surfaces = [
        { x1: -HALF_WIDTH + .08, z1: 1, x2: -HALF_WIDTH + .08, z2: length - 1, rotY: Math.PI / 2, zone: 'Grand Hall' },
        { x1: HALF_WIDTH - .08, z1: 1, x2: HALF_WIDTH - .08, z2: length - 1, rotY: -Math.PI / 2, zone: 'Grand Hall' },
    ];
    return {
        id: 'grand_hall', spawn: { x: 0, z: 1.3, yaw: Math.PI },
        floorRects: [{ x0: -HALF_WIDTH, x1: HALF_WIDTH, z0: 0, z1: length }],
        walkRects: [{ x0: -HALF_WIDTH + PAD, x1: HALF_WIDTH - PAD, z0: PAD, z1: length - PAD }],
        walls, slots: makeSlots(surfaces, count), fixtures: lineFixtures(0, 4, length - 3, 5),
        focalPoints: [{ type: 'plinth', x: 0, z: length / 2 }], bounds: { minX: -HALF_WIDTH, maxX: HALF_WIDTH, minZ: 0, maxZ: length },
    };
}

function buildLGallery(count) {
    const perSurface = Math.max(1, Math.ceil(count / 4));
    const verticalLength = Math.max(15, (perSurface + 1) * SPACING + 10.8);
    const horizontalLength = Math.max(15, (perSurface + 1) * SPACING + 6.3);
    const zTurn0 = verticalLength - HALF_WIDTH * 2;
    const zTurn1 = verticalLength;
    const endX = horizontalLength;
    const walls = polygonWalls([
        [-HALF_WIDTH, 0], [HALF_WIDTH, 0], [HALF_WIDTH, zTurn0], [endX, zTurn0],
        [endX, zTurn1], [-HALF_WIDTH, zTurn1], [-HALF_WIDTH, 0],
    ]);
    const surfaces = [
        { x1: -HALF_WIDTH + .08, z1: 1, x2: -HALF_WIDTH + .08, z2: zTurn0 - .8, rotY: Math.PI / 2, zone: 'First Wing' },
        { x1: HALF_WIDTH - .08, z1: 1, x2: HALF_WIDTH - .08, z2: zTurn0 - .8, rotY: -Math.PI / 2, zone: 'First Wing' },
        { x1: HALF_WIDTH + .8, z1: zTurn0 + .08, x2: endX - 1, z2: zTurn0 + .08, rotY: 0, zone: 'Second Wing' },
        { x1: HALF_WIDTH + .8, z1: zTurn1 - .08, x2: endX - 1, z2: zTurn1 - .08, rotY: Math.PI, zone: 'Second Wing' },
    ];
    return {
        id: 'l_gallery', spawn: { x: 0, z: 1.3, yaw: Math.PI },
        floorRects: [
            { x0: -HALF_WIDTH, x1: HALF_WIDTH, z0: 0, z1: verticalLength },
            { x0: HALF_WIDTH, x1: endX, z0: zTurn0, z1: zTurn1 },
        ],
        walkRects: [
            { x0: -HALF_WIDTH + PAD, x1: HALF_WIDTH - PAD, z0: PAD, z1: verticalLength - PAD },
            { x0: HALF_WIDTH - PAD, x1: endX - PAD, z0: zTurn0 + PAD, z1: zTurn1 - PAD },
        ],
        walls, slots: makeSlots(surfaces, count),
        fixtures: [...lineFixtures(0, 4, zTurn0 - 2, 5), ...lineFixturesHorizontal(HALF_WIDTH + 3, endX - 3, verticalLength - HALF_WIDTH, 5)],
        focalPoints: [{ type: 'sculpture', x: 0, z: verticalLength - HALF_WIDTH }],
        bounds: { minX: -HALF_WIDTH, maxX: endX, minZ: 0, maxZ: verticalLength },
    };
}

function buildCourtyard(count) {
    const perSurface = Math.max(1, Math.ceil(count / 8));
    const outerW = Math.max(22, (perSurface + 1) * SPACING + 9.4);
    const outerL = Math.max(22, (perSurface + 1) * SPACING + 9.4);
    const x0 = -outerW / 2, x1 = outerW / 2, z0 = 0, z1 = outerL;
    const ring = 4.2;
    const ix0 = x0 + ring, ix1 = x1 - ring, iz0 = z0 + ring, iz1 = z1 - ring;
    const walls = [...rectangleWalls(x0, x1, z0, z1), ...rectangleWalls(ix0, ix1, iz0, iz1)];
    const surfaces = [
        { x1: x0 + .08, z1: 1, x2: x0 + .08, z2: z1 - 1, rotY: Math.PI / 2, zone: 'West Gallery' },
        { x1: x1 - .08, z1: 1, x2: x1 - .08, z2: z1 - 1, rotY: -Math.PI / 2, zone: 'East Gallery' },
        { x1: x0 + 1, z1: z0 + .08, x2: x1 - 1, z2: z0 + .08, rotY: 0, zone: 'South Gallery' },
        { x1: x0 + 1, z1: z1 - .08, x2: x1 - 1, z2: z1 - .08, rotY: Math.PI, zone: 'North Gallery' },
        { x1: ix0 - .08, z1: iz0 + .5, x2: ix0 - .08, z2: iz1 - .5, rotY: -Math.PI / 2, zone: 'Courtyard West' },
        { x1: ix1 + .08, z1: iz0 + .5, x2: ix1 + .08, z2: iz1 - .5, rotY: Math.PI / 2, zone: 'Courtyard East' },
        { x1: ix0 + .5, z1: iz0 - .08, x2: ix1 - .5, z2: iz0 - .08, rotY: Math.PI, zone: 'Courtyard South' },
        { x1: ix0 + .5, z1: iz1 + .08, x2: ix1 - .5, z2: iz1 + .08, rotY: 0, zone: 'Courtyard North' },
    ];
    const floorRects = [
        { x0, x1, z0, z1: iz0 }, { x0, x1, z0: iz1, z1 },
        { x0, x1: ix0, z0: iz0, z1: iz1 }, { x0: ix1, x1, z0: iz0, z1: iz1 },
    ];
    // Walkable ring: pad only against real walls (outer walls inset, inner
    // courtyard inflated) and let the four strips overlap in the corners.
    // Shrinking each floor band on all sides leaves 2×PAD dead strips at the
    // band boundaries, which blocked the walk halfway around the loop.
    const walkRects = [
        { x0: x0 + PAD, x1: x1 - PAD, z0: z0 + PAD, z1: iz0 - PAD },
        { x0: x0 + PAD, x1: x1 - PAD, z0: iz1 + PAD, z1: z1 - PAD },
        { x0: x0 + PAD, x1: ix0 - PAD, z0: z0 + PAD, z1: z1 - PAD },
        { x0: ix1 + PAD, x1: x1 - PAD, z0: z0 + PAD, z1: z1 - PAD },
    ];
    return {
        id: 'courtyard_loop', spawn: { x: 0, z: 1.4, yaw: Math.PI }, floorRects,
        walkRects,
        walls, slots: makeSlots(surfaces, count),
        fixtures: [...lineFixtures(x0 + ring / 2, 3, z1 - 3, 5), ...lineFixtures(x1 - ring / 2, 3, z1 - 3, 5), ...lineFixturesHorizontal(x0 + 3, x1 - 3, ring / 2, 5), ...lineFixturesHorizontal(x0 + 3, x1 - 3, z1 - ring / 2, 5)],
        focalPoints: [{ type: 'courtyard', x: 0, z: outerL / 2, width: ix1 - ix0, length: iz1 - iz0 }],
        bounds: { minX: x0, maxX: x1, minZ: z0, maxZ: z1 },
    };
}

function makeSlots(surfaces, count) {
    const slots = [];
    const base = Math.floor(count / surfaces.length);
    let remainder = count % surfaces.length;
    surfaces.forEach((surface, surfaceIndex) => {
        const amount = base + (remainder-- > 0 ? 1 : 0);
        for (let i = 0; i < amount; i++) {
            const t = (i + 1) / (amount + 1);
            slots.push({
                x: surface.x1 + (surface.x2 - surface.x1) * t,
                z: surface.z1 + (surface.z2 - surface.z1) * t,
                rotY: surface.rotY, zone: surface.zone, surfaceIndex,
            });
        }
    });
    return slots;
}

function rectangleWalls(x0, x1, z0, z1) {
    return polygonWalls([[x0,z0],[x1,z0],[x1,z1],[x0,z1],[x0,z0]]);
}

function polygonWalls(points) {
    const walls = [];
    for (let i = 0; i < points.length - 1; i++) walls.push({ x1: points[i][0], z1: points[i][1], x2: points[i+1][0], z2: points[i+1][1] });
    return walls;
}

function lineFixtures(x, start, end, step) {
    const out = [];
    for (let z = start; z <= end; z += step) out.push({ x, z });
    return out;
}

function lineFixturesHorizontal(start, end, z, step) {
    const out = [];
    for (let x = start; x <= end; x += step) out.push({ x, z });
    return out;
}
