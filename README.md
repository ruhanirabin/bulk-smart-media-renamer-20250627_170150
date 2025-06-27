# Bulk Smart Media Renamer (bulk-smart-media-renamer-20250627_170150)

AI-driven bulk renaming of WordPress media library assets with preview, inline editing, scheduling, audit logging, rollback and optional SEO plugin integration.

---

## Table of Contents

1. [Overview](#overview)  
2. [Features](#features)  
3. [Requirements & Dependencies](#requirements--dependencies)  
4. [Installation](#installation)  
5. [Configuration](#configuration)  
6. [Usage Examples](#usage-examples)  
7. [Components](#components)  
8. [Architecture & Flow](#architecture--flow)  
9. [Missing / Future Work](#missing--future-work)  
10. [Contributing](#contributing)  
11. [License](#license)  

---

## Overview

Bulk Smart Media Renamer is a WordPress plugin that:

- Scans your media library (full or incremental)  
- Sends images to an external AI service for context-aware labels  
- Applies user-defined filename templates (e.g. `{date}-{ai_label}`)  
- Presents a review UI with inline editing, bulk selection, filters and WCAG-compliant keyboard navigation  
- Renames files on disk, updates database records, regenerates thumbnails, fixes serialized references  
- Records actions for audit and rollback  
- Supports scheduled or one-off runs via WP-Cron or ActionScheduler  
- Optionally integrates with Yoast/AIO SEO for alt text, captions & sitemap updates  

? Full project description:  
https://docs.google.com/document/d/1vBQ5srKeZw4Trt2kPpkIKoMQOkiN9TqDRfExkXsYgQg/

---

## Features

- **Full or Incremental Scanning** ? process entire library or only new uploads  
- **AI-Driven Analysis** ? image recognition + NLP categorization  
- **Template-Based Filenames** ? customize with tokens (date, AI tags, custom fields)  
- **Admin Review UI** ? preview suggestions, inline edit, keyboard navigation, bulk select  
- **Bulk & Single-Item Renaming**  
- **Scheduled & On-Demand Runs** ? WP-Cron or ActionScheduler  
- **Auto-Apply** ? optional auto-apply suggestions post-analysis  
- **Audit Logging & Rollback** ? track changes and undo if needed  
- **Custom Taxonomy** ? ?AI Media Category? terms  
- **SEO Plugin Hooks** ? Yoast & All-in-One SEO integration  
- **Secure & Standards-Compliant** ? nonce checks, capability checks, PSR-12 + WP coding standards  

---

## Requirements & Dependencies

- WordPress ? 5.8  
- PHP ? 7.4  
- WP Filesystem API (with PHP `rename` fallback)  
- External AI Service API (via secure API key)  
- Optional:
  - Yoast SEO or AIO SEO plugin  
  - ActionScheduler or WP-Cron for advanced scheduling  

---

## Installation

1. Download or clone this repository to your local machine.  
2. Zip the plugin folder (`bulk-smart-media-renamer-20250627_170150.zip`).  
3. In WordPress Admin, go to **Plugins ? Add New ? Upload Plugin**.  
4. Choose the zip file and click **Install Now**, then **Activate**.  
5. Navigate to **Settings ? Bulk Smart Media Renamer** to configure.  

---

## Configuration

1. Go to **Settings ? Bulk Smart Media Renamer**.  
2. Enter your **AI Service API Key**.  
3. Define your **Filename Template** using tokens, for example:
   - `{date}` ? current date  
   - `{ai_label}` ? AI-generated label  
   - `{original_name}` ? original filename  
4. Set scanning options (full vs incremental) and scheduling preferences.  
5. Save changes.

---

## Usage Examples

### 1. One-Off Bulk Rename

1. Go to **Media ? Bulk Smart Media Renamer**.  
2. Click **Scan Now** to fetch AI suggestions.  
3. In the review table, adjust filenames inline as needed.  
4. Select items (or ?Select All?) and click **Apply Rename**.  
5. Changes are applied, thumbnails regenerated, and history logged.

### 2. Scheduled Runs

- On the settings page, enable **Scheduled Scans** and choose an interval.  
- WP-Cron or ActionScheduler will trigger automatic AI analysis and (optionally) auto-apply renames.

### 3. Single-Item Rename

- In **Media Library**, open an attachment and click **Smart Rename**.  
- Review the AI suggestion, edit if desired, and click **Rename**.

### 4. Rollback

1. Go to **Tools ? Bulk Smart Media Renamer ? History**.  
2. Locate the batch or individual action.  
3. Click **Rollback** to restore original filenames and database records.

---

## Components

Below is a list of core files and their responsibilities:

- **pluginmain.php**  
  - WordPress plugin header, loads `mainpluginfile.php`.  
- **mainpluginfile.php**  
  - Bootstrap orchestrator; initializes services via a simple service locator.  
- **adminmenumanager.php**  
  - Registers admin menu & submenus under **Media** and **Settings**.  
- **assetsmanager.php**  
  - Enqueues CSS/JS only on plugin admin pages.  
- **assets/bulk-renamer-admin.css**  
  - Styles for review UI, tables, responsive layouts.  
- **assets/bulk-renamer-admin.js**  
  - AJAX calls, inline editing, keyboard navigation.  
- **ajaxcontroller.php**  
  - Handles AJAX endpoints: preview, apply, rollback; nonce & capability checks.  
- **settingspage.php**  
  - Renders settings form (API key, templates, scan options).  
- **reviewui.php**  
  - Renders the bulk-review dashboard with filters and inline edit controls.  
- **mediascannerservice.php**  
  - Scans attachments table in batches; hooks into WP-Cron for scheduling.  
- **aiclient.php**  
  - Sends HTTP requests to the AI API; handles retries, errors, secure key storage.  
- **filenamegenerator.php**  
  - Applies templates, sanitizes slugs, ensures unique filenames.  
- **renameexecutor.php**  
  - Renames files via WP Filesystem API, updates `wp_posts` & postmeta, regenerates thumbnails, fixes serialized data.  
- **taxonomymanager.php** *(implemented)*  
  - Creates & assigns *AI Media Category* terms.  
- **historylogger.php** *(implemented)*  
  - Records rename actions for audit and rollback.  
- **schedulerintegration.php** *(planned)*  
  - Advanced scheduling via ActionScheduler.  
- **seopluginhooks.php** *(planned)*  
  - Integrations for Yoast/AIO SEO (alt tags, captions, sitemaps).

---

## Architecture & Flow

1. `pluginmain.php` loads `mainpluginfile.php` on `init`.  
2. **AdminMenuManager** ? registers **Bulk Smart Media Renamer** under **Media**.  
3. **AssetsManager** ? enqueues `bulk-renamer-admin.css/js` on plugin pages.  
4. **SettingsPage** ? under **Settings** or **Tools** for configuration.  
5. **MediaScannerService** ? scans attachments (full/incremental).  
6. **AIClient** ? batches image URLs to AI endpoint.  
7. **FilenameGenerator** ? applies template tokens, slugifies, ensures uniqueness.  
8. **ReviewUI** ? AJAX-driven table for suggestion preview and inline edits.  
9. **AjaxController** ? routes preview, apply, rollback; enforces nonces and capabilities.  
10. **RenameExecutor** ? renames files, updates DB, regenerates thumbnails, patches references.  
11. **TaxonomyManager** ? assigns ?AI Media Category? terms.  
12. **HistoryLogger** ? logs actions for rollback.  
13. **SchedulerIntegration** ? (ActionScheduler) for advanced cron jobs.  
14. **SEOPluginHooks** ? optional alt text, captions & sitemap updates.  

---

## Missing / Future Work

- **pluginBootstrapLoader** ? main bootstrap loader (status: Fail)  
- **SchedulerIntegration** ? full ActionScheduler support  
- **SEOPluginHooks** ? deep integration with Yoast/AIO SEO  
- **Unit & Integration Tests** ? cover error & edge cases  

---

## Contributing

1. Fork the repo  
2. Create a feature branch (`git checkout -b feature/your-feature`)  
3. Commit your changes (`git commit -m 'Add new feature'`)  
4. Push to the branch (`git push origin feature/your-feature`)  
5. Open a Pull Request  

Please follow PSR-12 and WordPress PHP Coding Standards. All new code must include unit tests.

---

## License

MIT License ? [Your Name or Company]  

See `LICENSE` for details.