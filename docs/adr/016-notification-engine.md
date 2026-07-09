# ADR 016: Notification Engine & Email (Epic 2 foundation)

## Status

Accepted

## Context

Epic 2 (Communication Platform) is large - Notifications/Email/SMS/Push/In-app, Templates, Campaigns/Newsletters, CRM/Tags/Segments. This pass builds only the foundation: a Notification Engine, DB-backed email templates, a scheduled-send queue, and member/admin notifications wired to events Members already fires. SMS, Push, in-app notifications, Campaigns/Newsletters, and CRM/Tags/Segments are explicitly deferred to a later pass within this Epic - none of that surface exists yet and nothing in this ADR assumes it will look a particular way when it lands.

Two prerequisites fell out of scoping this: `Member` had no email of its own (only an optional `wp_user_id`), so there was no address to send to for members without a WP account; and every notification's content needed to be something an admin can actually edit, not a string baked into PHP.

## Decision

**Mail transport: `wp_mail()` only.** WordPress already delegates real SMTP configuration to the site/host or a dedicated SMTP plugin (WP Mail SMTP, etc.) - anything using `wp_mail()` automatically benefits from that configuration for free. No custom `MailTransportInterface` abstraction was introduced in this pass; `NotificationQueueRunner` calls `wp_mail()` directly. If a future need arises for a transport WordPress can't reach this way (e.g. a transactional-email API bypassing `wp_mail` entirely), that's a new decision, not a gap in this one.

**`Member` gains a real `email` column** (migration `008_add_email_to_members_table.php`, additive `dbDelta()` in the same style as migration 004). This is a breaking domain change - `Member`'s constructor gained a new property - accepted under the same pre-1.0 breaking-change precedent as ADR-008. It rippled through every construction site: `Member::draft()`, `MemberRepository::hydrate()/insert()/update()`, `MemberService::createMember()/updateMembershipType()/importRow()`, `MembersController::create()` (validated via `is_email()`, 422 on invalid) and `toArray()`, `MemberCsvExporter`, `MembersListTable`, and every existing test constructing a `Member`.

**Templates are DB-backed and admin-editable**, not hardcoded strings (`wp_am_notification_templates`, unique on `event_key, channel`; `NotificationTemplatesPage` is a list-then-edit-one page, same shape as `EditMemberPage` from Sprint 10, not another `WP_List_Table`). Migration `009_create_notification_templates_table.php` also seeds five default rows (`admin_new_member`, `member_activated`, `member_suspended`, `member_archived`, `membership_renewed`) if not already present - a deliberate, noted one-off departure from every prior migration being schema-only, chosen so the feature has real, admin-visible content to edit immediately instead of shipping empty.

**Queue + cron, mirroring `MembershipExpiryRunner` (Sprint 11).** `wp_am_notification_queue` (migration 010) holds one row per pending/sent/failed send, with a `scheduled_at` column that makes "scheduled emails" a property of the engine itself rather than a separate feature - `NotificationDispatcher::notify(..., ?string $scheduledAt = null)` defaults to "now" but accepts a future timestamp. `NotificationQueueRunner::run()` (the `association_manager_process_notification_queue` cron callback, scheduled hourly, defensively re-scheduled on `admin_init` per the ADR-005/ADR-012 pattern so an already-active install picks it up without deactivate/reactivate) fetches due rows and marks each `sent` or `failed` with `last_error`.

**Event-hook listening, no container coupling to Members.** `NotificationsModule` listens to Members' existing plain WordPress hooks - `association_manager_member_status_changed` and `association_manager_member_renewed` (both already fired pre-Epic-2) - rather than Members depending on or calling into Notifications. Since these are `add_action`/`do_action`, not container-resolved services, `Kernel::registerModules()` has no ordering constraint between the two modules (unlike ADR-004's Directory-depends-on-Members case): the event payload (the `Member` object) already carries everything a listener needs.

