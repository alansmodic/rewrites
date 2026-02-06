# CLAUDE.md - Rewrites Plugin

## Project Overview

Rewrites is a WordPress plugin (v1.0.0) that lets authors save changes to published posts as staged revisions without immediately publishing them. It provides an editorial review workflow with approval/rejection, a publication checklist, and scheduled publishing via WP-Cron.

**Requirements:** WordPress 6.4+, PHP 7.4+

## Repository Structure

```
rewrites.php                         # Main plugin entry point (constants, autoloader, init)
includes/
  class-rewrites.php                 # Core class: meta registration, REST route init, editor asset enqueue
  class-rewrites-staged-revision.php # Staged revision CRUD, publish, schedule, approve/reject
  class-rewrites-rest-controller.php # REST API endpoints (rewrites/v1/staged/*)
  class-rewrites-cron-handler.php    # WP-Cron handler for scheduled publishing
admin/
  class-rewrites-admin.php           # Admin menu page, dashboard widget
  class-rewrites-settings.php        # Settings page, checklist editor, AJAX handler
  views/
    admin-page.php                   # Admin page HTML template
assets/
  js/
    editor.js                        # Block editor sidebar + checklist modal (React via wp.element)
    admin.js                         # Admin page jQuery interactions
    settings.js                      # Settings page jQuery UI sortable + AJAX save
  css/
    admin.css                        # Admin styles, status badges, responsive layout
```

## Architecture

- **Singleton pattern** for core classes (`Rewrites`, `Rewrites_Cron_Handler`, `Rewrites_Admin`, `Rewrites_Settings`)
- **Static methods** on `Rewrites_Staged_Revision` for all revision operations (no singleton)
- **Autoloader** in `rewrites.php` maps `Rewrites\` namespace to `includes/class-rewrites-*.php`
- Classes are initialized on the `plugins_loaded` hook

### Key Technical Details

- WordPress revision posts (`post_type = 'revision'`) are used as the underlying storage for staged content
- `update_post_meta()`/`get_post_meta()` do **not** work on revision posts; use `update_metadata('post', ...)` / `get_metadata('post', ...)` instead
- The `_has_staged_revision` meta on parent posts stores the revision ID for quick lookups
- `protect_staged_revisions()` filter ensures WordPress doesn't skip creating new revisions when a staged one exists

### Meta Keys (stored on revision posts via `update_metadata`)

| Key | Type | Purpose |
|-----|------|---------|
| `_staged_revision` | boolean | Marks a revision as staged |
| `_staged_status` | string | Workflow status: `pending`, `approved`, `rejected` |
| `_staged_publish_date` | string | Scheduled publish date (UTC, MySQL format) |
| `_staged_author` | integer | User ID of the author who created the staged revision |
| `_staged_notes` | string | Editorial notes |

### REST API (namespace: `rewrites/v1`)

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/staged` | List all staged revisions (filterable by status) |
| GET | `/staged/{post_id}` | Get staged revision for a specific post |
| POST | `/staged/{post_id}` | Create/update staged revision |
| DELETE | `/staged/revision/{revision_id}` | Discard a staged revision |
| POST | `/staged/{revision_id}/publish` | Publish staged revision to live post |
| POST | `/staged/{revision_id}/schedule` | Schedule future publishing |
| POST | `/staged/{revision_id}/approve` | Approve a staged revision |
| POST | `/staged/{revision_id}/reject` | Reject a staged revision |

### WordPress Hooks (custom)

**Actions:**
- `rewrites_staged_published` - After a staged revision is published
- `rewrites_staged_discarded` - After a staged revision is discarded
- `rewrites_staged_approved` - After a staged revision is approved
- `rewrites_staged_rejected` - After a staged revision is rejected
- `rewrites_scheduled_publish_completed` - After scheduled publish via cron
- `rewrites_before_publish` / `rewrites_after_publish` - Documented but not yet wired

**Filters:**
- `rewrites_supported_post_types` - Documented but not yet wired; currently all public post types are supported via `get_post_types(['public' => true])`

## Development Conventions

### PHP

- Follow **WordPress Coding Standards** (tabs for indentation, Yoda conditions, etc.)
- Use `sanitize_text_field()`, `sanitize_textarea_field()`, `wp_kses_post()`, `absint()` for sanitization
- All REST endpoint args must have `sanitize_callback`
- Permission checks via `current_user_can()` on every REST endpoint
- AJAX handlers must call `check_ajax_referer()` and verify capabilities
- Direct DB queries use `$wpdb->prepare()` with PHPCS ignore comments where interpolation of table names is unavoidable
- Always use `wp_slash()` when passing data to `wp_insert_post()` / `wp_update_post()`

### JavaScript

- Block editor JS (`editor.js`) uses **wp.element (React)** via WordPress component library — no build step, no JSX
- Admin JS (`admin.js`, `settings.js`) uses **jQuery** — standard for WP admin pages
- Script data passed via `wp_localize_script()` (`rewritesData`, `rewritesSettings`)
- All user-facing strings use `wp.i18n.__()` in editor JS or PHP `__()` for translatable text

### CSS

- Scoped via `.rewrites-*` class prefix to avoid conflicts
- Responsive breakpoints at 1200px and 960px
- Status badge classes: `.rewrites-status-pending`, `.rewrites-status-approved`, `.rewrites-status-rejected`

## Build & Tooling

- **No build step** — PHP is loaded natively, JS is plain ES6 (no transpilation/bundling)
- **No package.json or composer.json** — no npm/composer dependencies
- **PHPCS** with `WordPress` standard — run via `phpcs --standard=WordPress --extensions=php .`
- **No test suite** is configured yet

## Settings Storage (wp_options)

| Option Key | Type | Purpose |
|------------|------|---------|
| `rewrites_checklist_enabled` | bool | Whether the publication checklist is active |
| `rewrites_checklist_items` | array | List of `{label, required}` objects for the checklist |

## Common Tasks

### Adding a new REST endpoint

1. Add route registration in `Rewrites_REST_Controller::register_routes()`
2. Add callback method and corresponding `*_permissions_check()` method
3. All args need `sanitize_callback`; use `absint` for IDs, `sanitize_text_field` for strings

### Adding new revision metadata

1. Register the meta key in `Rewrites::register_meta()` with proper type, sanitize, and auth callbacks
2. Use `Rewrites_Staged_Revision::update_revision_meta()` / `get_revision_meta()` — never `get_post_meta()` on revisions
3. Add cleanup in `Rewrites_Staged_Revision::publish()` to delete the meta after publishing

### Modifying the block editor UI

1. Edit `assets/js/editor.js` — uses `wp.element.createElement` (no JSX)
2. Data passed from PHP via `rewritesData` global (set in `Rewrites::enqueue_editor_assets()`)
3. Uses `wp.data.subscribe()` to watch editor state changes
