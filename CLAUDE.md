# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project status

**Pre-implementation.** This repo currently contains only the vhost scaffold (`public/`, `logs/`) and the design spec — no application code exists yet. There is no build system, package manifest, linter, or test runner to document until code is written. When you add the first real code, update this file with the actual commands (composer scripts, PHPUnit invocation, etc.) rather than guessing them now.

The full specification lives at `docs/spec-v0.10.md` (976 lines). It is the single source of truth for every rule, formula, phase order and open decision below — this file only orients you to its structure so you don't have to re-read it in full each session. Grep for a `§` section number or keyword instead of reading it end to end.

## What this is

**Caelorn** (working name — trademark clearance pending, spec §11 item 1; host `caelorn.silverday.de`, owned by SilverDay Media) is a browser-based, asynchronous, simultaneous-turn space 4X strategy game inspired by VGA Planets 3's loop (fixed cluster map, mineral economy, starbases, ship design, simultaneous turn hosting) — but reimplemented with server-authoritative state, documented formulas, fleet combat, AI opponents and replay. **No franchise names, races, ships or assets are reused** (spec §1.1) — this is binding.

Stack: PHP 8.3 (strict types), MariaDB 10.11+, Apache, a JSON front-controller API at `/api/v1`, and a vanilla-JS ES-module Canvas client. No framework.

## Architecture (spec §5)

```
Browser (Canvas client, vanilla JS)  --HTTPS/JSON-->  Apache -> PHP front controller
                                                          ├─ API layer (auth, validation, known-world reads, order writes)
                                                          ├─ Domain layer: rules engine — pure PHP, no I/O
                                                          └─ Persistence (PDO, prepared statements)
CLI workers (systemd timers/cron): host-runner, ai-runner, mailer
```

The **rules engine is a pure library**: `(state, orders, ruleset, seed) -> (new state, events)`. No I/O, fully unit-testable and replayable. Human API requests and the AI worker call the *same* order-validation and known-world-read services — AI gets no privileged shortcut or hidden-state access (spec §3.12).

### Three invariants that shape almost every module

1. **Determinism.** Per-game secret seed; per-turn RNG is HMAC-DRBG keyed on `(seed, turn_number, phase, stable_event_key)`. Host runs must be bit-for-bit reproducible from `(previous state, all orders, ruleset version, seed)`. This means: integer/fixed-point arithmetic only in the authoritative host (no floats), stable entity ordering (never SQL retrieval order), canonical JSON encoding, named rounding helpers (never PHP's bare `round()`). See spec §5.4 and §15.2.
2. **Fog-of-war / server authority.** Clients submit **orders only**, never state. "Known-world" materialization (per-slot visibility filtering) is the single choke point for state disclosure — no endpoint, error message, timing difference, or ID sequence may leak hidden information (e.g. IDs must be opaque/random, not sequential). See spec §7.2 — this is the core anti-cheat model and needs dedicated negative tests per endpoint.
3. **Ruleset immutability per game.** Every game pins an immutable "rules bundle" at creation (ruleset JSON + ship/component catalogs + trait definitions + map params + combat params + phase-schema version, all content-hashed). Balance changes never alter running games (spec §5.3).

### Host turn execution (spec §5.5)

The host resolves a turn in a fixed 15-phase sequence over a frozen input snapshot (a later phase never retroactively affects an earlier one): acquire/lock → diplomacy → pre-movement → movement → path hazards (mines) → intercept/encounter grouping → space combat → orbital/ground combat → post-combat actions → planet economy → starbase actions → mine operations → visibility → score/victory → commit. State machine for one execution: `pending → locked → resolving → committed` (or `retryable_failure`, retried on the *same* frozen input). Only one execution may ever commit for a given `(game_id, turn_number)` — see §5.5.1 for lease/fencing/recovery details before touching host concurrency.

Resource allocation when drafts exceed a planet's stock follows a fixed fallback-queue tier order (structures → ammo/supplies → ship repairs → starbase construction, then ascending order ID) — spec §3.4.

### Ruleset profiles — don't blend them

- **MPR-1** (Appendix A) — the minimum playable ruleset: 2–4 players, 80 planets, logistic population growth, surface-stock mining. This is the current default/headless-test profile.
- **SCC-1** (Appendix B) — the ship/component catalog paired with MPR-1.
- **PHost 4.1h reference baseline** (§15) — documents selected PHost mechanics as an independently-implemented *reference*, tagged `REF` (directly from PHost docs), `ADAPT` (deliberate Caelorn change), or `PENDING` (not yet approved — **must not be executable**). This is a separate `phost-inspired` profile; never silently substitute its formulas into MPR-1 or vice versa (§15.9 rule 1). No PHost source/data is imported — original implementation only, with attribution in `NOTICE`.

### Subdomain isolation (spec §7.1)

Caelorn shares the `silverday.de` apex with sibling services. Session cookies use the `__Host-` prefix (no `Domain` attribute), no cross-subdomain CORS, and separate Apache vhost / PHP-FPM pool / system user / DB credentials from other silverday.de services. Don't introduce anything that assumes shared session state with a sibling vhost.

### Data model and API shape

- Per-turn, append-only authoritative state (`turns`, `planets_state`, `ships_state`, `starbases_state`, `minefields_state`, …) to support replay and post-game review — see §5.6 for the full table outline.
- `/api/v1` is JSON, uses `ETag`/`If-Match` optimistic concurrency on order writes, returns `409` on locked/stale turn revisions, requires idempotency keys for creation/state-changing calls, and uses a standardized `{code,message,details,request_id}` error shape that never discloses hidden state (§5.7).
- A separate internal `/admin/sim/…` area (read-only JSON under `/api/v1/admin/sim/…`, roles `sim_viewer`/`sim_reviewer`) exposes the automated-game replay/dashboard tooling (§14.9) — hard-separated from live game data; it must never be able to load a live game ID.

### Testing strategy (spec §8, §14)

- Rules-engine unit tests per phase with golden-file tests: `(state, orders, seed) -> expected state hash`.
- Property tests: resource conservation, no negative stocks, determinism across reruns, fog-of-war leak tests per endpoint.
- A headless AI-vs-AI simulation harness (§14) runs smoke/regression/balance/stress batches against an immutable experiment manifest, for balance telemetry — this must never affect a live game's pinned ruleset, and AI controllers in the harness get the same authorized known-world view as in production (the evaluator may inspect full state only *after* each turn, for analysis).

### Delivery milestones (spec §10, acceptance gates in §12)

M0 rules contract → M1 headless playable → M2 multiplayer integrity (accounts/lobby/locking) → M3 combat + UI → M4 social/AI/simulation viewer → M5 custom races/map editor → M6 public beta. Each milestone has a hard acceptance gate (§12) — check it before considering a milestone's work done.

### Open decisions (spec §11)

Several values are explicitly **not yet pinned** — e.g. combat parameter values (`weapon_potential`, stance weights, retreat curve — required before M3), resource-allocation priority details (required for M1), map-fairness radius/weighting (required for M1), and the final project name/trademark clearance (item 1). Don't treat provisional/candidate numbers in the spec (marked "candidate", "provisional", or in Appendix A/B) as settled balance — they're explicitly unplaytested.
