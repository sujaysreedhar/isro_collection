# Collection tools and admin improvements

## Deployment

1. Run `composer install --no-dev` using the updated lock file. The current application lock file requires PHP 8.2 or newer. Album PDFs use the locked Dompdf and BaconQrCode packages and the application's GD, DOM, XMLWriter, mbstring, fileinfo and PDO extensions.
2. Apply the documented SQL appended to `update.sql` in the block named `BEGIN approved collection tools and seller price tracking (2026-11-02)`. The same feature-only SQL is available in `scripts/wishlist_offers_update.sql` and `scripts/collection_workbench_update.sql`. Do not apply both copies separately; the fragments are included in the cumulative root file.
3. Refresh Admin → Modules and enable **Collection Workbench**. Keep or enable **Wishlist** and **People & Biographies**. No new setting keys are needed. Existing enabled-module choices are preserved.
4. The SQL follows the project's existing MariaDB syntax (`ADD COLUMN IF NOT EXISTS`), matching the supplied MariaDB 10.4 database dump.

The new code checks whether its required tables/columns exist and displays a migration notice rather than silently altering schemas during normal page loads. Existing free-text seller prices remain visible until an administrator supplies a numeric amount and currency.

## Collecting workflow

- **Wishlist:** add wanted stamps or covers, catalogue references and target prices. Add multiple sellers with a URL, amount, three-letter currency code, shipping, availability and checked date. Update prices manually; each observation is retained. Removing an offer archives it and retains its history. Cheapest offers are compared within one currency and only when shipping is known and the offer is available. Collecting statuses are searching, shortlisted, ordered and acquired. Creating a catalogue record from a wanted item makes a private draft and does not copy seller notes into its description.
- **Collection Workbench → Completeness:** define expected-item checklists grouped by country, year, series or theme. Add expected entries and link owned collection records. Missing entries stay highlighted. An existing set can be imported as a one-time checklist snapshot; future changes to the original set do not rewrite it.
- **Collection Workbench → Storage:** search physical locations and record album, page, sleeve, box and shelf. Purchase prices, valuations and private notes use separate admin-only tables, reusing existing storage and valuation data.
- **Bulk editing:** select catalogue rows and choose **Preview bulk edit**, or use the workbench Bulk editing tab. Check the fields to change, review each old/new value, then confirm. Unchecked fields are retained. A changed or deleted record invalidates the preview; previews expire after 15 minutes. When enabled, the existing Bulk Manager opens this preview workflow.
- **Album pages:** select items in Manage items and choose Album pages. Preview to reorder items, select A4 or US Letter and 1/2/4/6 items per page, then download the PDF. Image captions, short descriptions and QR record links are optional. Only published catalogue items are included. Private purchase, valuation, storage and notes are excluded. Long captions are abbreviated to fit the chosen layout.
- **High-resolution images:** new uploads retain their untouched original and generate proportional previews under `uploads/thumbnails/`. The default/custom viewers use originals for full-size viewing and downloads. Original downloads validate the parent item's public visibility. Missing legacy originals cannot be reconstructed; replace the upload when only a cropped/resized file survives. Optional cropping affects only the small preview, never the original or full-size view.
- **Biographies:** search/filter profiles, preserve established slugs, edit linked records and infobox facts, and preview/remove profile photos. Photos retain uncropped originals. Public profiles list only public collection records.

Shareable catalogue CSV/API outputs and album PDFs exclude private tables. Administrative backups and migration archives retain private collection data and require administrator access.

## Verification

The regression scripts use SQLite fixtures and synthetic images, without connecting to the application database:

```text
php scripts/test_wishlist_tracking.php
php scripts/test_collection_workbench.php
php scripts/test_people_admin.php
php scripts/test_album_media.php
php scripts/test_collection_exports.php
```

`scripts/render_album_qa.py` renders the generated album fixture with pypdfium2. Temporary runtimes and generated files stay under `scripts/.runtime/` and are excluded from version control. Guarded local UI routers are available under `scripts/qa_people_router.php` and `scripts/qa_collection_router.php`.

The Composer advisory scan found no advisories for the newly added PDF/QR packages. It did flag the pre-existing locked versions of `guzzlehttp/guzzle`, `guzzlehttp/psr7`, and `mtdowling/jmespath.php`, including a critical JMESPath compiler issue. Those dependencies were retained to keep this feature change within scope; address their advisory-guided updates before a production rollout. Live MariaDB migrations and real S3 transfers still require deployment-environment verification.
