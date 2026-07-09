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

## Addendum: MVP hardening (same session, follow-up request)

A follow-up request re-specified the Documents MVP more precisely and surfaced two real gaps against the implementation above:

**REST access-control fix.** `DocumentsController::index()` originally decided private-tier access with a bare `is_user_logged_in()` check - meaning *any* authenticated WordPress account (e.g. a plain WP subscriber with no relation to the association) could see private documents through the REST API, inconsistent with the Portal's actual model (ADR-020), which requires the WP account to be *linked to a Member record*. Since "members can view/download documents allowed for them" specifically means members, not any logged-in WP user, the controller now resolves the caller's `Member` via `MemberRepositoryInterface::findByWpUserId()` (Documents now depends on Members' repository interface, same ADR-004 pattern already used elsewhere) and calls a new `DocumentService::listVisibleToViewer(?Member $member)` - null for both anonymous visitors and unlinked WP accounts, both getting the identical public-only view. This is deliberately a testable policy method on the Service, not inline logic in the REST controller, since this codebase doesn't unit-test REST controllers directly.

**Basic metadata "manage" capability added**, distinct from versioning: `DocumentRepositoryInterface::update()`, `Document::withMetadata()` (title/description/category/visibility only - `wpAttachmentId` is immutable through this path, same as every other property in an update), `DocumentService::updateMetadata()`, and an edit view on the admin Documents page (list-then-edit-one, matching the `EditMemberPage`/`CertificateTemplatesPage` shape). Replacing the underlying file remains unsupported by design - the edit form's own description text tells admins to delete-and-re-upload for that, keeping "no complex versioning" intact while still letting admins correct a typo or reclassify a document without losing it.

**Visibility naming**: the follow-up request named the three tiers "public / members_only / admin_only". `Core\Visibility`'s underlying constant *values* (`public`/`private`/`admin`) were kept as-is rather than renamed - that class is shared with `FieldDefinition`, and renaming its values is a real cross-module change for a purely cosmetic difference. The admin UI's dropdown labels were changed to say "Members only" / "Admin only" explicitly, which is what the naming was actually asking for.

**Known, accepted limitation, not addressed**: `wp_get_attachment_url()` returns a directly-fetchable WordPress media URL. WordPress does not restrict access to files under `wp-content/uploads/` by post/attachment visibility on its own - a private-tier document's *file* is not truly access-controlled at the HTTP level, only its *discoverability* (admin UI, REST listing, Portal) is. Building a real access-controlled download proxy (nonce-gated streaming endpoint, `X-Sendfile`, etc.) is exactly the kind of "overbuilt media handling" explicitly ruled out for this MVP - noted here so it's a visible, deliberate scope boundary rather than a security gap discovered later.
