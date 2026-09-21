# Security and Performance Remediation Implementation Plan

> **For agentic workers:** Use `superpowers:executing-plans` to implement this plan task by task in the current task. The intended executor is GPT-5.6. Do not create tasks, delegate, deploy, or commit unrelated work. Implementation begins only after the user approves this plan. The next review will audit the actual edited files and their dependencies.

**Goal:** Fix the twelve findings from the September 20, 2026 audit, preserve existing application behavior, and provide reproducible verification plus a precise edited-file handoff.

**Architecture:** Retain the PHP/PDO/module architecture. Strengthen shared request boundaries, correct visibility predicates at public data sources, and reuse shared media and storage abstractions. Keep security fixes and performance changes in separate reviewable phases; use measured query plans before adding indexes.

**Tech stack:** PHP 8.2, PDO/MySQL or MariaDB, Apache 2.4, JavaScript, GD/WebP, existing local/S3 storage interfaces, Composer, Tailwind.

**Spec:** The findings and required behavior below are the embedded remediation specification. The original audit is in this task's conversation; line numbers there are starting points, not immutable references.

## Authorization and global constraints

- This document is a plan, not evidence that fixes have been implemented.
- The repository's supplied AGENTS.md requires a proposed-change checklist and approval before execution. Present this plan for approval; once approved, execute its authorized scope without asking at every task.
- Scope is limited to the twelve listed findings. Preserve routes, item editing, public navigation, storage backends, admin access, rich blog content, and existing filters.
- The initial working tree is dirty, including existing security changes. Inspect `git status --short` and `git diff` before editing. Preserve all pre-existing work. Do not reset, stash, revert, reformat, or commit the whole tree.
- Record pre-existing edits separately from this run's edits. Git's final diff alone is not an accurate attribution of this run.
- Do not run existing installation, reset, activation, migration, or test scripts without reading them. Some bootstrap paths write caches; some admin requests trigger backups.
- Run mutation and concurrency tests against a disposable database and temporary media directory, never the collection database. Require an explicitly configured test DSN; refuse destructive tests without it.
- Every schema or persistent settings change must be appended to root `update.sql`, with a feature comment. Never rewrite earlier SQL. Add warning comments for destructive changes; avoid destructive migrations here.
- New settings-table keys must also be whitelisted in `admin/ajax.php`. Prefer code constants for initial fixed limits to avoid unnecessary settings UI work.
- All newly generated image variants belong under `uploads/thumbnails/`; do not create new `thumbs/` or `display/` variants. Preserve reading existing paths until legacy media is migrated safely.
- Put all new tests, diagnostics, benchmark tools, and CLI jobs under `scripts/`. Reports and plans belong under `docs/`.
- Do not change dependencies just to obtain a green audit. Check advisories and compatibility first; report unreachable registries honestly.
- No production database migration, media batch rewrite, operating-system scheduler installation, deployment, or broad package upgrade is part of this execution. Prepare their commands and record any remaining rollout steps.

## Findings and coverage map

| ID | Finding | Task |
|---|---|---|
| S1 | Local Apache serves `.git/HEAD` with HTTP 200 | 1 |
| S2 | Private checklists and hidden item metadata appear on public routes | 2 |
| S3 | Set deletion uses GET without CSRF; blog/bulk forms lack checks | 3 |
| S4 | Blog upload accepts arbitrary file content and extensions | 4 |
| S5 | Atlas popup interpolates stored text as HTML | 5 |
| S6 | Login lacks application throttling | 6 |
| S7 | Anonymous writes lack limits; gallery cookie/mutations need protection | 6 |
| S8 | Public errors disclose database exception details | 7 |
| P1 | Blog search appends unlimited results after catalog pagination | 8 |
| P2 | Search repeats expensive predicates and vocabulary work | 8 |
| P3 | Display images remain full resolution; decode lacks pixel cap | 4 |
| P4 | Due backups run during admin page rendering | 9 |

## Review focus

