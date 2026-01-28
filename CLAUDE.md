# CLAUDE.md

> **Keep this file current.** When making changes that affect the project's structure, conventions, data model, or tech stack, update this file to reflect those changes. If you notice any errors or outdated information in this file, fix it proactively.

## Project Overview

**Klog** — A self-hosted app for collecting and preserving memories (text, photos, videos, audio, web clippings). Built
for longevity: minimal dependencies, portable data, server-rendered HTML. Designed to remain viewable 20-40 years from
now.

## Tech Stack

- **Backend:** Laravel 12 / PHP 8.2+
- **Database:** SQLite (single file at `storage/database.sqlite`)
- **Frontend:** Blade templates, Tailwind CSS v4, vanilla JS (no Vue/React/Livewire)
- **Build:** Vite 7 with `laravel-vite-plugin`
- **Testing:** Pest v3 with `pest-plugin-laravel`
- **Linting:** Laravel Pint

## Key Commands

```bash
composer setup          # Install deps, generate key, migrate, build frontend
composer dev            # Run dev servers (Laravel, queue, pail logs, Vite HMR)
composer test           # Clear config cache and run Pest tests
./vendor/bin/pint       # Format code with Laravel Pint
npm run build           # Production build
php artisan migrate     # Run migrations
php artisan db:seed     # Seed database
```

## Architecture & Design Principles

- **Longevity over convenience** — avoid heavy framework dependencies; prefer server-rendered HTML with optional JS
  enhancements
- **Portable data** — SQLite + clean schema for easy export/backup
- **Soft deletes** on all core entities (Memory, Media, Tag, WebClipping)
- **No denormalized type column** — `Memory->types` is computed from relationships (content, media, web clippings)
- **Polymorphic media** — `Media` attaches to `Memory` and `WebClipping` via `mediable_type`/`mediable_id`

## Data Model

- **Memory** — core entity; has title (nullable), content (nullable), captured_at
- **Media** — polymorphic attachment (image/video/audio) with type enum, JSON metadata, ordering
- **WebClipping** — URL snapshot belonging to a Memory
- **Tag** — unique name + auto-generated slug, many-to-many with Memory via `memory_tag` pivot
- **User** — standard Laravel auth

### Key Relationships

- `Memory` hasMany `Media` (morphMany), hasMany `WebClipping`, belongsToMany `Tag`
- `Media` morphTo `mediable` (Memory or WebClipping)
- `Tag` belongsToMany `Memory`

## Enums

- `MemoryType` — photo, video, audio, webclip, text
- `MediaType` — image, video, audio
- `MimeType` — JPEG, MP4, MPEG

## Coding Conventions

- PSR-12 via Laravel Pint
- PHP 8.2+ features (enums, match expressions, named arguments)
- Models: PascalCase singular (`Memory`, `WebClipping`)
- Tables: snake_case plural (`memories`, `web_clippings`, `memory_tag`)
- Columns: snake_case (`captured_at`, `original_filename`, `mime_type`)
- Factories with modifier methods (e.g., `MediaFactory->image()`, `->video()`, `->audio()`)
- Pest BDD-style tests with `describe`/`it` blocks and `expect()` fluent assertions
- Commit messages: imperative mood, short descriptive titles

## Testing

- Tests use in-memory SQLite (`phpunit.xml` overrides DB)
- `RefreshDatabase` trait for test isolation
- Feature tests for models and seeders in `tests/Feature/`
- Factories available for all models in `database/factories/`

## Project Structure

```
app/Enums/          — PHP enums (MemoryType, MediaType, MimeType)
app/Models/         — Eloquent models
app/Services/       — Business logic
app/Http/Requests/  — Form request validation
database/migrations/ — Schema definitions
database/factories/ — Test data factories
database/seeders/   — Database seeders
resources/views/    — Blade templates
resources/css/      — Tailwind entry point
resources/js/       — Minimal vanilla JS
tests/Feature/      — Feature/integration tests
tests/Unit/         — Unit tests
```
