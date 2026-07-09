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
