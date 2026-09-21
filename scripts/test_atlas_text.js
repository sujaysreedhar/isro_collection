const assert = require('node:assert/strict');
const { escape, itemId } = require('../assets/js/atlas_text.js');
assert.equal(escape('<img src=x onerror="x"> & Ω'), '&lt;img src=x onerror=&quot;x&quot;&gt; &amp; Ω');
assert.equal(itemId('12'), 12);
assert.equal(itemId('12" onclick="x'), null);
assert.equal(itemId('-1'), null);
console.log('PASS atlas text escaping and item IDs');
