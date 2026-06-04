# Klog

> 🚧 **PLEASE NOTE: this is a work in progress.** 🚧
>
> Some things are rough around the edges. Especially the design, which I have so far put zero time into (functionality
> first).

An app for collecting memories of my kids. Like a private Tumblr.

**Why not just use Tumblr or some other existing platform?** Longevity. I want to be able to view these memories 20, 30,
or 40 years from now, and I want my kids and their families to be able to do the same. I realize it's very ambitious to
build anything on the web that's supposed to last that long, but to improve my chances, I can't rely on a platform that
might shut down. I'm sure I could export the data, but then it would be a pain to import it or use it.

**What's with the name?** It's a play on words: kid log, kinda like vlog and video log.

**Live demo:** https://klog-demo.diego.works/. Some features are disabled or limited in the demo, e.g. settings are view
only and uploads are limited to 25MB.

## Goals

- Very easy and quick to upload content. I should be able to upload a simple memory in one minute or less.
- Multiple memory formats:
    - Text
    - Photo/video
    - Audio
    - URL (aka web clipping) — automatically takes a snapshot of the URL in case of broken links down the road.
- Can be browsed by visiting a domain and logging in (as opposed to installing an app).
- Built to last. The idea is that we will view these 20+ years from now.
- Excellent search. If we can't find a memory, what's the point?

## Architecture Decisions

- **Laravel back end.** I already know Laravel. It's healthy. It's flexible. The data is clean and portable.
- **Blade front end enhanced with Alpine or vanilla JS.** No dependence on a build step means better maintainability and
  longevity because there are fewer things to become obsolete. Server-rendered HTML is more likely to last and just
  simpler. Blade templates can easily be migrated to something else later if needed.
- **No UI library.** To increase longevity, I'll rely on HTML as much as possible and sprinkle in JS only where it's
  necessary. It won't win any UX awards, but it will be around a lot longer and with a lot less effort than anything
  that does.
- **Mobile experience via PWA.** No additional work needed. I can always make a mobile app later if needed. If an API is
  needed, adding that would be trivial too.
- **Vanilla CSS with Vite.** I was initially going to avoid any kind of build step or preprocessor for the sake of
  longevity, but Laravel comes with Vite out of the box. It's nice for development because of HMR, plus it handles
  minification, prefixing, and cache busting. However, I'm going to use vanilla CSS for styling so it can be easily
  migrated to something else or used without a build step at all.
- **SQLite database.** Lightweight, portable, and plenty powerful for this project.
- **HTML saved in DB.** Memory text content will be HTML so we get rich text, and stored in the DB for better
  searchability. I considered Markdown for the content but decided against it to avoid depending on a parser.
- **Email notifications for errors.** This app is intented to be ownable by non-technical folks and durable. Error
  monitoring is designed to just work. It does not rely on an external service and will find someone to notify even if
  it hasn't been set up.

### User Management

There is no registration page. Users are managed entirely through the admin settings page or via Artisan commands on the
server.

```bash
# Create a new user (interactive prompts for name, email, password)
php artisan user:create

# Reset a user's password (interactive prompts for email, new password)
php artisan user:reset-password
```

### Text Editor

Normally, I would use one of the many excellent WYSIWYG editors out there. However, since reducing dependencies is a
goal of this project, and I only need very basic formatting, I decided to roll my own.

A `contenteditable` div is used to capture and render the text when editing. A bit of JS replaces certain tags with
semantic equivalents and removes empty space. Then on the backend, the HTML is sanitized and saved to the DB.

### Web Clipping Screenshots

An optional screenshot feature is available for web clippings. I wanted to include it because it's pretty useful, but
it's disabled by default because it depends on a couple third-party libraries. When enabled, a
screenshot is taken from each web clipping URL and saved to the app's media. This is for archiving purposes because this
app is meant to outlast most web pages. It's built as a progressive enhancement: web clippings work fine either way,
screenshots are simply added if the system is enabled. The system can be turned on and off in admin settings or via
Artisan commands.

#### Artisan Commands

To enable screenshots, run the installation command:

```bash
php artisan clippings:install-screenshots
```

This installs the required dependencies (Browsershot and Puppeteer) and runs a pipeline test to make sure everything
works. The scheduler is already set up to capture any missing screenshots every day at 2 am if Browsershot is installed.

