# Member fields UX + styling: width, read-only-for-member, repeaters, visual polish

## Context

Follow-up to the field-definition/Portal work done earlier this session (ADR-011, ADR-023 and its addenda). Three concrete gaps in the custom-fields system were raised: no per-field layout control on the account page, no way to show an admin-set field to a member without also letting them edit it, and no support for naturally-repeating data (multiple phone numbers/addresses). Separately, a wider discussion (ACF/FacetWP/Elementor Pro evaluation) concluded: keep the custom `Core\Fields` system rather than replatforming onto ACF (Member isn't a WP post/user - ADR-003 - and this is meant to be a sellable standalone Core platform, not one bolted onto a paid third-party dependency), but the *visual* gap that discussion surfaced (member-facing pages are literally unstyled, admin screens are bare wp-admin) is real and worth closing now, in the same pass as the functional field work since both touch the same templates.

**Classification: Core** (`Core\Fields`) for width/read-only/repeatable - same reasoning as every prior field-definition addition (ADR-011, ADR-023): these are generic capabilities any association benefits from, not ELESYTH-specific. Styling is **Core** too (the stylesheets style generic `am-*` classes this plugin's own templates emit, nothing ELESYTH-specific).

**Explicitly decided against, not part of this spec:** ACF/FacetWP as a replacement for the Core\Fields data layer or the Directory module (data-model mismatch - Member/Event/Payment are custom-table entities, not WP posts/users; licensing complication for a resellable plugin). Elementor Pro for front-end *page design* around this plugin's shortcodes is a live option but a separate, later decision - not implemented here.

## 1. Field width (Portal only)

- `FieldDefinition` gains `int $widthPercent = 100` (1-100, free-form, admin's own responsibility to make rows add up sensibly - no "must sum to 100" validation).
- Applies **only** to the Portal ("My Account"). The admin Edit Member Fields page keeps its existing single-column wp-admin `form-table` untouched.
- Mobile: below a phone-width breakpoint, every field forces `flex-basis: 100%` via CSS, unconditionally. No per-field mobile override.
- `FieldRenderer` gains a new public method, `renderPortalField()`, that outputs a self-contained `<div class="am-field" style="flex-basis:{width}%">` block (label + input, no `<tr>/<td>`) for the Portal's grid layout, reusing the same private input-generation logic the existing table-row `render()` method already has. `render()` itself (used by `member-edit.php` and `field-definition-edit.php`'s own admin contexts) is unchanged.
- `templates/public/portal.php`'s editable-profile section changes from `<table class="form-table">` to a flex-wrap container (`<div class="am-portal-fields-grid">`) of these blocks.
- Admin UI: new "Width %" number input (min 1, max 100, default 100) on the Member Fields edit form.

## 2. Read-only-for-member fields

- `FieldDefinition` gains `bool $memberReadOnly = false`.
- New checkbox "Member can view but not edit" on the admin Member Fields form, shown (via the existing type-conditional JS pattern, extended to also key off Visibility) only when Visibility = Members only - matches Visibility=Admin already meaning "not shown to the member at all," so this checkbox would be meaningless there.
- Applies **always**, independent of member status - a `memberReadOnly` field renders as plain text on the Portal from the member's very first view through onboarding and after becoming active. It is never an `<input>` for the member, in any state.
- **Server-side enforcement, not just hidden markup.** `FieldValueService::save()` gains a `bool $bypassApproval = false` parameter (currently only `saveWithUploads()` has this flag; `save()` did not need it before this spec). When not bypassing, any submitted key matching a `memberReadOnly` field is silently dropped before validation/persistence - a member can never change it even via a hand-crafted POST. `saveWithUploads()` passes its own `$bypassApproval` value through to its internal `save()` call (single trust flag covers both the existing file-pending-approval bypass and this new read-only enforcement - there is no scenario where a caller would want to bypass one but not the other, so this is deliberately one flag, not two). `MemberImportService`'s existing `save()` call passes `bypassApproval: true` (import is an admin-trust operation). Member-originated calls (`PortalService::submitForApproval()`, `updateFileFields()`) keep the default `false`.
- The admin's own Edit Member Fields page is unaffected - admin can always edit every field regardless of `memberReadOnly`, since that flag only constrains the member-facing Portal.

## 3. Repeater fields

- `FieldDefinition` gains `bool $repeatable = false`, `?int $maxEntries = null` (blank = unlimited).
- Allowed types, enforced in the admin UI's type-conditional JS (not a hard model-level constraint): Text, Textarea, Number, Date, Location. Excluded: File (separate upload/approval system already built), Checkbox, Select (no natural "repeat" meaning).
- **Storage**: `field_values.value` (already `LONGTEXT`) stores a JSON-encoded array for repeatable fields (e.g. `["6971234567","6947654321"]`) instead of a scalar string - no schema change to `field_values`. `FieldValueService` gains `encodeRepeatable(array $values): string` (JSON-encodes, drops empty entries) and `decodeRepeatable(?string $raw): array` (JSON-decodes; if decode fails and `$raw` is non-empty, falls back to `[$raw]` so a field flipped to repeatable *after* already holding a plain scalar value degrades gracefully instead of crashing) - same class that already owns the file-upload reshaping logic, not a new class, for the same reason that logic lives there.
- **Validation** (`FieldValidator`): for a repeatable field, each submitted entry is validated individually against the field's existing type rules (minLength/maxLength/min/max value/date format/location format, as applicable). `required` means "at least one non-empty entry" (not every row). Submitting more entries than `maxEntries` (when set) is a validation error, not silent truncation.
- **Rendering**: both the admin table-row mode and the new Portal grid mode render N sub-inputs (one per decoded entry, at least one empty row shown when there are zero saved entries) plus Add-another/Remove-row controls. New shared JS (`assets/js/repeatable-field.js`) generalizes the pattern already proven for the field-definition Options editor (`assets/js/field-definition-editor.js`), driven by data attributes (field key, max entries) rather than hardcoded to one textarea, since this needs to run in multiple rendering contexts with arbitrary field keys.
- **Other consumers needing flattened (not decoded-to-array) display**: `MemberCsvExporter` (CSV cell), `MembersListTable`'s `show_in_list` columns, and `DirectoryService::buildEntry()` (`src/Modules/Directory/Services/DirectoryService.php`) all join multiple values with `; ` for their single-cell/single-line contexts.
- **Import scope, explicitly deferred**: an importer-mapped value populates a repeatable field's first entry only (`[$importedValue]`). True multi-value import mapping is out of scope.

## 4. Front-end stylesheet (Register / Login / Portal)

- New `assets/css/public.css`, enqueued on the front end (enqueue call added to `PortalModule::boot()`, the module that owns all three shortcodes), scoped under the `am-` class prefix these templates already emit.
- Covers typography, spacing, form control styling, and notice/error box styling (`.am-register-errors`, `.am-login-errors`, `.am-portal-errors` exist as classes today with zero CSS behind them).
- **Real bug fix, not just polish**: Register/Login/Portal buttons use `class="button button-primary"` - WordPress's own wp-admin button classes, whose CSS only loads inside wp-admin. On the public site these currently render as unstyled browser defaults. `public.css` defines real styling for those same classes on the front end; no template changes needed for this part.
- The Portal's flex-grid layout (section 1) is delivered in the same pass, since it's the same template.

## 5. Admin CSS polish (existing wp-admin screens)

- New `assets/css/admin.css`, enqueued only on this plugin's own admin pages (conditional enqueue, same pattern as the existing `members-quick-edit.js`/`field-definition-editor.js` hook-suffix checks), wired in `CoreServiceProvider::boot()` or wherever the plugin's admin-wide enqueue already lives.
- **Polish only, no page restructuring**: colored status badges for Member status (candidate/pending/active/suspended/archived) and Certificate status (draft/issued/revoked) - requires a small template change wrapping existing status text in `<span class="am-badge am-badge-{status}">`, not new page structure. Consistent spacing/borders around existing sections (Identity, Member Portal access, Fields on Edit Member; the tables on Members/Certificates/Field Definitions).
- WP's own `.notice` boxes are already styled correctly inside wp-admin - untouched.

## Data model summary

Three new additive migrations, same full-schema `dbDelta()` pattern as every prior migration in this repo:
- `025_add_width_percent_to_field_definitions_table` - `width_percent` INT NOT NULL DEFAULT 100.
- `026_add_member_read_only_to_field_definitions_table` - `member_read_only` TINYINT(1) NOT NULL DEFAULT 0.
- `027_add_repeatable_to_field_definitions_table` - `repeatable` TINYINT(1) NOT NULL DEFAULT 0, `max_entries` INT NULL.

`tests/Unit/Database/MigrationLoaderTest.php` gets the three new ids appended, same as every prior migration addition this session.

## Testing approach

Same split this whole session has followed (ADR-015): `FieldDefinition`/`FieldDefinitionRepository` (new column round-trips), `FieldValidator` (repeatable per-entry validation, required-means-at-least-one, maxEntries enforcement), `FieldValueService` (encode/decode round-trip, `bypassApproval` enforcement on `memberReadOnly` fields for both `save()` and `saveWithUploads()`) all get real PHPUnit coverage. Admin-post handlers, template rendering, CSS, and JS remain WP-glue verified on staging, not directly unit tested - consistent with every prior admin-post/template addition this session.

## Explicitly out of scope

- Repeaters for File/Checkbox/Select field types.
- Stricter per-row repeater validation modes (e.g. "every visible row required").
- Multi-value import mapping.
- A full custom admin SPA (discussed as a future option, not this pass).
- Adopting ACF, FacetWP, or Elementor Pro as an implementation dependency of this plugin - all explicitly declined for the Core data/Directory layer; Elementor-for-page-design remains a separate, later, non-architectural decision.
- Per-field mobile-specific width override.
- Width/layout changes to the admin Edit Member Fields page - Portal only.
