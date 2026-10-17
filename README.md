# Laravel Starter Template

A Laravel 13 application starter using Inertia 3, React 19, TypeScript 6, Tailwind CSS 4, and MySQL. The application includes manual authentication, email verification, profile and user management, role and permission management, and activity logs.

## Requirements

- PHP 8.4+ with `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `tokenizer`, and `xml`
- Composer
- MySQL 8
- Node.js and npm

## Local setup

1. Create a MySQL database (the example configuration uses `starter_template`).
2. Copy `.env.example` to `.env`, then set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` for your local database. Keep `.env` private and out of source control.
3. Install dependencies, generate the application key, run migrations, and build frontend assets:

   ```sh
   composer run setup
   ```

4. Seed the permission catalog and administrator role, then create an administrator with the interactive command:

   ```sh
   php artisan db:seed
   php artisan users:create-admin
   ```

5. Start the local server, queue worker, logs, and Vite with:

   ```sh
   composer run dev
   ```

The general database seeder creates the permission catalog and administrator role, but no default user account.

Email uses Laravel's `log` mailer by default. Verification messages are written to the application log until a mail provider is configured.

### Optional Google sign-in

Google sign-in is disabled by default. To enable it locally:

1. In the [Google Cloud Console](https://console.cloud.google.com/), select or create a project, configure its OAuth consent/branding details, and create an OAuth client for a **Web application**. While the app is in testing mode, add the Google accounts that should be allowed to sign in as test users.
2. Add **both** callback URLs as authorized redirect URIs; each must match the app's scheme, host, port, and path exactly:

   ```text
   http://localhost:8000/auth/google/callback
   http://localhost:8000/profile/social/google/callback
   ```

   The first is for login and the second is for linking Google from an existing signed-in account.
3. Set the credentials and feature flags in your private `.env` file:

   ```dotenv
   APP_URL=http://localhost:8000
   SOCIAL_LOGIN_ENABLED=true
   GOOGLE_LOGIN_ENABLED=true
   GOOGLE_CLIENT_ID=your-web-client-id
   GOOGLE_CLIENT_SECRET=your-web-client-secret
   GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback
   ```

   Change the host or port in both Google Cloud callback entries, `APP_URL`, and `GOOGLE_REDIRECT_URI` when your local app uses a different address. Keep the client secret private.
4. Clear cached configuration if enabled, then visit `/login`. Google sign-in only works for Google identities already linked to an existing local user; new accounts are not created automatically. Users can link an identity from their profile after confirming their password.

The project requests basic Google sign-in identity only. See Google's [OAuth web-server setup](https://developers.google.com/identity/protocols/oauth2/web-server) and [client ID and consent configuration](https://developers.google.com/identity/gsi/web/guides/get-google-api-clientid) for current console instructions. Google Cloud console labels may change.

For more on administrator access and permissions, see [docs/authorization.md](docs/authorization.md).

### Article cover images

The optional Article & Blog Manager accepts one JPEG or PNG cover image up to 4 MB and 6,000 pixels in either dimension. Images are stored on the private `local` disk; article editors see them only with `articles.update`, and the public blog serves them only while the article is published and unarchived. Replaced, removed, and permanently deleted covers are cleaned up. Set `DEMO_BLOG_COVER_IMAGES_STRICT_INSPECTION=true` to reject common embedded script/polyglot indicators for this upload field. This heuristic is not a malware scanner and cannot detect every malicious file.

Article drafts can also contain inline JPEG/PNG images up to 4 MB. Inline images are private, attached to one article, and served to editors or preview users through permission-checked routes; public delivery is enabled only for images referenced by a published, unarchived article. Permanently deleting an article removes its uploaded inline images. Set `DEMO_BLOG_INLINE_IMAGES_STRICT_INSPECTION=true` for the same heuristic checks on this separate upload field. This is not antivirus scanning.

The article library supports selected-column CSV, XLSX, and PDF exports using its current title search and active/archive view. PDF exports are limited to 1,000 matching articles; narrow the search or export as CSV/XLSX for larger lists.

### Contact Directory attachments

The optional Contact Directory accepts PDF, PNG, and JPEG attachments, up to five files of 10 MB each per save. PHP's `upload_max_filesize` must be at least `10M` and `post_max_size` at least `52M` for those limits to be reachable; PHP may reject a request before Laravel handles it otherwise. Files use randomized names in the private `local` storage disk and are served only through permission-checked download routes as attachments. The server checks the extension, detected MIME type, and file format before storing them.

Community Feed posts accept up to four PNG or JPEG images, 10 MB each. Images are stored on the private `local` disk and delivered through the feed's authenticated view permission; the optional `DEMO_COMMUNITY_POST_MEDIA_STRICT_INSPECTION=true` adds an active-content scan. PHP's upload limits should allow `upload_max_filesize=10M` and `post_max_size` of at least `42M` for the combined files and request overhead.

Activity logging is enabled by default. `ACTIVITYLOG_CLEAN_AFTER_DAYS` sets the retention period (default 365 days; minimum 1); it takes effect when the activity-log cleanup command is run or scheduled. Set `AUDIT_DISABLED_EVENTS` to a comma-separated list of exact event names to opt out of individual audit events; unspecified events remain enabled. Keep this list limited to events approved for omission.

Optional stricter script/polyglot indicator checks can be enabled for this upload field with `DEMO_CONTACT_ATTACHMENTS_STRICT_INSPECTION=true`. These checks reject common active-content markers and obvious trailing image payloads; they are not a malware scanner and cannot guarantee detection of every malicious file. Keep uploaded files private and do not execute them. Contact attachments are removed when their Contact is permanently deleted.

## Checks

```sh
php artisan test
php vendor/bin/pint --test
npx tsc --noEmit
npm run build
```

PHPUnit uses SQLite in memory, so these tests do not touch the configured MySQL database. Dependency versions are pinned by `composer.lock` and `package-lock.json`.

## Environment notes

`.env.example` is a local-development starting point. Before deployment, configure production database, queue, cache, session, and mail services; set `APP_ENV=production`, `APP_DEBUG=false`, and a secure HTTPS application URL. Do not commit `.env` or real credentials.

Public registration routes are not currently available. Create the first administrator with the command above; do not use a default account or password.
