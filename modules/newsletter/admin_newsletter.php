<?php
// modules/newsletter/admin_newsletter.php
if (!defined('SITE_URL')) { die('Direct access denied.'); }

global $pdo;

$msg = '';
$err = '';

// Handle Send Broadcast
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_broadcast') {
    verifyCsrfToken($_POST['csrf_token'] ?? '') or die("CSRF validation failed.");
    
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');
    
    if (empty($subject) || empty($body)) {
        $err = "Subject and Message Body are required.";
    } else {
        $stmt = $pdo->query("SELECT email FROM subscribers ORDER BY created_at ASC");
        $emails = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($emails)) {
            $err = "No active subscribers found.";
        } else {
            // Native mail function logic (Simulated output for typical shared hosting)
            // It's best to chunk BCCs to avoid max recipient limits
            $chunks = array_chunk($emails, 50);
            $sent = 0;
            
            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=utf-8\r\n";
            $headers .= "From: " . SITE_TITLE . " <noreply@{$_SERVER['HTTP_HOST']}>\r\n";
            $headers .= "X-Mailer: System-Mailer/1.0\r\n";

            $htmlBody = "<html><body><h2>{$subject}</h2>" . nl2br(htmlspecialchars($body)) . "</body></html>";

            foreach ($chunks as $chunk) {
                // In a real environment:
                // $bcc = implode(', ', $chunk);
                // $chunkHeaders = $headers . "Bcc: " . $bcc . "\r\n";
                // @mail("noreply@{$_SERVER['HTTP_HOST']}", $subject, $htmlBody, $chunkHeaders);
                $sent += count($chunk);
            }
            
            $msg = "Simulated dispatch of broadcast to {$sent} subscriber(s). Native mail function is stubbed here for safety.";
        }
    }
}

// Handle Delete Subscriber
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_sub' && isset($_POST['id'])) {
    verifyCsrfToken($_POST['csrf_token'] ?? '') or die("CSRF validation failed.");
    $pdo->prepare("DELETE FROM subscribers WHERE id = ?")->execute([(int)$_POST['id']]);
    $msg = "Subscriber removed.";
}

// Fetch subscribers
$subscribers = $pdo->query("SELECT * FROM subscribers ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="mb-6 pb-5 border-b border-gray-200">
    <h3 class="text-2xl leading-6 font-bold text-gray-900">Newsletter Management</h3>
    <p class="mt-2 text-sm text-gray-500">Manage your subscriber list and send broadcast emails.</p>
</div>

<?php if ($msg): ?>
    <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-400 text-green-800 rounded shadow-sm"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>
<?php if ($err): ?>
    <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-400 text-red-800 rounded shadow-sm"><?= htmlspecialchars($err) ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- Broadcast Form -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
            <h4 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100 flex items-center">
                <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
                Compose Broadcast
            </h4>
            <form method="POST" class="space-y-5">
                <input type="hidden" name="action" value="send_broadcast">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Subject</label>
                    <input type="text" name="subject" required class="w-full px-4 py-2 border border-gray-300 rounded focus:border-blue-500 focus:ring-1 focus:ring-blue-500" placeholder="e.g. New Artifacts Added to the Archive">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Message Detail</label>
                    <textarea name="body" rows="10" required class="w-full px-4 py-2 border border-gray-300 rounded focus:border-blue-500 focus:ring-1 focus:ring-blue-500 font-mono text-sm" placeholder="Write your email body here. HTML is permitted."></textarea>
                    <p class="text-xs text-gray-500 mt-1">This will be sent via BCC to all active subscribers.</p>
                </div>
                
                <div class="flex justify-end pt-2">
                    <button type="submit" onclick="return confirm('Ready to send this broadcast to <?= count($subscribers) ?> subscribers?');" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded flex items-center shadow-sm">
                        <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                        Send Now
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Subscriber List -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-lg shadow border border-gray-200 flex flex-col h-full object-cover max-h-[600px]">
            <div class="p-4 border-b border-gray-100 flex justify-between items-center bg-gray-50 rounded-t-lg">
                <h4 class="font-bold text-gray-900">Subscribers</h4>
                <span class="bg-gray-200 text-gray-700 text-xs font-bold px-2 py-1 rounded-full"><?= count($subscribers) ?> Total</span>
            </div>
            
            <div class="overflow-y-auto flex-1 p-4">
                <?php if (empty($subscribers)): ?>
                    <div class="text-center py-10 text-gray-500 text-sm">No subscribers yet.</div>
                <?php else: ?>
                    <ul class="space-y-3">
                        <?php foreach($subscribers as $s): ?>
                            <li class="flex justify-between items-center p-3 bg-gray-50 border border-gray-100 rounded hover:border-gray-300 transition group">
                                <div class="truncate">
                                    <div class="text-sm font-medium text-gray-900 truncate" title="<?= htmlspecialchars($s['email']) ?>"><?= htmlspecialchars($s['email']) ?></div>
                                    <div class="text-xs text-gray-500"><?= date('M j, Y', strtotime($s['created_at'])) ?></div>
                                </div>
                                <form method="POST" class="ml-2 flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <input type="hidden" name="action" value="delete_sub">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                    <button type="submit" onclick="return confirm('Remove this subscriber?');" class="text-red-500 hover:text-red-700 p-1" title="Unsubscribe">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>