To reduce complexity, a queue is not used for processing screenshots. Instead, the system processes a max of 10
clippings at a time. If there's a backlog of screenshots to capture, it'll
process 10 per day until it's caught up. Since screenshots are simply for long-term archiving, it doesn't matter if it
takes days or weeks to process them.

You can also process screenshots manually, and override the default limit of 10 with a flag.

```bash
# Take screenshots for up to 50 clippings.
php artisan clippings:screenshot --limit=50
```

To deactivate the system, run the uninstallation command:

```bash
php artisan clippings:uninstall-screenshots
```

This removes the dependencies, which automatically deactivates the scheduled task.

#### Consent Banners and Captchas

Consent banners are problematic for the screenshot system when they obscure the target content. The app uses three
methods of getting around them:

1. CSS hiding: targets common class/ID patterns.
2. Text-based button clicking: targets common dismissal button text and uses JS to click the button.
3. Nuclear option: removes any remaining fixed/sticky high-z-index elements covering large viewport areas.

I considered trying to get around captchas with AI, but given the goals of this project, that felt too heavy even as an
opt-in feature. Especially for a "best effort" archival feature like webpage screenshots.

### Media Optimization

Uploaded media is automatically optimized server-side via queued jobs:

- **HEIC/HEIF/AVIF images** are converted to JPEG (max 2048px, 85% quality) for universal browser
  compatibility. Images are also resized client-side before upload, but HEIC/HEIF/AVIF are skipped
  client-side because Canvas can't decode them.
- **MOV/WebM videos** are re-encoded to H.264 MP4 (max 2048px, CRF 23) for universal playback and
  better compression.

This requires two system dependencies:

- **PHP Imagick extension** with HEIC/HEIF delegates (libheif, libde265)
- **FFmpeg** and **FFprobe** binaries

```bash
# macOS (Homebrew)
brew install imagemagick libheif ffmpeg
pecl install imagick

# Ubuntu/Debian
sudo apt install imagemagick libmagickwand-dev libheif-dev ffmpeg
pecl install imagick
```

Verify Imagick HEIC support:

```bash
php -r "echo in_array('HEIC', Imagick::queryFormats()) ? 'OK' : 'Missing HEIC support';"
```

The FFmpeg and FFprobe paths can be customized via environment variables if they aren't on your PATH:

```env
FFMPEG_PATH=/usr/local/bin/ffmpeg
FFPROBE_PATH=/usr/local/bin/ffprobe
```

A **queue worker** must be running for media optimization to process. In development, `composer dev`
starts one automatically. In production, configure a queue worker via your hosting platform (e.g.,
Laravel Forge) or a process manager like Supervisor.

### Health Check

Laravel's built-in `/up` health check endpoint is disabled by default. If you use an uptime monitor or load balancer
that needs to verify the app is running, you can enable it by setting the following in your `.env` file:

```env
HEALTH_CHECK_ENABLED=true
```

When enabled, the `/up` route returns a `200` response to confirm the app is running. It is public (no authentication
required).

## Roadmap

- [ ] **Static export command.** Something like `php artisan memories:export-static`. This would generate HTML files for
  all pages and bundle them into a single ZIP file along with all assets. These snapshots would be extremely portable
  and almost timeless compared to a live site. Anyone could unzip them and view the entire site in a browser. They could
  also be used as backups.
- [ ] **PDF export command.** Something like `php artisan memories:export-pdf {start_date}`. This would generate a PDF
  of all memories in a layout that would work well for printing. This could be used for creating a scrapbook or just a
  physical backup. The start date argument would be optional and would
  allow us to print new memories from time to time.
- [ ] **Audio and video transcription.** When an audio or video memory is uploaded, it would be automatically
  transcribed and
  saved to the DB. The text could be used for search, displayed for accessibility, and used in the PDF export. This will
  be AI-powered and require a local dependency or third-party service, so it will be opt-in like the screenshot feature.
- [ ] **Automated image alt text.** When an image is uploaded, AI scans it and generates alt text. This will also be
  opt-in like the screenshot feature.
- [x] **2FA and self-service password reset.**
- [x] **Admin settings page** for managing users, enabling features, and controlling other app settings.
- [ ] **Bulk import.** Import a CSV, folder of images, etc. Possibly with an opt-in AI assistance feature where AI
  analyzes the images, groups them, and guides the user through creating memories.

... plus many other smaller improvements.