1. Hidden data must remain hidden in JSON, totals, progress counts, and direct URLs, not just visible cards. Task 2 owns these cases.
2. GET, missing/array-shaped tokens, stale sessions, and invalid POSTs must never mutate state or produce uncaught type errors. Tasks 3 and 6 own these cases.
3. Valid legacy images and local/S3 media must remain usable while new variants use the required directory. Task 4 owns these cases.
4. Search filters, blog-only matches, cold caches, and concurrent cache rebuilds must retain correct results and bounded resource use. Task 8 owns these cases.
5. Simultaneous login attempts, gallery creates, and backup jobs must not bypass limits, corrupt state, or create duplicate jobs. Tasks 6 and 9 own these cases.

## Phase A — Contain access and data exposure

### Task 0: Establish baseline and regression harness

**Files:** Create `scripts/audit_regression.php`, `scripts/audit_test_bootstrap.php`; later create `docs/audits/2026-09-20-remediation-results.md` and `docs/audits/2026-09-20-edited-files.md`.

- [ ] Record initial modified/untracked paths and inspect relevant existing diffs. Keep a private, temporary before-copy of files edited during this run so final attribution can distinguish previous changes. Do not put secret-bearing snapshots in the served project directory.
- [ ] Inventory active modules/themes using read-only database queries where available. Do not expose credentials/settings values in reports. Label unavailable runtime information rather than assuming modules are active.
- [ ] Build a CLI regression harness that refuses fixture mutations without `AUDIT_TEST_DSN`, `AUDIT_TEST_USER`, and an explicitly disposable database. Add an explicit test-mode flag and verify the resolved database differs from the configured application database.
- [ ] Use failure exit codes and assertions that execute behavior. A minimal assertion helper is:

```php
function auditCheck(bool $ok, string $message): void {
    if (!$ok) {
        throw new RuntimeException($message);
    }
}
```

- [ ] Run syntax checks and safe baseline tests. Capture failing security cases before fixing them. Do not substitute source-string matching for authorization, CSRF, or SQL-result tests.

**Interface:** `php scripts/audit_regression.php --group=<access|visibility|csrf|media|xss|abuse|errors|search|backup>` runs the corresponding behavioral checks and exits nonzero on failure. Tests needing fixtures must clearly fail as unconfigured rather than silently pass.

### Task 1: Deny access to repository and internal files

**Modify:** `.htaccess`. Preserve `scripts/.htaccess`, `tmp/.htaccess`, and `uploads/.htaccess` protections.

- [ ] Reproduce the known exposure using HEAD only: `curl.exe -I http://localhost/collection/.git/HEAD`. Do not download repository history.
- [ ] Place explicit denial before the front-controller rules. Block `.git`, `.svn`, `.hg`, `.codex`, and `.agents` path segments plus environment/backup secret files. Preserve `.well-known` for legitimate hosting use and preserve intentional public assets.
- [ ] Use Apache 2.4-compatible rules; start from a narrow directory denial such as:

```apache
RewriteRule (^|/)(\.git|\.svn|\.hg|\.codex|\.agents)(/|$) - [F,L,NC]
```

- [ ] Retain SQL/config protections. Verify `.git/HEAD` and `.git/config` return 403/404, `update.sql` remains blocked, and the actual homepage/CSS/image URLs still work. Test both the existing subdirectory installation and a root installation in isolated configuration if available.
- [ ] Document deployment exclusion of version-control directories; do not claim local rules verify production.

### Task 2: Enforce visibility throughout checklist and atlas output

**Modify:** `modules/set_manager/frontend_controller.php`, `modules/set_manager/module.php`, relevant `modules/set_manager/views/frontend_*.php`, `modules/postmark_atlas/atlas.php`, `modules/postmark_atlas/route_planner.php`.

- [ ] Create fixtures: public/private sets, visible/hidden items, structured/unmapped membership, and atlas locations linked to each item.
- [ ] Return 404 for a direct private-checklist slug on the public controller. Do not change admin lookup methods to hide admin records.
- [ ] Restrict public set membership to `i.is_visible = 1`. Add a public-only option to progress calculation so totals agree with visible membership; keep the admin default unchanged and explicitly update public callers.
- [ ] Apply visibility to the atlas JOIN, preserving locations whose linked items are hidden:

```sql
LEFT JOIN items i ON i.id = pl.linked_item_id AND i.is_visible = 1
```

