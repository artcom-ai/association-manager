# ADR 014: Directory Engine (public/private views, search, maps)

## Status

Accepted

## Context

Directory has been public-only since Sprint 5 (ADR-004): a fixed allow-list, no search, no map, unpaginated shortcode. The roadmap's "Directory Engine" asks for Public, Private, Search, Maps, REST endpoints.

## Decision

**Visibility becomes a property of custom fields, not of the Directory module.** `FieldDefinition` gains `visibility` (`public`/`private`/`admin`, default `admin` - existing fields registered before this sprint stay admin-only unless explicitly changed) and a pure `isVisibleTo(string $viewerLevel): bool` using a rank (`public < private < admin`, so a "private" viewer also sees "public" fields). This is the same registry-driven idiom as statuses (ADR-010) and plans (ADR-012), now applied to a visibility axis rather than existence - Directory doesn't need its own field-selection logic, it just asks each `FieldDefinition` "are you visible to this viewer level."

**Private is "any logged-in WordPress user"** (`is_user_logged_in()`), not "a user linked to an active Member" - simpler, and there's no login-to-member wiring in the product yet to build on. `DirectoryService::paginatePrivate()` shares the exact same query as `paginate()` (`search()` with `status: active`, from Sprint 9) - the only difference is which visibility level gets passed into entry-building, plus `status`/`expires_at` are always included at "private" level, same as they always are for a private-visibility custom field. Neither view ever exposes `id`/`wp_user_id`/`uuid` - that allow-list line from ADR-004 doesn't move just because the viewer is authenticated.

**Search reuses `MemberSearchCriteria`/`search()` unchanged** - both `paginate()` and `paginatePrivate()` take an optional `$search` string and pass it straight through. No EAV join against custom field values was added (per the decision) - if searching by a custom field value becomes a real need later, that's an addition to `MemberRepositoryInterface::search()` or a new query, not something bolted onto Directory.

**Maps are manual-entry, not geocoded.** A new `FieldDefinition::TYPE_LOCATION` stores `"lat,lng"` as plain text (validated by regex + range, admin enters it directly - no address-to-coordinates lookup, no third-party geocoding API/key). `DirectoryService::mapPoints()` finds the first registered `member` field of this type (documented limitation: one location field, first-registered wins) and loops every page of active members (capped at 100/page per ADR-008, so a single large `PaginationParams` request would have silently truncated a bigger directory - this loops until `totalPages()` is exhausted instead). Rendering uses Leaflet.js + OpenStreetMap tiles, loaded from the `unpkg.com` CDN, no API key required. This is a real external-request dependency (CDN, tile server) that a stricter/GDPR-sensitive deployment may want to replace with self-hosted assets and a self-hosted tile source later - noted as a known trade-off, not addressed here.

**`listPublicEntries()` (Sprint 5, made redundant by `paginate()` in Sprint 8) is removed.** The shortcode now uses `paginate()` directly with GET-based search (`?am_search=`) and pagination (`?am_page=`) - no JS, matching how little client-side complexity this project uses outside the already-flagged Quick Edit (ADR-013). Two new shortcodes, `[association_manager_directory_private]` and `[association_manager_directory_map]`, plus corresponding REST routes (`/directory/private` gated by `is_user_logged_in`, `/directory/map` public).

## Consequences

Positive:

- Custom field visibility is opt-in and safe-by-default (`admin`) - nothing already registered becomes accidentally more exposed by this sprint.
- Public and private views are one code path (`paginateFor()`) differing only by which rank gets passed in - no risk of the two views drifting out of sync over time.
- Map data comes from the exact same `Member`/field-value machinery as everything else - no parallel "member location" table or separate sync step.

Negative:

- Only one location field is supported per entity type (first-registered wins if more than one is somehow registered) - fine for the "one association, one address field" case this was built for, would need real design work to support multiple location fields per member.
- Leaflet/OSM tiles load from third-party CDNs on every page with the map shortcode - a privacy-conscious or offline-hosting requirement would need self-hosted assets, not addressed here.
- As with Sprint 12's Quick Edit, the actual Leaflet map rendering in a browser cannot be verified in this environment - it needs a real staging check.
