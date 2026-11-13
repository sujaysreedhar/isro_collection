const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const source = path.join(__dirname, '../assets/js/atlas_public.js');
assert.ok(fs.existsSync(source), 'Shared public Atlas behavior must exist');
const atlas = require(source);
assert.deepEqual(atlas.coordinates({latitude: '0', longitude: '20'}), [0, 20]);
assert.deepEqual(atlas.coordinates({latitude: '-12.5', longitude: '0'}), [-12.5, 0]);
for (const pair of [[null, 20], ['', 20], ['abc', 20], [91, 20], [20, 181], [0, 0], ['12oops', 77]]) {
    assert.equal(atlas.coordinates({latitude: pair[0], longitude: pair[1]}), null);
}
const locations = [
    {id: 1, post_office: 'Bangalore GPO', state: 'Karnataka', district: 'Bengaluru', pin_code: 560001, is_acquired: '1', linked_item_title: 'Space stamp'},
    {id: 2, post_office: 'Thrissur', state: 'Kerala', pin_code: '680001', is_acquired: 0},
    {id: 3, post_office: 'Mysore', state: 'Karnataka', pin_code: '570001', is_acquired: 0}
];
const filter = (query = '', status = 'all', state = '') => locations.filter(l => atlas.matches(l, {query, status, state})).map(l => l.id);
assert.deepEqual(filter('  SPACE  560001 ', 'collected', 'Karnataka'), [1]);
assert.deepEqual(filter('', 'seeking', 'Karnataka'), [3]);
assert.deepEqual(filter('Thrissur', 'collected'), []);
assert.deepEqual(filter('not found'), []);
assert.deepEqual(filter(), [1, 2, 3]);
assert.equal(atlas.safeImageUrl('javascript:alert(1)', 'https://example.com'), null);
assert.equal(atlas.safeImageUrl('data:image/svg+xml,bad', 'https://example.com'), null);
assert.equal(atlas.safeImageUrl('/uploads/thumbnails/test.webp', 'https://example.com'), 'https://example.com/uploads/thumbnails/test.webp');
console.log('PASS Atlas coordinate validation, combined search/filters, reset defaults, image URLs');
