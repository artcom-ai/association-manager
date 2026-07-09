# ADR 018: Documents module

## Status

Accepted

## Context

Release 0.3 (ELESYTH Pilot Prototype) scopes in a Documents module - the minimum needed to evaluate the platform on a real staging install, not full commercial completeness. Per the user's explicit constraints: no payment gateway integration, no Committees (not even a stub, since nothing in this scope needs one), don't over-engineer, and everything must stay generic Core/Module code - nothing ELESYTH-specific.

**Classification: Module** (`Modules\Documents\`), plus one small Core extraction (`Core\Visibility`, below). Fully generic - association-wide documents with a visibility tier, no ELESYTH-specific categories, branding, or workflow.

## Decision

**File storage reuses the existing WP Media Library upload pattern**, not a new custom-uploads mechanism. `DocumentService::upload()` calls `media_handle_upload()` exactly the way `Core\Fields\Services\FieldValueService::handleFileUpload()` already does for file-type custom fields - WordPress's own upload security (mime validation, filename sanitization) for free, and one fewer pattern to maintain. `wp_am_documents` (migration 011) stores only the metadata (title, description, category, visibility, uploader) plus the `wp_attachment_id` foreign reference; the actual file lives wherever WordPress's media library already puts it.

**Visibility reuses the same 3-tier scheme `Core\Fields\FieldDefinition` already had** (`public < private < admin`), rather than inventing a separate per-document ACL system. The rank-comparison logic that previously lived only inside `FieldDefinition::isVisibleTo()` is now extracted to a new **`Core\Visibility`** class (`VISIBILITY_PUBLIC`/`VISIBILITY_PRIVATE`/`VISIBILITY_ADMIN` constants + a static `isAtLeast()` comparison) - `FieldDefinition`'s own constants now just alias `Visibility`'s (`public const VISIBILITY_PUBLIC = Visibility::VISIBILITY_PUBLIC;`), so every existing caller and test (`FieldDefinition::VISIBILITY_PUBLIC`, `$field->isVisibleTo()`) keeps working unchanged. This is exactly the "can another module reuse this? then it goes in Core" judgment call - Documents needed the identical scheme Fields already had, so the scheme moved to Core rather than being copied.

**Category is a plain string, not a managed taxonomy table.** Versioning and per-member ACLs beyond the 3-tier scheme are explicitly out of scope for this pass - re-uploading a document today just creates a new row; there's no revision history. `DocumentRepositoryInterface` has no `update()` method at all yet, only `find()`/`all()`/`insert()`/`delete()`, since nothing in this pass edits a document's metadata after upload.

**`DocumentRepository::all()` / no pagination.** Given the prototype's expected document volume, a plain array return (mirroring `PaymentRepository::all()`) is enough; `PaginatedResult` can be added later without an interface-breaking change if the admin list actually needs it.

**`GET /documents` (REST) is viewer-scoped**, same pattern as Directory: unauthenticated requests see only `VISIBILITY_PUBLIC` documents, logged-in requests see `VISIBILITY_PUBLIC` + `VISIBILITY_PRIVATE`. `VISIBILITY_ADMIN` documents never appear over this route at all - they're only visible from the admin list page.

## Consequences

Positive:

- Zero new file-handling code paths to secure/maintain - Documents inherits the same upload security WordPress's Media Library and the existing Fields precedent already provide.
- `Core\Visibility` is now a single, tested, shared implementation instead of a second inline copy - `FieldDefinition`'s existing 108+ tests continue to pass unchanged, confirming the extraction didn't alter behavior.
- The Portal's Documents view (ADR-020) needed no new visibility logic of its own - it just calls `DocumentService::listVisibleTo(Visibility::VISIBILITY_PRIVATE)`.

Negative:

- No versioning means re-uploading a document to "correct" it creates an entirely new, unrelated row - acceptable for a prototype evaluation, a real gap before commercial completeness.
- No document-category management UI - categories are free-text, so typos/inconsistent naming across documents is possible until a later pass adds a real taxonomy.
- `DocumentService::delete()` calls `wp_delete_attachment()`, permanently removing the underlying file - there's no trash/undo.
