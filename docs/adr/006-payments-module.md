# ADR 006: Payments module

## Status

Accepted

## Context

Sprint 6 adds bookkeeping for member payments: recording a payment, and moving it through a lifecycle (pending → completed / failed, completed → refunded). This is not a payment gateway integration (no Stripe/PayPal/webhook handling yet) — it is the internal record-keeping layer future gateway integrations will write into.

## Decision

Follows the module structure convention from ADR-003 (`Domain`/`Repositories`/`Services`/`Admin`/`Rest`), with two payments-specific choices:

- **Money is stored and passed around as integer cents (`amount_cents`)**, never as float, to avoid floating-point rounding errors on monetary values. Currency is a separate `VARCHAR(3)` (`EUR` default) rather than baked into the amount.
- **Status transitions are validated in the domain object, not the service**: `Payment::refund()` throws `\LogicException` if the payment isn't `completed`. The REST controller maps that to HTTP 409, and `RuntimeException` (not found) to 404 — the two failure modes are distinguished, unlike Members' lifecycle which only has a single "not found" failure case.
- Admin/REST are, for now, the only way to move a payment through its lifecycle (`complete`/`fail`/`refund` as authenticated `manage_options` actions). There is intentionally no public or member-facing endpoint here, unlike Directory.

Payments does not depend on `MemberRepositoryInterface` to validate that `member_id` refers to a real member — that cross-module check is deferred; a payment currently records whatever `member_id` it's given.

## Consequences

Positive:

- Integer-cents avoids an entire class of rounding bugs common to float-based money handling.
- Clear separation between "not found" (404) and "invalid state transition" (409) gives REST API consumers an actionable distinction.

Negative:

- No referential check against Members means a payment can be recorded against a non-existent `member_id`; this is a known gap, to be closed either when a Committees/Payments-Members integration is designed, or with a lightweight existence check added to `PaymentService::record()`.
- No gateway/webhook integration yet — `record()`/`complete()` must currently be called manually (e.g. from the admin UI or REST), there is no automatic transition on an external payment provider's callback.