**Recipient resolution** lives in `NotificationsModule::resolveMemberEmail()`, not in `Member` domain - keeps `Member` free of WordPress calls (`get_userdata`). It prefers `$member->email`; if null, falls back to `get_userdata($member->wpUserId)->user_email` when `wpUserId` is set; otherwise returns null, and `NotificationDispatcher::notify()` silently no-ops on a null recipient (not an error - a member with neither an email nor a linked WP account is an expected state, not a bug).

**Built-in event keys wired in this pass**: `member_activated`/`member_suspended`/`member_archived` (status transitions to active/suspended/inactive), `membership_renewed`, and `admin_new_member` (fired to `get_option('admin_email')` when a member is first created - old status `null` in the status-changed payload). This covers "Member notifications" and "Admin notifications" from the Epic 2 list without trying to enumerate every possible future event; a status with no mapped event key (e.g. `expired`, `candidate`) is a deliberate no-op, not a missing case.

## Testing note

`NotificationsModule`'s handlers are wired via `add_action()`, which is a no-op stub in the test environment (there's no real WordPress hook dispatcher to exercise, per ADR-015) - so `NotificationsModuleTest` invokes the module's private `handleMemberStatusChanged()`/`resolveMemberEmail()` methods directly via `ReflectionMethod` rather than re-implementing their branching logic in the test. This is a new pattern for this test suite (no prior test used reflection); it was chosen over extracting the logic into a separately-testable public class, which would have added an abstraction with no other caller purely to make it reachable without reflection.

## Consequences

Positive:

- Members without a WP account (imported via CSV, or created before an account is linked) can now receive notifications, since `email` no longer depends on `wp_user_id`.
- Admins can edit every notification's subject/body without a code change or redeploy.
- "Scheduled emails" required no separate feature - it's just `notify()`'s `$scheduledAt` parameter, reusing the same queue/cron machinery as immediate sends.
- 125 tests / 237 assertions now pass (108 prior + 17 new), covering template rendering, dispatch (enqueue/no-op/scheduling), queue processing (due-only, sent/failed marking), event-to-notification mapping, recipient resolution, and the `Member.email` round trip.

Negative:

- `wp_mail()` failures are only as informative as WordPress makes them (`last_error` is a fixed string, not whatever the underlying mailer reported) - acceptable for this pass since diagnosing real SMTP failures is a hosting/plugin-configuration concern, not something this engine can improve on without the transport abstraction this pass deliberately skipped.
- No real end-to-end verification that `add_action()` wiring in `NotificationsModule::boot()` actually connects to Members' hooks in a live WordPress install - the reflection-based tests verify the handler logic is correct, not that boot-time registration succeeds. Real staging verification is still needed here, same caveat as `WP_List_Table`/Leaflet JS in ADR-013/014.
- SMS, Push, in-app notifications, Campaigns/Newsletters, and CRM/Tags/Segments remain entirely unbuilt - this ADR only covers the foundation slice of Epic 2.

## Addendum: Portal-facing read model + `certificate_issued`/`document_published` listeners (Release 0.3, same session as ADR-018/019/020)

A follow-up request required member-visible notification history in the Portal, a fourth status (`read`), an explicit `EmailAdapter`, and hook listeners for the two events Documents and Certificates now fire.

**`NotificationStatus::READ` and `QueuedNotification::markRead()`** follow the same immutable-rebuild shape as `markSent()`/`markFailed()`. No migration was needed - `status VARCHAR(20)` (migration 010) was already wide enough. `read` is a Portal-view concern layered on top of the send lifecycle, not a replacement for it: only a `sent` row can become `read` (`NotificationService::markAllReadForRecipient()` filters on that explicitly), since "read" presupposes the member could actually have seen it.

**A new `NotificationQueueRepositoryInterface::allForRecipient(string $email)`** (`WHERE recipient = %s ORDER BY id DESC`) is the query this pass actually needed - the existing `findDue()` is scoped to `pending` rows for the cron runner, wrong shape for "show this member everything ever sent to them."

