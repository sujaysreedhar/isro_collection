<?php
// themes/modern_blue/contact.php
require_once ThemeManager::getHeader();
?>

<main class="flex-grow">
    <!-- Hero Banner -->
    <div class="bg-gradient-to-br from-modern-950 via-modern-900 to-slate-900 text-white py-16 sm:py-24 relative overflow-hidden">
        <div class="absolute inset-0 opacity-10">
            <div class="absolute inset-0" style="background-image: radial-gradient(circle at 25% 25%, rgba(59,130,246,0.3) 0%, transparent 50%), radial-gradient(circle at 75% 75%, rgba(99,102,241,0.3) 0%, transparent 50%);"></div>
        </div>
        <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <div class="inline-flex items-center gap-2 px-3 py-1 bg-white/10 backdrop-blur-sm rounded-full text-xs font-semibold uppercase tracking-widest text-blue-200 mb-6 border border-white/10">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                Get in Touch
            </div>
            <h1 class="text-4xl sm:text-5xl font-extrabold tracking-tight mb-4">Contact Us</h1>
            <p class="text-lg text-blue-200/80 max-w-xl mx-auto">Have a question, feedback, or interested in contributing? We'd love to hear from you.</p>
        </div>
    </div>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 -mt-12 relative z-10 pb-16">

        <?php if (!empty($success)): ?>
            <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-700 px-5 py-4 rounded-2xl flex items-start gap-3 shadow-sm">
                <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span class="font-medium"><?= htmlspecialchars($success) ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-5 py-4 rounded-2xl flex items-start gap-3 shadow-sm">
                <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span class="font-medium"><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-3xl border border-slate-200 shadow-xl shadow-slate-200/50 p-8 sm:p-10">
            <form method="POST" class="space-y-6">
                <!-- Honeypot -->
                <div style="display:none !important"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label for="name" class="block text-sm font-semibold text-slate-700 mb-1.5">Your Name <span class="text-red-500">*</span></label>
                        <input type="text" id="name" name="name" required value="<?= htmlspecialchars($name ?? '') ?>"
                               class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-modern-500/20 focus:border-modern-500 transition-all shadow-sm" placeholder="John Doe">
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-semibold text-slate-700 mb-1.5">Email Address <span class="text-red-500">*</span></label>
                        <input type="email" id="email" name="email" required value="<?= htmlspecialchars($email ?? '') ?>"
                               class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-modern-500/20 focus:border-modern-500 transition-all shadow-sm" placeholder="john@example.com">
                    </div>
                </div>
                <div>
                    <label for="subject" class="block text-sm font-semibold text-slate-700 mb-1.5">Subject</label>
                    <input type="text" id="subject" name="subject" value="<?= htmlspecialchars($subject ?? '') ?>"
                           class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-modern-500/20 focus:border-modern-500 transition-all shadow-sm" placeholder="How can we help?">
                </div>
                <div>
                    <label for="message" class="block text-sm font-semibold text-slate-700 mb-1.5">Message <span class="text-red-500">*</span></label>
                    <textarea id="message" name="message" required rows="6" minlength="10"
                              class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-modern-500/20 focus:border-modern-500 transition-all shadow-sm resize-none" placeholder="Tell us what's on your mind..."><?= htmlspecialchars($message ?? '') ?></textarea>
                </div>
                <div class="flex justify-end pt-2">
                    <button type="submit" class="group px-8 py-3 bg-gradient-to-r from-modern-600 to-modern-700 text-white text-sm font-bold rounded-xl hover:from-modern-700 hover:to-modern-800 transition-all shadow-lg shadow-modern-500/20 hover:shadow-xl hover:shadow-modern-500/30 active:scale-[0.98]">
                        <span class="flex items-center gap-2">
                            Send Message
                            <svg class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </span>
                    </button>
                </div>
            </form>
        </div>

    </div>
</main>

<?php require_once ThemeManager::getFooter(); ?>
