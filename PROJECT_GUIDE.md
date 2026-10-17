# PROJECT_GUIDE.md
> ****Purpose:**** This document is the permanent source of truth for this project's requirements, architecture, standards, and roadmap. It is written to be portable across fresh AI conversations — a new session should be able to read this file and continue development without the original chat history.
>
> ****Companion file:**** `PROJECT_PROGRESS.md` tracks current implementation status, verified test results, and the exact next step. This guide describes what **should** be true; the progress file describes what **is** true.
>
> ****Accuracy rule:**** Nothing in this guide may be assumed to be installed, working, or verified unless it is also reflected in `PROJECT_PROGRESS.md` with reported command output.
---
## 1. Project overview and objectives
### What is being built
A reusable, production-minded ****Laravel starter template**** for authenticated business applications. It is a monolithic Laravel application using Inertia.js and React — not a separate SPA, not a REST API, not a domain-specific product.
### Intended use
The template will be the foundation for future projects such as school management systems, appointment systems, dashboards, and administrative portals. It must be generic enough to support those without architectural rework.
### Guiding principles
- ****Correctness and maintainability over cleverness.****
- ****Security by design**** — authorization enforced on the server, not only in the UI.
- ****Simple, appropriate architecture**** — no abstractions without demonstrated need.
- ****Automated testing is mandatory****, not a final activity.
- ****Reusability without overengineering**** — build components and services when a real need appears, not speculatively.
- ****Documented decisions**** — architectural choices are recorded with their reasoning.
- ****Junior-maintainable**** — a junior developer should be able to read the codebase and understand the layout.
---
## 2. Approved technology stack
### Target stack
| Layer | Target |
|---|---|
| Backend framework | Laravel 13 |
| PHP | 8.4 or newer (subject to package compatibility) |
| Database | MySQL 8 |
| ORM | Eloquent |
| Server-side rendering bridge | Inertia.js 3 |
| Validation | Laravel Form Requests |
| Authorization | Policies, Gates, middleware |
| Architecture | Service-based, no repository pattern |
| Backend testing | PHPUnit (selected for this project) |
| Backend formatting | Laravel Pint |
| Frontend framework | React 19 or compatible newer |
| Frontend language | TypeScript |
| Inertia client | `@inertiajs/react` |
| CSS | Tailwind CSS 4 |
| UI conventions | Flowbite styling classes only (not Flowbite React components) |
| Icons | Local inline SVG icon component (no icon package dependency) |
| Frontend testing | Vitest + React Testing Library (pending setup) |
| Roles and permissions | `spatie/laravel-permission` 8.3.0 (MIT; Laravel 13 compatible) |
| Activity auditing | `spatie/laravel-activitylog` 5.1.1 (MIT; installed) |
### Fresh project status and versions

The project began as a **new project from scratch**. Current runtime, dependency, database, migration, application, and test status is recorded in `PROJECT_PROGRESS.md`; do not treat a target or this guide alone as proof of installation or verification. Verify actual versions and compatibility before adding or upgrading packages.

