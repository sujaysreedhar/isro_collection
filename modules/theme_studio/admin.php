<?php
// modules/theme_studio/admin.php — Visual customizer admin panel
if (!defined('SITE_URL')) exit;

global $pdo, $appSettings;

$s = static fn(string $k, string $d = '') => $appSettings[$k] ?? $d;
$ajaxUrl  = SITE_URL . '/admin/ajax.php';
$saveUrl  = SITE_URL . '/modules/theme_studio/ajax_save.php';
$csrf     = htmlspecialchars(ensureCsrfToken());

$heroImage = $s('theme_studio_hero_image', '');
$heroImgUrl = $heroImage && file_exists(__DIR__ . '/../../uploads/branding/' . $heroImage)
    ? SITE_URL . '/uploads/branding/' . rawurlencode($heroImage)
    : '';

// Google Font pairs for the dropdown
$fontPairs = [
    ['body' => 'Inter',        'heading' => 'Playfair Display', 'label' => 'Classic (Inter + Playfair Display)'],
    ['body' => 'Lato',         'heading' => 'Merriweather',     'label' => 'Editorial (Lato + Merriweather)'],
    ['body' => 'Open Sans',    'heading' => 'Raleway',          'label' => 'Modern (Open Sans + Raleway)'],
    ['body' => 'Poppins',      'heading' => 'Poppins',          'label' => 'Unified (Poppins + Poppins)'],
    ['body' => 'Nunito',       'heading' => 'Libre Baskerville','label' => 'Bookish (Nunito + Libre Baskerville)'],
    ['body' => 'Roboto',       'heading' => 'Oswald',           'label' => 'Bold (Roboto + Oswald)'],
    ['body' => 'Source Sans 3','heading' => 'Spectral',         'label' => 'Academic (Source Sans 3 + Spectral)'],
    ['body' => 'system-ui',    'heading' => 'system-ui',        'label' => 'System fonts (offline-safe)'],
];

