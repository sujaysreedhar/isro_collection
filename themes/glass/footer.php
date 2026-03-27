<?php
// themes/glass/footer.php
?>
    <footer class="mt-auto bg-white/5 backdrop-blur-md border-t border-white/10 relative z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-12 mb-12">
                <div>
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-8 h-8 bg-glass-500/20 border border-glass-400/30 backdrop-blur-md rounded-lg flex items-center justify-center text-white font-bold shadow-lg shadow-glass-500/20">
                            <?= substr(SITE_TITLE, 0, 1) ?>
                        </div>
                        <span class="text-xl font-bold text-white tracking-tight"><?= SITE_TITLE ?></span>
                    </div>
                    <p class="text-slate-300 text-sm leading-relaxed max-w-sm">Preserving history and heritage with modern technology. Explore our digital archive of artifacts and stories.</p>
                </div>
                <div>
                    <h3 class="text-white font-semibold mb-6">Quick Links</h3>
                    <ul class="space-y-3 text-sm text-slate-300">
                        <li><a href="<?= SITE_URL ?>/" class="hover:text-white hover:text-shadow-[0_0_8px_rgba(255,255,255,0.8)] transition-all">Home Collection</a></li>
                        <li><a href="<?= SITE_URL ?>/gallery.php" class="hover:text-white hover:text-shadow-[0_0_8px_rgba(255,255,255,0.8)] transition-all">Visual Gallery</a></li>
                        <li><a href="<?= SITE_URL ?>/atlas.php" class="hover:text-white hover:text-shadow-[0_0_8px_rgba(255,255,255,0.8)] transition-all">Atlas View</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-white font-semibold mb-6">Administration</h3>
                    <ul class="space-y-3 text-sm text-slate-300">
                        <li><a href="<?= SITE_URL ?>/admin/" class="hover:text-white transition-all flex items-center gap-2"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg> Admin Console</a></li>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <li><a href="<?= SITE_URL ?>/logout.php" class="hover:text-white transition-all">Sign Out</a></li>
                        <?php else: ?>
                            <li><a href="<?= SITE_URL ?>/login.php" class="hover:text-white transition-all">Sign In</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            
            <div class="pt-8 border-t border-white/10 flex flex-col md:flex-row items-center justify-between gap-4">
                <p class="text-slate-400 text-sm">
                    &copy; <?= date('Y') ?> <?= SITE_TITLE ?>. All rights reserved.
                </p>
                <div class="text-xs text-slate-500 font-medium tracking-wide">
                    Powered by Glass Theme
                </div>
            </div>
        </div>
    </footer>
    <?php if (class_exists('HookRegistry')) { HookRegistry::doAction('frontend_footer'); } ?>
</body>
</html>