### Known compatibility constraints
- ****TypeScript 6**** deprecates `baseUrl`; `tsconfig.json` uses `paths` relative to the config file's location without it.
- ****Inertia 3 React adapter**** requires React 19+.
- The verified frontend lock currently uses `@vitejs/plugin-react` 5.2.0 with Vite 7.3.7; do not infer Vite 8 compatibility from this setup.
- ****Tailwind CSS 4**** uses the `@tailwindcss/vite` Vite plugin, not the older PostCSS plugin.
- ****SQLite in-memory**** is used for tests (see testing section), not MySQL.
---
## 3. Feature scope and requirements
### A. Authentication and account security
Implement:
- Login and logout.
- Password reset.
- Email verification where appropriate.
- Login rate limiting.
- Secure session handling.
- Protection of authenticated routes.
- Password confirmation for sensitive actions.
- Secure password change functionality.
****Confirmed implementation approach:**** manual authentication, not Laravel Fortify. See section 10 for the full decision record.
****Public registration requirement:**** registration is currently unavailable. If added, it must be disabled by default and controlled by `config('auth.registration.enabled')`, backed by `REGISTRATION_ENABLED`. When disabled, its routes must not be registered, so requests return 404 rather than a "feature disabled" message. Administrators currently create accounts through user management or the `users:create-admin` command.
**Do not install Laravel Breeze, Jetstream, or Laravel UI.**
### B. Profile management
Users should be able to:
- View and update their profile information.
- Change their name and email.
- Change their password.
- See their basic account information.
- Complete email verification when required.
Email changes must follow a secure verification process. Sensitive changes should require reauthentication or password confirmation where warranted.
Do not add unnecessary profile functionality until the core account features work correctly.
### C. User management
Create a reusable user management module supporting:
- Listing users.
- Viewing user details.
- Creating users.
- Editing users.
- Assigning roles.
- Activating and deactivating accounts.
- Searching and filtering.
- Server-side sorting and pagination.
- Clear validation feedback.
- Confirmation dialogs for sensitive operations.
Prevent unauthorized access and modification of user records. Protect privileged accounts and prevent self-deactivation or privilege-escalation scenarios.
Prefer ****deactivation over permanent deletion**** in ordinary administrative workflows, so historical records and audit relationships are preserved.
### D. Role and permission management
Use the installed `spatie/laravel-permission` package.
Implement:
- Role listing and management.
- Permission listing and management.
- Creating and updating roles.
- Assigning permissions to roles.
- Assigning roles to users.
- Server-side permission enforcement.
- Permission-based frontend visibility.
- Tests for authorized and unauthorized access.
****Reserved permission names**** (format `resource.action`):
```
users.view, users.create, users.update, users.delete, users.manage
roles.view, roles.manage
permissions.view, permissions.manage
activity-logs.view
```
Create an ****idempotent**** permission seeder so records can be synchronized safely without duplication.
Create and document an initial administrative role and setup procedure. ****Never ship hardcoded production administrator credentials or publicly documented default passwords.****
The UI must not be the only security boundary. All protected operations must also be authorized on the Laravel server.
### E. Activity auditing
Use the installed `spatie/laravel-activitylog` package.
Record significant events such as:
- User creation and modification.
- Account activation or deactivation.
- Role assignment and removal.
- Permission and role configuration changes.
- Important administrative actions.
- Other significant record changes when the relevant feature requires them.
Evaluate whether authentication events and failed login attempts should be recorded separately as security events.
Create an activity log page supporting:
- Viewing audit records.
- Identifying the responsible user when available.
- Displaying action, subject, and timestamp.
- Relevant change information.
- Search or filters when appropriate.
- Server-side pagination.
- Permission-restricted access.
****Do not**** indiscriminately log every query or request.
****Never**** log passwords, password hashes, access tokens, session secrets, API keys, or unnecessary sensitive personal information. Protect audit records against unauthorized reading, modification, and deletion. Consider retention requirements for future deployments.
### F. Dashboard and application shell
Build a simple, reusable authenticated application shell containing:
- Responsive sidebar or navigation.
- Header.
- Account menu.
- Breadcrumbs.
- Page titles and actions.
- Dashboard page.
- Consistent page layout.
- Mobile-responsive navigation.
- Appropriate loading, empty, and error states.
Keep the dashboard generic. No complex analytics, charts, or business-specific reporting unless later required.
### G. Reusable UI components
Build a ****custom**** React component library using Tailwind CSS 4 and Flowbite styling conventions (CSS classes and styling utilities only — ****not**** Flowbite React components).
Components to build incrementally as needed:
- Buttons and links.
- Text inputs, password inputs, selects, textareas.
- Checkboxes and radio controls.
- Form fields and validation messages.
- Cards and badges.
- Modals and confirmation dialogs.
- Dropdown menus.
- Toast notifications.
- Drawers when appropriate.
- Breadcrumbs and page headers.
- Data tables.
- Search and filter controls.
- Sortable column headers.
- Pagination and page-size controls.
- Loading indicators.
- Empty states and error states.
Do not build every component before it is needed. Develop incrementally, then refactor genuinely reusable patterns into shared components.
Use React for interactive behavior. Do not initialize an additional JavaScript UI framework to manipulate the same components. All interactive components must consider keyboard operation, accessibility attributes, focus management, and clear visual states.
Use TypeScript deliberately. Avoid `any` unless there is a clearly justified exceptional case.
---

## 3A. Approved expanded capabilities (mandatory project scope)

These capabilities are part of the reusable starter foundation. **The three demonstration modules are optional and independently disableable.** Implement them incrementally under the approval-gated roadmap; do not scaffold all of them at once.

### Structured person names

Replace the single `users.name` field with `prefix` (nullable), `first_name` (required), `middle_name` (nullable), `last_name` (required), and `suffix` (nullable). Provide centralized, tested name-formatting helpers/accessors for full name, preferred display name, and middle initial (for example, `Miguel` -> `M.`), handling blank middle names and whitespace safely. Update authentication, shared Inertia props, forms, user lists, audit actor display, and reports to use these fields consistently. Do not retain a competing, manually synchronized `name` field without an explicit compatibility requirement.

