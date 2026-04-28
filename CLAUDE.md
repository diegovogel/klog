# CLAUDE.md

> **Keep this file current.** When making changes that affect the project's structure, conventions, data model, or tech stack, update this file to reflect those changes. If you notice any errors or outdated information in this file, fix it proactively.

## Project Overview

**Klog** — A self-hosted app for collecting and preserving memories (text, photos, videos, audio, web clippings). Built
for longevity: minimal dependencies, portable data, server-rendered HTML. Designed to remain viewable 20-40 years from
now.

## Tech Stack

- **Backend:** Laravel 12 / PHP 8.2+
- **Database:** SQLite (single file at `storage/database.sqlite`)
- **Frontend:** Blade templates, plain CSS, vanilla JS (no Vue/React/Livewire)
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
php artisan user:create          # Create a new user (interactive)
php artisan user:reset-password  # Reset a user's password (interactive)
php artisan user:change-role <email> <admin|member>  # Change a user's role
php artisan clippings:fetch-content           # Fetch & archive text content for clippings (--limit=10)
php artisan clippings:install-screenshots    # Install Browsershot + Puppeteer for screenshots
php artisan clippings:uninstall-screenshots  # Remove screenshot packages
php artisan clippings:screenshot             # Capture screenshots for clippings (--limit=10, --force)
php artisan 2fa:install-authenticator        # Install TOTP authenticator packages
php artisan 2fa:uninstall-authenticator      # Remove authenticator packages, migrate users to email 2FA
php artisan search:reindex                   # Rebuild the memories_fts search index from scratch
```

## Architecture & Design Principles

- **Longevity over convenience** — avoid heavy framework dependencies; prefer server-rendered HTML with optional JS
  enhancements
- **Portable data** — SQLite + clean schema for easy export/backup
- **Soft deletes** on all core entities (Memory, Media, Tag, WebClipping)
- **No denormalized type column** — `Memory->types` is computed from relationships (content, media, web clippings)
- **Polymorphic media** — `Media` attaches to `Memory` and `WebClipping` via `mediable_type`/`mediable_id`
- **Private media storage** — media files are stored on the `local` disk (`storage/app/private/`) and served through
  an auth-protected `MediaController`. No public symlink. URLs use `route('media.show', $filename)`
- **Session-based auth with optional 2FA** — no public registration, no password reset flow. Users are created
  via `php artisan user:create` or admin invite (see "Two-tier roles" below). Public routes: `/login` and
  `/invites/{token}`. Two-factor authentication supports two methods: email (always available) and authenticator
  app (optional add-on). The `EnsureTwoFactorChallenge` middleware intercepts authenticated users who haven't
  completed 2FA, redirecting to a challenge page. Devices can be remembered via an encrypted cookie. The remember
  duration defaults to `TWO_FACTOR_REMEMBER_DAYS` (env, default 30) but is overridden at runtime by the
  admin-configured `app_settings.two_factor_remember_days`, resolved through `TwoFactorConfigService`. Recovery
  codes (8 per user, bcrypt-hashed) work with both methods; the settings page shows unused-code count and lets
  the user regenerate (re-display is impossible by design).
- **Two-tier roles, invite-based onboarding, deactivation over deletion** — every user has a `UserRole` (`admin`
  or `member`). Admins manage application settings + users from `/settings` (admin sections gated by the `admin`
  middleware alias, registered in `bootstrap/app.php`). New users are invited via `settings.users.invite`, which
  creates a stub user + a single-use 64-char `UserInvite` (2-day expiration), then mails a signed `/invites/{token}`
  setup URL. Removed users are *deactivated* (`users.deactivated_at`), not deleted, so memory authorship survives.
  Login rejects deactivated users with the generic `auth.failed` message. The `EnsureUserActive` middleware
  (alias `user-active`, applied to every authenticated route) kicks deactivated users mid-session on their next
  request — and enforces a per-user **session epoch**: `users.session_invalidated_at` is bumped by
  `User::deactivate()`, by `logOutOtherDevices`, and by password change. Sessions carry an `auth.created_at`
  value (stamped by the `StampAuthCreatedAt` listener on every `Login` event, including Laravel's recaller-cookie
  path); the middleware compares the two with an inclusive `<=` so a session created in the same whole second as
  an invalidation event is treated as stale. The actor's own session is preserved by stamping `+1s`.
  `User::deactivate()` also cycles `remember_token`, deletes `two_factor_remembered_devices` rows, and (on the
  database session driver) deletes the user's `sessions` rows. Last-admin guard wraps role-change and
  deactivation in `DB::transaction` + `lockForUpdate` so concurrent admins can't both succeed and leave zero
  active admins. Invite accept uses an atomic conditional `UPDATE` so a single token can't be consumed twice.
- **Web clipping content extraction** — `clippings:fetch-content` fetches page HTML via Laravel's HTTP client,
  strips non-content elements, and stores minimal structural HTML (headings, paragraphs, lists). Runs daily at 01:00.
  No external dependencies. Failed URLs are retried up to 14 times before being permanently skipped.
- **URL probe with SSRF protection** — `/url-check` validates web-clipping URLs at form-blur time
  (suggests fixes for partial entries like `google.com`, warns on auth-walled / unreachable URLs).
  Uses `HostValidator::resolvePublic()` to reject private / loopback / link-local / reserved IPs,
  pins the validated IP via `CURLOPT_RESOLVE` to defeat DNS rebinding, and follows redirects
  manually with per-hop re-validation. Reuse `HostValidator` for any future outbound HTTP that
  takes user-supplied hosts.
- **Optional screenshot add-on** — web clipping screenshots use `spatie/browsershot` + `puppeteer`, installed via
  `php artisan clippings:install-screenshots`. The schedule in `routes/console.php` activates automatically via
  `class_exists()` check. Screenshots are full-page PNGs stored as polymorphic `Media` on `WebClipping`. The app
  works without it. Failed URLs are retried up to 14 times. `--force` recaptures all clippings, replacing existing
  screenshots. Before capture, `ScreenshotService` injects CSS and JS to dismiss cookie banners, consent dialogs,
  and other overlays (three-phase: selector-based click → text-based button click → removal of large fixed overlays).
- **Optional authenticator app add-on** — TOTP-based 2FA uses `pragmarx/google2fa` + `chillerlan/php-qrcode`,
  installed via `php artisan 2fa:install-authenticator`. Follows the same pattern as the screenshot feature:
  `AuthenticatorService::isAvailable()` uses `class_exists()` guard. The settings UI conditionally shows the
  authenticator option. Uninstalling via `php artisan 2fa:uninstall-authenticator` gracefully migrates confirmed
  authenticator users to email method (no lockouts), clears unconfirmed setups, then removes packages.
- **Chunked uploads with client-side image resize** — media files are uploaded via a chunked AJAX pipeline
  to bypass Cloudflare's 100MB per-request limit. Images are resized client-side (Canvas API, max 2048px,
  JPEG 0.85) before upload. Files start uploading eagerly on add (not on form submit) for parallel processing
  while the user composes the memory. `UploadSession` model tracks chunk progress; assembled files go to the
  same `uploads/YYYY/MM/` path as direct uploads. The form submits upload UUIDs as hidden inputs instead of raw
  files. Orphaned sessions are cleaned up daily at 03:00. Config in `config/klog.php` under `uploads` key.
  Traditional multipart upload still works as fallback (no-JS).
- **Server-side media optimization** — HEIC/HEIF/AVIF images are converted to JPEG and MOV/WebM videos
  are re-encoded to H.264 MP4 via queued `OptimizeMedia` jobs. Requires PHP Imagick extension (with HEIC
  delegates) and FFmpeg/FFprobe binaries. `MediaOptimizationService` handles conversion logic.
  `MediaStorageService` dispatches the job after creating Media records. Media records track state via
  `processing_status` column (ProcessingStatus enum: Pending, Processing, Complete, Failed). The memory
  card UI shows placeholders for processing media and error messages for failures. Config in
  `config/klog.php` under `media_optimization` key. Client-side image resize reads the same config values
  via data attributes on the upload component.
- **Error email notifications** — a custom Monolog log channel (`email`) in the default logging stack sends
  error-level (and above) log entries to a maintainer via email. Recipient resolution priority:
  (1) admin-configured `app_settings.maintainer_email` from the Settings UI,
  (2) `MAINTAINER_EMAIL` env var,
  (3) cached auto-discovery `app_settings.maintainer_email_autodiscovered` (lower priority on purpose so a
  transient send failure can't reroute future mail away from the configured value),
  (4) iterate users by ID until one succeeds (then cache that recipient under the auto-discovery key).
  The ordering — UI > env — is deliberate: admins should be able to change the maintainer email through the
  browser without ops access. Pending invitees and deactivated users are excluded from the user-iteration
  fallback. Includes rate limiting (1 email per unique error per 15 min), infinite-loop prevention, and
  resilience to pre-migration state. Configured in `config/logging.php` and `config/klog.php`.
- **Full-text search via SQLite FTS5** — search is powered by a `memories_fts` virtual table (FTS5, porter
  tokenizer + unicode61 with diacritic folding) kept in sync with the source via the `MemoryObserver` and
  `SearchIndexer` service. Searchable fields: memory title, memory content (HTML stripped), tag names, and
  web clipping URLs. Stemming (dish ↔ dishes) and prefix matching (birth* → birthday) are built in — the
  `SearchService` appends `*` to each sanitized token. Filters (types, date range, children, author) apply
  identically whether or not a text query is present, via `Memory` query scopes (`scopeFilterByTypes`,
  `scopeFilterByDateRange`, `scopeFilterByChildren`, `scopeFilterByUser`). When the query is empty, search
  returns memories ordered by `memory_date` DESC (same as the feed). When text is present, results are
  joined against FTS5 and ordered by `memories_fts.rank` (BM25). No external dependencies, no separate
  server process — the index travels with the SQLite file. Rebuild manually with `php artisan search:reindex`
  if the index ever falls out of sync. The `MemoryObserver` auto-reindexes on save/update/delete/restore;
  callers that mutate relationships (tags, web clippings) without touching the Memory row call
  `$memory->reindexSearch()` explicitly — see `memories.store` route and `Memory::syncTagNames` / `attachTagNames`.

## Data Model

- **Memory** — core entity; has user_id (required), title (nullable), content (nullable), memory_date
- **Media** — polymorphic attachment (image/video/audio) with type enum, JSON metadata, ordering,
  processing_status (ProcessingStatus enum)
- **WebClipping** — URL snapshot belonging to a Memory; has title (nullable), content (nullable, minimal HTML),
  fetch_attempts, screenshot_attempts (both track retry counts, max 14)
- **Tag** — unique name + auto-generated slug, many-to-many with Memory via `memory_tag` pivot
- **UploadSession** — tracks chunked upload progress; UUID primary key, belongs to User, stores chunk
  indices and assembled file path on completion
- **AppSetting** — key-value store for application settings (`maintainer_email`,
  `maintainer_email_autodiscovered`, `two_factor_remember_days`, `screenshots_enabled`,
  `screenshots.install.status`)
- **User** — standard Laravel auth, plus: `role` (`UserRole` enum), `deactivated_at` (nullable timestamp),
  `session_invalidated_at` (nullable timestamp, the per-user session epoch), and optional 2FA columns:
  `two_factor_method` (enum), `two_factor_secret` (encrypted), `two_factor_recovery_codes` (encrypted array),
  `two_factor_confirmed_at`. Scopes: `active` / `deactivated`. `User::multipleExist()` is a request-scoped
  cached check used by the memory card to decide whether to render the author label.
- **UserInvite** — single-use invite token belonging to a User; columns `token` (64-char unique),
  `expires_at`, `accepted_at` (nullable). Created by `UserInviteService::invite()`, consumed atomically by
  `UserInviteService::accept()` (conditional UPDATE on `accepted_at IS NULL`). Expired-and-unaccepted
  invites are purged daily; if the stub user has no memories, the user is deleted alongside.

### Key Relationships

- `Memory` belongsTo `User`, hasMany `Media` (morphMany), hasMany `WebClipping`, belongsToMany `Tag`, belongsToMany `Child`
- `Media` morphTo `mediable` (Memory or WebClipping)
- `Tag` belongsToMany `Memory`

## Enums

- `MemoryType` — photo, video, audio, webclip, text
- `MediaType` — image, video, audio
- `MimeType` — JPEG, PNG, GIF, WEBP, HEIC, HEIF, AVIF, MOV, MP4, WEBM_VIDEO, MPEG, WAV, M4A, WEBM_AUDIO
- `TwoFactorMethod` — EMAIL, AUTHENTICATOR
- `ProcessingStatus` — Pending, Processing, Complete, Failed
- `UserRole` — admin, member

## Coding Conventions

- PSR-12 via Laravel Pint
- PHP 8.2+ features (enums, match expressions, named arguments)
- Models: PascalCase singular (`Memory`, `WebClipping`)
- Tables: snake_case plural (`memories`, `web_clippings`, `memory_tag`)
- Columns: snake_case (`captured_at`, `original_filename`, `mime_type`)
- Factories with modifier methods (e.g., `MediaFactory->image()`, `->video()`, `->audio()`, `->heic()`, `->mov()`, `->processing()`, `->failed()`)
- Pest BDD-style tests with `describe`/`it` blocks and `expect()` fluent assertions
- Commit messages: imperative mood, short descriptive titles

## Code Review

**OpenAI Codex** is used for automated PR code review. Use the `/pr-with-codex` slash
command — it creates the PR and runs it through up to 5 automated `@codex review` cycles,
addressing each finding as a new commit until the review is clean. Use `/review-with-codex`
to iterate on a branch *before* opening the PR (same loop, no PR noise).

Codex reviews appear as reviews with `state: "COMMENTED"` and inline comments from the
`chatgpt-codex-connector[bot]` user. When Codex has no suggestions, it posts a top-level
issue comment (not a review) starting with "Codex Review: Didn't find any major issues".

## Testing

- Tests use in-memory SQLite (`phpunit.xml` overrides DB)
- `RefreshDatabase` trait for test isolation
- Feature tests for models, seeders, auth, and commands in `tests/Feature/`
- Factories available for all models in `database/factories/`

## Project Structure

```
app/Console/Commands/ — Artisan commands (user:create, user:reset-password, media:migrate-to-private, clippings:*, 2fa:*, search:reindex)
app/Enums/            — PHP enums (MemoryType, MediaType, MimeType, TwoFactorMethod, ProcessingStatus, UserRole)
app/Http/Controllers/ — Controllers (Auth/LoginController, Auth/TwoFactorChallengeController, Auth/InviteController, AccountSettingsController, AppSettingsController, ScreenshotSettingsController, SettingsController, TwoFactorSettingsController, UserManagementController, MediaController, UploadController, UrlCheckController, SearchController)
app/Http/Middleware/  — Custom middleware (EnsureTwoFactorChallenge, EnsureUserActive, RequireAdmin, SecurityHeaders)
app/Http/Requests/    — Form request validation (Auth/*, Settings/*, InitUploadRequest, StoreChunkRequest, SearchRequest, CheckUrlRequest)
app/Models/           — Eloquent models
app/Observers/        — Eloquent observers (MemoryObserver for search index sync)
app/Logging/          — Custom Monolog handlers (EmailLogHandler)
app/Listeners/        — Event listeners (StampAuthCreatedAt for the session-epoch comparison)
app/Mail/             — Mailable classes (ErrorOccurred, TwoFactorCodeMail, UserInvited)
app/Jobs/             — Queued jobs (OptimizeMedia, InstallScreenshotsJob, UninstallScreenshotsJob)
app/Services/         — Business logic (MediaStorageService, MediaOptimizationService, MaintainerResolverService, ScreenshotService, ScreenshotFeatureService, TwoFactorService, TwoFactorConfigService, AuthenticatorService, UserInviteService, WebClippingContentService, HtmlSanitizer, HostValidator, SearchService, SearchIndexer)
database/migrations/  — Schema definitions
database/factories/   — Test data factories
database/seeders/     — Database seeders
resources/views/      — Blade templates (auth/login, auth/accept-invite, settings/index + partials, emails/*)
resources/css/        — CSS entry point
resources/js/         — Minimal vanilla JS (components/, lib/)
tests/Feature/        — Feature/integration tests
tests/Unit/           — Unit tests
```

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.2.30
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v3
- phpunit/phpunit (PHPUNIT) - v11

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `pest-testing` — Tests applications using the Pest 3 PHP framework. Activates when writing tests, creating unit or feature tests, adding assertions, testing Livewire components, architecture testing, debugging test failures, working with datasets or mocking; or when the user mentions test, spec, TDD, expects, assertion, coverage, or needs to verify functionality works.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan

- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging

- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.

## PHPDoc Blocks

- Add useful array shape type definitions when appropriate.

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd and will be available at: `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs for the user.
- You must not run any commands to make the site available via HTTP(S). It is always available through Laravel Herd.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.
- CRITICAL: ALWAYS use `search-docs` tool for version-specific Pest documentation and updated code examples.
- IMPORTANT: Activate `pest-testing` every time you're working with a Pest or testing-related task.
</laravel-boost-guidelines>
