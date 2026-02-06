# Rewrites

A WordPress plugin that lets you save changes to published posts without immediately publishing them. Includes editorial review workflow and scheduled publishing.

[<img src="https://img.shields.io/badge/Try%20it%20on-WordPress%20Playground-3858e9?style=for-the-badge&logo=wordpress" alt="Try it on WordPress Playground" />](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/alansmodic/rewrites/main/blueprint.json)

## Features

- **Staged Revisions**: Save changes to published posts as drafts for review before going live
- **Publication Checklist**: Configurable checklist that authors must complete before publishing
- **Scheduled Publishing**: Schedule staged revisions to go live at a specific date and time
- **Editorial Notes**: Add notes to staged revisions for reviewer context
- **REST API**: Full REST API support for headless workflows
- **Block Editor Integration**: Seamless integration with the WordPress block editor

## Requirements

- WordPress 6.4 or higher
- PHP 7.4 or higher

## Installation

1. Download the plugin zip file or clone this repository
2. Upload to your `/wp-content/plugins/` directory
3. Activate the plugin through the WordPress admin

## Usage

### Saving a Rewrite

1. Edit any published post
2. Make your changes
3. Click "Save as Rewrite" instead of "Update"
4. Your changes are saved as a staged revision for review

### Publishing a Rewrite

1. Go to the Rewrites admin page
2. Review the staged revision
3. Click "Publish" to apply changes to the live post

### Publication Checklist

1. Go to Rewrites > Settings
2. Enable the checklist feature
3. Add custom checklist items (mark items as required or optional)
4. Authors will see the checklist when updating published posts

## Hooks

### Actions

- `rewrites_before_publish` - Fires before a staged revision is published
- `rewrites_after_publish` - Fires after a staged revision is published

### Filters

- `rewrites_supported_post_types` - Filter which post types support rewrites

## License

GPL-2.0-or-later