### Optional MFA and extensible social authentication

- Add **TOTP authenticator-app MFA** with enrollment confirmation, secure recovery codes, verification, disabling, and recovery flows. Use established free/open-source packages or Laravel primitives where suitable; never store raw recovery codes or TOTP secrets in logs. Encrypt stored TOTP secrets at rest. MFA is independently toggleable through `MFA_ENABLED=false` by default.
- Support **Google OAuth login** initially through a provider-adapter approach that can later add Facebook, Microsoft, or others without rewriting the authentication core. Provide `SOCIAL_LOGIN_ENABLED=false` and `GOOGLE_LOGIN_ENABLED=false` toggles; provider secrets belong in `.env`, with documented `.env.example` placeholders. Never silently auto-link an existing account solely by matching an unverified email. Protect OAuth `state`, callback handling, account linking, and MFA enforcement.
- Planned `.env.example` placeholders (final naming may be refined once implemented):
  ```dotenv
  MFA_ENABLED=false
  SOCIAL_LOGIN_ENABLED=false
  GOOGLE_LOGIN_ENABLED=false
  GOOGLE_CLIENT_ID=
  GOOGLE_CLIENT_SECRET=
  GOOGLE_REDIRECT_URI=
  ```
- Add a setup guide for creating Google Cloud OAuth credentials, configuring the consent screen, redirect URI, and testing locally. TOTP authenticator-app MFA does **not** require a Google/Facebook developer account. Document provider-specific setup when another social provider is actually added. Disabled integrations must not expose usable login routes/buttons.
- Do not invent credentials or imply external OAuth accounts have been configured.

### System-wide, configurable auditing

Use Spatie Activitylog (or a justified free compatible alternative) as a reusable cross-module auditing capability. Audit meaningful model changes, authentication/security events, CRUD lifecycle actions, role/permission changes, attachment events, and relevant administrative actions. Record actor, event, subject, time, and safe change details; support a protected, searchable, filtered, server-paginated audit viewer. Provide per-model/event opt-in/opt-out configuration, centralized redaction of sensitive attributes, and retention considerations. Avoid logging every query/request or storing passwords, tokens, secrets, or sensitive file contents.

### Shared server-driven data table capability

Create a reusable React table and Laravel query/pagination conventions, shared by future modules rather than coupled to a demo. Requirements:

- Server-side search, filters, sorting, and pagination; preserve and validate query parameters and enforce authorized column/filter allowlists.
- Page-size choices **10, 25, 50, 100, All**. For **All**, switch to **infinite loading in server-fetched batches**; do not return all rows in one response. Infinite loading is an optional table mode, not mandatory for every table. Support stable cursor-based pagination when suitable, with deterministic ordering, reset on filter changes, loading/end/error states, and accessible load-more fallback.
- User-configurable visible columns, with sensible defaults and optional persistence when appropriate. Support independently selected export/report columns; validate selected fields server-side and prevent exporting hidden/sensitive attributes.
- CSV, XLSX, and PDF exports of **authorized filtered/sorted records** and selected columns. Provide safe limits or queued/streamed processing for large exports. Use free commercially usable libraries; escape spreadsheet formula injection in CSV/XLSX and sanitize report output.
- Reusable table behavior should be composable/configurable, not a forced model trait or an oversized one-size-fits-all abstraction. Preserve standard page-number pagination where selected.

### Reusable file uploads with optional strict inspection

Provide secure, configurable upload handling with extension, MIME, file-signature and format-aware validation, size limits, private/quarantine storage, safe filenames, and controlled serving. Allow **strict script/polyglot inspection to be enabled per upload field/module** rather than forcing it globally. Detect suspicious embedded scripts/polyglot patterns where technically feasible, reject unsafe files, and allow an optional free/self-hostable malware scanner integration if justified. Frontend checks are advisory; **server-side checks are authoritative**. No detector guarantees catching every malicious polyglot: document limitations and apply defense-in-depth (including safe storage and content-disposition). Do not execute or publicly serve untrusted uploads during inspection.

### Reusable trash, restoration, and archiving

Use Laravel Eloquent **SoftDeletes** for trash/recycle-bin operations, including listing trashed records, restoring, and authorized permanently deleting (`forceDelete`) with explicit confirmation. Implement **archiving separately** using `archived_at` or a justified explicit status, with archive/unarchive actions. A record may be archived without being deleted; trash and archive are not synonyms. Audit these transitions and protect bulk/permanent actions through policies and tests. Build reusable patterns without forcing them on models that do not need them.

### Three optional demonstration CRUD modules

