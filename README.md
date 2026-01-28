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
  minification, prefixing, and cache busting. However, I'm going to use vanilla CSS for styling so they can be easily
  migrated to something else or used without a build step at all.
- **SQLite database.** Lightweight, portable, and plenty powerful enough for this project.
- **Markdown saved in DB.** Memory text content will be Markdown so we get rich text, but stored in the DB for better
  searchability.

## Models

**memories**

- id
- type (text|photo|video|audio|url)
- title
- content
- captured_at (for memories with media)
- created_at

**media**

- id
- memory_id
- filename
- original_filename
- mime_type
- size
- path

**tags**

- Simple pivot table.

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
