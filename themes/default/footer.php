<?php
// themes/default/footer.php
?>
    <!-- Footer -->
    <footer class="bg-gray-900 text-white mt-auto py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row justify-between items-center text-sm text-gray-400">
            <p>&copy; <?= date('Y') ?> <?= SITE_TITLE ?>. All rights reserved.</p>
            <div class="flex space-x-6 mt-4 md:mt-0">
                <a href="#" class="hover:text-white transition-colors">Privacy</a>
                <a href="#" class="hover:text-white transition-colors">Terms</a>
            </div>
        </div>
    </footer>
    
    <!-- Module footer hooks -->
    <?php if (class_exists('HookRegistry')) { HookRegistry::doAction('frontend_footer'); } ?>
</body>
</html>
