# Collection Workbench

Apply the cumulative root `update.sql` using the application database updater, refresh **Admin → Modules** and enable **Collection Workbench**. Missing schema displays an update notice rather than creating tables on page loads. The module adds a sidebar link under Catalogue.

## Storage and private details

Search by registration number, title, album, box, shelf or sleeve. Open a record's private details to edit album, page, sleeve, box, shelf, location notes, purchase price/date/currency, valuation and private notes. Existing Storage Labels and Valuation Tracker data is reused, not copied. Sleeve, shelf and private notes also appear in the standard item editor when this module is enabled.

Storage and financial values are in separate private tables. This module registers no public route, public template, export or download. Public catalogue fields and visibility remain in the core item table; hiding a record is an explicit bulk-edit option. Public album/export implementations must continue to select public fields explicitly.

## Completeness

Create a country, year, series or theme checklist. List each expected stamp or cover, optionally with its catalogue reference. Link an existing collection record ID to mark an expected item as owned. Blank links are missing and highlighted. One record can fill only one position in a checklist, so duplicate links cannot inflate the result. Deleting a collection record makes its expected position missing again. Hidden records count as owned in this private admin view.

Existing Sets / Checklists can be imported once when they have an explicit expected-item structure. Their item links and expected labels are copied; the source set is retained. Changes after import are independent. Totals are based on listed expectations, never inferred from owned item counts or guessed catalogue sizes.

## Bulk editing

Search and select up to 100 items on a page. Check the fields to change and supply their new values. Checked empty storage fields are cleared. Unchecked fields are retained. Changing categories deliberately replaces all category associations with the selected category and synchronizes the core primary category.

Preview displays each record's current and proposed values. The preview remains in the authenticated admin session, expires after 15 minutes, and is confirmed using a random token plus CSRF protection. Saving rechecks the reviewed inventory and private-detail snapshots under a transaction and rejects stale previews; it does not overwrite edits made after the preview. A successful confirmation consumes the preview. No bulk deletion is provided.

## Verification

Run `php scripts/test_collection_workbench.php` with PDO SQLite enabled. Fixtures use an in-memory database and never connect to application configuration or persistent data.
