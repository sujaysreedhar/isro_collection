import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const file = path.resolve('modules/virtual_museum/assets/layouts.js');
if (!fs.existsSync(file)) {
  console.error('FAIL: layouts.js does not exist yet.');
  process.exit(1);
}
const source = fs.readFileSync(file, 'utf8');
const layouts = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);

function assert(condition, message) {
  if (!condition) {
    console.error(`FAIL: ${message}`);
    process.exit(1);
  }
}

for (const [name, count] of [['grand_hall', 1], ['l_gallery', 12], ['courtyard_loop', 24]]) {
  const result = layouts.buildMuseumLayout(name, count);
  assert(result.id === name, `${name} keeps its layout id`);
  assert(result.slots.length === count, `${name} creates exactly ${count} artwork slots`);
  assert(result.walkRects.length > 0, `${name} defines walkable collision rectangles`);
  assert(result.walls.length >= 4, `${name} defines enclosing wall segments`);
  assert(result.fixtures.length > 0, `${name} defines light fixtures`);
}

const lGallery = layouts.buildMuseumLayout('l_gallery', 40);
assert(lGallery.slots.some(slot => Math.abs(slot.rotY) < 0.01), 'L-Gallery places artwork on its horizontal wing');
assert(lGallery.slots.some(slot => Math.abs(Math.abs(slot.rotY) - Math.PI / 2) < 0.01), 'L-Gallery places artwork on its vertical wing');

const courtyard = layouts.buildMuseumLayout('courtyard_loop', 100);
assert(courtyard.slots.length === 100, 'Courtyard supports the 100-item cap');
assert(courtyard.walkRects.length === 4, 'Courtyard uses four walkable sides around a non-walkable center');

const fallback = layouts.buildMuseumLayout('unknown-layout', 5);
assert(fallback.id === 'grand_hall', 'unknown layouts fall back to Grand Hall');

console.log('PASS: virtual museum room topologies');