All demos must use the shared foundation, serve as implementation references, and be removable/disableable without breaking reusable infrastructure.

1. **Contact Directory — traditional forms CRUD:** structured fields, validation, relationships, attachments, secure optional upload inspection, server-driven tables, filters, sorting, column visibility, selectable PDF/XLSX/CSV exports, trash/restore/permanent delete, archive/unarchive, authorization and auditing.
2. **Article & Blog Manager — rich content CRUD:** modular free rich-text editor (evaluate free Tiptap core or equivalent) supporting headings, fonts/styles, spacing, lists, links, inline media, cover image, drafts, preview, publication, safe sanitization/rendering, author permissions, versioning only if approved, trash/archive, and audit history. Do not rely on paid editor extensions.
3. **Community Feed — social CRUD:** posts (text/media), comments, replies, likes/unlikes, ownership rules, moderation, efficient feed pagination/infinite loading, safe upload handling, trash/restore and archive for applicable records, and auditing. No mandatory real-time chat, follower system, or external social network integration.

Control visibility **and backend availability** through `.env` -> config, defaulting to **disabled for a fresh reusable template**:

```dotenv
DEMO_MODULES_ENABLED=false
DEMO_CONTACT_DIRECTORY_ENABLED=false
DEMO_BLOG_ENABLED=false
DEMO_COMMUNITY_FEED_ENABLED=false
```

The global flag overrides individual flags. Disabled demo routes/endpoints must be unavailable (not merely hidden in navigation); data and source files remain intact, and demo seed data must not be loaded automatically. Shared tables, exports, auditing, uploads, auth, and other foundation services must continue working when every demo is disabled.

### Professional reusable UI

Use **Tailwind CSS 4 with Flowbite styling classes/conventions only**, never Flowbite React components. Deliver a polished, cohesive enterprise UI with responsive layouts, dark mode, accessible forms/modals/menus, clear feedback, loading/empty/error states, and consistent design tokens. Keep shared components modular and domain-agnostic.

### Free package and service policy

Use **free, actively maintained, commercially usable open-source packages** where dependencies are necessary; prefer Laravel built-ins first. Check actual license, compatibility, security, and maintenance before installation. Avoid paid-only features, premium plugins, mandatory subscriptions, and lock-in. Free code may still incur hosting or optional external-service costs; core functionality must not require a paid service. Record approved dependencies and decisions in this guide when chosen.

---

