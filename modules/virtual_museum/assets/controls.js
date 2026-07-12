export function isInsideWalkable(x, z, rects) {
    return rects.some(rect => x >= rect.x0 && x <= rect.x1 && z >= rect.z0 && z <= rect.z1);
}

export function resolveWalkMove(origin, delta, rects) {
    const nx = origin.x + delta.x;
    const nz = origin.z + delta.z;
    if (isInsideWalkable(nx, nz, rects)) return { x: nx, z: nz };
    if (isInsideWalkable(nx, origin.z, rects)) return { x: nx, z: origin.z };
    if (isInsideWalkable(origin.x, nz, rects)) return { x: origin.x, z: nz };
    return { x: origin.x, z: origin.z };
}
