const fs = require('fs');
const path = require('path');
const vm = require('vm');

const files = [
    'themes/default/atlas.php',
    'themes/dark/atlas.php',
    'themes/glass/atlas.php',
    'themes/modern_blue/atlas.php',
    'modules/postmark_atlas/admin_map.php'
];

let allPassed = true;

files.forEach(file => {
    const fullPath = path.join(__dirname, '..', file);
    const content = fs.readFileSync(fullPath, 'utf8');

    // Extract content inside <script> tags
    const scriptMatches = content.match(/<script[\s\S]*?>([\s\S]*?)<\/script>/gi);
    if (!scriptMatches) {
        console.log(`[WARN] No <script> found in ${file}`);
        return;
    }

    scriptMatches.forEach((scriptTag, idx) => {
        // Only check inline scripts without src
        if (/<script[^>]*src=/i.test(scriptTag)) return;

        // Strip out <script ...> and </script>
        let jsCode = scriptTag.replace(/^<script[\s\S]*?>/i, '').replace(/<\/script>$/i, '');
        if (!jsCode.trim()) return;

        // Replace PHP tags like <?= $jsonLocations ?> with mock values so parser can parse valid JS
        jsCode = jsCode.replace(/<\?=\s*\$jsonLocations\s*\?>/g, '[]');
        jsCode = jsCode.replace(/<\?=\s*\$csrfToken\s*\?>/g, '"mock-token"');
        jsCode = jsCode.replace(/<\?=\s*SITE_URL\s*\?>/g, '"http://localhost"');
        jsCode = jsCode.replace(/<\?php[\s\S]*?\?>/g, '');

        try {
            new vm.Script(jsCode, { filename: `${file}#script${idx}` });
            console.log(`[PASS] JS Syntax OK in ${file} (script #${idx})`);
        } catch (e) {
            console.error(`[FAIL] JS Syntax Error in ${file} (script #${idx}):`, e.message);
            allPassed = false;
        }
    });
});

if (!allPassed) process.exit(1);
console.log('All JS syntax checks passed successfully!');
