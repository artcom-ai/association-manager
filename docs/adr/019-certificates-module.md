# ADR 019: Certificates module (dompdf)

## Status

Accepted

## Context

Same Release 0.3 prototype scope as ADR-018. Certificates is listed in the longer-term roadmap with "PDF generation, QR verification, digital signatures" - this pass builds only PDF generation from an admin-editable template; QR verification and digital signatures are explicitly deferred, consistent with "don't over-engineer" for a first evaluation build.

**Classification: Module** (`Modules\Certificates\`), plus one small Core promotion (`Core\Templating\TemplateRenderer`, below).

## Decision

**dompdf**, chosen over mpdf/tcpdf via explicit user decision (AskUserQuestion) - pure PHP, MIT-licensed, renders styled HTML/CSS directly to PDF, no external binary or PHP extension dependency. This is the first non-WordPress runtime dependency this codebase has taken (everything before this was either WordPress itself or a dev-only tool like PHPUnit/PHPStan) - added via `composer require dompdf/dompdf` as a real `require`, not `require-dev`. Verified it doesn't break PHPStan/PHPCS scanning or add any test-suite regressions before relying on it.

**Certificate content is one admin-editable HTML template per `type_key`**, DB-backed (`wp_am_certificate_templates`, migration 012, unique on `type_key`), rendered via list-then-edit-one admin pages - the exact same shape as `NotificationTemplatesPage` (ADR-016). Migration 012 seeds one default `membership` template so there's real, admin-visible content to evaluate immediately, the same deliberate one-off seeding departure migration 009 established. `{token}` placeholder substitution is shared with Notifications rather than reimplemented: the existing `Modules\Notifications\Services\NotificationTemplateRenderer` (pure `strtr()`-based substitution, no WP calls) is **promoted to `Core\Templating\TemplateRenderer`** and registered in `CoreServiceProvider` rather than `NotificationsModule`, since Certificates needed the identical capability. The old class was removed outright (not kept as a thin wrapper) - a 10-line pure function didn't justify two names for one implementation. `NotificationDispatcher` and its tests were updated to the new location; behavior is unchanged.

**Generation is split into a pure step and a WP-calling step**, so the pure logic is fully unit-testable without any WordPress stub surface: `CertificateGenerator::generate(CertificateTemplate, array $placeholders): string` renders the template + placeholders via `TemplateRenderer` and returns raw PDF bytes from dompdf - zero WordPress calls. `CertificateService::issue()` is the WP-calling orchestration: writes those bytes to a `wp_tempnam()` temp file, hands it to `media_handle_sideload()` (the from-a-local-path sibling of `media_handle_upload()`, same WP Media Library security/storage this codebase already relies on for Documents and Fields file uploads), persists the `Certificate` row, and fires `association_manager_certificate_issued`.

**A certificate is generated once, at issuance, and stored** - not regenerated on every download. Issuance is a point-in-time snapshot (the member's data at that moment); a member's later profile changes shouldn't silently alter an already-issued certificate's content. `wp_am_certificates` (migration 013) records `member_id`, `type_key`, the resulting `wp_attachment_id`, `issued_at`, `issued_by`.

**Issuing a certificate is a deliberate admin action, not a background auto-trigger** - unlike `NotificationDispatcher::notify()`'s silent no-op on a missing template (an expected, recoverable state for event-driven notifications), `CertificateService::issue()` throws a real `\RuntimeException` when no template exists for the requested `type_key`, since an admin explicitly asked to issue a specific certificate and a missing template is a real configuration error worth surfacing, not silently swallowing.

**The admin "Issue Certificate" page takes a raw member ID**, not a member picker/autocomplete - consistent with keeping this pass minimal; an admin looks up the ID from the Members list first. The admin-post handler resolves the `Member` via `MemberRepositoryInterface` (Certificates depends on Members' repository interface, same ADR-004 cross-module pattern; `Kernel::registerModules()` registers Members before Certificates) to build the certificate's placeholders (`member_number`, `membership_type`, `issued_at`) and to validate the member actually exists before calling `issue()`.

## Consequences

Positive:

- The pure/impure split means `CertificateGenerator`'s actual rendering logic (the part most likely to have real bugs - placeholder substitution, template structure) is tested by generating real PDF bytes and asserting the `%PDF-` magic header, not a mock.
- Promoting `TemplateRenderer` to Core paid for itself immediately - Certificates needed zero new placeholder-substitution code.
- `Core\Templating` establishes a real pattern (not just Fields, not just Notifications) for "generic, no-WP-calls behavior belongs in Core" that future modules can point to.

Negative:

- dompdf is a real new dependency surface (plus its own transitive deps: `php-font-lib`, `php-svg-lib`, `sabberworm/php-css-parser`) - a supply-chain/maintenance consideration this codebase didn't have before this pass.
- The pure `CertificateGenerator` tests exercise real dompdf rendering (not mocked), which is correct for verifying actual output but makes those specific tests meaningfully slower than the rest of the suite (real PDF rendering, not µs-scale in-memory logic) - acceptable at the current test-suite size, worth watching if it grows.
- "Save PDF bytes as a WP attachment" (`media_handle_sideload()`) is WP-calling glue that needed a new `tests/bootstrap.php` stub (`wp_tempnam`, `media_handle_sideload`) rather than real WordPress - same category of untested-here surface as `MembersListTable`/AJAX JS in prior ADRs; a real staging pass is still needed to confirm actual certificate issuance end-to-end.
- QR verification and digital signatures remain entirely unbuilt - this ADR only covers the foundation slice explicitly scoped for this prototype.

## Addendum: draft/issued/revoked lifecycle (same session, follow-up request)

A follow-up request required `Certificate` to have real `draft`/`issued`/`revoked` statuses and distinct admin "issue"/"revoke" actions - the original design above went straight from "nothing" to "fully generated and issued" in one atomic step, with no status field at all.

**Draft means "generated, not yet released" - not "not yet built".** The alternative (defer PDF generation until the issue step, `wpAttachmentId`/`issuedAt` nullable until then) would have required altering two existing `NOT NULL` columns to `NULL` on an already-shipped table. `dbDelta()` is known to be unreliable at applying that specific kind of change (it handles adding new columns and indexes well, which is exactly what every prior migration in this project already relies on - migrations 004/008/011 all only add columns). So `CertificateService::createDraft()` still generates and stores the PDF immediately (reusing 100% of the prior single-step logic), and `status` is the only new column (migration 015, purely additive). This has a genuine upside beyond avoiding migration risk: an admin can preview/download a draft certificate themselves before deciding to release it.

**The state machine lives on `Certificate` itself** (`CertificateStatus::DRAFT`/`ISSUED`/`REVOKED` constants, `Certificate::draft()`/`issue()`/`revoke()` - the same factory-plus-guarded-transition-methods shape as `Payment`'s `draft()`/`complete()`/`fail()`/`refund()`), not in the Service. `issue()` and `revoke()` both throw `\LogicException` if called from the wrong status (only `draft` can be issued, only `issued` can be revoked) - `CertificateService::issue()`/`revoke()` just look up the certificate, delegate the transition (and its guard) to the domain object, persist, and (for `issue()` only) fire `association_manager_certificate_issued`. `issued_at` is set to a placeholder value at draft-creation time and genuinely overwritten at the `issue()` transition - an ordinary value update on an already-`NOT NULL` column, not a nullability change, so this also carries no migration risk.

**`CertificateService::issue()` was renamed from meaning "generate and store" to meaning the status transition** (`draft → issued`, taking a certificate id instead of a member id) - a breaking rename of an already-tested method, done deliberately because "Admin issue/revoke actions" names `issue` as the specific admin-facing action distinct from creation. The old "generate and store" behavior is now `createDraft()`. Every call site and test using the old signature was updated as part of this change, not left as a compatibility shim (pre-1.0, no shipped API contract to preserve here).

**Member-facing visibility now means `status = issued`, specifically** - `issuedForMember()` (Service) filters `allForMember()` (Repository, still returns every status) down to issued-only, and is what the Portal and the member-scoped REST endpoint call. Drafts (not yet released) and revoked certificates (no longer valid) simply stop appearing to the member; the row and file are retained either way for the admin's own audit trail (`CertificateService::all()`, the new admin list view).

**No `certificate_revoked` notification was added.** Wiring it would be a trivial, near-free addition given the existing Notifications infrastructure, but it wasn't part of what this pass asked for - added here as a deliberate scope boundary, not an oversight, consistent with the instruction to avoid opportunistic scope growth.

**Admin UI**: `IssueCertificatePage` was replaced outright by `CertificatesPage` (same `DocumentsPage`-style combined create-form-plus-list shape) rather than kept alongside a new page, since it now needs to show every certificate's status with Issue/Revoke actions per row - a single "create and immediately issue" form no longer matches the three-state model.

## Addendum: gated certificate downloads

Follow-up ask, directly after the ADR-023 addendum closed the identical gap for member custom-field files: certificate PDFs had the same problem - both the admin certificates list and the Portal's own download link built their `<a href>` straight from `wp_get_attachment_url()`, the plain public WordPress uploads URL. Server-side access control existed at the *listing* level (`issuedForMember()` already filters to `status = issued`), but the file itself, once its URL was known, was reachable by anyone regardless of login or status.

**`Core\Fields\Services\FieldFileStreamer` was promoted and renamed to `Core\Media\AttachmentStreamer`**, since "given a WP attachment ID, send it as an HTTP response, no authorization" was never actually a Fields-specific capability - it just hadn't had a second caller yet. Certificates now reuses it directly rather than duplicating the same `get_attached_file()`/`wp_check_filetype()`/`readfile()` logic a second time. The rename touched exactly the places that constructed or referenced it (`CoreServiceProvider`, `MembersModule`, `PortalModule`) - no behavior change, same class, new home.

**Authorization stays inside `CertificatesModule` itself, not routed through Portal.** Unlike member field files (where the member-facing gate lives in `PortalModule`, since Portal already owns the `memberFor()` ownership resolution), Certificates already depends on `MemberRepositoryInterface` directly (documented at the top of this file: needed to resolve a member id to placeholders, and a WP user to their own certificates in the REST controller) - so `handleDownloadCertificate()` (admin-only, any status, since the admin needs to preview drafts before deciding to Issue) and `handleDownloadOwnCertificate()` (member-facing: `findByWpUserId( current user )` must resolve to the exact member the certificate's `member_id` names, **and** the certificate must be `status = issued` - a member can never reach their own draft or a revoked certificate through this endpoint even if they know its ID) both live directly on `CertificatesModule`, with no new Portal-side code at all.

No new tests: this changes only admin-post handlers and template markup (URL construction), the same WP-glue category ADR-015 already excludes from direct unit testing, and `AttachmentStreamer`'s logic is unchanged from its already-existing (also untested-by-design) `FieldFileStreamer` form. 268 tests still passing; `composer stan`/`composer cs` both clean.