**A new `Modules\Notifications\Services\NotificationService`** is the member-facing read model: `forRecipient(string $email)` and `markAllReadForRecipient(string $email)`, both keyed on a plain email string rather than a `Member` - same reasoning as `PortalService::visibleDocuments()` needing no member argument, kept deliberately decoupled from Members so this Service has exactly one reason to change. It sits alongside `NotificationDispatcher` (writes: renders a template and enqueues) and `NotificationQueueRunner` (the cron send loop) rather than folding into either - each has a distinct caller and a distinct concern (dispatch vs. send vs. member-facing read).

**`EmailAdapter::send(string $to, string $subject, string $message): bool`** is a thin wrapper around `wp_mail()`, and `NotificationQueueRunner` now depends on it instead of calling `wp_mail()` inline. This does **not** reopen the "no `MailTransportInterface`" decision above - there is still exactly one mail transport (WordPress' own) and no second implementation in sight, so a swappable-transport interface would still be speculative generality. `EmailAdapter` exists only so `NotificationQueueRunner` has something to depend on and so the requirement ("email adapter using `wp_mail()`") is met literally, not to enable substitutability that isn't needed yet.

**`resolveMemberEmail()` moved from a private method on `NotificationsModule` to a public `MemberService::resolveEmail(Member $member): ?string`**, along with a new `MemberService::findByWpUserId()` passthrough. The Portal's new `notificationsFor()`/`markNotificationsReadFor()` needed the exact same "member's own email, falling back to their linked WP account" logic that `NotificationsModule` already had privately - duplicating it would have meant two implementations of the same fallback rule silently drifting apart. `MemberService` is the right home, not `Member` domain (which stays free of `get_userdata()` and every other WordPress call) and not `Core` (this is fundamentally a Members-module concern, tied to `MemberRepositoryInterface`). `NotificationsModule` and `PortalService` both now depend on `MemberService` instead of `MemberRepositoryInterface` directly - a strictly bigger dependency (`MemberService` already wraps the repository), but the right one now that both callers need Service-layer behavior, not just row access.

**Portal "mark read on view"**: `PortalShortcode::render()` fetches `notificationsFor($member)` and renders the template *before* calling `markNotificationsReadFor($member)` - so the current render still shows accurate `sent`/`read` status (an admin didn't just click something to "read" a notification; viewing the Portal itself is the read action), and only the *next* visit reflects everything from this view as read. No REST endpoint was added for member-facing notifications - Documents added one because "REST endpoints if consistent with existing modules" was explicit for that pass; this pass's requirement list didn't ask for it, and the Portal template is the only consumer so far.

**No new hook listeners were needed for `certificate_issued`/`document_published`** - both were already wired in `NotificationsModule::boot()` during the Documents/Certificates passes (ADR-018/019 addenda). This pass's "hook listeners for member status changed / certificate issued / document published" requirement was already satisfied; the actual gap was the read-model and status, not the listeners themselves.

Positive:

- Zero new migrations - the entire Portal notification history feature fits inside the existing `wp_am_notification_queue` schema.
- The `resolveEmail()` promotion eliminated the only piece of near-duplicate logic this pass would otherwise have introduced.
- 184 tests / 370 assertions now pass (166 prior + 18 new), covering `markRead()`, `allForRecipient()`, `EmailAdapter`, `NotificationService`, and the Portal's notification-history/mark-read composition.

Negative:

- `NotificationService` and `NotificationDispatcher` both read/write the same table through the same repository interface but were kept as two classes rather than merged - correct given their distinct callers (Portal vs. event listeners) and distinct concerns (read model vs. write/dispatch), but it does mean `Modules\Notifications\Services\` now has three top-level services (`Dispatcher`, `QueueRunner`, `NotificationService`) plus `EmailAdapter` - worth revisiting if a fourth concern shows up and the module directory starts feeling crowded.
- No real end-to-end verification that the Portal's mark-read-on-view timing behaves correctly under concurrent requests (e.g. two tabs open) - acceptable for a single-admin-per-member-account prototype, same category of untested-here concurrency concern as every other WP-glue surface in this codebase.
