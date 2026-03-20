<?php
// modules/item_comments/comments_ui.php
// Expected variables: $item (array), $comments (array of associative arrays)

// Start session to show success flags if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$commentSuccess = $_SESSION['comment_success'] ?? false;
$commentError = $_SESSION['comment_error'] ?? false;
unset($_SESSION['comment_success'], $_SESSION['comment_error']);
?>

<div class="mt-16 pt-10 border-t border-slate-200" id="comments">
    <h3 class="text-2xl font-bold font-serif text-slate-900 mb-8">Community Notes</h3>

    <?php if ($commentSuccess): ?>
        <div class="mb-8 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg flex items-center">
            <svg class="w-5 h-5 mr-3 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            Thank you! Your note has been submitted and is pending moderation.
        </div>
    <?php elseif ($commentError): ?>
        <div class="mb-8 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg flex items-center">
            <svg class="w-5 h-5 mr-3 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            <?= htmlspecialchars($commentError) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-12">
        <!-- Comments List -->
        <div class="lg:col-span-2 space-y-8">
            <?php if (empty($comments)): ?>
                <div class="bg-slate-50 p-8 rounded-2xl border border-slate-100 text-center">
                    <p class="text-slate-500 italic">No community notes yet. Share your knowledge or memories about this item!</p>
                </div>
            <?php else: ?>
                <?php foreach ($comments as $c): ?>
                    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex gap-4">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 rounded-full bg-slate-100 flexitems-center justify-center font-bold text-slate-500 text-lg border border-slate-200 uppercase flex items-center">
                                <?= htmlspecialchars(substr($c['author_name'], 0, 1)) ?>
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <h4 class="font-bold text-slate-900"><?= htmlspecialchars($c['author_name']) ?></h4>
                                <span class="text-sm text-slate-400">&bull;&nbsp; <?= date('M j, Y', strtotime($c['created_at'])) ?></span>
                            </div>
                            <div class="prose prose-sm text-slate-700 leading-relaxed">
                                <?= nl2br(htmlspecialchars($c['comment'])) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Submission Form -->
        <div class="lg:col-span-1">
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-lg sticky top-24">
                <h4 class="font-bold text-xl text-slate-900 mb-2">Contribute</h4>
                <p class="text-sm text-slate-500 mb-6">Do you have historical context, a memory, or more information about this artifact? Share it here.</p>

                <form action="<?= SITE_URL ?>/modules/item_comments/submit.php" method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">

                    <!-- Honeypot -->
                    <div class="hidden">
                        <label>Leave empty: <input type="text" name="website_url"></label>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1" for="author_name">Name *</label>
                        <input type="text" id="author_name" name="author_name" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1" for="author_email">Email * <span class="text-slate-400 font-normal">(never published)</span></label>
                        <input type="email" id="author_email" name="author_email" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1" for="comment_text">Your Note *</label>
                        <textarea id="comment_text" name="comment" rows="4" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></textarea>
                    </div>

                    <button type="submit" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold py-2.5 px-4 rounded-lg transition-colors shadow-sm">
                        Submit Note
                    </button>
                    <p class="text-xs text-center text-slate-400 mt-3">All notes are reviewed by an archivist before publishing.</p>
                </form>
            </div>
        </div>
    </div>
</div>
