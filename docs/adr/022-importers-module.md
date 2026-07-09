# ADR 022: Importers module (MemberPress Importer MVP)

## Status

Accepted

## Context

The ELESYTH pilot has real member data sitting in an existing WordPress/MemberPress install (`wp_users`, `wp_usermeta`, MemberPress's own custom fields stored as usermeta) that the Member Portal needs to show. The explicit goal was a *generic* importer, not a one-off script: "Keep importer generic enough to support future importers," with MemberPress-specific code required to live in "an integration/importer namespace, not Core," and ELESYTH's own field mapping required to live in the ELESYTH implementation layer.

**Classification: mostly Module, one small Implementation contribution.** `Modules\Importers\` (generic orchestration/contracts) and `Modules\Importers\MemberPress\` (MemberPress's own reading logic - reusable by any association using MemberPress, not ELESYTH-specific) are both Module-classified. Only the actual mepr-slug -> Association Manager field-key mapping is Implementation-classified, living in `association-manager-elesyth`.

## Decision

**`ImportSourceInterface` (`key()`, `label()`, `fetchRows(): ImportRow[]`) is the only thing `MemberImportService` knows about.** It has no idea "memberpress" exists - a future CSV or third-party-API importer only needs to implement this interface and register itself in `ImportSourceRegistry`; nothing in the orchestration layer changes. This is what makes `wp association-manager import <source>` source-agnostic rather than MemberPress-specific.

**Field mapping is a registry, not a database table** (`Modules\Importers\FieldMappingRegistry`, `register(string $sourceSystem, FieldMapping $mapping)` / `forSource(string $sourceSystem): FieldMapping[]`) - the same shape as `Core\Fields\FieldRegistry`, which this codebase already established as the correct pattern for "an implementation configures something without touching Core." This satisfies "configurable field mapping layer, not hardcoded SQL" without inventing a mapping-editor admin UI, which wasn't asked for and would have been real scope beyond an MVP.

**`MemberPressUserSource` reads directly from `wp_users`/`wp_usermeta` via `get_users()`/`get_user_meta()`** - no MemberPress plugin classes, hooks, or Composer package anywhere in this codebase. This is what "do not make Association Manager depend permanently on MemberPress" means literally: the importer only needs MemberPress's *data* to already exist in WordPress-core tables, not the MemberPress plugin to be active. A WP user only becomes an `ImportRow` if at least one of their usermeta keys starts with a configurable prefix (default `mepr`) - without this filter, every ordinary WP user (admins, subscribers with no MemberPress relationship) would import as a member, which isn't what "MemberPress Importer" means.

**Only `wp_users`/`wp_usermeta` are read - MemberPress's own transaction/subscription tables (`wp_mepr_*`) are not.** The requirement scoped source data to exactly those two tables plus "MemberPress user meta/custom fields," which live in `wp_usermeta`. Consequently `membershipType` is never set by this importer (it's a native `Member` column, not a "dynamic field," and the requirement specifically said "map MemberPress fields to Association Manager *dynamic* fields") - deliberately out of scope, not an oversight.

**Idempotency: `Member` gains three new nullable columns** (`source_system`, `source_user_id`, `imported_at` - migration 016, purely additive `dbDelta()`, same pattern as every prior migration) **and two lookup paths, checked in order**: `findBySource($sourceSystem, $sourceUserId)` first (a previous run of this exact importer), falling back to `findByWpUserId($wpUserId)` (a member that already existed - e.g. created manually via the pre-existing "link WP account" admin control - before this importer ever ran). Either match means "update," never "create." This is what makes re-running the importer safe, and also backfills source tracking onto members an admin already created by hand, rather than treating them as untouchable.

**Every existing `new Member(...)` reconstruction site in `MemberService` had to be updated to pass the three new fields through explicitly.** Since they default to `null`, forgetting even one call site (`updateMembershipType()`, `linkWpUser()`, `importRow()`'s update branch) would have silently wiped an imported member's source tracking the next time an admin used an unrelated feature like Quick Edit - audited and fixed all three before this was considered done, not discovered later via a bug report.

**Dry-run and commit share one code path** (`MemberImportService::processRow()`, branching only on whether `$commit` triggers actual writes) - a dry-run report is guaranteed to describe what a commit run would really do, rather than two independently-maintained implementations drifting apart. Conflicts are detected by comparing each mapped field's freshly-computed value against `FieldValueService::valuesFor()`'s currently-stored value (and email similarly) - flagged for visibility, not blocking; import is deliberately "last-import-wins."

**Admin page is deliberately thin**: pick a registered source, dry-run or commit, see a report (counts, missing required fields, unmapped source keys, per-row conflicts) rendered from a short-lived transient. No mapping editor - mappings are `FieldMappingRegistry` config, not admin-editable data in this pass.

**WP-CLI command is a plain `__invoke()`-based class, not a `WP_CLI_Command` subclass**, registered via `WP_CLI::add_command()` only behind `class_exists( WP_CLI::class )` in `ImportersModule::boot()` - safe to autoload on any request, since the class only *references* `WP_CLI` inside method bodies, never at file-load time. `php-stubs/wp-cli-stubs` was added as a new dev dependency (scanned by `phpstan.neon`, mirroring how `szepeviktor/phpstan-wordpress` already covers WordPress core) so PHPStan can actually verify this file instead of needing a baseline entry for an "unknown class" - a real stub-coverage gap, not a suppressible false positive.

## Consequences

Positive:

- The generic/MemberPress/ELESYTH three-way split was validated for real: adding a second future importer requires zero changes to `MemberImportService`, `ImportPage`, or `ImportCommand` - only a new `ImportSourceInterface` implementation and its own `FieldMapping` registrations.
- Idempotency was tested directly (running the same import twice, asserting exactly one member exists both times) rather than merely reasoned about.
- 13 new tests cover field mapping, dry-run reporting (missing/unmapped/conflicts), member creation, member update, the wp_user_id fallback backfill, and duplicate prevention - all against a fake `ImportSourceInterface`, so none of them depend on real WordPress user functions.

Negative:

- `MemberPressUserSource`'s actual WP-glue (`get_users()`/`get_user_meta()`) has no unit test, same precedent as every other WP-glue class in this codebase (REST controllers, Shortcode/Admin-page `render()`) - a real staging pass against an actual MemberPress-populated database is still needed before trusting this end-to-end.
- The `mepr` meta-key-prefix filter is a pragmatic heuristic, not a MemberPress API guarantee - if ELESYTH's real installation uses a different naming convention, `MemberPressUserSource`'s constructor argument needs adjusting (or `ImportersModule` needs to pass a different prefix) once real data is seen.
- Field-mapping misconfiguration (a `FieldMapping` targeting a field key nobody registered in `FieldRegistry`) fails silently - `FieldValueService::save()` already silently skips unregistered field keys (existing, correct behavior elsewhere in this codebase), but this importer doesn't add its own detection/warning for that specific misconfiguration. Not a blocker for the MVP (ELESYTH's own mappings target fields ELESYTH itself registers, so this can't happen with the shipped configuration), but worth a defensive check if a mapping-editor UI is ever built.
- No admin-facing mapping editor exists - changing which meta keys map to which fields requires a code change (in `association-manager-elesyth`) and a deploy, not a wp-admin action.
