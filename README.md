# Scrape Course Importer

WordPress plugin for importing course data from your own public category pages and exporting the resulting course records to ZIP.

## Features
- Registers a `course` custom post type
- Import course title, excerpt/content, and cover image from category pages
- Prevents duplicates by title and source URL
- Exports all course records to `courses-export.zip`
- Includes WordPress admin UI
- Supports dry run and batch import
- Works with `wp sci import --url="..."`

## Installation
1. Upload the `scrape-course-importer` folder to `wp-content/plugins/`
2. Activate the plugin in WordPress
3. Go to `Cursos` in the admin menu
4. Use the form to import or export

## CLI example
```bash
wp sci import --url="https://example.com/categoria/" --batch=20 --dry_run
```

## Notes
This plugin is intended for your own site or data you are authorized to import. Use it responsibly and only against sources you own or control.
