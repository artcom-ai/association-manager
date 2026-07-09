# ADR 017: Static analysis (PHPStan level 8, PHPCS/WPCS)

## Status

Accepted

## Context

The user set a formal quality gate for this project: every task must pass PHPUnit, PHPStan, and PHPCS before being considered complete. Neither PHPStan nor PHPCS was installed yet - `docs/09_Coding_Standards.md` lists both as requirements, but CLAUDE.md and ADR-015 explicitly deferred them to Epic 9. This mirrors exactly how PHPUnit itself was deferred, then pulled forward for Epic 2 once the cost of not having it became clear (undetected regressions across renamed APIs). Same move here, decided explicitly via the user rather than assumed - this ADR extends ADR-015 (testing strategy) rather than rewriting it, the same way ADR-004 extended ADR-002 instead of amending it.

**Classification: Core.** Dev-tooling/config for the `association-manager` repo itself - applies uniformly to `src/` and `database/` regardless of Feature Module or future Implementation. No production runtime behavior changes resulted from adopting the tools; the fixes below are real bug/type/style corrections the tools surfaced.

## Decision

**PHPStan 2.2** (`phpstan/phpstan` + `szepeviktor/phpstan-wordpress` for real WordPress function/class stub signatures), **level 8** (the second-highest level; effectively max minus the strictest `mixed`-type scrutiny) from day one rather than starting low and raising it later - this codebase already uses `declare(strict_types=1)`, readonly properties, and named-argument construction throughout, so it was well-positioned for it. `phpstan.neon` scans `src/` and `database/` only - not `tests/` (checking `tests/bootstrap.php`'s WP-function *fakes* against `szepeviktor/phpstan-wordpress`'s *real* stub signatures would be a mismatch of purpose; tests get their correctness signal from PHPUnit, not static analysis, matching ADR-015's own choice not to use a real WordPress install for tests) and not `templates/` (plain PHP+HTML admin view partials, a distinct concern, deferred to a later pass if desired).

**`phpstan-constants.php`** (new, root, loaded via `scanFiles`, never executed at runtime): a small stub declaring `AM_PLUGIN_FILE`/`AM_PLUGIN_DIR`/`AM_PLUGIN_URL`/`AM_PLUGIN_VERSION`. The real constants in `association-manager.php` are computed via `plugin_dir_path()`/`plugin_dir_url()` calls PHPStan can't resolve to a literal value across files, so it doesn't register them for use elsewhere without this stub - the standard, documented pattern for WordPress plugins (the tool's own "discovering symbols" guidance points at exactly this).

**Result: zero baseline entries.** The plan allowed for a `phpstan-baseline.neon` to defer anything not trivially fixable. In practice every one of the 72 initial findings was a real, fixable issue - either a genuine bug/type gap or a WordPress-stub artifact resolvable with proper PHPDoc - so `phpstan-baseline.neon` stays present but empty rather than being removed, ready to receive future genuine debt without needing to re-wire the config.

**PHPCS 3.3 + WordPress Coding Standards 3.3**, ruleset `WordPress-Extra` minus `WordPress-Docs` (the mandatory-full-docblock-on-everything sub-standard - this codebase's established convention is minimal comments, self-documenting names; forcing ~150+ retroactive docblocks would reverse that), plus `PHPCompatibilityWP` (`testVersion="8.1-"`, matching `composer.json`'s actual floor - not touching the pre-existing 8.1-vs-8.3 discrepancy against `.docs/09_Coding_Standards.md`, out of scope here). `phpcs.xml.dist` scans `src/`, `database/`, `association-manager.php` - same scope reasoning as PHPStan.

### Excluded sniffs (ruleset-wide, documented inline in `phpcs.xml.dist` and here for visibility)

Each of these was individually verified against the actual code before exclusion, not assumed:

- `WordPress.Files.FileName`, `WordPress.NamingConventions.ValidFunctionName`/`ValidVariableName`, `Generic.WhiteSpace.DisallowSpaceIndent` - fundamental, unavoidable conflicts between WPCS's legacy procedural conventions (`class-foo-bar.php` filenames, snake_case, tab indentation) and this codebase's PSR-4/PSR-12 OOP style, which `docs/09_Coding_Standards.md` mandates as a co-equal requirement alongside WPCS.
- `WordPress.PHP.YodaConditions`, `Universal.Arrays.DisallowShortArraySyntax`, `Universal.Operators.DisallowShortTernary` - legacy PHP-version-compat conventions (guards against `=`/`==` typos, pre-5.4 array syntax) that are redundant with `strict_types` + PHPStan level 8, or already safely idiomatic throughout (verified: every short-ternary instance is the standard `$rows ?: []` fallback pattern).
- `WordPress.DB.DirectDatabaseQuery.DirectQuery`/`NoCaching` - this plugin's custom-tables architecture (ADR-003) intentionally bypasses `wp_cache_*()`; wrapping every repository query in object-cache calls is a distinct, larger undertaking, not a linting fix.
- `WordPress.Security.EscapeOutput.ExceptionNotEscaped` - verified every flagged instance is an exception *message* being constructed (`throw new X("...")`), not HTML output; this codebase's Services/Repositories never echo directly, and the REST layer returns structured `WP_REST_Response`/`WP_Error`. The general output-escaping sniffs (real HTML/attribute escaping) stay active.
- `WordPress.WP.AlternativeFunctions.file_system_operations_fopen`/`_fclose` - used only for `php://output` (a stream wrapper `WP_Filesystem` cannot write to at all) and for reading an already-uploaded `$_FILES[...]['tmp_name']` temp file row-by-row via `fgetcsv()` (`WP_Filesystem` has no streaming-read equivalent).
- `PHPCompatibility.Keywords.ForbiddenNames.publicFound` - verified empirically (`php -r 'namespace Foo\Public; ...'`) that `Public` parses correctly as a namespace path segment on the target PHP version; this codebase deliberately uses `Public/` as a module's public-facing layer (parallel to `Admin/`, `Rest/`, `Domain/`, `Services/`), not a reserved-word collision.
- `WordPress.Security.NonceVerification.Recommended` - verified every one of the 25 findings is read-only `$_GET`/`$_REQUEST` access for page-display filtering/pagination, the same convention `WP_List_Table` itself uses core-wide (`?paged=`, `?status=`). Every genuine mutating action already has a correctly-positioned `check_admin_referer()`/`check_ajax_referer()` call - confirmed by the stricter `.Missing` variant (kept active) never firing anywhere.
- `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` - verified every occurrence interpolates only `{$table}` (always `DatabaseManager::table()`'s hardcoded, non-user-controlled value) and/or a `{$where}` clause built entirely from hardcoded column templates by internal query builders, never raw user input. Real user-supplied values always flow through `%s`/`%d` placeholders + `$wpdb->prepare()`, which this sniff can't see across that abstraction boundary. `WordPress.DB.PreparedSQLPlaceholders.*` (catches real placeholder-count mismatches) stays active.

### Targeted inline suppressions (small, specific call sites, not ruleset-wide)

- `MemberRepository::search()`, two lines - `PreparedSQLPlaceholders.UnfinishedPrepare`/`.ReplacementsWrongNumber` false positives from the same dynamic-`$where` opacity as above (values are correctly paired via `prepare()`, just invisible to static analysis across `buildWhere()`'s return boundary).
- `MemberService::renewMembershipByPlan()` and `MembershipExpiryCalculator::cutoffFor()` - `date()` instead of `gmdate()` is intentional: both derive from `current_time('mysql')`'s site-local convention, which every other stored timestamp in this codebase follows; `gmdate()` would introduce a UTC/site-local mismatch.
- `MembersModule`'s CSV-import read loop (`while (($row = fgetcsv($handle)) !== false)`) - the standard, already comparison-guarded read-until-EOF idiom.
- Three `$request`/`$hookSuffix` unused-parameter cases (`DirectoryController::map()`, `NotificationsController::index()`, an `admin_enqueue_scripts` closure) - required by the REST route / WordPress hook callback signature, not dead code.

## PHPStan fixes worth calling out

Nothing was baselined, but a few fixes are more than mechanical PHPDoc additions:

- **`PaginatedResult` is now genuinely PHPDoc-generic** (`@template T`) - it was being used with `@return PaginatedResult<Member>`-style annotations everywhere without the class itself supporting generics, so PHPStan couldn't check the annotation at all. This one change resolved 13 separate findings across every module that paginates.
- **`Member::requireId(): int`** (new method) - `Member->id` is `?int` to represent the not-yet-persisted draft state (`Member::draft()`), but roughly a dozen call sites across `MemberService`, `MembersController`, `MemberCsvExporter`, `DirectoryService`, `EditMemberPage`, and `MembershipExpiryRunner` already know - by control flow - that they're holding a persisted member, and were passing the still-nullable `$member->id` into APIs that require a real `int`. Rather than duplicating an ad-hoc null-check or a private helper in six different classes, `Member` itself now owns the assertion (`$this->id ?? throw new \LogicException(...)`), since `Member` is the class that owns the invariant.
- **`MembersController::updateFields()`** had a genuine, if narrow, correctness gap: it re-queried the member after saving custom fields and passed the result straight into `toArray()` without checking for `null`, unlike the identical lookup a few lines earlier in the same method. Added the same null-check/404 pattern already used there.
- **`NotificationsController::update()`** was re-querying the just-saved template from the database instead of reusing the already-in-scope object it had just built and passed to `save()` - removed the redundant, nullable-typed re-query entirely rather than adding a null-check for it.
- **`MembersListTable::column_cb()`/`column_default()`**: an initial attempt to add real `Member $item` type hints was reverted - `WP_List_Table`'s parent class declares these params untyped, and narrowing a child method's parameter type is a Liskov substitution violation PHPStan correctly flags as non-ignorable (`method.childParameterType`). Fixed with PHPDoc-only `@param Member $item` instead, which gives the same static-analysis benefit inside the method body without changing the actual (loosely-typed, parent-compatible) signature.
- **`MembershipExpiryCalculator`**: `$plan?->gracePeriodDays ?? 0` simplified to `$plan->gracePeriodDays ?? 0` - confirmed via an isolated reproduction that PHP's `??` operator evaluates its left operand with `isset()`-like semantics for property-access chains, so plain `->` is already null-safe immediately before `??`; the `?->` was genuinely redundant, not a false-positive PHPStan claim.
- Two real, if minor, robustness gaps: `fopen('php://output', 'w')` in the CSV export handler now guards against a `false` return before use, and the CSV import handler's header row is coerced to `string[]` before being used as `array_combine()`'s key list (a malformed/empty header cell would otherwise be a `null` key, which `array_combine()` rejects).

`composer.json`'s `stan` script bakes in `--memory-limit=1G` (PHP's default 128M isn't enough for this codebase plus the WordPress stub package).

## Consequences

Positive:

- Full `src/`+`database/` coverage at PHPStan level 8 with zero deferred debt, and a clean `WordPress-Extra`-derived PHPCS pass, achieved in one pass rather than an ongoing backlog.
- Two genuine (if narrow) bugs fixed as a direct result: the `updateFields()` missing null-check and the `fopen()`/`array_combine()` robustness gaps.
- `Member::requireId()` makes an implicit invariant (this member is persisted) explicit and centrally enforced instead of silently assumed at each call site.
- `composer test`, `composer stan`, `composer cs` are all real, fast, CI-ready commands now.

Negative:

- `phpstan-constants.php` is a small piece of duplicated truth (constant names/types, not values) against `association-manager.php`'s real `define()` calls - if a constant is renamed in the bootstrap file, this stub needs a matching update, or PHPStan will flag the old name as unused/the new one as undefined without necessarily explaining why.
- The excluded-sniff list is long. Each exclusion is individually justified and documented, but a future contributor unfamiliar with this ADR could mistake the length of the list for looseness rather than precision - this ADR and the inline `phpcs.xml.dist` comments are the mitigation, not a shorter list.
- `templates/` and `tests/` remain outside both tools' scope, same caveat as ADR-015's own scope boundary - the `WP_List_Table`/Leaflet-JS/admin-template surface still has no static-analysis or automated-test coverage, only real-staging verification.
- No CI wiring yet (still Epic 9 scope, same as ADR-015 already noted) - all three tools (`composer test`/`stan`/`cs`) must be run manually.
