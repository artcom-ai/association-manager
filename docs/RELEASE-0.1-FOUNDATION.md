# Release 0.1 Foundation

## Goal

Establish the technical foundation of Association Manager.

This release does not introduce user-facing functionality.  
It prepares the database, migration mechanism, documentation structure, and architectural decision records.

## Scope

- Database table naming convention
- Migration runner
- Initial members table
- Documentation structure
- ADR structure

## Database Prefix

All custom plugin tables use the `am_` prefix after the WordPress table prefix.

Example:

```text
wp_am_members