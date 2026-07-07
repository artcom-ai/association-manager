# ADR 015: Testing strategy (PHPUnit, FakeWpdb, no real WordPress)

## Status

Accepted

## Context

Every sprint since Sprint 9 was verified with an ad-hoc PHP script in the assistant's scratchpad: stub WordPress functions, a hand-rolled `FakeWpdb` reimplemented (with small variations) each time, run once, then discarded. This caught real bugs during development but left **zero durable regression protection** - several breaking API renames already happened during Foundation (`register/approve/suspend` -> `createMember/activateMember/suspendMember/archiveMember`, `paginateByStatus` -> `search`, `changeStatus` -> `transitionStatus`) with nothing automated to catch a future regression against them. `09_Coding_Standards.md` has listed PHPUnit as a standard from day one; it was never actually installed until now. This ADR is Epic 9 (Operations & Enterprise)'s testing item, pulled forward per the architect's recommendation rather than left until the very end of the roadmap.

## Decision

**PHPUnit 10.5** (`composer require --dev phpunit/phpunit:^10.5`) - version 10, not 11, specifically to stay compatible with the `composer.json` floor of `php >=8.1` (PHPUnit 11 requires PHP 8.2+).

**No real WordPress install.** There still isn't one available in this environment, and pulling in `wordpress/wordpress` + a real MySQL instance just for unit tests would be disproportionate to what these tests actually need to verify (domain/service/repository business logic, not WordPress integration itself). Instead:

- `tests/bootstrap.php` defines the WordPress function/class surface the plugin's own code actually calls (`current_time`, `sanitize_text_field`, `do_action`, `WP_Error`, `WP_REST_Request`/`Response`, etc.) as simple stubs - the same functions every ad-hoc smoke script already stubbed, now written once and committed.
- `tests/Support/FakeWpdb.php` stands in for `$wpdb`: it pattern-matches the specific query shapes this plugin's repositories issue (`WHERE` with `=`/`>=`/`<=`/`LIKE` combined with `AND`, `ORDER BY` + `LIMIT/OFFSET`, and the `INSERT ... ON DUPLICATE KEY UPDATE` upsert used by the fields EAV table) against in-memory arrays keyed by table name. It is **not** a SQL engine - it does not understand arbitrary SQL, only the queries this codebase's repositories are already known to issue. Adding a new repository query shape means extending `FakeWpdb`, not writing real SQL against it.
- `tests/Support/TestCase.php` is the shared base class: fresh `FakeWpdb` per test (`setUp()`), plus small test-only accessors (`setNow()`, `setCurrentUserId()`, `firedActionsNamed()`) so tests can control/assert against the stubbed WordPress surface without reaching into globals directly.
- ABSPATH points to `tests/Support/fakewp/` (empty stub `wp-admin/includes/{upgrade,file,media,image}.php` files) rather than the real plugin root, so `require_once ABSPATH . 'wp-admin/includes/...'` calls in migrations/`FieldValueService::handleFileUpload()` don't fatal - the real functions they'd load (`dbDelta`, `media_handle_upload`) are already stubbed in `bootstrap.php` before those requires ever execute. `AM_PLUGIN_DIR` still points to the real plugin root (needed for template `require`s elsewhere), so this is a deliberate split between "the WordPress-root concept" and "the plugin's own directory," which happen to be identical in production but are not the same concept.

**Test structure mirrors `src/`**: `tests/Unit/Modules/Members/...`, `tests/Unit/Core/Fields/...`, etc. - a new file always has an obvious home. Tests construct real repository/service objects wired to `FakeWpdb` (via `TestCase::$wpdb`) rather than mocking interfaces - this is closer to integration testing across the repository/service boundary than pure isolated unit testing, matching how these components were actually designed to be exercised together (same reasoning the ad-hoc smoke scripts already used, just made permanent).

**Scope of this pass**: ported the essential behaviors already covered by every prior sprint's smoke script - Members (domain purity, status registry transitions, service lifecycle, renewals/grace/expiry, bulk actions, CSV export), Payments, Events, Directory (public/private visibility, search, map points), and Core (field validation including the `location` type, pagination, migration loading). **Not** re-verified here: `WP_List_Table` rendering (`MembersListTable`) or the Quick Edit/Leaflet JS - those still need a real staging check, as already flagged in ADR-013/ADR-014's own caveats; this ADR doesn't change that, it just means the business logic underneath those UI layers now has real regression coverage even though the UI layers themselves don't.

`composer.json` gained `autoload-dev` (`AssociationManager\Tests\` -> `tests/`) and a `scripts.test` entry (`composer test` runs `phpunit`). `phpunit.xml` points at `tests/Unit`, uses `tests/bootstrap.php`, and includes `src/` for coverage reporting (coverage itself isn't enforced yet - no threshold gate).

## Consequences

Positive:

- 108 tests / 203 assertions now run in well under a second, covering the majority of business-logic behavior built since Sprint 9 - a real regression safety net for the first time in this project.
- The `FakeWpdb` pattern is now a single, shared, tested piece of test infrastructure instead of being silently reinvented (with drift) in every future ad-hoc verification script.
- New sprints can (and should) add to `tests/Unit/` directly instead of writing scratchpad scripts that get thrown away.

Negative:

- `FakeWpdb` is a simulation, not real MySQL - a query shape it doesn't recognize will silently misbehave rather than error clearly; extending it requires care and is itself untested-by-a-second-layer.
- No PHPStan yet (still Epic 9 scope) - these tests catch behavioral regressions, not type errors.
- No CI wiring yet - `composer test` must still be run manually; automating that (GitHub Actions or similar) is a natural near-term follow-up, not done in this pass.
- WordPress-integration-level bugs (real `$wpdb`/MySQL behavior, real `WP_List_Table` rendering, real AJAX/JS) remain entirely unverified by this suite - it strictly complements, not replaces, the real-staging verification already called out in ADR-012/013/014.
