# ADR 020: Member Portal

## Status

Accepted

## Context

The last of the five Release 0.3 prototype areas. The longer-term roadmap's Member Portal Epic lists Dashboard, Profile, Membership, Payments, Documents, Certificates, Notifications, Events, Committee participation, and Preferences - far more than a first evaluation prototype needs. Per the user's explicit scope, this pass builds only: a membership-status dashboard, a documents view, and a certificates view, server-rendered (not a JS/REST-driven SPA), reusing the exact shortcode pattern already established by Directory.

**Classification: Module** (`Modules\Portal\`), explicitly a **thin aggregator that owns no domain data of its own** - it composes read access to Members/Documents/Certificates rather than introducing new business data.

## Decision

**A member accesses the Portal only if their WP account is linked to a `Member` record** (`wp_am_members.wp_user_id`, a column that already existed but had no admin UI to set it - `MembersController::create()` accepted it over REST, but nothing in wp-admin let an admin link an *existing* member to a WP account after the fact). This pass adds: `MemberRepositoryInterface`/`MemberRepository::findByWpUserId()` (new), `MemberService::linkWpUser()` (a plain field-correction method, same "no transition check, no history entry" shape as the existing `updateMembershipType()`, with one added guard - it rejects linking a WP account already linked to a *different* member, since the Portal's member-resolution assumes a 1:1 mapping), and a "Link WP account" control added directly to the existing per-member `EditMemberPage`/`member-edit.php` (a plain numeric WP-user-ID input, not a picker/autocomplete - same minimal-surface reasoning as Certificates' "Issue Certificate" page in ADR-019).

**Self-service profile editing and member self-registration are explicitly deferred**, not built even as a stub. Profile editing would need its own field-level self-edit permission model (a materially different problem from the read-only Dashboard/Documents/Certificates views here); self-registration would need a real account-creation/verification flow. Neither is necessary to evaluate the platform - an admin manually creating a WP account and linking it via the new control above is sufficient for a first staging evaluation.

**`PortalService` depends on `MemberRepositoryInterface`, `DocumentService`, and `CertificateService` directly** (not narrower interfaces for the latter two) - the same ADR-004 cross-module interface-dependency shape, extended to services rather than only repositories where a repository interface alone wouldn't be enough (Document/Certificate *business logic* - visibility filtering, PDF issuance - lives in their services, not just CRUD). `Kernel::registerModules()` registers Portal strictly last: unlike Notifications' hook-based Members dependency (resolved at `boot()` time, where module order never matters, per ADR-016/017's own note), `PortalModule::register()` resolves `DocumentService`/`CertificateService` from the container immediately during registration, so Members, Documents, and Certificates must have all already run their own `register()` first.

**The "logged in but not yet linked to a member" state renders a plain, deliberate message**, not an error - flagged as the single most likely thing a staging tester hits if the admin-side linking step is missed, so it's handled as a first-class, expected state in `PortalShortcode::render()` rather than an edge case.

**Every logged-in member currently sees the same private-tier documents** (`PortalService::visibleDocuments()` takes no member argument) - there's no per-member document ACL beyond the 3-tier visibility scheme from ADR-018 in this pass, so passing an unused `$member` parameter through for symmetry would have been dead weight, not real flexibility.

## Consequences

Positive:

- The admin "link WP account" control closes a real, pre-existing gap (the column existed, nothing could set it after creation) - without it, the Portal would have been unreachable for any already-existing member, not just a hypothetical future one.
- `PortalService` added no new domain data or migration - purely a composition layer, which is what let it stay this small.
- The unlinked-account message was designed in from the start rather than discovered as a bug during staging evaluation.

Negative:

- Portal rendering itself (`PortalShortcode`/`templates/public/portal.php`) has no dedicated test, consistent with this codebase's existing precedent of not unit-testing Shortcode/Admin-page `render()` methods directly (only the Services underneath them) - it needs a real staging pass, same caveat as every prior public-facing template.
- The "link WP account" admin control has no search/autocomplete - for an association with many WP users, an admin needs to already know the target user's numeric ID (findable via the standard Users screen), which is a real but accepted rough edge for a first-evaluation prototype.
- No Profile/Payments/Events/Committee/Preferences views exist yet - only Dashboard, Documents, and Certificates, per this pass's explicit scope.

## Addendum: MVP section structure (same session, follow-up request)

A follow-up request asked for the same Portal, specified more explicitly: distinct Profile / Membership status / Membership summary sections rather than one flat table, a placeholder Notifications section, and explicit documentation of the capability-check model. None of this changed the architecture above - it's the same `PortalService`, same two-layer access gate, same read-only scope - only `templates/public/portal.php` and a doc comment changed:

- **`templates/public/portal.php`** now has six sections: Profile (member #, email), Membership status (status, membership type), Membership summary (joined/approved/expires dates - all read-only, explicitly labeled as such in the Profile section's own note pointing members to contact the association for changes), Documents, Certificates, and a **Notifications placeholder** (static "coming soon" text, no query). A real notification-history view would need `wp_am_notification_queue` to be queryable by recipient - that's new repository surface, not something a "placeholder section" should grow into by accident.
- **Capability check model made explicit**: `PortalShortcode::render()` now carries a doc comment stating why `current_user_can()` doesn't appear anywhere here - membership is business-domain state resolved via `PortalService::memberFor()`, not a WP capability string, so the existing two-layer check (login, then member-link) *is* the complete capability model for this feature, not a partial one.
- `PortalServiceTest` gained an explicit assertion that `memberFor()` returns a fully-hydrated `Member` (email, membership type, status - not just an id), since the Profile/Status/Summary sections now read those fields directly.

## Addendum: notification history + custom-field rendering (Release 0.3, later same session)

Two follow-up requests: a real Notifications section (see the ADR-016 addendum for the notification-history/mark-read design - `PortalService` gained `NotificationService` as a fourth dependency and `notificationsFor()`/`markNotificationsReadFor()`), and - during the ELESYTH implementation-layer pass (ADR-021) - the ability for a Portal to actually display an implementation's custom member fields.

**Before this addendum, the Portal could not render any custom field at all** - `Core\Fields\FieldRegistry`/`FieldValueService` existed and Directory already composed them (`DirectoryService::visibleCustomFields()`/`buildEntry()`), but Portal only ever read fixed `Member` properties. This was a real, generic gap: no per-client implementation's registered fields could ever reach a member's own self-service view, regardless of the visibility level chosen. `PortalService::customFieldsFor(Member $member)` closes it by composing `FieldRegistry::forEntityType('member')` + `FieldDefinition::isVisibleTo()` + `FieldValueService::valuesFor()` - the exact same composition Directory already uses, not new logic. Portal always evaluates visibility at `Visibility::VISIBILITY_PRIVATE` (a member viewing their own Portal is a "private"-tier viewer, same rank `visibleDocuments()` already uses) - fields registered at `VISIBILITY_ADMIN` never appear here, by design.

**`PortalService` gained `FieldRegistry` and `FieldValueService` as two more constructor dependencies** (both already registered in `CoreServiceProvider` at container-`register()` time, so no module-ordering change was needed). `templates/public/portal.php`'s Profile section renders each visible custom field as a plain read-only label/value row, appended after the fixed Member fields - no new admin-form-style rendering (`Core\Fields\Admin\FieldRenderer` is edit-form-specific and not reused here; a read-only view needed no `<input>` at all, just `esc_html()`).

This is Module-layer work, not implementation-specific - it was built and tested here with generic field definitions, and ELESYTH (or any future client) benefits automatically by registering its own `FieldDefinition`s through the existing `FieldRegistry` extension point.
