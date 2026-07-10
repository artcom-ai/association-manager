# ADR 023: Admin-managed field definitions

## Status

Accepted

## Context

Field *definitions* (`Core\Fields\FieldDefinition` - what fields exist, their type/label/visibility) had only ever been registered in PHP code against `FieldRegistry`, by a Module or a per-client implementation calling `register()` at boot time. Field *values* (`wp_am_field_values`, migration 006) were already admin-editable per-member via `EditMemberPage`, but the set of fields itself required a code change and a deploy to alter. Once ELESYTH had real imported data and a real staging feedback loop, this stopped being acceptable - the explicit ask was "how does the admin create the fields" followed by "I want you to create it, I find it important," plus a request to recreate field definitions directly from the real MemberPress data already flowing through the Importer built earlier this session.

**Classification: Core**, not Module or Implementation. Field definitions are Core's own concept (`Core\Fields\FieldDefinition`/`FieldRegistry` already lived there); making them admin-manageable is infrastructure any association benefits from, the same way `Core\Fields\Admin\FieldRenderer` already was. `Modules\Importers\FieldMappingRegistry` (a different, Importers-owned concept - which *source* key maps to which field) got the identical treatment for the same reason, staying in Importers rather than moving to Core.

## Decision

**Two new tables, one per existing in-memory registry**: `wp_am_field_definitions` (migration 018) and `wp_am_field_mappings` (migration 019) - both purely additive, matching this project's standing migration discipline. Neither registry (`FieldRegistry`, `FieldMappingRegistry`) changed shape or its consumers' code at all - `FieldValueService`, `DirectoryService`, `PortalService`, `MemberImportService` still just call `FieldRegistry::forEntityType()`/`FieldMappingRegistry::forSource()` exactly as before. The only new thing is *where the registries get populated from*.

**Boot-time loading is deferred to `init` at priority 5** (`CoreServiceProvider`/`ImportersModule`), not run synchronously during `Kernel::boot()`. `boot()` runs on every request starting at `plugins_loaded`, before `admin_init` has had a chance to apply a just-added migration on a fresh activation - querying the new tables that early risks hitting them before they exist. Priority 5 keeps this ahead of `Kernel::registerHooks()`'s default-priority `association_manager_loaded` (used by implementations), though in the end state that ordering no longer matters much since ELESYTH stopped registering fields/mappings in code at all (see below).

**Upsert-by-key repositories, hand-written find-then-insert-or-update** (`FieldDefinitionRepository`, `FieldMappingRepository`) - the same pattern `NotificationTemplateRepository`/`CertificateTemplateRepository` already established, not a raw `INSERT ... ON DUPLICATE KEY UPDATE`. Raw prepared `INSERT` doesn't correctly express SQL `NULL` for nullable numeric placeholders (`%d`/`%f` coerce a PHP `null` to `0`), while `$wpdb->insert()`/`update()` handle `null` in the data array correctly - both repositories have several nullable numeric columns (`min_length`, `max_value`, etc.), so this wasn't optional.

**A new admin page, `Core\Fields\Admin\FieldDefinitionsPage`**, list-then-edit-one (same shape as `CertificateTemplatesPage`/`NotificationTemplatesPage`): create, edit, delete. Deliberately fixed to the `"member"` entity type for this pass - it's the only entity type anything in this codebase actually uses (Directory/Portal/Importers all hardcode it); parameterizing the page for other entity types later is trivial, not a redesign, so it wasn't built speculatively now. A field's `key` is immutable once created (shown read-only on the edit form) - renaming it would silently orphan every already-stored value under the old key, a real data-loss footgun not worth the convenience.

**Deleting a field cascades to its stored values** (`FieldValueRepositoryInterface::deleteForField()`, new) - an admin explicitly confirms this in the UI (a JS `confirm()` on the delete button) rather than leaving orphaned `wp_am_field_values` rows under a key nothing references anymore.