- [ ] Replace `pl.*` in public payloads with an explicit field list. A raw `pl.linked_item_id` would still leak the hidden ID even when the JOIN is filtered. Emit link identifiers only from the visible joined item; preserve each frontend's expected alias.
- [ ] Verify public HTML, embedded JSON, API-equivalent payloads, progress counts, and item links do not reveal private set data or hidden item titles/IDs. Verify admin views still show all authorized data.

## Phase B — Request, upload, and rendering security

### Task 3: Complete CSRF protection for module mutations

**Modify:** `admin/module_page.php`, `includes/Csrf.php` and `admin/auth.php` only as needed to retain one validation behavior; `modules/set_manager/module.php`, `modules/set_manager/admin_page.php`, `modules/blog/admin_posts.php`, `modules/bulk_manager/admin/tool.php`; affected module form callers discovered at the central boundary.

- [ ] Inventory POST forms and AJAX calls routed through `admin/module_page.php` before adding a central guard; record forms requiring tokens so legitimate saves are not broken.
- [ ] After authentication and before any module hook/output, reject invalid mutation requests. Normalize tokens as strings before calling typed helpers; accept the existing POST field or request header.

```php
$supplied = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
$token = is_string($supplied) ? $supplied : null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verifyCsrfToken($token)) {
    http_response_code(403);
    exit('Security check failed.');
}
```

- [ ] Convert set deletion to a POST form carrying ID, action, and token. GET must display a page or reject the operation and must never delete.
- [ ] Add hidden token inputs to affected forms. Preserve AJAX response conventions and existing valid token callers. Do not globally reject harmless GET reads.
- [ ] Exercise authenticated mutation requests with missing, incorrect, array-shaped, and correct tokens. Assert database rows are unchanged for rejected requests. Exercise a cross-site top-level GET to the former deletion route and confirm no mutation.

### Task 4: Validate blog uploads and produce bounded image variants

**Modify:** `includes/MediaProcessor.php`, `modules/blog/admin_posts.php`, `themes/default/partials/gallery_items.php`, other verified image consumers in `themes/`, `includes/StorageInterface.php` implementations only if required by existing interfaces.
**Create if needed:** `includes/ImageUploadValidator.php`, `scripts/migrate_image_variants.php` with dry-run default.

- [ ] Introduce reusable validation without calling an item-media insert method for a blog image. Keep validation separate from persistence.
- [ ] Require successful PHP upload, verified temporary upload source, MIME allowlist JPEG/PNG/GIF/WebP, matching decodable image, maximum 20 MiB file size, maximum 20 million pixels, and maximum 10,000 pixels on either axis. Apply the same safety checks to cropped uploads before decoding. Return a useful validation error rather than a fatal allocation error.
- [ ] Generate cryptographically random names and extensions from the encoder. Do not retain the client filename. Re-encode the blog image to WebP; reject HTML, SVG, PHP, disguised files, and corrupt images. Store no user-controlled executable content.
- [ ] Preserve existing blog image on validation/storage failure; clean up only newly created files from the failed operation. Check GD, local write, and S3 storage results before persisting references.
- [ ] Generate new 200 px thumbnail, 640 px card, and 1200 px display variants under `uploads/thumbnails/`, preserving aspect ratio except the existing square thumbnail behavior. Example naming: `<base>_200.webp`, `<base>_640.webp`, `<base>_1200.webp`. Preserve originals under their established original storage location.
- [ ] Resolve new variant URLs through the existing storage abstraction; preserve a fallback to existing legacy display/thumbnail paths when variants are absent. Use a shared resolver instead of scattering path construction. Update every affected consumer and media deletion/orphan-detection behavior for the new names.
- [ ] Use `srcset`/`sizes` where useful; small cards should not request original-size display images. Do not regenerate all media during an HTTP request.
- [ ] Provide an optional resumable CLI migration with `--dry-run` default and explicit `--apply`; do not execute it on collection media. Never remove originals as part of migration.
- [ ] Verify JPEG/PNG/WebP/transparent images, corrupt files, HTML/SVG, oversized dimensions, extreme aspect ratios, cropped-image limits, legacy fallback, local/S3 URLs, failed writes, deletion, and orphan detection with disposable fixtures. Record actual resulting dimensions and byte sizes.

