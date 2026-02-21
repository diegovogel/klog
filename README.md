# Klog

An app for collecting memories of our kids. Like a private Tumblr.

**Why not just use Tumblr or some other existing platform?** Longevity. I want to be able to view these memories 20, 30,
or 40 years from now, and I want the boys and their families to be able to do the same. I realize it's very ambitious to
build anything on the web that's supposed to last that long, but to improve my chances, I can't rely on a platform that
might shut down. I'm sure I could export the data, but then it would be a pain to import it or use it.

**What's with the name?** It's a play on words: kid log, kinda like blog and web log.

## Goals

- Very easy and quick to upload content. We should be able to upload a simple memory in one minute or less.
- Multiple memory formats:
    - Text
    - Photo/video
    - Audio
    - URL — automatically take a snapshot of the URL so we avoid broken links down the road.
- Can be browsed by visiting their domains and logging in.
- Built to last. The idea is that we/they will view these 20+ years from now.
- Excellent search. If we can't find a memory, what's the point?

## Architecture Decisions

- **Laravel back end.** I know Laravel. It's healthy. It's very flexible. The data is clean and portable (as opposed to
  WordPress, where the DB tables are a mess).
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

### User Management

There is no registration page. Users are managed entirely via Artisan commands on the server. Since new users will very
rarely be created, this slight inconvenience seemed worth the simplicity and security benefits.

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
screenshots are simply added if the system is enabled.

To enable screenshots, run the installation command:

```bash
php artisan clippings:install-screenshots
```

This installs the required dependencies (Browsershot and Puppeteer) and runs a pipeline test to make sure everything
works. The scheduler is already set up to take screenshots every day at 2 am if Browsershot is installed.

To reduce complexity, a queue is not used for processing screenshots. Instead, the system processes a max of 10
clippings at a time. If there's a backlog of clippings needing screenshots when the system is first enabled, it'll
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

## Roadmap

- [ ] **Static export command.** Something like `php artisan memories:export-static`. This would generate HTML files for
  all pages and bundle them into a single ZIP file along with all assets. These snapshots would be extremely portable
  and almost timeless compared to a live site. Anyone could unzip them and view the entire site in a browser. They could
  also be used as backups.
- [ ] **PDF export command.** Something like `php artisan memories:export-pdf {start_date}`. This would generate a PDF
  of all memories in a layout that would work well for printing. The start date argument would be optional and would
  allow us to print new memories from time to time.
- [ ] **Audio and video transcription.** When an audio or video memory is uploaded, it's automatically transcribed and
  saved to the DB. The text could be used for search, displayed for accessibility, and used in the PDF export.
- [ ] **Password reset flow and 2FA.**