## 4. Backend architecture
### Approach
Simple, service-based Laravel. Eloquent as the data-access layer. No repository pattern.
### Required principles
1. Use Eloquent models and relationships for database interaction.
2. Use Form Request classes for request validation when appropriate.
3. Use Policies, Gates, and middleware for authorization.
4. Use service classes for meaningful business logic, transactions, and multi-step operations.
5. Keep controllers focused on coordinating requests and responses.
6. Keep business rules out of React components and avoid bloated controllers.
7. Use database transactions for operations that must succeed or fail together.
8. Use explicit validation and safe mass assignment.
9. Use dependency injection where it provides a clear benefit.
10. Follow Laravel naming conventions and established framework patterns.
### Do NOT use the Repository pattern
Laravel's Eloquent ORM already provides a suitable abstraction for most data access in this project. An additional repository layer would create unnecessary complexity without demonstrated benefit. Do not create repository interfaces and repository implementations for every model.
Do not introduce a generic CRUD framework, arbitrary base classes, or excessive abstractions.
Create a service class when it provides a meaningful place for business rules or coordinates a complex operation. Simple Eloquent operations can remain simple.
### Directory structure (agreed)
```
app/
├── Http/
│   ├── Controllers/        # Thin: coordinate request -> service -> response
│   ├── Middleware/         # Includes HandleInertiaRequests
│   └── Requests/           # Form Request validation classes
├── Models/                 # Eloquent models only
├── Policies/               # One policy per model that needs authorization
└── Services/               # Business logic for multi-step or transactional operations
```
No `Repositories/`, no `Contracts/` interfaces, no custom `BaseController` beyond Laravel's own.
### Naming conventions (PHP)
| Thing | Convention | Example |
|---|---|---|
| Controller | Singular + `Controller` | `UserController` |
| Form Request | Verb + Noun + `Request` | `StoreUserRequest`, `UpdateUserRequest` |
| Policy | Model + `Policy` | `UserPolicy` |
| Service | Noun + `Service` | `UserService` |
| Migration | Laravel default (timestamped snake_case) | `create_users_table` |
### Middleware registration
Laravel 11+ style: middleware registered in `bootstrap/app.php` via `->withMiddleware(...)`. `AuthServiceProvider` is not used in Laravel 13's default scaffold; rate limiters and other auth-related setup live in `AppServiceProvider::boot()`.
---
## 5. Frontend architecture
### Approach
React + TypeScript + Inertia + Tailwind CSS 4 + custom reusable components. No separate SPA, no REST API for ordinary application functionality, no additional state-management library (Redux, Zustand) unless a genuine requirement appears.
### Directory structure (agreed)
```
resources/js/
├── app.tsx                 # Inertia bootstrap (single JS entry point)
├── components/
│   └── ui/                 # Generic domain-agnostic primitives
├── Layouts/                # Shared page shells (app shell, auth shell)
├── lib/                    # Pure utilities and small helpers
├── Pages/                  # Inertia page components (MUST stay here)
│   ├── Auth/               # Login, ForgotPassword, ResetPassword, etc.
│   ├── Users/              # Index, Create, Edit, etc.
│   └── ...
└── types/                  # Shared TypeScript types
```
The folder name ****must remain exactly `Pages`**** because `app.tsx` resolves pages via `import.meta.glob('./Pages/**/*.tsx')`.
Feature-specific components that are not generic (e.g., a `UserStatusBadge`) live next to the pages that use them, e.g. `Pages/Users/partials/UserStatusBadge.tsx`. This pattern is introduced when the first such component appears — not preemptively.
### Naming conventions (TypeScript / React)
| Thing | Convention | Example |
|---|---|---|
| Page component file | PascalCase | `Pages/Users/Index.tsx` |
| Component file | PascalCase | `components/ui/Button.tsx` |
| Type / interface | PascalCase | `User`, `SharedData` |
| Utility file | kebab-case or `index.ts` | `lib/format-date.ts` |
| Props interface | `ComponentNameProps` | `ButtonProps` |
### Inertia integration
- Shared props are provided by `app/Http/Middleware/HandleInertiaRequests.php`.
- Shared props are added ****deliberately**** and kept minimal. Target: `appName` and a lazy `auth.user` containing only `id`, `name`, `email`. Sending the full Eloquent model is prohibited — it would leak columns such as `email_verified_at` or any future sensitive field.
- Pages are resolved via `import.meta.glob\<ResolvedComponent>('./Pages/**/*.tsx')` in `resources/js/app.tsx`. This is Inertia 3's TypeScript-native pattern.
- The Blade root view is `resources/views/app.blade.php`, using `\<x-inertia::head />` and `\<x-inertia::app />`.
### TypeScript standards
- `tsconfig.json` uses `strict: true`, `noUnusedLocals`, `noUnusedParameters`, `noFallthroughCasesInSwitch`.
- `moduleResolution: "bundler"`, `jsx: "react-jsx"`.
- `@/*` alias maps to `resources/js/*`, mirrored in `vite.config.js`.
- `baseUrl` is ****not**** used (deprecated in TypeScript 6).
- Avoid `any`. Use `unknown` and narrow, or define a proper type.
### Styling
- Tailwind CSS 4 via `@tailwindcss/vite`.
- Flowbite styling conventions and CSS classes only — never Flowbite React components.
- Custom React components maintained by the developer.
- Use Inter for interface text, Poppins for headings, and Montserrat for brand labels.
- Login, password-reset, and future registration pages share the two-column `resources/js/Layouts/AuthLayout.tsx` shell; the company mark is a replaceable placeholder for now.
- Keep the current brand color centralized in a CSS custom property. A future settings area must let administrators configure the brand color and company logo; do not treat the current placeholder identity or color as final.
### Data, filtering, and state
- Use server-side pagination, filtering, and sorting for data-heavy tables. Do not download every database record to filter locally.
- Preserve query parameters so searches and filters can be bookmarked.
- Do not duplicate server-provided state unnecessarily in component state.
- Use Inertia's form and navigation features where appropriate.
### Accessibility
All reusable interactive components must consider keyboard operation, appropriate ARIA attributes, focus management, and clear visual states.
---
## 6. Security requirements
Security is integrated into every module from the beginning. The following are mandatory requirements.
- Enforce authorization on the server, not just in the UI.
- Follow the principle of least privilege.
- Use validation and safe database operations.
- Protect authenticated routes and sensitive administrative actions.
- Use secure authentication, password-reset, and session practices.
- Configure CSRF protection appropriately for Inertia and Laravel.
- Avoid exposing internal exceptions, database details, or stack traces to users in production.
- Never store credentials in source control.
- Use environment variables for secrets and environment-specific settings.
- Do not expose sensitive environment values to frontend code.
- Avoid unsafe HTML rendering.
- Validate uploads and other external input whenever applicable.
- Use safe file-storage conventions if file uploads are added later.
- Protect audit logs from unauthorized access and sensitive data leakage.
- Check third-party dependency compatibility and vulnerabilities.
- Add automated tests for unauthorized access and privilege-escalation scenarios.
- Configure secure production behavior separately from local development.
Do ****not**** claim the template is fully secure or certified merely because these measures are implemented. Identify additional review required for production deployment, and any future regulatory or compliance requirements.
### Session and authentication hardening (for the manual auth implementation)
- Regenerate the session ID on login via `$request->session()->regenerate()`. Laravel's `Auth::attempt()` does not do this automatically.
- Invalidate the session and regenerate the CSRF token on logout: `$request->session()->invalidate()` and `$request->session()->regenerateToken()`.
- Use Laravel's `Password` broker for password resets. Do not invent a token system.
- Always return the same success message from the forgot-password flow regardless of whether the email exists, to prevent enumeration.
- Rate limit login and password reset by email + IP (see section 4 and the confirmed values in section 10).
- Email verification uses signed URLs and a hash of the user's email.
- Sensitive actions use Laravel's `password.confirm` middleware.
---
## 7. Testing and code quality
### Testing framework
****PHPUnit**** — revised from the original spec's Pest. See section 10 for the decision record. Pest is not installed and will not be installed unless this decision changes.
### Test environment
When configured, `phpunit.xml` should set:
- `APP_ENV=testing`
- `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`
- `BCRYPT_ROUNDS=4`
- `CACHE_STORE=array`, `SESSION_DRIVER=array`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`
Tests never touch the development MySQL database. SQLite and MySQL diverge on JSON operations, full-text search, and some DDL semantics — if a future feature depends on MySQL-specific behavior, add a MySQL test connection at that time rather than pre-emptively.
### Required testing practices
For every completed module or feature, test:
- Expected successful behavior.
- Validation failures.
- Unauthorized access.
- Relevant edge cases.
- Database behavior and relationships.
- Regressions in existing functionality.
Examples that must be covered as features are built:
- Guests cannot access protected routes.
- Users without the required permission cannot perform an action.
- Users with the correct permission can perform the action.
- Invalid requests do not persist data.
- Role assignments behave correctly.
- Audit records are produced for configured events.
- Sensitive information does not leak through page props or audit logs.
### Standard commands
- Run tests: `php artisan test`
- Format PHP: `php vendor/bin/pint`
- Check formatting only: `php vendor/bin/pint --test`
Use the project’s verified scripts and commands; do not assume a script exists before checking `composer.json`.
### Frontend testing
Vitest + React Testing Library are in the target stack but ****not yet installed****. They will be introduced when the UI foundation is ready. Not before.
### Verification rule
A code edit is not proof that a feature works. A step is complete only after Codex applies the change, executes the appropriate checks when available, and reports actual results for the user to review.
### Truthfulness rule
Never claim a test has passed unless Codex actually ran it and observed successful output, or the user supplied trustworthy results. If something fails, stop and diagnose before proceeding.
---
## 8. Development roadmap
The phases below are the reference order. A technical dependency may justify reordering, but verification is never skipped and scope is never silently expanded.
### Phase 0 — Environment and requirements
- Verify OS and development environment.
- Verify PHP, Composer, Node.js, npm, database availability.
- Check compatibility requirements.
- Agree on initial setup approach and conventions.
### Phase 1 — Application foundation
- Create the Laravel 13 application.
- Establish Inertia 3 with React and TypeScript.
- Configure Tailwind CSS 4 and Flowbite styling.
- Confirm the initial application renders correctly.
- Run baseline checks.
### Phase 2 — Architecture and quality
- Establish directory structure and naming conventions.
- Configure tests and formatting.
- Establish frontend layout and component conventions.
- Document important architectural decisions.
### Phase 3 — Authentication
- Implement and verify authentication.
- Add password reset and email verification as appropriate.
- Configure route protection, rate limiting, and session behavior.
- Test authentication and unauthorized access.
### Phase 4 — Application shell and reusable UI
- Build the dashboard layout and navigation.
- Develop the first reusable UI components.
- Establish consistent form, table, notification, and dialog behavior.
- Test important interactive components.
### Phase 5 — User management
- Create the user management pages and backend operations.
- Implement validation and authorization.
- Add search, filtering, and server-side pagination.
- Test the full feature.
### Phase 6 — Roles and permissions
- Integrate `spatie/laravel-permission`.
- Create the permission seeder and naming conventions.
- Implement role and permission management.
- Integrate user-role assignment.
- Test positive and negative authorization cases.
### Phase 7 — Profile management
- Implement profile updates and password changes.
- Add suitable protections for sensitive changes.
- Test validation and account security.
### Phase 8 — Activity auditing
- Integrate `spatie/laravel-activitylog`.
- Configure important events and audit properties.
- Implement a protected audit log viewer.
- Test auditing behavior and information protection.
### Phase 9 — Final quality and documentation
- Run the relevant test suites.
- Review security-sensitive functionality.
- Review dependencies and configuration.
- Document local setup, environment variables, migrations, seeders, tests, and conventions.
- Prepare a clean, reusable Git repository.
---
### Phase 10 — Expanded reusable foundation
- Structured user names and centralized name formatting.
- Optional MFA and provider-extensible Google OAuth, with `.env.example` and setup documentation.
- Shared auditing, table/query/pagination, column selection, export and secure upload capabilities.
- Reusable trash/archive patterns, with permission and security tests.
- Implement as individually approved, tested steps; reorder relative to earlier phases when dependencies require it.

### Phase 11 — Optional demonstration modules
- Contact Directory (traditional forms).
- Article & Blog Manager (rich content).
- Community Feed (posts/comments/likes).
- Verify independent `.env` toggles, route isolation, shared services and example-module removal safety.
- Complete security, accessibility, regression, and export/upload tests.

## 9. Mandatory Codex collaboration workflow

Codex is the **implementation agent** in the user's dedicated ChatGPT Work project. The user is the **reviewer and decision-maker**. Codex may inspect the repository, run safe commands, create and modify files, and run tests using the tools available in that environment. Do not instruct the user to copy/paste code unless tool access is unavailable.

### Strict one-step approval gate

1. At the start of a session, read `AGENTS.md`, this guide, and `PROJECT_PROGRESS.md`; inspect only files relevant to the next step.
2. State the **one small, testable step** to be performed, then implement **only that step**. Phase 0 starts with environment/repository inspection; do not scaffold the entire application at once.
3. Make the smallest coherent changes, without unrelated refactoring or speculative features.
4. Run targeted verification (tests, static checks, or manual browser instructions when tools cannot verify UI). Record the exact command and outcome.
5. Update `PROJECT_PROGRESS.md` with the observed status, touched files, blockers, and next proposed step.
6. Respond briefly with what changed, verification results, and the question **“Approve the next step?”**
7. **Stop.** Do not begin another step until the user explicitly approves. If verification fails, fix only the current step and retest; request guidance when blocked.

### Command safety

Do not run destructive operations (database resets, dropping tables, deleting user data, force pushes, destructive cleanups) without explicit user approval. Never modify production environments or secrets. Prefer non-destructive checks and targeted changes. If the environment does not grant repository access, say so and request access rather than claiming changes were made.

### Token and context efficiency

- Treat the three Markdown files as canonical; avoid restating their contents in each reply.
- Search filenames/symbols first; read only relevant files or ranges. Avoid full-repository dumps and repeated large outputs.
- Do not paste entire source files or long diffs into chat; edit files directly and summarize paths and changes.
- Keep user-facing responses short: **Step / Changes / Verification / Next approval**. Expand only when asked or when a risk needs explanation.
- Prefer targeted tests; run full suites at milestones or when risk warrants.
- Reuse established conventions, components, and installed dependencies. Avoid unnecessary packages, generated boilerplate, repeated searches, and speculative planning.
- Batch independent *checks within the same approved step* when efficient, but never batch multiple implementation steps.
- Keep `PROJECT_PROGRESS.md` compact: current phase, completed/verified milestones, exact evidence, blockers, and one next step. Archive verbose logs outside the checkpoint if needed.
- Never trade away correctness, security, or necessary verification merely to save tokens.

## 10. Architectural decisions and constraints
### Confirmed decisions (must not be silently changed)
1. ****Monolithic Laravel + Inertia + React.**** No separate frontend application, no REST API for ordinary functionality.
2. ****No starter kit.**** Laravel Breeze, Jetstream, and Laravel UI are not installed.
3. ****Manual authentication.**** Laravel Fortify is ****not**** used. Authentication (login, logout, password reset, email verification, password confirmation, rate limiting) is implemented directly using Laravel's built-in `Auth`, `Password` broker, and middleware. This was chosen to keep the template less package-dependent and easier to customize later.
4. ****Public registration is unavailable and disabled by default.**** If implemented, it must be controlled by `config('auth.registration.enabled')` (env: `REGISTRATION_ENABLED`) and its routes must not be registered while disabled. Administrators currently create accounts through user management or `users:create-admin`.
5. ****PHPUnit instead of Pest.**** The original spec named Pest. PHPUnit is the selected test framework; verify its compatible version during setup. Do not install Pest unless the user changes this decision.
6. ****No repository pattern.**** Eloquent is the data-access layer. Services are introduced only when an operation spans multiple models, requires a transaction, or contains business rules worth isolating.
7. ****No additional state-management library.**** No Redux, no Zustand, no Axios unless a genuine requirement appears.
8. ****Flowbite CSS classes only.**** Flowbite React components are prohibited.
9. ****`@/*` alias**** mapped in both `tsconfig.json` and `vite.config.js` to `resources/js/*`.
10. ****TypeScript 6 (native compiler).**** Selected with user approval because it is the current official release; `baseUrl` is not used because it is deprecated, and `paths` are resolved relative to `tsconfig.json`.
11. ****SQLite in-memory for tests.**** MySQL-specific test connection only if a feature later requires it.
12. ****Shared Inertia props are minimal.**** Target shape: `appName` and lazy `auth.user` with `id`, `name`, `email` only; implement and verify during setup.
13. ****Rate limiters are named and centralized**** in `AppServiceProvider::boot()`, keyed by email + IP.
14. ****If public registration is implemented, its toggle belongs in `config/auth.php`****, not a separate config file. The toggle is not currently implemented.
15. ****Confirmed defaults:**** login rate limit 5/min, password reset rate limit 5/min, password reset token expiry 60 minutes, session lifetime 120 minutes.
16. ****Shared React page layouts live in `resources/js/Layouts/`**** and are composed by pages in `resources/js/Pages/`.
### Prohibited approaches
- Repository interfaces and implementations for every model.
- A generic CRUD framework or arbitrary base classes.
- Reintroducing Breeze, Jetstream, or Laravel UI.
- Reintroducing Pest without a decision to reverse decision #5.
- Adding Fortify without a decision to reverse decision #3.
- Sending full Eloquent models as shared Inertia props.
- Storing credentials in source control or documenting default passwords.
- Logging passwords, hashes, tokens, session secrets, or API keys.
### Unresolved or unverified
- `spatie/laravel-permission` 8.3.0 is installed and locked; its official package metadata declares MIT licensing and Laravel 12/13 compatibility.
- `spatie/laravel-activitylog` 5.1.1 is verified as MIT-licensed and compatible with PHP 8.4 and Laravel 13. It is installed for Phase 8.
- Whether Flowbite's Tailwind 4 plugin will be installed, or whether only Flowbite's CSS utility patterns will be replicated manually. To be decided before Phase 4.
- Whether Vitest + React Testing Library will be introduced at all, and in which phase. To be decided when the UI foundation is ready (Phase 4 at the earliest).
- Authentication successes, failures, logout, and password resets are recorded in the audit log without credential, token, or IP metadata.
---
### Expanded-scope decisions
- New projects start with **all demo modules disabled**; demos are optional reference implementations.
- `All` means **batched infinite loading**, never a single unbounded query.
- Trash uses Eloquent SoftDeletes; archiving is a distinct state.
- MFA and social login are independently configurable, and OAuth is designed for future providers.
- Free, commercially usable open-source packages only; verify licenses and compatibility before adding them.
- No code, installation, test result, or external account is assumed to exist in this fresh project.

## 11. Rules for maintaining project documentation
### Three documents, distinct purposes
****`PROJECT_GUIDE.md`**** (this file) — durable requirements, architecture, standards, and the roadmap. Update only when an important requirement or architectural decision changes, or when the roadmap is adjusted.
****`PROJECT_PROGRESS.md`**** — current development status, verified test results, blockers, and the exact next step. Update whenever implementation status changes. It must distinguish completed edits from verification and cite actual commands/results without pasting long logs.
### Update rules
- Never mark work as verified merely because code was generated or edited.
- When a decision in this guide changes, record the reason and identify affected files or tests before proceeding.
- When progress is reported, distinguish between **implemented**, **verified**, and **blocked**.
- Do not duplicate application source code in either document.
- Keep this guide detailed enough that a fresh conversation can continue without the original chat history.
- Keep `PROJECT_PROGRESS.md` concise and factual.
### Handoff rule
A new Codex session should be able to:
1. Read `AGENTS.md` for operational rules.
2. Read `PROJECT_GUIDE.md` for requirements and constraints.
3. Read `PROJECT_PROGRESS.md` for current state and next step.
4. Resume without asking the user to re-explain the project.
If either document is missing information needed to resume, update the document before continuing implementation.
---
**End of PROJECT_GUIDE.md**
