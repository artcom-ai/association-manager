# ADR 021: Implementation layer = a separate WordPress plugin

## Status

Accepted

## Context

`docs/adr/001-database-migrations.md`'s parent principle (`ADR-001-Core.md` in the planning repo) states that Core must never depend on, or hardcode logic for, a specific implementation/client - but no ADR had ever decided the *physical mechanism* by which an Implementation (e.g. ELESYTH, the confirmed first pilot) actually separates from Core. Three options existed: (a) a separate WordPress plugin depending on Core, (b) an mu-plugin, (c) a namespace/folder inside this same plugin, loaded conditionally. This was flagged explicitly as a decision to make deliberately, not default into, before any ELESYTH-specific work began.

**Classification: this ADR is about the Implementation layer's shape itself**, not any one implementation's content. It governs `association-manager-elesyth` (new, sibling repo at `C:\Projects\association-manager-elesyth`) and any future Implementation.

## Decision

**A per-client Implementation is a separate WordPress plugin**, activated alongside Core, not a namespace inside Core's own codebase. Chosen (via explicit user decision, AskUserQuestion) over the in-repo-namespace option specifically for physical separation: Core literally cannot `use` or reference ELESYTH's classes (they're not even on the same autoload path unless ELESYTH is installed), which enforces ADR-001's principle at the filesystem/deployment level rather than relying on PHPCS discipline alone. It also gives ELESYTH independent versioning and deployment from Core - a real requirement once Core ships updates to associations that aren't ELESYTH.

**Core exposes exactly the extension points an Implementation needs, and no more; nothing new was added to Core for this pass.** Auditing what ELESYTH actually needed against what already existed:

- **Member field visibility** - `Core\Fields\FieldRegistry` already existed, already documented as "a per-client implementation fetches this instance from the container and calls register()." Nothing to add.
- **Certificate/notification content** - both `wp_am_certificate_templates` and `wp_am_notification_templates` were already DB-backed and admin-editable, with upsert-by-key `save()` methods on their repository interfaces. An Implementation seeding its own rows through the same interface Core's own admin UI writes to needed no new Core surface.
- **Document categories** - `Document::category` is a plain string (ADR-018, deliberately no fixed taxonomy table). "Supporting" ELESYTH categories needed nothing from Core; it's a convention enforced client-side via a progressive-enhancement `<datalist>`, not a schema or validation change.
- **The Portal could not render *any* custom field**, which would have made "member field visibility for portal" impossible to satisfy regardless of mechanism. This was the one genuine Module-layer gap - closed generically in `Modules\Portal\Services\PortalService::customFieldsFor()` (see ADR-020's addendum), not with ELESYTH-specific code; any Implementation registering fields benefits automatically.

**No generic label/rebranding filter was added.** "ELESYTH labels" and "Greek admin/member-facing text where implementation-specific" resolve to: text ELESYTH itself supplies as data (field labels via `FieldDefinition->label`, certificate/notification content via the seeders above) is naturally already in Greek, because ELESYTH authors it directly - no translation layer needed for content it owns outright. Translating Core's own generic chrome (Portal section headings, admin page labels, etc.) into Greek would be a Core i18n concern (a `.mo` file for the existing `association-manager` textdomain, benefiting *any* Greek-speaking association, not an ELESYTH-specific one) - out of scope here, and not something this pass's requirements asked for once "implementation-specific" is read literally.

**How the ELESYTH plugin reaches Core without a hard dependency at load time**: `AssociationManager\Core\Plugin::boot(): Kernel` is idempotent and already returns the booted `Kernel` (`container()` from there). ELESYTH's bootstrap file only calls `add_action(...)` at its own top level - every callback that actually touches Core (`FieldRegistry` registration, template seeding) is deferred to `association_manager_loaded` (fired on WP's `init`, by which point every plugin has finished loading, regardless of Core-vs-ELESYTH file load order) or `admin_init`/`admin_footer` (same guarantee). A `class_exists(CorePlugin::class)` guard on `plugins_loaded` shows an admin notice if Core isn't active, instead of a fatal error.

**Template/field seeding runs on `admin_init`, idempotently (upsert), mirroring Core's own `Migrator::installPending()` pattern** (also hooked on `admin_init`, per ADR-005/012) - rather than `register_activation_hook()`. This means ELESYTH's Greek content stays in sync automatically if a later ELESYTH plugin release changes the seeded copy, with no separate upgrade routine to write.

**Repo structure** (`C:\Projects\association-manager-elesyth`, its own git repo):
```
association-manager-elesyth.php   (bootstrap - defines constants, requires vendor/autoload.php, calls Plugin::boot())
composer.json                     (PSR-4: AssociationManagerElesyth\ -> src/, no runtime deps)
src/
  Plugin.php                       (registers the four hooks; nothing Core-touching runs at top level)
  Fields/MemberFieldDefinitions.php        (3 FieldDefinitions: Ειδικότητα/Φορέας Απασχόλησης at "private", ΑΦΜ at "admin")
  Documents/DocumentCategories.php         (5 canonical Greek category strings + admin_footer datalist)
  Certificates/CertificateTemplateSeeder.php   (Greek "membership" certificate HTML template)
  Notifications/NotificationTemplateSeeder.php (Greek subject/body for all 7 event keys Core already fires)
```
No PHPUnit/PHPStan/PHPCS toolchain was set up for this small plugin in this pass (see Consequences) - every file was verified with `php -l` and a manual autoload smoke test instead.

## Consequences

Positive:

- Core needed zero code changes to support ELESYTH's labels, certificate/notification content, or document categories - every one of those was already a first-class extension point (`FieldRegistry`, DB-backed admin-editable templates, a free-text category field). This is strong evidence ADR-001/002's Core/Module design was already correctly shaped for implementations, not just modules.
- The one real gap found (Portal rendering no custom fields) was fixed generically in the Portal module, benefiting every future Implementation, not patched around from ELESYTH's side.
- Physical separation (a distinct plugin, distinct repo) means a future second implementation (a different association) starts from a clean slate with no risk of ELESYTH-specific code ever having leaked into Core - there's no file it could have leaked into.

Negative:

- The ELESYTH plugin has no automated test suite, PHPStan, or PHPCS run in this pass - a real gap relative to Core's own quality bar (`composer test`/`stan`/`cs`), accepted for a first pilot-scale companion plugin; worth revisiting once ELESYTH's own codebase grows past "a handful of seeders and field definitions."
- `admin_init`-based idempotent reseeding means every WP-admin page load on a site running the ELESYTH plugin performs a handful of upsert queries - harmless at this scale (mirrors `Migrator::installPending()`'s existing cost) but worth reconsidering if either plugin's admin surface grows significantly.
- Relying on `association_manager_loaded`/`admin_init` timing (rather than a documented, versioned "Core is ready" contract) means any future change to when Core fires that hook is a breaking change for every Implementation plugin - there's no formal compatibility guarantee yet, only current behavior.
- No real WordPress multi-plugin staging verification has been done (both plugins loading together, WP's actual plugin-load order, a live `wp_mail()`/DB) - same category of untested-here gap as every other WP-glue surface in this codebase; a real staging pass with both plugins active is still needed before calling the ELESYTH pilot evaluation-ready.
