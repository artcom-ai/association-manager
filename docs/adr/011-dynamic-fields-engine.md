# ADR 011: Dynamic Fields Engine

## Status

Accepted

## Context

The product must support per-implementation custom fields with zero Core/module code changes: an ELESYTH install needs `Αριθμό Μητρώου, Ειδικότητα, Εκπαίδευση, Ψυχοθεραπευτική προσέγγιση`; a sports club needs something else entirely. Every field on `Member` so far (`member_number, status, membership_type, joined_at, expires_at, approved_at`) is a hardcoded column — there was no extension point at all for implementation-specific data.

## Decision

**The engine lives in Core** (`src/Core/Fields/`), not inside Members, and is entity-agnostic from day one:

- `FieldDefinition` — plain value object (`key, label, type, required, options, minLength/maxLength, minValue/maxValue, helpText, order`). Zero WordPress dependency, same discipline as `Member`/`Payment`/`Event`.
- `FieldRegistry` — `register(string $entityType, FieldDefinition)`, `forEntityType(string $entityType)`, `get(string $entityType, string $key)`. Registered once in `CoreServiceProvider` (Core infrastructure, like `AdminMenu`/`Pagination` - not a `ModuleManager` business module). This is the **third** occurrence of the registry pattern in this codebase (`AdminMenu` in ADR-002, `MemberStatusRegistry` in ADR-010, now this) - worth naming explicitly as this project's established idiom for "Core hosts an extension point, anyone with container access populates it, Core never needs to know what got registered."
- Members registers **zero** fields itself. The engine is proven in this sprint's smoke test by registering example fields directly against `FieldRegistry` (text/select/checkbox/file) - no permanent "Elesyth implementation" module ships yet. That's the natural next artifact once a real client implementation is designed, not part of this sprint.

**Storage: a single EAV table**, `wp_am_field_values` (`entity_type, entity_id, field_key, value LONGTEXT`, unique on the first three). Chosen over a JSON column on each entity table because: no schema migration needed per new field (the whole point), and it generalizes across entity types without each module needing its own field-values table. Trade-off accepted: filtering/sorting members by a custom field's value requires a join or a follow-up denormalization, not a plain `WHERE` on the main table - acceptable, since Sprint 10 doesn't need custom-field search yet (Directory's `search()` from ADR-010 only conditions on `Member`'s own hardcoded columns).

**Field types**: `text, textarea, number, date, select, checkbox, file`. `file` stores a WordPress attachment ID (int, as a string) - the actual upload goes through core `media_handle_upload()` (`FieldValueService::handleFileUpload()`), so all mime/size/security validation is WordPress's own battle-tested code, not something built here.

**Validation is a pure, separate concern** (`Services\FieldValidator`): one field, one value, no I/O - trivially testable, matches the domain-purity discipline used for `Member`/`Payment`/`Event` transitions. `FieldValueService` is the orchestration layer: `validate()` (partial - only checks keys actually present in the submission), `save()` (re-validates, throws `FieldValidationException` on failure, else persists), `valuesFor()` (read), `handleFileUpload()`.

**New HTTP semantics**: `FieldValidationException` maps to **422 Unprocessable Entity** in `MembersController`, distinct from the existing `\RuntimeException` -> 404 / `\LogicException` -> 409 pattern (ADR-006/ADR-010) - "well-formed request, invalid data" is a genuinely different failure mode than "not found" or "wrong state," and 422 is the conventional REST status for it.

**Members integration**: `POST /members` validates `custom_fields` *before* creating the member (422 means nothing was written); new `POST /members/{id}/fields` for updating them afterward; `MembersController::toArray()` now includes `custom_fields`. Admin-side, a new `EditMemberPage` renders whatever fields are registered via a shared `FieldRenderer` (one form-table row per field, type-aware input) and posts to a new `admin_post_association_manager_save_member_fields` handler. This page is registered through the normal `AdminMenu` mechanism (so capability checks and routing work identically to every other admin page) but then removed from the visible nav via `remove_submenu_page()` on a later-priority `admin_menu` hook - it's only reachable via the "Edit fields" link on the members list, since there's no sensible standalone entry point for "edit fields" without first picking a member.

**Scope boundary**: wp-admin forms only. No public/member-facing dynamic form - there is no member-facing account area in the product at all yet (a separate, larger initiative flagged earlier in this project), so a public form for editing one's own custom fields has nothing to attach to yet.

## Consequences

Positive:

- A future implementation module adds fields with zero Core/Members changes - exactly the stated goal - by fetching `FieldRegistry` from the container and calling `register()`.
- Reusable beyond Members: Events/Committees can register their own fields against `entity_type = 'event'`/`'committee'` against the exact same table/service, no new engine needed.
- Validation and rendering are both driven purely by `FieldDefinition` - adding a new field never requires new PHP beyond the `register()` call itself.

Negative:

- EAV storage means no DB-level type safety or indexing on individual field values - a "search members by custom field" feature would need either a join-based query or a denormalization strategy, neither built yet.
- `FieldRegistry` state is in-memory per request (same limitation `MemberStatusRegistry` already has, per ADR-010) - there's no admin UI for defining fields; adding one is still a code change in whatever module/implementation owns those fields.
- The 422 error envelope (`{code, message, data: {status, errors}}`) is new and only used by this feature so far - if a similar validation need appears elsewhere, this shape should be reused rather than inventing another one.
