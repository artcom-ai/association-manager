# ADR 002: Admin menu registration

## Status

Accepted

## Context

Association Manager needs a WP-Admin presence. Per the module system, each future module (Members, Directory, Payments, Events, ...) owns its own Admin UI, but WordPress admin menus are a single global registry (`add_menu_page` / `add_submenu_page`), and something has to own the shared top-level entry point so modules aren't each creating their own competing top-level menu.

## Decision

Core provides a generic `AdminMenu` registry (`AssociationManager\Core\Admin`):

- `AdminPageInterface` describes a single admin page (slug, parent slug, titles, capability, `render()`), independent of any specific module.
- `AdminMenu::register()` collects pages; `AdminMenu::boot()` hooks `admin_menu` once and builds the actual WordPress menu tree from registered pages.
- Core registers one built-in top-level page, `DashboardPage` (slug `association-manager`), as the shared parent menu.
- Future modules register their own `AdminPageInterface` implementations against the same `AdminMenu` instance (via the container), using `association-manager` as `parentSlug()` to appear as submenus. Core never knows about module-specific pages.

## Consequences

Positive:

- Core stays implementation/module-agnostic (ADR-001) — it only knows the `AdminPageInterface` contract, not any concrete page.
- Single, predictable place (`admin_menu` hook) building the whole menu tree, avoiding duplicate top-level menus per module.
- New modules add admin screens without touching Core.

Negative:

- Modules must be booted (registered into the container) before `CoreServiceProvider::boot()` runs, or must register pages into `AdminMenu` before the `admin_menu` action fires — ordering matters once real modules exist.
- Capability is currently fixed to WordPress' built-in `manage_options`; a dedicated capability/permissions model (per module) is deferred to when the permissions system is designed.
