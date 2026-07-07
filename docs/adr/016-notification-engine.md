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
