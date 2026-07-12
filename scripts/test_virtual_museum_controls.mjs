import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const file = path.resolve('modules/virtual_museum/assets/controls.js');
if (!fs.existsSync(file)) {
  console.error('FAIL: controls.js does not exist yet.');
  process.exit(1);
}
const source = fs.readFileSync(file, 'utf8');
const controls = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);

function assert(condition, message) {
  if (!condition) { console.error(`FAIL: ${message}`); process.exit(1); }
}

const rects = [{ x0: -2, x1: 2, z0: 0, z1: 8 }, { x0: 1, x1: 8, z0: 6, z1: 10 }];
assert(controls.isInsideWalkable(0, 2, rects), 'point inside first room is walkable');
assert(controls.isInsideWalkable(5, 8, rects), 'point inside connected wing is walkable');
assert(!controls.isInsideWalkable(-5, 8, rects), 'point outside all rooms is blocked');

const next = controls.resolveWalkMove({ x: 1.8, z: 2 }, { x: 1, z: 1 }, rects);
assert(next.x === 1.8 && next.z === 3, 'axis-separated collision slides along a blocked wall');

console.log('PASS: virtual museum movement and collision');
