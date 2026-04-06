# Agent Instructions: Database Schema Synchronization

To ensure consistent environments across all deployments, any automated agent (like Gemini/Antigravity) making modifications to the application's database schema or persistent settings **MUST** maintain a cumulative SQL update file.

## Required Workflow
1. **Track Changes**: For every modification to the database (e.g., `ALTER TABLE`, `CREATE TABLE`, `INSERT INTO settings`, etc.), keep a record of the precise SQL required to replicate the change.
2. **Consolidate**: Instead of just running the commands during the session, append the SQL statements to a file named `update.sql` at the project root.
3. **Documentation**:
    - Each SQL block must be preceded by a comment (`--`) describing what the change does and which feature it belongs to.
    - If a change is destructive (e.g., `DROP COLUMN`), add a warning comment.
4. **Settings Whitelist**: If adding new configuration to the `settings` table, also ensure `admin/ajax.php` is updated to whitelist the new keys.
5. **Media Pipeline**: Use `uploads/thumbnails/` for all image variants. The legacy `thumbs/` directory is deprecated and should be avoided in new code.
6. **Test Code Hygiene**: Any scripts, test files, or one-off tools created during development **MUST** be moved to the `scripts/` directory after use. Never leave diagnostic or testing code in the root directory.
7. **Strict Scope**: Do not change any functionality other than what was explicitly requested. Avoid modifying irrelevant files.
8. **Pre-execution Checklist**: Before starting any execution, you **MUST** provide a clear implementation plan (checklist) of all proposed changes and wait for user approval.

## Example `update.sql` entry:
```sql
-- Add 'is_locked' to postmark_locations to support manual editing overrides
ALTER TABLE postmark_locations ADD COLUMN is_locked TINYINT(1) DEFAULT 0 AFTER linked_item_id;

-- Add new Hero Text Color to theme settings
INSERT INTO settings (setting_key, setting_value) VALUES ('theme_studio_hero_text_color', '')
ON DUPLICATE KEY UPDATE setting_value = IF(setting_value = '' OR setting_value IS NULL, '', setting_value);
```