$currentBodyFont    = $s('theme_studio_font_body',    'Inter');
$currentHeadingFont = $s('theme_studio_font_heading', 'Playfair Display');
$matchedPair = 'custom';
foreach ($fontPairs as $pair) {
    if ($pair['body'] === $currentBodyFont && $pair['heading'] === $currentHeadingFont) {
        $matchedPair = $pair['body'] . '|' . $pair['heading'];
        break;
    }
}
?>
<style>
/* Admin panel scoped styles */
.ts-tab-btn        { padding:.55rem 1.25rem; font-size:.85rem; font-weight:600; border-bottom:2px solid transparent; color:#6b7280; cursor:pointer; transition:all .15s; white-space:nowrap; }
.ts-tab-btn.active { border-bottom-color:#2563eb; color:#1d4ed8; }
.ts-tab-panel      { display:none; }
.ts-tab-panel.active { display:block; }
.ts-color-row      { display:flex; align-items:center; gap:.75rem; padding:.6rem 0; border-bottom:1px solid #f3f4f6; }
.ts-color-row:last-child { border-bottom:none; }
.ts-color-swatch   { width:2.2rem; height:2.2rem; border-radius:.4rem; border:2px solid #e5e7eb; flex-shrink:0; cursor:pointer; }
.ts-color-label    { flex:1; font-size:.875rem; color:#374151; font-weight:500; }
.ts-color-hex      { font-size:.8rem; font-family:monospace; color:#6b7280; width:6rem; border:1px solid #e5e7eb; border-radius:.35rem; padding:.25rem .5rem; }
.ts-section-title  { font-size:.8rem; font-weight:700; color:#9ca3af; text-transform:uppercase; letter-spacing:.08em; margin-bottom:.75rem; margin-top:1.5rem; }
.ts-section-title:first-child { margin-top:0; }
.ts-radio-card     { border:2px solid #e5e7eb; border-radius:.75rem; padding:1rem; cursor:pointer; transition:all .15s; flex:1; text-align:center; }
.ts-radio-card.selected { border-color:#2563eb; background:#eff6ff; }
.ts-radio-card:hover { border-color:#93c5fd; }
.ts-toggle         { position:relative; display:inline-flex; align-items:center; cursor:pointer; gap:.5rem; }
.ts-toggle input   { position:absolute; opacity:0; width:0; height:0; }
.ts-toggle-track   { width:42px; height:24px; background:#d1d5db; border-radius:9999px; transition:background .2s; position:relative; }
.ts-toggle input:checked + .ts-toggle-track { background:#2563eb; }
.ts-toggle-thumb   { position:absolute; top:3px; left:3px; width:18px; height:18px; background:#fff; border-radius:50%; transition:transform .2s; box-shadow:0 1px 3px rgba(0,0,0,.2); }
.ts-toggle input:checked ~ .ts-toggle-track .ts-toggle-thumb { transform:translateX(18px); }
.ts-preview-bar    { height:4px; border-radius:2px; margin-top:.4rem; transition:background .2s; }
/* Pickr overrides */
.pcr-button        { width:2.2rem!important; height:2.2rem!important; border-radius:.4rem!important; border:2px solid #e5e7eb!important; flex-shrink:0; cursor:pointer; box-shadow:none!important; }
.pcr-button::after { border-radius:.3rem!important; }
.pickr             { line-height:0; }
</style>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@simonweis/pickr@1.9.1/dist/themes/nano.min.css">
<script src="https://cdn.jsdelivr.net/npm/@simonweis/pickr@1.9.1/dist/pickr.min.js"></script>

<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">🎨 Theme Studio</h1>
        <p class="text-sm text-gray-500 mt-1">Customise colors, fonts, and home page layout for the <strong>Custom</strong> theme.</p>
    </div>
    <div class="flex items-center gap-3">
        <a href="<?= SITE_URL ?>" target="_blank"
           class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
            Preview site
        </a>
        <button id="ts-save-btn" onclick="tsSave()"
                class="inline-flex items-center gap-1.5 px-5 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            Save All Changes
        </button>
    </div>
</div>

<div id="ts-toast" class="hidden fixed bottom-6 right-6 z-50 px-5 py-3 rounded-xl shadow-xl text-sm font-semibold text-white bg-green-600 transition-all">✓ Saved!</div>
<div id="ts-errtoast" class="hidden fixed bottom-6 right-6 z-50 px-5 py-3 rounded-xl shadow-xl text-sm font-semibold text-white bg-red-500 transition-all">Error saving.</div>

<!-- Tabs -->
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="flex items-center border-b border-gray-200 px-4 overflow-x-auto">
        <button class="ts-tab-btn active" onclick="tsTab('colors',this)">🎨 Colors</button>
        <button class="ts-tab-btn"        onclick="tsTab('typography',this)">🔤 Typography</button>
        <button class="ts-tab-btn"        onclick="tsTab('layout',this)">🖼 Layout & Hero</button>
    </div>

    <div class="p-6">

        <!-- ══════════════ COLORS TAB ══════════════ -->
        <div id="ts-tab-colors" class="ts-tab-panel active">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

                <!-- Left: colour pickers -->
                <div>
                    <p class="ts-section-title">Brand Colors</p>
                    <?php
                    $colorFields = [
                        ['key' => 'theme_studio_color_primary',    'label' => 'Primary / Headings',   'default' => '#111827'],
                        ['key' => 'theme_studio_color_accent',     'label' => 'Accent (links, buttons)','default'=> '#2563eb'],
                        ['key' => 'theme_studio_color_accent_dark','label' => 'Accent hover state',    'default' => '#1d4ed8'],
                    ];
                    foreach ($colorFields as $f):
                        $val = $s($f['key'], $f['default']);
                    ?>
                    <div class="ts-color-row">
                        <div class="ts-pickr-btn" id="cp-<?= $f['key'] ?>" data-key="<?= $f['key'] ?>" data-value="<?= htmlspecialchars($val) ?>"></div>
                        <span class="ts-color-label"><?= $f['label'] ?></span>
                        <input type="text" class="ts-color-hex" id="hex-<?= $f['key'] ?>" data-key="<?= $f['key'] ?>"
                               value="<?= htmlspecialchars($val) ?>"
                               oninput="fromHexInput('<?= $f['key'] ?>',this.value)"
                               maxlength="7">
                    </div>
                    <?php endforeach; ?>

                    <p class="ts-section-title">Backgrounds</p>
                    <?php
                    $bgFields = [
                        ['key' => 'theme_studio_color_bg',      'label' => 'Page background',  'default' => '#f9fafb'],
                        ['key' => 'theme_studio_color_hero_bg', 'label' => 'Hero background',  'default' => '#ffffff'],
                        ['key' => 'theme_studio_color_surface', 'label' => 'Card / surface',   'default' => '#ffffff'],
                    ];
                    foreach ($bgFields as $f):
                        $val = $s($f['key'], $f['default']);
                    ?>
                    <div class="ts-color-row">
                        <div class="ts-pickr-btn" id="cp-<?= $f['key'] ?>" data-key="<?= $f['key'] ?>" data-value="<?= htmlspecialchars($val) ?>"></div>
                        <span class="ts-color-label"><?= $f['label'] ?></span>
                        <input type="text" class="ts-color-hex" id="hex-<?= $f['key'] ?>" data-key="<?= $f['key'] ?>"
                               value="<?= htmlspecialchars($val) ?>"
                               oninput="fromHexInput('<?= $f['key'] ?>',this.value)"
                               maxlength="7">
                    </div>
                    <?php endforeach; ?>

                    <p class="ts-section-title">Hero Image Overlay</p>
                    <?php
                    $overlayColor   = $s('theme_studio_hero_overlay_color',   '#ffffff');
                    $overlayOpacity = $s('theme_studio_hero_overlay_opacity',  '75');
                    ?>
                    <div class="ts-color-row">
                        <div class="ts-pickr-btn" id="cp-theme_studio_hero_overlay_color" data-key="theme_studio_hero_overlay_color" data-value="<?= htmlspecialchars($overlayColor) ?>"></div>
                        <span class="ts-color-label">Overlay / shadow color <span class="text-xs text-gray-400">(tint over hero image)</span></span>
                        <input type="text" class="ts-color-hex" id="hex-theme_studio_hero_overlay_color" data-key="theme_studio_hero_overlay_color"
                               value="<?= htmlspecialchars($overlayColor) ?>"
                               oninput="fromHexInput('theme_studio_hero_overlay_color',this.value)"
                               maxlength="7">
                    </div>
                    <div class="ts-color-row items-center">
                        <span class="ts-color-label">Overlay opacity</span>
                        <div class="flex items-center gap-2 ml-auto">
                            <input type="range" id="ts-overlay-opacity" min="0" max="100" value="<?= (int)$overlayOpacity ?>"
                                   class="w-28 accent-blue-600"
                                   oninput="document.getElementById('ts-overlay-opacity-val').textContent=this.value+'%'">
                            <span class="text-sm font-mono text-gray-600 w-10" id="ts-overlay-opacity-val"><?= (int)$overlayOpacity ?>%</span>
                        </div>
                    </div>

                    <p class="ts-section-title">Text & Borders</p>
                    <?php
                    $textFields = [
                        ['key' => 'theme_studio_color_text',       'label' => 'Body text',        'default' => '#374151'],
                        ['key' => 'theme_studio_color_text_muted',  'label' => 'Muted / secondary text','default'=> '#6b7280'],
                        ['key' => 'theme_studio_color_border',      'label' => 'Border',           'default' => '#e5e7eb'],
                        ['key' => 'theme_studio_color_footer_bg',   'label' => 'Footer background','default' => '#111827'],
                        ['key' => 'theme_studio_color_footer_text', 'label' => 'Footer text',      'default' => '#9ca3af'],
                    ];
                    foreach ($textFields as $f):
                        $val = $s($f['key'], $f['default']);
                    ?>
                    <div class="ts-color-row">
                        <div class="ts-pickr-btn" id="cp-<?= $f['key'] ?>" data-key="<?= $f['key'] ?>" data-value="<?= htmlspecialchars($val) ?>"></div>
                        <span class="ts-color-label"><?= $f['label'] ?></span>
                        <input type="text" class="ts-color-hex" id="hex-<?= $f['key'] ?>" data-key="<?= $f['key'] ?>"
                               value="<?= htmlspecialchars($val) ?>"
                               oninput="fromHexInput('<?= $f['key'] ?>',this.value)"
                               maxlength="7">
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Right: live palette preview -->
                <div>
                    <p class="ts-section-title">Live Preview</p>
                    <div id="ts-color-preview"
                         class="rounded-xl overflow-hidden shadow border border-gray-200"
                         style="border-color:<?= $s('theme_studio_color_border','#e5e7eb') ?>">
                        <!-- Fake header -->
                        <div id="pv-header" class="px-5 py-3 flex items-center justify-between"
                             style="background:<?= $s('theme_studio_color_surface','#fff') ?>;border-bottom:1px solid <?= $s('theme_studio_color_border','#e5e7eb') ?>">
                            <span class="font-bold text-sm" id="pv-title" style="color:<?= $s('theme_studio_color_primary','#111827') ?>"><?= SITE_TITLE ?></span>
                            <span class="text-xs px-3 py-1 rounded-full text-white" id="pv-btn" style="background:<?= $s('theme_studio_color_accent','#2563eb') ?>">Search</span>
                        </div>
                        <!-- Fake hero -->
                        <div id="pv-hero" class="px-5 py-8" style="background:<?= $s('theme_studio_color_hero_bg','#fff') ?>">
                            <div class="font-extrabold text-xl mb-2" id="pv-heading" style="color:<?= $s('theme_studio_color_primary','#111827') ?>"><?= SITE_TITLE ?></div>
                            <div class="text-sm mb-4" id="pv-sub" style="color:<?= $s('theme_studio_color_text_muted','#6b7280') ?>">Explore the collection</div>
                            <div class="ts-preview-bar w-1/2" id="pv-bar" style="background:<?= $s('theme_studio_color_accent','#2563eb') ?>"></div>
                        </div>
                        <!-- Fake body -->
                        <div id="pv-body" class="px-5 py-6" style="background:<?= $s('theme_studio_color_bg','#f9fafb') ?>">
                            <div class="grid grid-cols-2 gap-3">
                                <?php for($i=0;$i<4;$i++): ?>
                                <div class="rounded-lg p-3" id="pv-card-<?= $i ?>"
                                     style="background:<?= $s('theme_studio_color_surface','#fff') ?>;border:1px solid <?= $s('theme_studio_color_border','#e5e7eb') ?>">
                                    <div class="h-2 rounded mb-2 w-3/4" style="background:<?= $s('theme_studio_color_border','#e5e7eb') ?>"></div>
                                    <div class="h-2 rounded w-1/2" style="background:<?= $s('theme_studio_color_text_muted','#6b7280') ?>;opacity:.4"></div>
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <!-- Fake footer -->
                        <div id="pv-footer" class="px-5 py-3 text-xs" style="background:<?= $s('theme_studio_color_footer_bg','#111827') ?>;color:<?= $s('theme_studio_color_footer_text','#9ca3af') ?>">
                            © <?= date('Y') ?> <?= SITE_TITLE ?>
                        </div>
                    </div>

                    <!-- Color palette presets -->
                    <p class="ts-section-title mt-6">Quick Presets</p>
                    <div class="flex flex-wrap gap-2">
                        <?php
                        $presets = [
                            ['name'=>'Classic Dark', 'primary'=>'#111827','accent'=>'#2563eb','bg'=>'#f9fafb','surface'=>'#ffffff','footer_bg'=>'#111827'],
                            ['name'=>'Warm Sepia',   'primary'=>'#4a3728','accent'=>'#c2410c','bg'=>'#fef9f0','surface'=>'#fffbf5','footer_bg'=>'#4a3728'],
                            ['name'=>'Deep Violet',  'primary'=>'#1e1b4b','accent'=>'#7c3aed','bg'=>'#f5f3ff','surface'=>'#ffffff','footer_bg'=>'#1e1b4b'],
                            ['name'=>'Forest Green', 'primary'=>'#14532d','accent'=>'#16a34a','bg'=>'#f0fdf4','surface'=>'#ffffff','footer_bg'=>'#14532d'],
                            ['name'=>'Midnight Rose','primary'=>'#4c0519','accent'=>'#e11d48','bg'=>'#fff1f2','surface'=>'#ffffff','footer_bg'=>'#4c0519'],
                            ['name'=>'Ocean Blue',   'primary'=>'#0c4a6e','accent'=>'#0284c7','bg'=>'#f0f9ff','surface'=>'#ffffff','footer_bg'=>'#0c4a6e'],
                        ];
                        ?>
                        <?php foreach ($presets as $pr): ?>
                        <button class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-gray-200 hover:border-blue-300 hover:bg-blue-50 transition-colors"
                                onclick="applyPreset(<?= htmlspecialchars(json_encode($pr)) ?>)">
                            <span class="inline-block w-3 h-3 rounded-full mr-1.5 align-middle" style="background:<?= $pr['accent'] ?>"></span>
                            <?= htmlspecialchars($pr['name']) ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div><!-- /colors tab -->

        <!-- ══════════════ TYPOGRAPHY TAB ══════════════ -->
        <div id="ts-tab-typography" class="ts-tab-panel">
            <div class="max-w-xl space-y-8">
                <div>
                    <label class="ts-section-title block">Font Pairing</label>
                    <div class="space-y-3 mt-1">
                        <?php foreach ($fontPairs as $pair):
                            $val = $pair['body'] . '|' . $pair['heading'];
                            $checked = $val === $matchedPair ? 'checked' : '';
                        ?>
                        <label class="flex items-start gap-3 p-3 rounded-xl border border-gray-200 cursor-pointer hover:border-blue-300 hover:bg-blue-50/50 transition-all has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                            <input type="radio" name="font_pair" value="<?= htmlspecialchars($val) ?>"
                                   <?= $checked ?>
                                   onchange="applyFontPair('<?= $pair['body'] ?>','<?= $pair['heading'] ?>')"
                                   class="mt-0.5 accent-blue-600">
                            <div>
                                <div class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($pair['label']) ?></div>
                                <div class="text-xs text-gray-500">Body: <?= htmlspecialchars($pair['body']) ?> · Heading: <?= htmlspecialchars($pair['heading']) ?></div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Hidden inputs updated by JS -->
                <input type="hidden" id="ts-font-body"    value="<?= htmlspecialchars($currentBodyFont) ?>">
                <input type="hidden" id="ts-font-heading" value="<?= htmlspecialchars($currentHeadingFont) ?>">

                <div>
                    <label class="ts-section-title block">Card Border Radius</label>
                    <div class="flex gap-3 flex-wrap mt-1">
                        <?php foreach (['0px'=>'Sharp','0.25rem'=>'Soft','0.5rem'=>'Rounded','0.75rem'=>'Rounder','1rem'=>'Pill-ish'] as $rv=>$rl):
                            $cur = $s('theme_studio_border_radius','0.5rem');
                        ?>
                        <label class="ts-radio-card <?= ($cur===$rv?'selected':'') ?> flex-none"
                               style="min-width:5rem"
                               onclick="document.querySelectorAll('.ts-radio-card').forEach(c=>c.classList.remove('selected'));this.classList.add('selected');document.getElementById('ts-radius').value='<?= $rv ?>'">
                            <div class="w-8 h-8 mx-auto mb-1 bg-blue-100 border-2 border-blue-400"
                                 style="border-radius:<?= $rv ?>"></div>
                            <div class="text-xs font-semibold text-gray-700"><?= $rl ?></div>
                            <div class="text-xs text-gray-400"><?= $rv ?></div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" id="ts-radius" value="<?= htmlspecialchars($s('theme_studio_border_radius','0.5rem')) ?>">
                </div>
            </div>
        </div><!-- /typography tab -->

        <!-- ══════════════ LAYOUT & HERO TAB ══════════════ -->
        <div id="ts-tab-layout" class="ts-tab-panel">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
                <div class="space-y-8">

                    <!-- Hero style -->
                    <div>
                        <p class="ts-section-title">Hero Layout</p>
                        <div class="flex gap-3">
                            <?php
                            $heroStyles = [
                                'split'    => ['label'=>'Split',    'icon'=>'▌■', 'desc'=>'Text left, image right'],
                                'centered' => ['label'=>'Centered', 'icon'=>'◼',  'desc'=>'Text + image centered'],
                                'minimal'  => ['label'=>'Minimal',  'icon'=>'─',  'desc'=>'Search bar only'],
                            ];
                            $curHero = $s('theme_studio_hero_style','split');
                            foreach ($heroStyles as $hk => $hv):
                            ?>
                            <label class="ts-radio-card flex-1 <?= ($curHero===$hk?'selected':'') ?>" data-group="hero"
                                   onclick="selectCard('hero',this);document.getElementById('ts-hero-style').value='<?= $hk ?>'">
                                <div class="text-2xl mb-1"><?= $hv['icon'] ?></div>
                                <div class="text-xs font-bold text-gray-700"><?= $hv['label'] ?></div>
                                <div class="text-xs text-gray-400 mt-0.5"><?= $hv['desc'] ?></div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" id="ts-hero-style" value="<?= htmlspecialchars($curHero) ?>">
                    </div>

                    <!-- Hero tagline -->
                    <div>
                        <label class="ts-section-title block">Hero H1 Text <span class="text-gray-400 font-normal normal-case text-xs">(first line above site title)</span></label>
                        <input type="text" id="ts-hero-title"
                               value="<?= htmlspecialchars($s('theme_studio_hero_title','')) ?>"
                               placeholder="e.g. Discover history in the"
                               class="mt-1 w-full border border-gray-300 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        <p class="mt-1 text-xs text-gray-400">In Split layout this appears as a second line in accent color below the h1 text.</p>
                    </div>

                    <!-- Hero tagline -->
                    <div>
                        <label class="ts-section-title block">Hero Tagline</label>
                        <input type="text" id="ts-hero-tagline"
                               value="<?= htmlspecialchars($s('theme_studio_hero_tagline','')) ?>"
                               placeholder="e.g. Explore the postmarks of India..."
                               class="mt-1 w-full border border-gray-300 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                    </div>

                    <!-- Hero text colors -->
                    <div class="p-4 rounded-xl border border-gray-200 space-y-3">
                        <p class="ts-section-title" style="margin-top:0">Hero Text Colors <span class="text-gray-400 font-normal normal-case text-xs">(leave blank to use theme defaults)</span></p>
                        <?php
                        $heroTxtColor = $s('theme_studio_hero_text_color','');
                        $heroTagColor = $s('theme_studio_hero_tagline_color','');
                        ?>
                        <div class="ts-color-row">
                            <div class="ts-pickr-btn" id="cp-theme_studio_hero_text_color"
                                 data-key="theme_studio_hero_text_color"
                                 data-value="<?= htmlspecialchars($heroTxtColor ?: '#111827') ?>"></div>
                            <span class="ts-color-label">H1 heading color</span>
                            <input type="text" class="ts-color-hex" id="hex-theme_studio_hero_text_color" data-key="theme_studio_hero_text_color"
                                   value="<?= htmlspecialchars($heroTxtColor ?: '#111827') ?>"
                                   oninput="fromHexInput('theme_studio_hero_text_color',this.value)" maxlength="7">
                            <button class="text-xs text-gray-400 hover:text-red-500 ml-1" title="Use theme default"
                                    onclick="clearHeroColor('theme_studio_hero_text_color')">✕</button>
                        </div>
                        <div class="ts-color-row">
                            <div class="ts-pickr-btn" id="cp-theme_studio_hero_tagline_color"
                                 data-key="theme_studio_hero_tagline_color"
                                 data-value="<?= htmlspecialchars($heroTagColor ?: '#6b7280') ?>"></div>
                            <span class="ts-color-label">Tagline / subtitle color</span>
                            <input type="text" class="ts-color-hex" id="hex-theme_studio_hero_tagline_color" data-key="theme_studio_hero_tagline_color"
                                   value="<?= htmlspecialchars($heroTagColor ?: '#6b7280') ?>"
                                   oninput="fromHexInput('theme_studio_hero_tagline_color',this.value)" maxlength="7">
                            <button class="text-xs text-gray-400 hover:text-red-500 ml-1" title="Use theme default"
                                    onclick="clearHeroColor('theme_studio_hero_tagline_color')">✕</button>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Tip: use white (#ffffff) when the hero has a dark image for readability.</p>
                    </div>

                    <!-- Hero image -->
                    <div>
                        <p class="ts-section-title">Hero Image</p>
                        <div id="ts-hero-img-box"
                             class="w-full h-40 border-2 border-dashed border-gray-300 rounded-xl flex items-center justify-center bg-gray-50 overflow-hidden cursor-pointer relative"
                             onclick="document.getElementById('ts-hero-file').click()">
                            <?php if ($heroImgUrl): ?>
                                <img src="<?= htmlspecialchars($heroImgUrl) ?>" id="ts-hero-img-preview"
                                     class="absolute inset-0 w-full h-full object-cover">
                                <div class="absolute inset-0 bg-black/20 flex items-center justify-center opacity-0 hover:opacity-100 transition-opacity">
                                    <span class="text-white text-sm font-semibold bg-black/40 px-3 py-1 rounded-lg">Click to change</span>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-gray-400 pointer-events-none" id="ts-hero-placeholder">
                                    <svg class="mx-auto w-10 h-10 mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    <p class="text-xs font-medium">Click to upload hero image</p>
                                    <p class="text-xs">JPG, PNG, WebP · Max 5 MB</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <input type="file" id="ts-hero-file" accept="image/*" class="hidden" onchange="uploadHeroImage(this)">
                        <?php if ($heroImgUrl): ?>
                        <button onclick="removeHeroImage()" class="mt-2 text-xs text-red-500 hover:text-red-700 font-medium">Remove hero image</button>
                        <?php endif; ?>
                        <p class="mt-1.5 text-xs text-gray-400">Used in Split (right column) and Centered (background) hero layouts.</p>
                    </div>

                    <!-- Grid cols -->
                    <div>
                        <p class="ts-section-title">Items Grid Columns</p>
                        <?php
                        $colOptions = [
                            '2' => ['label' => '2 – Comfortable', 'desc' => 'Large cards, 2 per row'],
                            '3' => ['label' => '3 – Standard',    'desc' => 'Default, 3 per row'],
                            '4' => ['label' => '4 – Compact',     'desc' => 'More items, 4 per row'],
                        ];
                        $curCols = $s('theme_studio_grid_cols','3');
                        ?>
                        <div class="flex gap-3 mt-1">
                            <?php foreach ($colOptions as $cols => $opt): ?>
                            <label class="ts-radio-card flex-1 <?= ($curCols===$cols?'selected':'') ?>" data-group="cols"
                                   onclick="selectCard('cols',this);document.getElementById('ts-grid-cols').value='<?= $cols ?>'">
                                <div class="grid gap-1 mx-auto mb-2" style="grid-template-columns:repeat(<?= $cols ?>,1fr);width:<?= ((int)$cols*14) ?>px">
                                    <?php for($i=0;$i<(int)$cols;$i++): ?>
                                    <div class="rounded-sm" style="height:18px;background:var(--color-accent,#2563eb);opacity:<?= $curCols===$cols?'1':'.4' ?>"></div>
                                    <?php endfor; ?>
                                </div>
                                <div class="text-xs font-bold text-gray-800"><?= $opt['label'] ?></div>
                                <div class="text-xs text-gray-400 mt-0.5"><?= $opt['desc'] ?></div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" id="ts-grid-cols" value="<?= htmlspecialchars($curCols) ?>">
                    </div>

                    <!-- Featured count -->
                    <div>
                        <label class="ts-section-title block">Featured Items on Home Page</label>
                        <select id="ts-featured-count"
                                class="mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                            <?php foreach (['3','6','9','12'] as $n): ?>
                            <option value="<?= $n ?>" <?= $s('theme_studio_featured_count','6')===$n?'selected':'' ?>><?= $n ?> items</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Toggles -->
                    <div class="space-y-4">
                        <p class="ts-section-title">Sections</p>
                        <div class="flex items-center justify-between p-3 rounded-xl border border-gray-200">
                            <div>
                                <div class="text-sm font-semibold text-gray-800">Hero search bar</div>
                                <div class="text-xs text-gray-500">Show search input inside the hero section</div>
                            </div>
                            <label class="ts-toggle">
                                <input type="checkbox" id="ts-show-search"
                                       <?= $s('theme_studio_show_search','1')==='1'?'checked':'' ?>>
                                <div class="ts-toggle-track"><div class="ts-toggle-thumb"></div></div>
                            </label>
                        </div>
                        <div class="flex items-center justify-between p-3 rounded-xl border border-gray-200">
                            <div>
                                <div class="text-sm font-semibold text-gray-800">Stats bar</div>
                                <div class="text-xs text-gray-500">Show total items count below the hero</div>
                            </div>
                            <label class="ts-toggle">
                                <input type="checkbox" id="ts-show-stats"
                                       <?= $s('theme_studio_show_stats','0')==='1'?'checked':'' ?>>
                                <div class="ts-toggle-track"><div class="ts-toggle-thumb"></div></div>
                            </label>
                        </div>
                    </div>

                    <!-- Footer text -->
                    <div>
                        <label class="ts-section-title block">Footer Text <span class="text-gray-400 font-normal normal-case text-xs">(leave blank to use default copyright)</span></label>
                        <textarea id="ts-footer-text" rows="2"
                                  placeholder="© <?= date('Y') ?> <?= SITE_TITLE ?>. All rights reserved."
                                  class="mt-1 w-full border border-gray-300 rounded-lg px-3.5 py-2.5 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"><?= htmlspecialchars($s('theme_studio_footer_text','')) ?></textarea>
                        <p class="mt-1 text-xs text-gray-400">Supports line breaks. Shown left-aligned in the footer.</p>
                    </div>

                </div><!-- /col 1 -->

                <!-- Layout preview mockup -->
                <div>
                    <p class="ts-section-title">Mockup</p>
                    <div class="rounded-xl overflow-hidden shadow border border-gray-200 bg-white">
                        <div class="bg-gray-800 flex items-center gap-1.5 px-3 py-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-red-400"></span>
                            <span class="w-2.5 h-2.5 rounded-full bg-yellow-400"></span>
                            <span class="w-2.5 h-2.5 rounded-full bg-green-400"></span>
                        </div>
                        <div id="pv-layout-hero" class="px-4 py-6 text-center border-b border-gray-100" style="background:#f8fafc">
                            <div class="font-bold text-sm text-gray-800"><?= SITE_TITLE ?></div>
                            <div class="text-xs text-gray-500 mt-1">Your tagline here</div>
                            <div class="mt-3 h-6 bg-gray-200 rounded-lg w-3/4 mx-auto flex items-center px-2">
                                <div class="w-2 h-2 bg-gray-400 rounded-full mr-1.5"></div>
                                <div class="h-1.5 bg-gray-300 rounded flex-1"></div>
                                <div class="ml-1.5 px-2 py-0.5 rounded" style="background:var(--color-accent,#2563eb);width:24px;height:14px"></div>
                            </div>
                        </div>
                        <div id="pv-layout-grid" class="p-4 grid gap-2" style="grid-template-columns:repeat(3,1fr);">
                            <?php for($i=0;$i<6;$i++): ?>
                            <div class="bg-gray-100 rounded h-10"></div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div><!-- /layout tab -->

    </div><!-- /p-6 -->
</div><!-- /card -->

<script>
const TS_AJAX_URL = '<?= $ajaxUrl ?>';
const TS_CSRF    = '<?= $csrf ?>';

// ── Tab switching ─────────────────────────────────────────────────────────────
function tsTab(id, btn) {
    document.querySelectorAll('.ts-tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.ts-tab-btn').forEach(b  => b.classList.remove('active'));
    document.getElementById('ts-tab-' + id).classList.add('active');
    btn.classList.add('active');
}

// ── Radio card group selection (isolated by data-group) ───────────────────────
function selectCard(group, el) {
    document.querySelectorAll(`.ts-radio-card[data-group="${group}"]`).forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
}

// ── Clear hero text color (revert to theme default) ───────────────────────────
function clearHeroColor(key) {
    const anchor = document.getElementById('cp-' + key);
    const hexEl  = document.getElementById('hex-' + key);
    if (anchor) anchor.dataset.value = '';
    if (hexEl)  hexEl.value = '';
    // Reset Pickr to placeholder
    const p = _pickrs[key];
    const def = key.includes('tagline') ? '#6b7280' : '#111827';
    if (p) p.setColor(def, true);
}

// ── Pickr color picker initialisation ───────────────────────────────────────
const _pickrs = {}; // key -> Pickr instance

function initPickrs() {
    document.querySelectorAll('.ts-pickr-btn').forEach(el => {
        const key     = el.dataset.key;
        const defVal  = el.dataset.value || '#000000';

        const p = Pickr.create({
            el,
            theme:   'nano',
            default: defVal,
            comparison: false,
            components: {
                preview:  true,
                opacity:  false,
                hue:      true,
                interaction: { hex: true, rgba: false, input: true, save: true },
            },
        });

        p.on('change', color => {
            const hex = '#' + color.toHEXA().join('').substring(0, 6);
            const hexEl = document.getElementById('hex-' + key);
            if (hexEl) hexEl.value = hex;
            el.dataset.value = hex;
            updatePreview(key, hex);
        });

        p.on('save', color => {
            p.hide();
            const hex = '#' + color.toHEXA().join('').substring(0, 6);
            el.dataset.value = hex;
            const hexEl = document.getElementById('hex-' + key);
            if (hexEl) hexEl.value = hex;
            updatePreview(key, hex);
        });

        _pickrs[key] = p;
    });
}

// Called when user types in the hex box manually
function fromHexInput(key, val) {
    if (!/^#[0-9a-fA-F]{6}$/.test(val)) return;
    const anchor = document.getElementById('cp-' + key);
    if (anchor) anchor.dataset.value = val;
    const p = _pickrs[key];
    if (p) p.setColor(val, true); // silent = don't fire 'change'
    updatePreview(key, val);
}

document.addEventListener('DOMContentLoaded', initPickrs);

// Map setting key -> which preview elements to update and how
const PREVIEW_MAP = {
    theme_studio_color_primary:    el => {
        ['pv-title','pv-heading'].forEach(id => { const e=document.getElementById(id); if(e)e.style.color=el; });
    },
    theme_studio_color_accent:     el => {
        ['pv-btn','pv-bar'].forEach(id => { const e=document.getElementById(id); if(e)e.style.background=el; });
    },
    theme_studio_color_bg:         el => {
        const e=document.getElementById('pv-body'); if(e)e.style.background=el;
    },
    theme_studio_color_hero_bg:    el => {
        const e=document.getElementById('pv-hero'); if(e)e.style.background=el;
    },
    theme_studio_color_surface:    el => {
        const h=document.getElementById('pv-header'); if(h)h.style.background=el;
        document.querySelectorAll('[id^=pv-card-]').forEach(c=>c.style.background=el);
    },
    theme_studio_color_border:     el => {
        const h=document.getElementById('pv-header'); if(h)h.style.borderBottomColor=el;
        const p=document.getElementById('ts-color-preview'); if(p)p.style.borderColor=el;
        document.querySelectorAll('[id^=pv-card-]').forEach(c=>c.style.borderColor=el);
    },
    theme_studio_color_text_muted: el => {
        const e=document.getElementById('pv-sub'); if(e)e.style.color=el;
    },
    theme_studio_color_footer_bg:  el => {
        const e=document.getElementById('pv-footer'); if(e)e.style.background=el;
    },
    theme_studio_color_footer_text:el => {
        const e=document.getElementById('pv-footer'); if(e)e.style.color=el;
    },
};
function updatePreview(key, val) {
    if (PREVIEW_MAP[key]) PREVIEW_MAP[key](val);
}

// ── Presets ───────────────────────────────────────────────────────────────────
function applyPreset(pr) {
    const map = {
        'theme_studio_color_primary':    pr.primary,
        'theme_studio_color_accent':     pr.accent,
        'theme_studio_color_bg':         pr.bg,
        'theme_studio_color_surface':    pr.surface,
        'theme_studio_color_footer_bg':  pr.footer_bg,
    };
    for (const [k,v] of Object.entries(map)) {
        const anchor = document.getElementById('cp-' + k);
        const hx     = document.getElementById('hex-' + k);
        if (anchor) anchor.dataset.value = v;
        if (hx) hx.value = v;
        if (_pickrs[k]) _pickrs[k].setColor(v, true);
        updatePreview(k, v);
    }
}

// ── Font pair ─────────────────────────────────────────────────────────────────
function applyFontPair(body, heading) {
    document.getElementById('ts-font-body').value    = body;
    document.getElementById('ts-font-heading').value = heading;
}

// ── Hero image upload ─────────────────────────────────────────────────────────
function uploadHeroImage(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const fd   = new FormData();
    fd.append('csrf_token',      TS_CSRF);
    fd.append('action',          'theme_studio_upload_hero');
    fd.append('hero_image_file', file);
    fetch(TS_AJAX_URL, { method:'POST', body:fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                const box = document.getElementById('ts-hero-img-box');
                box.innerHTML = `<img src="${d.url}" class="absolute inset-0 w-full h-full object-cover">
                    <div class="absolute inset-0 bg-black/20 flex items-center justify-center opacity-0 hover:opacity-100 transition-opacity">
                        <span class="text-white text-sm font-semibold bg-black/40 px-3 py-1 rounded-lg">Click to change</span>
                    </div>`;
                toast(true, '✓ Hero image uploaded!');
            } else {
                toast(false, d.error || 'Upload failed.');
            }
        }).catch(() => toast(false, 'Network error.'));
}
function removeHeroImage() {
    if (!confirm('Remove hero image?')) return;
    const fd = new FormData();
    fd.append('csrf_token', TS_CSRF);
    fd.append('action', 'theme_studio_remove_hero');
    fetch(TS_AJAX_URL, {method:'POST', body:fd}).then(r=>r.json()).then(d => {
        if(d.success) {
            const box = document.getElementById('ts-hero-img-box');
            box.innerHTML = `<div class="text-center text-gray-400 pointer-events-none">
                <svg class="mx-auto w-10 h-10 mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <p class="text-xs font-medium">Click to upload hero image</p></div>`;
            toast(true, '✓ Hero image removed.');
        }
    });
}

// ── Gather all settings from the form ────────────────────────────────────────
function gatherSettings() {
    const settings = {};
    // Colors — read from .ts-pickr-btn data-value
    document.querySelectorAll('.ts-pickr-btn[data-key]').forEach(el => {
        settings[el.dataset.key] = el.dataset.value || '#000000';
    });
    // Typography
    settings['theme_studio_font_body']    = document.getElementById('ts-font-body').value;
    settings['theme_studio_font_heading'] = document.getElementById('ts-font-heading').value;
    settings['theme_studio_border_radius']= document.getElementById('ts-radius').value;
    // Layout
    settings['theme_studio_hero_style']         = document.getElementById('ts-hero-style').value;
    settings['theme_studio_hero_title']          = document.getElementById('ts-hero-title').value;
    // Hero text colors — store empty string if the value was cleared (uses theme default on frontend)
    const _htc = document.getElementById('cp-theme_studio_hero_text_color')?.dataset.value || '';
    settings['theme_studio_hero_text_color']     = (_htc === '#111827') ? '' : _htc;
    const _htagc = document.getElementById('cp-theme_studio_hero_tagline_color')?.dataset.value || '';
    settings['theme_studio_hero_tagline_color']  = (_htagc === '#6b7280') ? '' : _htagc;
    settings['theme_studio_hero_overlay_color']  = document.getElementById('cp-theme_studio_hero_overlay_color')?.dataset.value || '#ffffff';
    settings['theme_studio_hero_overlay_opacity']= document.getElementById('ts-overlay-opacity')?.value || '75';
    settings['theme_studio_grid_cols']           = document.getElementById('ts-grid-cols').value;
    settings['theme_studio_featured_count'] = document.getElementById('ts-featured-count').value;
    settings['theme_studio_show_search']    = document.getElementById('ts-show-search').checked ? '1' : '0';
    settings['theme_studio_show_stats']     = document.getElementById('ts-show-stats').checked  ? '1' : '0';
    settings['theme_studio_hero_tagline']   = document.getElementById('ts-hero-tagline').value;
    settings['theme_studio_footer_text']    = document.getElementById('ts-footer-text').value;
    return settings;
}

// ── Save ──────────────────────────────────────────────────────────────────────
async function tsSave() {
    const btn = document.getElementById('ts-save-btn');
    btn.disabled = true; btn.textContent = 'Saving…';
    const fd = new FormData();
    fd.append('csrf_token', TS_CSRF);
    const settings = gatherSettings();
    for (const [k,v] of Object.entries(settings)) fd.append('settings[' + k + ']', v);
    fd.append('action', 'theme_studio_save');

    try {
        const r = await fetch(TS_AJAX_URL, { method:'POST', body:fd });
        const d = await r.json();
        toast(d.success, d.success ? '✓ All changes saved!' : (d.error || 'Save failed.'));
    } catch(e) {
        toast(false, 'Network error.');
    } finally {
        btn.disabled = false; btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Save All Changes';
    }
}

// ── Toast ─────────────────────────────────────────────────────────────────────
function toast(ok, msg) {
    const el = document.getElementById(ok ? 'ts-toast' : 'ts-errtoast');
    el.textContent = msg;
    el.classList.remove('hidden');
    setTimeout(() => el.classList.add('hidden'), 3000);
}
</script>
