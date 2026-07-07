# ADR 001: Database migrations

## Status

Accepted

## Context

Association Manager requires custom database tables for association-specific data.

WordPress provides options and post meta, but the product needs structured, queryable, long-term data for members, payments, approvals, statuses, and future modules.

## Decision

We will use custom WordPress database tables with a simple internal migration system.

Each migration:

- has a unique ID
- runs once
- is stored in the `association_manager_migrations` option
- is never modified after being committed

## Consequences

Positive:

- predictable schema evolution
- safer upgrades
- cleaner separation from WordPress posts/meta
- better long-term product architecture

Negative:

- more responsibility for upgrade handling
- migrations must be written carefully
- rollback is not included in the initial version