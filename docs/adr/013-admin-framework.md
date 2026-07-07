# ADR 013: Admin Framework (grid, filters, bulk actions, quick edit, export/import)

## Status

Accepted

## Context

Every admin screen so far (`MembersPage`, `PaymentsPage`, `EventsPage`) was a bare read-only HTML table. The roadmap calls for a real admin experience on Members specifically: grid, filters, bulk actions, inline editing, export, import.

## Decision

**Grid**: `Members\Admin\MembersListTable extends \WP_List_Table` - WordPress's own admin-grid framework, not a hand-rolled table. Gets sorting, checkbox selection, the bulk-actions dropdown, and pagination chrome for free. `prepare_items()` reuses `MemberService::search()` (Sprint 9) - no new query logic, just wiring `$_GET['status']`/`$_GET['membership_type']`/`$_GET['s']` into the existing `MemberSearchCriteria` and `$_GET['paged']` into the existing `PaginationParams`.

**Filters** reuse existing registries rather than hardcoding options: the status dropdown comes from `MemberStatusRegistry::all()`, the plan dropdown from `MembershipPlanRegistry::all()` - both already extensible per ADR-010/012, so a future custom status or plan automatically shows up in the filter UI with no admin-framework code change.

**Bulk actions** (activate/suspend/archive) and the underlying **CSV row-shaping** for export are pulled out of `MembersListTable` into plain classes - `Members\Admin\MemberBulkActions` and `Members\Services\MemberCsvExporter` - specifically so they can get the same FakeWpdb smoke-test treatment as every other sprint. `WP_List_Table` itself cannot be meaningfully exercised outside a real WordPress admin request (it depends on internal WP globals/screen options), so keeping business logic out of it is what keeps this sprint testable at all.

**Inline editing is WP-style AJAX Quick Edit, deliberately narrow**: only `status` and `membership_type`, not `expires_at`. Membership expiry changes must go through `renewMembership()`/`renewMembershipByPlan()` (the only path that writes to `wp_am_membership_renewals`, per ADR-012) - allowing Quick Edit to also touch `expires_at` would create a second, unaudited way to change it. `MemberService::transitionStatus()` (the former private `changeStatus()`, made public and renamed) is the generic entry point Quick Edit's status dropdown uses - it runs through the exact same `MemberStatusRegistry` validation as the four named convenience methods, so Quick Edit cannot produce an invalid transition that the REST API would otherwise reject. `updateMembershipType()` is a plain field correction (no history, no event) - it's data cleanup, not a lifecycle transition.

**Export**: CSV, core fields plus one column per `FieldRegistry::forEntityType('member')` entry (custom fields), always the full member list (no filter-scoping) - a deliberately simple "give me everything" export rather than trying to mirror the grid's current filter state.

**Import**: CSV, create-or-update matched by `member_number` (`MemberService::importRow()`, a new `MemberRepositoryInterface::findByMemberNumber()`). Core fields only - no custom fields via import in this sprint. Bypasses the transition-graph check (a bulk data load is asserting ground truth, not requesting a business-rule-governed transition) but still rejects a status string that isn't registered in `MemberStatusRegistry` at all, and still records a `wp_am_member_status_history` entry (`reason: "import"`) when an existing row's status actually changes. Per-row errors are collected and shown together rather than aborting the whole file on the first bad row.

**Explicit limitation of this session**: there is no real WordPress + browser available here to exercise `WP_List_Table`'s actual HTML output or the Quick Edit AJAX round-trip. Everything with business logic (`MemberBulkActions`, `MemberCsvExporter`, `transitionStatus`, `updateMembershipType`, `importRow`, `findByMemberNumber`) has the usual FakeWpdb smoke-test coverage; `MembersListTable` and `assets/js/members-quick-edit.js` are `php -l`/code-review only and need a real staging pass (upload the next zip, exercise the grid/filters/bulk actions/quick edit/export/import by hand) before being trusted in production.

## Consequences

Positive:

- Filters and bulk actions are driven by the same registries already built for status/plan extensibility - no duplicate hardcoded lists to keep in sync.
- All the actual decision logic (which member gets touched, what counts as valid) is unit-testable without WordPress, even though the UI chrome around it isn't.
- Expiry stays behind a single audited path (Renew), even with fully free-form inline editing of everything else.

Negative:

- `WP_List_Table` and the Quick Edit JS are the least-verified code shipped so far in this project - real risk of a markup mismatch or a JS selector that doesn't match `WP_List_Table`'s actual rendered output, discoverable only on the staging site, not here.
- Import's "bypass transition graph, only check the status string exists" rule means an imported CSV can silently put a member into a state (e.g. `expired` with no `expires_at`) that would never arise through normal transitions - acceptable for a data-migration tool, but worth knowing it isn't held to the same rigor as the rest of the system.
- `updateMembershipType()` has no audit trail at all - if "who changed a member's plan and when" ever matters, this method would need revisiting.