**"Recreate fields from MemberPress" reuses the existing dry-run wholesale**, adding no new source-scanning logic: `FieldDiscoveryService::createFieldsFromUnmappedKeys()` is handed `ImportSummary::allUnmappedKeys()` - the exact same list the Import page's report already computes and shows. For each key with no existing mapping yet, it creates a plain-text, admin-only-visibility `FieldDefinition` plus a `FieldMapping`, using a best-effort humanized label (strip a `mepr-`/`mepr_` prefix, replace remaining separators with spaces, title-case). This is explicitly a **starting point**, not a finished configuration - the admin is expected to review and correct labels/types/visibility via the Member Fields page afterward, especially since a source key that's itself a transliteration (e.g. `eidikotita`) doesn't become correct Greek by title-casing it. Source-key hyphens are converted to underscores when deriving the field key (`mepr-tilefono-epikoinonias` → `mepr_tilefono_epikoinonias`), not stripped - stripping would have collapsed real ELESYTH meta keys into unreadable runs like `meprtilefonoepikoinonias`.

**Seeding semantics split deliberately in two, by whether the seeded thing is meant to be admin-edited afterward.** `CertificateTemplateSeeder`/`NotificationTemplateSeeder` (existing, unchanged) always overwrite on every `admin_init`, so ELESYTH's canonical copy stays in sync with future plugin releases - by design, an admin edit to those templates would already be repeatedly reset today. The new `MemberFieldSeeder`/`MemberPressFieldMappingSeeder` do the opposite: create-if-missing only, never overwrite - since the entire point of this ADR is that fields become admin-editable, a seeder that reset them on every page load would silently undo the feature. ELESYTH's `Plugin::boot()` no longer calls `FieldRegistry::register()`/`FieldMappingRegistry::register()` directly at all (the old `association_manager_loaded` hooks were removed) - `MemberFieldDefinitions::all()`/`MemberPressFieldMappings::all()` are now purely seed data, consumed once per field/mapping's lifetime, not re-registered into memory on every request.

## Consequences

Positive:

- An admin can now add, edit, or remove a member field entirely from wp-admin - no code change, no deploy, closing the exact gap raised.
- "Recreate fields from MemberPress" turns the earlier dry-run's long, previously only-informational "unmapped source fields" list into real, immediately-usable fields with one click - directly closing the loop from the real 531-member import.
- Zero changes to any existing consumer of `FieldRegistry`/`FieldMappingRegistry` - the entire admin-manageability layer sits behind the same two registries every other module already depended on.
- 21 new tests (repository CRUD/upsert round-trips for both new tables, `FieldValueRepository::deleteForField()`, `FieldDiscoveryService`'s key-sanitization/humanization/skip-if-already-mapped behavior) - 237 total, all passing.
- A real `FakeWpdb::update()` bug surfaced and got fixed in the process: it only ever matched `WHERE id = ...`, silently crashing on any repository (like these two new ones) using a composite WHERE clause - a genuine gap in the shared test double, not a workaround.

Negative:

- Auto-generated field labels from "recreate from MemberPress" are not real translations - for ELESYTH specifically, most raw MemberPress meta keys are themselves Greek transliterations (`eidikotita`, `tilefono`), and title-casing a transliteration doesn't produce the correct Greek word. Every auto-created field needs a human pass before being trusted as a real "Ειδικότητα"-quality label.
- No reordering/drag-drop UI for `display_order` - a plain number input on the edit form. Acceptable for the handful of fields expected in practice; would need real UI work if a future association wants dozens of fields.
- The Member Fields page's "Options" input (for `TYPE_SELECT`) is a plain `value|Label` per-line textarea, not a proper repeater UI - matches this codebase's existing preference for minimal admin surfaces (e.g. Certificates' raw-member-ID input) over polished-but-slower-to-build widgets.
- Still only supports the `"member"` entity type through this admin page - a real constraint if a future Module wants its own admin-manageable fields for a different entity type, though extending it is expected to be a small, mechanical change (parameterize the page, don't redesign the storage).
