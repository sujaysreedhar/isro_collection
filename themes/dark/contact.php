<?php
// themes/dark/contact.php
require_once ThemeManager::getHeader();
?>

<main class="flex-grow max-w-4xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-12">
    <div class="text-center mb-10">
        <h1 class="text-3xl font-bold text-white mb-3">Contact Us</h1>
        <p class="text-gray-400 max-w-lg mx-auto">Have a question, suggestion, or want to contribute to the collection? We'd love to hear from you.</p>
    </div>

    <?php if (!empty($success)): ?>
        <div class="mb-6 bg-green-900/40 border border-green-700 text-green-300 px-5 py-4 rounded-lg flex items-start gap-3">
            <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span><?= htmlspecialchars($success) ?></span>
        </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="mb-6 bg-red-900/40 border border-red-700 text-red-300 px-5 py-4 rounded-lg flex items-start gap-3">
            <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-sm p-6 sm:p-8">
        <form method="POST" class="space-y-5">
            <div style="display:none !important"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-300 mb-1">Your Name <span class="text-red-400">*</span></label>
                    <input type="text" id="name" name="name" required value="<?= htmlspecialchars($name ?? '') ?>"
                           class="w-full border border-gray-600 rounded-md px-3 py-2.5 text-sm bg-gray-900 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition" placeholder="John Doe">
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-300 mb-1">Email Address <span class="text-red-400">*</span></label>
                    <input type="email" id="email" name="email" required value="<?= htmlspecialchars($email ?? '') ?>"
                           class="w-full border border-gray-600 rounded-md px-3 py-2.5 text-sm bg-gray-900 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition" placeholder="john@example.com">
                </div>
            </div>
            <div>
                <label for="subject" class="block text-sm font-medium text-gray-300 mb-1">Subject</label>
                <input type="text" id="subject" name="subject" value="<?= htmlspecialchars($subject ?? '') ?>"
                       class="w-full border border-gray-600 rounded-md px-3 py-2.5 text-sm bg-gray-900 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition" placeholder="How can we help?">
            </div>
            <div>
                <label for="message" class="block text-sm font-medium text-gray-300 mb-1">Message <span class="text-red-400">*</span></label>
                <textarea id="message" name="message" required rows="5" minlength="10"
                          class="w-full border border-gray-600 rounded-md px-3 py-2.5 text-sm bg-gray-900 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition resize-none" placeholder="Write your message here..."><?= htmlspecialchars($message ?? '') ?></textarea>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="px-6 py-2.5 bg-purple-600 text-white text-sm font-semibold rounded-md hover:bg-purple-700 transition-colors shadow-sm">
                    Send Message
                </button>
            </div>
        </form>
    </div>
</main>

<?php require_once ThemeManager::getFooter(); ?>
