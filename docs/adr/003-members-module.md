# ADR 003: Members module structure

## Status

Accepted

## Context

Members is the first real business module (Sprint 4), built on top of the `am_members` table (ADR/migration from Release 0.1) and the module/admin scaffolding from Sprints 1-3. It sets the structural template future modules (Directory, Payments, Events, ...) are expected to follow.

## Decision

Each module lives under `src/Modules/<Name>/` and is wired into the plugin only through `ModuleInterface` (`register()`/`boot()`), matching the existing `ServiceProviderInterface` lifecycle:

- `Domain/` — plain, persistence-agnostic value objects (`Member`, `MemberStatus`). Immutable (`readonly` properties); state transitions (`approve()`, `suspend()`) return a new instance rather than mutating in place.
- `Repositories/` — a `*RepositoryInterface` the rest of the module depends on, plus a `$wpdb`-based implementation. All queries go through `DatabaseManager::table()` and use `$wpdb->prepare()`/`$wpdb->insert()`/`$wpdb->update()` — no raw string interpolation of user input into SQL.
- `Services/` — orchestrates repository + domain rules (`MemberService::register/approve/suspend/find/all`). This is the only layer other code (Admin, REST) is allowed to call into; nothing reaches into the repository directly except the service.
- `Admin/` — an `AdminPageInterface` implementation registered as a submenu under Core's `association-manager` parent menu (see ADR-002). Depends only on the module's own service.
- `Rest/` — a controller registered on `rest_api_init`, under the shared `association-manager/v1` namespace, calling the service and mapping domain objects to arrays at the boundary.

`Kernel::registerModules()` is the single place first-party modules are wired into `ModuleManager`. This is acceptable coupling for modules that ship with the product itself (per the roadmap); it is not a channel for client/implementation-specific logic, which must stay out of Core entirely (ADR-001).

## Consequences

Positive:

- Consistent shape across modules makes them predictable to review and to extend.
- Domain layer has zero WordPress dependency, so business rules (status transitions) are trivially unit-testable without a database.
- Swapping persistence (e.g. caching, a different storage engine) only requires a new `MemberRepositoryInterface` implementation.

Negative:

- Authorization is currently a flat `manage_options` check in both the admin page and the REST controller; a per-module/action capability model is still deferred, same limitation noted in ADR-002.
- No pagination/filtering on `MemberService::all()` yet — fine at foundation stage, will need revisiting once member counts grow.