### Task 5: Escape atlas popup/tooltip text

**Modify:** `themes/default/atlas.php` and matching unsafe atlas/route-planner renderers found in other themes. No visual redesign.

- [ ] Inventory stored-data interpolation in popups, tooltips, and result cards; JSON encoding alone does not make a subsequent HTML concatenation safe.
- [ ] Prefer DOM nodes and text assignment:

```javascript
const title = document.createElement('h3');
title.textContent = loc.post_office ?? '';
popup.appendChild(title);
marker.bindPopup(popup);
```

- [ ] Build item URLs from a validated numeric ID and the trusted base URL. Keep stored names out of markup/inline handlers. Preserve required static markup and existing styling.
- [ ] Test literal `<img src=x onerror=...>`, quotes, ampersands, and Unicode in location/title fields. Assert they display as text, create no injected elements, and execute no handlers. Test tooltips as well as popups across affected themes.

### Task 6: Bound authentication and anonymous writes

**Create:** `includes/RateLimiter.php`.
**Modify:** `admin/login.php`, `modules/user_galleries/api.php`, `modules/user_galleries/gallery.js`, gallery cookie creation sites, `modules/contact_us/contact.php`, `modules/trade_manager/process_trade.php`, relevant public forms/templates, `update.sql`.

**Shared interface:** `RateLimiter::consume(PDO $pdo, string $scope, string $key, int $limit, int $windowSeconds): array` returns `['allowed' => bool, 'retry_after' => int]`. Implement atomic database accounting; do not use PHP-session-only counters or trust forwarded IP headers without configured proxy trust.

- [ ] Append the migration with a feature comment; apply only to the disposable test database during this run:

```sql
-- Security audit: shared fixed-window counters for authentication and public writes.
CREATE TABLE IF NOT EXISTS request_rate_limits (
    scope VARCHAR(64) NOT NULL,
    key_hash CHAR(64) NOT NULL,
    window_start BIGINT UNSIGNED NOT NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    expires_at BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (scope, key_hash, window_start),
    KEY idx_request_rate_limits_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- [ ] Hash limiter keys; never store raw passwords, bearer tokens, or session IDs. Use atomic upsert plus row-locked read/transaction, bound expiry cleanup, and return 429 with `Retry-After` on exceeded limits. On limiter failure, fail closed for protected mutations with a generic 503 and log the failure.
- [ ] Login defaults: 10 attempts per normalized username/IP pair per 15 minutes and 100 attempts per IP per 15 minutes. Count both known and unknown usernames; keep responses generic. Do not add permanent account lockouts or blocking sleeps.
- [ ] Contact/trade defaults: 5 accepted attempts per IP per 10 minutes per endpoint. Gallery mutations: 60 per owner token per 10 minutes plus 120 per IP per 10 minutes; creation: 10 per IP per hour. Reject oversized/scalar-invalid inputs before expensive work.
- [ ] Enforce maximums: names/titles 200 characters, email 254, contact subject 200, description 2,000, message 10,000; validate email and positive IDs. Reject rather than silently truncate. Trade/add-item operations must reference a visible eligible item using existing feature rules.
- [ ] Enforce 50 galleries per owner and 500 items per gallery. Serialize count-and-insert operations transactionally so concurrent creates cannot exceed quotas. Preserve existing owners and galleries; do not delete existing records above new limits.
- [ ] Set gallery owner cookie explicitly with HttpOnly, SameSite=Lax, Secure on HTTPS, existing path and lifetime. Preserve valid existing 64-character hex tokens; rotate malformed tokens. Find and update every creation site consistently.
- [ ] Require POST plus CSRF for owner-cookie mutations. Supply a token safely to gallery JavaScript and public forms; preserve the read-only list operation. CSRF is not a replacement for the above abuse limits.
- [ ] Test threshold, reset boundary, concurrent requests, malformed inputs, absent/invalid CSRF, valid legacy cookies, independent owners, quota races, hidden-item rejection, missing migration behavior, and limiter storage failure. Use separate connections/processes for concurrency tests.

### Task 7: Remove public exception details

**Modify:** `bootstrap/database.php`, `includes/pages/db_error.php`, `modules/user_galleries/api.php`, `modules/trade_manager/process_trade.php`; add a small shared error helper only if it fits existing autoload/bootstrap order.

- [ ] Generate a random correlation ID, log it with internal exception details, and return a fixed public message plus the ID. Do not log request bodies, passwords, cookies, or credentials.

```php
$errorId = bin2hex(random_bytes(8));
error_log('[' . $errorId . '] Database operation failed: ' . $e->getMessage());
http_response_code(503);
echo json_encode(['success' => false, 'error' => 'Service temporarily unavailable.', 'error_id' => $errorId]);
```

- [ ] Distinguish safe validation failures from unexpected database/system exceptions. Keep validation errors useful; use suitable 4xx/5xx statuses and existing HTML/JSON conventions.
- [ ] Remove the bootstrap `debug` response field and raw detail in HTML fallback, including when the settings table cannot load. Verify logs are not web-accessible.
- [ ] Inject connection/query failure in the test environment. Assert responses contain no SQL, hostnames, filesystem paths, usernames, or exception traces, and logs contain the matching correlation ID.

## Phase C — Performance with preserved behavior

### Task 8: Bound and measure search

**Modify:** `includes/SearchEngine.php`, `includes/pages/search.php`, `modules/blog/module.php`, `ajax_search.php`, existing search hooks/cache invalidation in `bootstrap/hooks.php` where needed.
**Create:** `scripts/rebuild_search_vocabulary.php`, `scripts/benchmark_search.php`.
**Conditional:** `update.sql` only for indexes justified by actual schema/query plans.

- [ ] Baseline query count, returned rows, memory, and p50/p95 elapsed time using fixed disposable fixtures (small and larger catalogs) and cold/warm caches. Capture `EXPLAIN` for representative filtered searches; report measurements without invented improvement percentages.
- [ ] Preserve the current combined catalog/blog search experience. Replace the blog hook that appends unlimited rows with a paginated provider/count contract. Fetch a globally ordered page of lightweight result identifiers, then hydrate only that page. Keep deterministic order including a source/type tie-breaker. Count blog matches consistently with item matches; do not fetch all matching content into PHP.
- [ ] Inventory other `search_results` hook providers before changing the contract and adapt affected providers within this finding's scope. Preserve category/material/tag/year/image filter behavior for items; explicitly document and test the existing policy for whether those filters include or exclude blog posts.
- [ ] Accept at most 200 characters/10 search words, 50 entries per filter array, and reasonable positive page numbers. Return a validation response for malformed array/scalar shapes or excessive inputs. Do not pass unchecked limits/offsets into SQL.
- [ ] Remove vocabulary rebuilding from visitor requests. Add CLI rebuild, atomic temporary-file-plus-rename publication, and a rebuild lock. If cache is missing/corrupt, run the original query without suggestions. Use vocabulary membership/length buckets and a fixed candidate budget to bound correction work.
- [ ] Preserve substring matching. Use `EXPLAIN` to decide whether bounded candidate queries, shared filtered-ID work, or facet caching reduce the repeated wildcard/facet cost. Do not drop LIKE behavior or add indexes blindly. If caching facets, key by normalized filters and catalog revision, with bounded TTL and invalidation on relevant item/tag/category mutations.
- [ ] Benchmark identical fixture/query combinations after changes. Test no matches, blog-only matches, mixed pages, final page, filter combinations, punctuation/Unicode, disabled blog module, absent/corrupt cache, and simultaneous rebuilds. Assert totals and page boundaries contain no duplicates or omissions.

### Task 9: Move automatic backups outside web requests

**Modify:** `modules/auto_backup/module.php`, `modules/auto_backup/AutoBackupService.php`, `modules/auto_backup/admin_page.php`.
**Create:** `scripts/run_auto_backup.php`, `docs/operations/auto-backup-scheduler.md`.

- [ ] Remove backup execution from `admin_head`; retain inexpensive status display and existing manual backup behavior. Do not silently disable backups: document the required scheduler rollout and show last successful run/stale status.
- [ ] Add a CLI-only entry point using the existing service and settings. Reject HTTP invocation before bootstrap; exit nonzero on a failed due job. The invocation is:

```powershell
F:\xampp\php\php.exe F:\xampp\htdocs\collection\scripts\run_auto_backup.php
```

- [ ] Replace check-then-set locking with an atomic lock around automatic/manual execution. Prefer MySQL advisory locking on the existing connection with release in `finally`; document its single-database semantics. Ensure expired time-based state cannot permit two live jobs.
- [ ] Preserve retention, protected storage directory, due intervals, and success timestamps. Persist failed-run status without exposing internal details. Any added persistent keys require `update.sql` and the settings whitelist.
- [ ] Document hourly Windows Task Scheduler/Linux cron invocation, working directory, least-privilege credentials, logs, and verification. Do not install the scheduler in this run.
- [ ] Test due/not-due/disabled states, two simultaneous runners, manual/auto overlap, export failure, lock release, and recovery against disposable DB/media. Verify an admin page GET does not create a backup or enumerate/archive media.

## Phase D — Evidence and edited-file handoff

### Task 10: Verify and prepare the focused audit

**Create/update:** `docs/audits/2026-09-20-remediation-results.md`, `docs/audits/2026-09-20-edited-files.md`.

- [ ] Run PHP lint for every edited PHP file. Run each behavioral group once against the final code, then rerun only when subsequent changes affect it.
- [ ] Read existing phase tests before deciding which to run. Report syntax checks separately from behavioral tests and source-pattern checks.
- [ ] Recheck Apache deny rules and valid public assets. Smoke-test affected public/admin forms and enabled theme variants in the disposable environment.
- [ ] Run `composer audit --locked --no-interaction --format=json` and `npm.cmd audit --package-lock-only --json --ignore-scripts`. Record registry/network failures as unverified, not clean results. Do not run package upgrade/fix commands automatically.
- [ ] Review final diff against the initial working-tree baseline. Remove accidental formatting, generated artifacts, diagnostic root files, or changes unrelated to the findings. Preserve earlier user edits.
- [ ] Produce this manifest for every file changed by the implementation:

```text
Absolute path | Finding IDs | Functions/blocks changed | Pre-existing edits? |
Tests/evidence | Schema/settings implications | Follow-up review focus
```

- [ ] Results report must list each S1–S8/P1–P4 as fixed-and-verified, implemented-but-unverified, or unresolved, with evidence and exact limitations. Include benchmark fixture sizes, query counts, timings, media dimensions/bytes, migration rollout order, scheduler prerequisite, and rollback instructions.
- [ ] Treat migration application, legacy media regeneration, scheduler setup, production access verification, and unreachable dependency audits as explicit remaining rollout work. Do not claim production remediation until verified there.
- [ ] Stop after the implementation handoff. Do not merge/deploy or silently start a new audit. The user will request the focused review of the manifest's edited files; that reviewer should also read directly affected callers and templates.

## Completion conditions

- Security paths have meaningful behavioral evidence, including rejected mutations and hidden-data checks.
- Performance improvements have comparable measurements or are explicitly marked unmeasured.
- Existing edits and unrelated functionality remain intact.
- All schema/settings changes are reproducible through the cumulative SQL file and relevant whitelist.
- The final edited-file manifest separates this run from the pre-existing dirty tree and points the follow-up reviewer to exact changed functions and risks.

## Prompt to give GPT-5.6 after approving this plan

> Implement the approved plan at `F:\xampp\htdocs\collection\docs\superpowers\plans\2026-09-20-security-performance-remediation.md` in this workspace. Follow repository instructions and execute tasks sequentially. Preserve all existing uncommitted work; record a before baseline so your final manifest identifies your changes specifically. Limit edits to the approved findings. Use disposable fixtures for mutation tests, append required SQL to `update.sql`, and keep all test/CLI tools under `scripts/`. Do not deploy, run production migrations, install a scheduler, or batch-rewrite existing media. Finish with verification results, remaining rollout steps, and the exact edited-file manifest so we can audit those files next.
