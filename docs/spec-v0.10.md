# Caelorn — Specification for a Web-Based Turn-Based Space 4X (VGA Planets-inspired)

| Field | Value |
|---|---|
| Name | **Caelorn** (from Latin *caelum*, sky; replaces `ORRERY` and `Caelorn`; trademark clearance pending, see §11 item 1) |
| Host | `caelorn.silverday.de` |
| Owner | SilverDay Media |
| Status | Draft v0.10 — renamed to Caelorn, host `caelorn.silverday.de` |
| Date | 2026-10-14 |
| Supersedes | Draft v0.9 |
| Rules fidelity | **Hybrid**: classic VGA Planets 3 core loop, modernised economy and combat |
| Audience | Public SilverDay service at `caelorn.silverday.de` (open sign-up, many concurrent games) |
| Stack | PHP 8.3 (strict types), MariaDB 10.11+, Apache, front-controller JSON API, vanilla-JS Canvas client |

---

## 1. Purpose and positioning

A browser-based, asynchronous, simultaneous-turn space strategy game for 2–11 players, recreating the *feel* of VGA Planets 3 (fixed cluster map, minerals, starbases, ship design, simultaneous turn hosting, diplomacy by message) while fixing what aged badly: client-side trust, opaque formulas, 1-on-1-only combat, no AI, and no replay of what happened.

**Not a goal:** reproducing VGA Planets 3 formulas, host order, file formats, or content verbatim. Planets.nu already serves that niche. This project borrows the game *loop*, not the game.

### 1.1 Intellectual property constraints (binding)

- No use of the name "VGA Planets", its logo, race names, ship names, or the "Echo Cluster" name.
- No races, ships or factions identifiable as franchise properties (Star Trek, Star Wars, Battlestar Galactica, etc.). Races are defined by **mechanical archetype**, with original names, lore and art.
- No reuse of original game data files, graphics or text.
- Game mechanics and genre conventions (4X, simultaneous turns, mineral economy) are used freely.
- Action item: legal review of title and race names before public launch.

---

## 2. Glossary

| Term | Meaning |
|---|---|
| Cluster | The game map: a 2D field of star systems |
| Turn | One simulation step; players submit orders, the host resolves them simultaneously |
| Host | The server-side turn processor |
| Orders | A player's intended actions for the next turn; the only thing a client can submit |
| Known world | The subset of game state a given player is entitled to see in a given turn |
| Archetype | A race's mechanical trait package |
| Battle log | Deterministic event stream of a combat, used for the replay viewer |
| Observation | Current-turn information directly detected by a player |
| Intelligence memory | Previously observed information retained with observation turn and freshness |
| Order revision | Monotonically increasing revision of a slot's complete order set |
| Host execution | One uniquely identified attempt to resolve a game turn |
| Provisional default | Initial rule selected for implementation/testing, subject to balance validation |

---

## 3. Game design

### 3.1 Core loop

1. Player receives the turn result (known world + event report).
2. Player issues orders: ship movement, missions, builds, transfers, taxes, diplomacy, messages.
3. Player marks the turn **ready** for the current order revision. Editing orders automatically clears ready status. An early host may lock the turn once all required human-controlled slots are ready; after lock, orders cannot change.
4. Host runs when all active players are ready, or at the deadline.
5. Repeat until a victory condition is met.

### 3.2 Map

- Default: ~500 planets in a rectangular field (configurable 50–1500), optional wraparound.
- Optional **nebulae** (reduce scan range / weapons), **ion storms** (moving hazards), and **star clusters** (radiation damage) — all toggleable per game.
- Homeworld placement enforces a minimum distance and roughly equal local resource value (see §3.10 Map generator).
- **Provisional starting preset:** 2–11 slots, 500 planets, one homeworld and one basic scout per slot, equal starting credits and supplies; precise initial stocks, ships and local-resource thresholds are ruleset data and must be finalized before M1 acceptance.

### 3.3 Resources

| Resource | Use | Source |
|---|---|---|
| Fuel (*Deuterite*) | Ship movement, torpedo production | Mined |
| Structural ore (*Ferrite*) | Hulls, structures | Mined |
| Component ore (*Corundite*) | Engines, beams | Mined |
| Exotic ore (*Iridium*) | Advanced components, torpedoes | Mined |
| Credits | Universal currency | Taxes, trade, selling supplies |
| Supplies | Construction labour, ship repair | Factories |
| Colonists | Population; required to work planets | Growth, transport |

Names are placeholders (original, non-derivative). Each planet has a surface stock and a core stock of each ore, with a **density** (0–100 %) governing extraction rate.

### 3.4 Planet economy (modernised)

All numeric values below are **initial tuning parameters**, not settled balance. They live in a per-game `ruleset` (§5.3) so they can be adjusted without code changes.

- **Population growth** — logistic:
  `ΔP = r · P · (1 − P / K) · climate_factor(archetype, planet_temp)`
  with `r` initially 0.05/turn and `K` the planet's climate-dependent capacity.
- **Structures**: mines, factories, defence posts. Maximum count is a function of population (diminishing returns, e.g. `max = a + b·√P`).
- **Mining**: `extracted = min(surface_stock, mines · base_rate · density · archetype_mining_mod)`; core ore migrates to surface at a slow rate.
- **Taxes and happiness**: tax rate drives credits and lowers happiness; happiness below thresholds reduces growth, then output, then triggers unrest (lost structures). Replaces opaque v3-style behaviour with a documented curve shown in the UI.
- **Natives** (optional per game): native populations with government level and tax potential; can be taxed, integrated, or ignored. Archetype modifiers apply.
- **Transparency rule**: every derived number in the UI must show its formula breakdown on hover/tap (e.g. "Mining 42 = 30 mines × 2 × 70 %").
- **Accounting and resource fallback queue:** orders are drafts and do not reserve stock on save. At host, resource-consuming orders execute against the authoritative stock of the paying planet. Player-configurable order priorities are a later feature; until then (and whenever a player sets none) every planet's phase 10–11 drafts are allocated at the **start of phase 10** in this fixed tier order:
  1. Structure builds (mines, factories, defence posts)
  2. Ammunition and supply production
  3. Ship repairs
  4. Starbase ship construction

  Within a tier, drafts are ordered by ascending stable order ID. Allocation reserves stock; each draft then executes in its own phase (structures in phase 10, the rest in phase 11). A draft that cannot be fully funded gets status `failed` with a reason code and consumes nothing, unless its action type is explicitly splittable (e.g. "build up to N mines"), in which case it executes the affordable units as `partially_executed`. Never negative stocks. **Pre-movement refuelling (phase 3) is not part of the queue**: it must happen before movement and is paid first, from stock at phase 3.
- **Calculation:** authoritative quantities use integers or fixed-point units; each formula defines clamping and rounding (default: floor nonnegative output at phase boundary). Population and happiness cannot fall below zero; happiness is clamped to 0–100.
- **Economy sequence:** population/happiness, tax income, ore migration/mining, factory supplies, structure construction, then starbase production. Newly captured/colonised planets do not generate economy output until the following turn (provisional default).

### 3.5 Starbases and production

- A planet can host one starbase (cost: credits + ores + supplies).
- Starbase tech levels (1–10) per track: **hulls, engines, beams, torpedoes**. Higher tech unlocks components.
- Starbase functions: build one ship per turn (modernisation option: configurable build queue), repair, refuel, recycle, build defence and fighters.
- **Ship design**: hull (from archetype's hull list) + engine + beam/torpedo/fighter loadout. Designs are saved as templates.
- **Catalog contract:** every hull specifies mass, fuel tank, cargo, crew, engine slots, weapon mounts, fighter capacity and cost; every component specifies tech prerequisite, mass, cost, efficiency and compatibility. Catalogs are immutable, versioned ruleset assets.
- **Ship lifecycle:** create with immutable opaque ID; allow renaming, repairs, refuelling, transfers, recycling and destruction. No negative cargo/fuel/crew; destruction invalidates later orders referencing the ship. Repair and construction consume resources in the economy allocation order.

### 3.6 Ships and movement

- Ships move along waypoints in straight lines; speed = warp factor 1–9 (squared-distance model is not mandated; define `distance_per_turn = warp²` as the initial value).
- **Fuel cost** = `f(mass, distance, warp, engine_efficiency)` — a lookup curve per engine, published in the ruleset.
- **Movement contract:** a waypoint is a persistent destination until reached or replaced. Travel distance per turn is capped by warp² (provisional). Fuel is calculated for actual travel, using starting mass plus cargo; if fuel is insufficient, travel is reduced to the greatest affordable deterministic distance, or zero. No negative fuel. Wraparound maps use the shortest toroidal displacement with a fixed tie-break.
- **Encounter and intercept contract:** moving paths are checked for minefield exposure along traversed segments, against minefields **active at the start of the turn** (§3.8). An intercept mission recomputes its target's position and adjusts its own trajectory during movement (phase 4); it never triggers combat at range. Combat is only triggered by co-location after movement (phase 6 grouping, phase 7 combat). Towing requires co-location at phase start, an eligible tow target, and a deterministic single-tower arbitration; towed ships do not independently move that turn.
- **Missions** (one per ship per turn): explore, colonise, transport, tow, intercept, patrol, mine-lay, mine-sweep, cloak (archetype-gated), capture (archetype-gated), bombard, salvage.
- **Friendly codes** (VGA-style 3-char codes) are **not** reproduced; their useful functions (e.g. "don't fire", "beam up fuel") are explicit order options.

### 3.7 Combat (modernised)

Replaces strict 1-on-1 duels with **fleet engagements**.

- **Trigger**: hostile units (per diplomacy matrix) sharing coordinates after phase 4 movement, or co-located with a hostile planet/starbase. No radius-based combat.
- **Stance per ship**: aggressive, defensive, evasive, hold fire.
- **Weapon classes**:
  - *Beams* — range-limited, strong vs. shields and fighters.
  - *Torpedoes* — consume ammunition, strong vs. hull.
  - *Fighters* — launched from carrier bays; screened by beams.
- **Damage model**: shields → hull → crew; crew loss enables capture by capture-capable archetypes.
- **Resolution**: discrete rounds, target selection by stance + threat score, **deterministic** given the game seed and turn number (§5.4).
- **Combat contract:** each engagement has a stable ID and participants fixed at combat start. Combat uses fixed-point/integer arithmetic. Hold-fire ships do not initiate attacks but may defend if configured.
- **Deterministic fleet engagement mechanics (v0.8, provisional):**
  - **Grouping:** all co-located, mutually hostile, combat-eligible units (ships, planets, starbases) form one engagement.
  - **Rounds:** up to `max_combat_rounds` (default 10) rounds.
  - **Target selection:** each round every attacker scores each eligible target:
    `score = weapon_potential × stance_weight + damage_penalty`.
    `weapon_potential` (the target's expected damage output this round), `stance_weight` (per attacker stance) and `damage_penalty` (bonus for already-damaged targets, to focus fire) are ruleset parameters with defined units — **values still open** (§11 item 10). Highest score wins; ties go to the ascending opaque unit ID. Opaque IDs must be drawn from the game RNG, never sequential, so this tie-break favours no player.
  - **Damage application:** all hits of a round apply simultaneously at round end, computed from the round-start snapshot (mutual destruction is possible). Shields absorb damage point for point; the overflow damages the hull. Crew loss per hit = `floor(0.10 × hull_damage_of_that_hit)`. Shields are hit before hull for beams and torpedoes alike.
  - **Retreat:** an evasive ship whose hull damage is ≥ 50 % of max hull, with a working engine and damage-limited max warp ≥ 1, attempts to retreat at the end of the round. Success chance = ruleset curve over the ratio of its side's to the opposing side's total combat mass, rolled on the engagement's seeded RNG stream — reproducible per seed, not a fixed outcome. A retreated ship leaves the engagement and ends the turn at its position.
  - **Capture:** a non-destroyed ship with zero crew is disabled. Capture-capable units in the same engagement claim disabled ships at combat end; the unit with the most surviving crew claims first, ties by ascending opaque ID. Each unit captures at most one ship per engagement (provisional).
- **Replay contract:** event entries include round, sequence, actor/target opaque identifiers, action, position, damage, ammunition delta and outcome. Visibility filtering applies before delivery; the authoritative log is immutable.
- **Planet combat**: planetary defences and starbase join battles at their coordinates; ground invasion resolves after space combat if orbit is won.
- Output: a **battle log** (JSON event stream) per engagement, stored for the replay viewer.

### 3.8 Minefields and vision

- Minefields: circular, owner-specific, decay per turn; hit probability per light-year travelled inside.
- **Minefield lifecycle:** mines laid, swept or decayed in phase 12 change state immediately, but movement hazard checks (phase 5) always use the state at the start of the turn. A newly laid field therefore first affects movement in the next turn.
- Visibility: scan range per ship/planet, cloak vs. sensor rules, nebula modifiers. The host computes each player's **known world** after every turn.
- **Intelligence model:** distinguish current observations, last-known observations (with `observed_turn` and freshness), and event reports. Discovered planets may persist as remembered locations, but live owner/resources/ships are shown only when visible or explicitly shared. Last-known enemy contacts must never appear as confirmed live positions.
- API game-state endpoints may serve only the caller's authorized known world, memory and own orders; public lobby/ruleset and moderator endpoints have separate access policies (§7).

### 3.9 Races (archetypes)

Eleven player slots. Races are defined by trait packages; v1 ships with ~8 original archetypes, e.g.:

| Archetype | Mechanical identity |
|---|---|
| Industrial hegemony | Strong taxes, cheaper structures, average ships |
| Carrier swarm | Fighter-heavy hulls, cheap fighters, weak beams |
| Shadow raiders | Cloaking hulls, ambush bonus, fragile |
| Ground legion | Superior ground combat, fast planetary capture |
| Terraformers | Climate manipulation, higher population caps |
| Engineers | Cheaper tech upgrades, ship recycling efficiency |
| Free captains | Ship capture, fuel theft, piracy income |
| Assimilators | Convert natives/captured populations efficiently |

**Custom races** (planned for public v1 after M5, not required for the first playable alpha): trait-point budget editor; each trait has a point cost; host rejects packages over budget. Game creator chooses whether custom races are allowed.

### 3.10 Map generator and editor

- Seeded generator with parameters: planet count, field size, wraparound, homeworld count, minimum homeworld distance, resource richness, native frequency, hazard toggles.
- Fairness pass: compare the total resource value within radius *R* around each homeworld; regenerate or rebalance if the spread exceeds a threshold. R, value weights, allowed spread and maximum regeneration attempts must be pinned in the ruleset; failed fairness validation blocks game start.
- Map editor (browser): place/move planets, set resources, hazards, homeworld slots. Export/import as JSON validated against a published JSON Schema.

### 3.11 Diplomacy and messaging

- **Relation matrix** per ordered player pair: war / neutral / non-aggression / alliance.
- Alliance sharing levels (each opt-in, unilateral): ship positions, planet info, minefields, full vision.
- Relation changes take effect at the next host run (no mid-turn betrayal exploits). Ordered-pair stances are unilateral; non-aggression and alliance require mutual acceptance, while war can be declared unilaterally. Vision-sharing grants and revocations apply during the diplomacy phase, before final visibility computation; historical shared observations remain marked as stale after revocation (provisional).
- **In-game mail**: 1:1 and group messages, per game, delivered on host or instantly (game setting). Anonymous-mode games hide account names.
- Abuse controls: block, report, rate limit, per-game moderator (game creator) plus platform moderation (§7.6).

### 3.12 AI opponents

- AI players are **ordinary players** for authorization purposes: they receive the same known-world DTO and submit orders through the same validation/application service as humans. The CLI worker calls this service locally (no HTTP round trip required). No AI access to hidden state.
- Difficulty tiers: easy / normal / hard (differ in planning horizon, economy optimisation, aggression thresholds — not in information).
- Uses: fill empty slots at game start; take over dropped players (with optional hand-back); solo practice games.
- Runs as a PHP CLI worker before each host run with a hard time budget per AI per turn. AI orders must be frozen before host order-lock; on timeout, use valid standing orders or a deterministic no-op fallback and log the failure.

### 3.13 Victory and scoring

- Configurable: last-player-standing, planet-percentage threshold, score after N turns, alliance victory.
- Score = weighted sum of planets, population, military value, structures (weights in ruleset). Victory is evaluated after final scoring; tie-break is score, then planets, then stable ruleset-defined tie-break. Eliminated players cannot submit new orders. Alliance victory requires mutually recognized alliance at evaluation time; precise thresholds and turn limits are pinned in the ruleset.

### 3.14 Combat replay viewer

- Plays back battle logs on Canvas: ship positions, beam/torpedo/fighter events, damage, destruction, capture.
- Controls: play/pause, step, speed, per-ship filter.
- Shareable replay link per battle (visibility-checked: only participants and, after game end, everyone if the game is public).

---

## 4. Game lifecycle

| Phase | Behaviour |
|---|---|
| Lobby | Creator sets ruleset, map, schedule, slot count, open/invite-only, custom races allowed, AI fill |
| Joining | Players claim slots and pick races; creator can kick before start |
| Running | Turn schedule: fixed deadline (e.g. 24 h / 48 h / weekend pauses) plus early host when all ready |
| Missed turn | Explicit standing orders carry forward; one-shot orders expire. After ruleset-defined N consecutive misses → AI takeover or replacement, applied only at turn boundary |
| Paused | Creator or vote can pause (vacation) up to a limit |
| Ended | Victory declared; full map and all replays become visible to all participants; public spectator access follows game visibility setting |

### 4.1 Slot and scheduling contract

- Slots are `open`, `human`, `ai`, `eliminated` or `vacant_pending_replacement`; only eligible human slots count toward all-ready. A game must have at least two participating slots (AI allowed for solo practice).
- Each turn has an absolute UTC deadline and a versioned order snapshot. `ready=true` applies to an exact order revision; any successful order edit clears readiness atomically. Ready/unready and order updates return `409` after the host locks the turn.
- Host acquisition uses a single-winner game/turn lock. The all-ready check and transition to `locked` occur atomically. Deadline-based locking uses server time, not browser time; pauses adjust deadlines according to a documented schedule policy.
- A missed turn increments a counter only for a human slot not ready at lock. AI takeover is scheduled for the *next* turn after the threshold, and hand-back is permitted only at a turn boundary. A replacement may not access the prior human player's private messages.
- The creator may configure games before start, but cannot view hidden opponent state or silently change pinned rules after start. Moderator powers are independent of game-creator powers.

---

## 5. Architecture

### 5.1 Components

```
Browser (Canvas client, vanilla JS, ES modules)
   │  HTTPS, JSON (/api/v1)
Apache → PHP 8.3 front controller
   ├─ API layer (auth, validation, known-world reads, order writes)
   ├─ Domain layer (rules engine, pure PHP, no I/O)
   └─ Persistence (PDO, prepared statements, MariaDB 10.11+)
CLI workers (systemd timers or cron)
   ├─ host-runner   — picks due games, runs host
   ├─ ai-runner     — generates AI orders before host
   └─ mailer        — notifications (turn hosted, deadline warning), sent from a dedicated
                      silverday.de sender with SPF, DKIM and DMARC alignment
```

- No heavy framework; front-controller routing as per house stack.
- Rules engine is a **pure library** (input: state + orders + ruleset + seed; output: new state + events). This makes it unit-testable and replayable.
- Separate application services for order submission, visibility-filtered state reads and turn execution. Both human API requests and AI workers call the same authorization/validation services.

### 5.2 Client

- Canvas starmap: pan/zoom (mouse + touch), layers (planets, ships, minefields, scan ranges, waypoints), clustering at low zoom.
- Side panels server-data-driven, rendered client-side from JSON.
- Orders are edited locally and saved via API (debounced); unsaved-change indicator, current revision, and explicit stale-edit conflict recovery.
- Turn-difference view highlights new discoveries, losses, changed contacts, completed/failed orders, combat and economic deltas.
- Provide bulk actions, standing-order management, and an order preview showing expected resource conflicts; previews are advisory and never override host authority.
- Responsive down to phone width; starmap usable on touch.
- i18n: DE and EN from v1.

### 5.3 Rulesets

- A ruleset is a versioned JSON document (all tunables: growth rates, costs, curves, combat constants).
- Each game pins an immutable **rules bundle** at creation: ruleset JSON, ship/component catalogs, trait definitions, map parameters, combat parameters, host-phase schema version and content hashes. Balance changes never alter running games.
- Ruleset validation requires types, ranges, unit definitions, rounding modes, defaults, and cross-field constraints. Every formula needs worked examples and boundary tests.

### 5.4 Determinism

- Per-game secret seed; per-turn RNG = HMAC-DRBG(seed, turn_number, phase, stable_event_key). RNG streams must not depend on incidental iteration order.
- Host run must be reproducible bit-for-bit from (previous state, all orders, ruleset version, seed). Used for debugging, dispute resolution, and regression tests.
- Seed is never exposed to players during the game (prevents outcome prediction).
- Stable entity ordering, fixed-point arithmetic, explicit rounding, canonical JSON encoding, pinned code/rules bundle and immutable input snapshots are required for reproducible state hashes. Runtime timestamps and notification IDs are excluded from simulation hashes.

### 5.5 Authoritative host phase order (v0.2 provisional)

The host runs each phase over a frozen input snapshot; a later phase cannot retroactively affect an earlier one. Within a phase, operations use stable entity/order IDs and explicit priority, never SQL retrieval order.

1. **Acquire/lock:** atomically lock `(game_id, turn_number)`; snapshot validated order revisions and standing orders.
2. **Diplomacy:** resolve declarations, mutual treaty acceptance and visibility grants/revocations.
3. **Pre-movement:** cloak activation, same-location loading/refuelling/transfers, tow attachment; validate cargo and ownership.
4. **Movement:** simultaneous route calculation, fuel consumption, towing and waypoint progress; intercept tracks are adjusted toward their targets; compute traversed path segments.
5. **Path hazards:** resolve minefield encounters and movement hazards against the traveled segments, using minefields active at turn start, with deterministic hit ordering.
6. **Intercept and encounters:** group co-located hostile units into engagements and fix combat rosters.
7. **Space combat:** execute fleet battles; record immutable event logs and survivors.
8. **Orbital/ground combat:** resolve planetary defence and invasion only after orbital combat; determine provisional controller.
9. **Post-combat actions:** colonisation, capture, salvage and unloading; newly owned worlds are marked ineligible for same-turn production.
10. **Planet economy:** allocate phase 10–11 drafts via the fallback queue (§3.4); population, happiness, taxes, migration/mining, supplies and structure builds.
11. **Starbase actions:** upgrades, repairs, recycling, ammunition/fighter production and at most one ship per starbase unless queue option changes this rule.
12. **Mine operations:** lay/sweep/decay; changes become relevant for movement hazards from the next turn (§3.8); apply published operation order.
13. **Visibility:** generate current observations and update per-slot intelligence memory, including diplomacy-sharing effects.
14. **Score/victory:** update score, elimination and win state, with documented tie-breaks.
15. **Commit:** atomically persist new authoritative state, event/report references, order outcomes and notification outbox; mark turn committed. Outbox delivery occurs asynchronously and idempotently.

**Conflict arbitration (binding framework):** for each contested target, first apply explicit gameplay eligibility, then priority/ruleset strength where defined, then a deterministic seeded tie-break keyed to target and turn. Examples: multiple colonisers for a neutral world compete after space combat; multiple tow requests for one target choose one eligible tower; competing captures resolve against surviving units. All tie-breaks are recorded in order reports. Avoid stable slot-number priority as the default, since it systematically favors particular players.

**Order outcomes:** every submitted action returns `executed`, `partially_executed` (only for explicitly splittable actions), `failed`, or `superseded`, with machine-readable reason codes and player-safe explanations. One-shot orders expire after evaluation; standing orders persist until changed or invalidated.

### 5.5.1 Host execution and recovery

- State machine: `pending → locked → resolving → committed`; `resolving → retryable_failure` may be retried using the **same** frozen input and rules bundle. Only one execution can commit for a game/turn.
- Resolve into temporary/isolated outputs. Publish state and reports in one authoritative transaction, with a unique constraint on committed `(game_id, turn_number)`. Long-running computation must not hold open database write locks.
- An idempotent notification outbox is committed with the turn; sending mail is not part of the simulation transaction. Recover orphaned locks via lease expiry and fencing tokens.
- Preserve input hash, rules bundle hash, engine build ID, output hash, execution ID and error trace. Reprocessing an already committed turn is forbidden except through an audited administrative recovery procedure.

### 5.6 Data model (outline)

| Table | Notes |
|---|---|
| `users`, `credentials`, `sessions` | Accounts; passkeys + password (§7.1) |
| `games`, `game_slots`, `game_settings` | Lifecycle, schedule, pinned ruleset |
| `rulesets` | Versioned JSON |
| `maps` | Generated or editor-built map definitions |
| `turns` | Turn number, host timestamps, status, input/output hashes, engine/rules bundle versions |
| `host_executions`, `notification_outbox` | Lease/fencing, attempts, recovery, idempotent notification delivery |
| `planets_state`, `ships_state`, `starbases_state`, `minefields_state` | Authoritative state per turn |
| `orders` | Per slot per turn; JSON, schema-validated |
| `known_world`, `intelligence_memory` | Per-slot observations and timestamped last-known intelligence |
| `battles` | Battle log JSON, participants, coordinates |
| `messages`, `message_recipients` | In-game mail |
| `diplomacy` | Relation matrix per turn |
| `races`, `race_traits` | Built-in and custom archetypes |
| `reports`, `moderation_actions` | Abuse handling |
| `audit_log` | Security-relevant events |

State is stored per turn (append-only) to support replays, rollback after host bugs, and post-game full-map review. Evaluate periodic full snapshots plus immutable events/deltas to control storage growth; restore correctness and reconstruction time must be load-tested. Retention policy in §7.7.

### 5.7 API (outline, `/api/v1`)

| Method | Path | Purpose |
|---|---|---|
| GET | `/games` | Lobby listing |
| POST | `/games` | Create game |
| POST | `/games/{id}/slots/{slot}/join` | Claim slot |
| GET | `/games/{id}/turn/current` | Known world for caller's slot |
| GET | `/games/{id}/turns/{n}/report` | Event report |
| PUT | `/games/{id}/orders` | Replace caller's orders for current turn (optimistic concurrency via `If-Match`) |
| POST | `/games/{id}/ready` | Mark ready / unready for an exact order revision |
| GET | `/games/{id}/turns/{n}/diff` | Authorized turn-to-turn change summary |
| GET | `/games/{id}/orders` | Current saved orders, revision, standing-order status |
| GET | `/games/{id}/battles/{bid}` | Battle log (visibility-checked) |
| GET/POST | `/games/{id}/messages` | Mail |
| PUT | `/games/{id}/diplomacy` | Relation changes |
| GET | `/rulesets/{v}` | Public ruleset |
| POST | `/maps/generate`, `/maps` | Generator / editor save |

**Data boundary:** game-state and replay endpoints return only authorized observations, intelligence memory, own orders and player-safe reports. Lobby and ruleset endpoints serve public metadata. Admin/moderation endpoints use separate explicitly audited authorization.

API contract: pagination and caps for list endpoints; standardized `{code,message,details,request_id}` errors without hidden-state disclosure; `ETag`/`If-Match` for order revisions; `409` for locked/stale revisions; idempotency keys for creation and state-changing actions; stable versioned JSON schemas.

---

## 6. Non-functional requirements

| Area | Requirement (initial targets, to be validated by load test) |
|---|---|
| Host performance | ≤ 10 s per host run for 11 players / 500 planets / 1 500 ships |
| API latency | p95 ≤ 300 ms for known-world fetch |
| Concurrency | ≥ 200 concurrent running games on a single VPS-class host |
| Availability | Missed host windows are caught up automatically; no turn lost on crash; define recovery-time and recovery-point objectives before beta |
| Storage | Establish per-game/turn budget, retention cap, snapshot cadence and restore-time target before load test |
| Monitoring | Host lag, retries, queue depth, outbox failures, database growth and authorization failures are instrumented and alerted |
| Accessibility | WCAG 2.1 AA for all non-map UI; map has list-view alternative |
| Browser support | Current Chrome, Firefox, Safari, Edge; no plugins |

---

## 7. Security, privacy and fairness

### 7.1 Authentication

- NIST SP 800-63B baseline: passkeys (WebAuthn) preferred; passwords ≥ 8 chars (≥ 15 recommended), breach-list check, no composition rules, Argon2id hashing.
- Session cookies: `Secure`, `HttpOnly`, `SameSite=Lax` or `Strict`; rotation on login; CSRF tokens on state-changing requests.
- **Subdomain isolation:** Caelorn runs on `caelorn.silverday.de` alongside other silverday.de services. Session cookies use the `__Host-` prefix (no `Domain` attribute, `Path=/`) so no sibling subdomain can read, set or overwrite them; no cross-subdomain CORS; separate Apache vhost, PHP-FPM pool, system user and database credentials from other silverday.de services.
- Rate limits on login, registration, password reset.

### 7.2 Fog-of-war integrity (core anti-cheat)

- The server is authoritative; clients submit **orders only**, never state.
- No endpoint, error message, timing difference, ID sequence or asset URL may leak hidden information (e.g. sequential ship IDs revealing enemy build counts → use per-player opaque IDs or random IDs).
- Known-world materialisation is the single choke point for game-state disclosure; covered by dedicated tests ("player A's response contains nothing outside A's visibility set"). Include current-vs-remembered data, shared-vision revocation and timing/ID side-channel cases.
- Battle logs and replays filtered to what each participant could observe.

### 7.3 Order validation

- JSON-Schema validation on submit and re-validation at host time.
- Ownership checks on every referenced object; resource and capacity checks; atomic revision updates with `If-Match` versioning. Resource checks on save are advisory where simultaneous or conditional orders can alter stock; host validation is authoritative.
- Size limits on orders and messages to prevent host-time DoS; also cap nesting depth, per-turn actions, map imports, race-trait combinations, and battle-log payloads.

### 7.4 Multi-accounting and collusion

- One slot per account per game; account age/verification requirements for ranked games.
- Detection heuristics (shared sessions/devices, synchronised behaviour) are privacy-sensitive under the GDPR: define purpose, minimal data, short retention, and document a legitimate-interest assessment before implementing any IP/device correlation.

### 7.5 Infrastructure

- TLS only, HSTS, strict CSP (no inline script), security headers, dependency inventory (SBOM). HSTS `includeSubDomains`/preload is decided at the silverday.de apex, since it affects every sibling service.
- Workers run with least privilege; DB users split (API read/write vs. host vs. admin).
- Backups with tested restore; authoritative turn commits are transactional. Set RPO/RTO, backup encryption, recovery drills and alert thresholds before public beta.
- Workers use scoped service identities, exclusive per-turn leases and fencing tokens; audit administrative recovery. Add an explicit role/permission matrix for player, spectator, creator, game moderator, platform moderator, administrator and AI.

### 7.6 Moderation

- Report button on messages, names, custom race names and map names.
- Name/content filter at creation; moderator queue; sanctions (mute, kick from game, suspend account) logged in `audit_log`.

### 7.7 Privacy and legal (Germany/EU)

- GDPR: data minimisation, privacy notice, account deletion with anonymisation of game history (player shown as "former player"), defined retention for logs and messages. Document separate periods for session logs, moderation evidence, messages, active-game state, ended-game archives and backups before launch.
- Impressum per § 5 DDG.
- Terms of use and community guidelines.
- Items needing legal confirmation before launch: whether in-game messaging triggers obligations under the EU Digital Services Act for this service size, and age requirements for sign-up.

---

## 8. Testing strategy

- Rules engine unit tests per phase, with golden-file tests: (state, orders, seed) → expected state hash.
- Property tests: conservation of resources (nothing created except by defined production), no negative stocks, determinism across runs; invariant checks for ownership, capacity, combat outcomes and fog-of-war.
- Conflict tests: simultaneous colonisation, tow contention, shared-target capture, insufficient multi-order resources, deadline/order-save race, AI timeout, host crash before/after commit, notification retry.
- Replay tests: canonical hashes across supported PHP environments; combat event schema compatibility; participant-specific redaction and last-known intelligence staleness.
- Visibility leak tests per endpoint.
- Simulation harness: AI-vs-AI games at scale for balance telemetry (win rate per archetype, game length).
- Load test of host runner and API against §6 targets.

---

## 9. Licensing

- Follows house conventions: code under MIT (or EUPL-1.2 — **decision needed**, see §11), non-code assets under CC BY-SA, managed via REUSE (`REUSE.toml`, SPDX headers); logo and wordmark excluded.

---

## 10. Delivery phases

| Phase | Content |
|---|---|
| M0 — Rules contract | Frozen phase order, schemas, catalogs, starter preset, deterministic arithmetic, test fixtures, engine skeleton |
| M1 — Headless playable | Economy, movement, colonisation, ship/starbase production, victory and headless AI-vs-AI tests; minimal simulation replay viewer (§14.9) |
| M2 — Multiplayer integrity | Accounts, lobby, known world, orders, deadlines, host locking/recovery, API, basic Canvas map |
| M3 — Combat and UI | Fleet/planet combat, minefields, reports, accessible map alternative, battle logs |
| M4 — Social and AI | Diplomacy, messages, moderation, AI easy/normal, takeover, simulation harness, full simulation viewer and experiment dashboard (§14.9) |
| M5 — Advanced v1 | Custom races, map editor, hard AI, replay viewer, optional hazards |
| M6 — Public beta | Security and privacy review, performance/restore tests, legal review, closed → open beta |

---

## 11. Open decisions

1. **Trademark clearance for "Caelorn"** in classes 9 and 41 (EUIPO/DPMA via TMview, plus USPTO), and availability of domains and social handles. A web search (2026-10-14) found no game, brand or company of that name. Rejected earlier: "Caelorn" (existing Windows game of that name; US application "EX ASTRIS" in classes 9/41) and "Astara" (name of a large European mobility group).
2. Code licence: MIT vs. EUPL-1.2.
3. Ranked/rated games in v1 or later?
4. Build queue per starbase vs. classic one-ship-per-turn.
5. Message delivery: instant vs. on host (or per-game setting, as drafted).
6. Monetisation, if any (affects ToS and infrastructure sizing).
7. Art direction for original races and ships.
8. Whether to support real-time "blitz" games (short deadlines, e.g. 5 min) in addition to async.
9. Validate and tune provisional MPR-1 starting stocks, hull/component catalog, victory thresholds and balancing constants (Appendices A–B); freeze the accepted release candidate before M1.
10. Combat parameter values: `weapon_potential` definition, stance weights, `damage_penalty`, retreat success curve, capture limits and fighter lifecycle (required for M3). Engagement radius is resolved: co-location only (v0.8).
11. Resource allocation priority and which orders may partially execute (required for M1).
12. RPO/RTO, retention periods, storage budget and moderator permission matrix (required for M6).
13. Public spectator access, post-game replay visibility and whether eliminated players retain visibility.
14. Map fairness radius, weighting and allowed homeworld resource variance (required for M1).
15. Approve combat-scale units and detailed per-round battle equations; SCC-1 weapon values are candidate statistics, not a completed combat ruleset.
16. Approve balance thresholds, AI policy roster, confidence intervals and seed budgets before promoting MPR-1.
17. Simulation viewer: share of games retaining full replay traces, AI decision-trace format and storage budget, reference hardware for the turn-switch target (§14.9).

---

## 12. Implementation acceptance gates (new in v0.2)

- **M0 gate:** every host phase has defined inputs/outputs; schemas and catalogs are versioned; a golden deterministic turn runs twice with identical hashes.
- **M1 gate:** a headless game can start, progress and end; economy and cargo invariants hold; contested orders produce explainable outcomes; any regression match can be replayed in the simulation viewer with matching per-turn hashes.
- **M2 gate:** two players can submit conflicting revisions without a race; only one host commit succeeds; crash/retry yields identical state; hidden state is inaccessible through every player endpoint.
- **M3 gate:** fleet and ground combats terminate within bounded rounds, are replayable from immutable events and never leak unobserved units.
- **M4 gate:** AI uses only the same authorized state and order service as humans; timeouts cannot block a turn; moderation actions are auditable; simulation viewer acceptance per §14.9.6 passes.
- **M5 gate:** custom races and map imports are schema/constraint validated; the replay viewer handles missing or redacted events safely.
- **M6 gate:** agreed p95/API and host performance targets pass; recovery drill meets approved RPO/RTO; security, accessibility, privacy and legal launch checks are signed off.

## 13. Revision notes (v0.3)

v0.2 clarified phase order, simultaneous conflict arbitration, order accounting, movement, intelligence persistence, game lifecycle, AI privilege boundaries, deterministic replay, transaction recovery, API contracts, security operations, milestone sequencing and acceptance gates. v0.3 adds Appendices A–B (provisional playable rules and ship/component catalog) and §14 (automated games and balance evaluation). **All proposed numbers are unplaytested tuning candidates.** Outstanding decisions remain in §11.


---

## 14. Automated games and balance evaluation (new in v0.3)

### 14.1 Objectives and isolation

The headless simulation harness must run complete games without a browser or human interaction, detect deterministic/regression failures, and evaluate starting-position fairness, economic viability, strategy diversity, combat outcomes and game duration. **Balance telemetry must not change a live game's pinned ruleset.** Test matches are isolated from public ratings, achievements, messages and notifications. AI controllers use only the same authorized known-world DTO and order-validation service as ordinary players; the evaluator may inspect full authoritative state **after** each turn, but that state must never reach an AI controller.

### 14.2 Reproducible experiment manifest

Every batch has an immutable manifest containing: experiment ID, git commit/build identifier, PHP/runtime version, canonical ruleset and catalog hashes, map generator version, policy IDs/versions, player count, map seed list, game RNG seed list, slot permutations, victory mode, maximum turn count, resource/time limits and metric schema version. Use independent domain-separated RNG streams for map generation, host events and each AI policy. A rerun with the same manifest and environment must reproduce per-turn state hashes and final outcomes; record any deviations as test failures.

Run modes:

- **Smoke:** 10–20 games per change for crashes, invariant failures and completion.
- **Regression:** at least 100 fixed-seed games per release candidate, compared against stored golden hashes and metrics.
- **Balance:** at least 500 completed games per candidate configuration, with randomized, paired starting-slot assignments; increase sample size when uncertainty is high.
- **Stress:** large maps, maximum fleets, dense combat, stalled/invalid AI orders, worker restarts and concurrent host runs; report wall-clock, peak memory, DB I/O and storage growth separately from balance.

### 14.3 AI policy roster and tournament design

Use several independently implemented, versioned policies: `baseline_balanced`, `expansion_first`, `economy_first`, `military_first`, `defensive`, and `random_legal` (a sanity baseline). Add a scripted deterministic policy for regression. A policy may use difficulty-dependent computation budgets but never privileged data. Record timeout, invalid order and fallback counts. Avoid using only self-play by a single AI: it can mistake AI-specific weaknesses for game balance.

For comparisons, use paired maps and seeds with cyclic/Latin-square slot rotation so each policy occupies each starting position approximately equally. Separate mirror-match faction tests from mixed-policy tournaments. For MPR-1, factions are mechanically identical; later faction tests must rotate faction, policy and slot independently. Evaluate both 2-player and 4-player formats; 3-player games are a secondary coverage set. Do not compare win rates from unmatched seed or policy distributions as if they were controlled experiments.

### 14.4 Per-turn and per-game telemetry

Store compact machine-readable metrics plus event references; avoid collecting player personal data in the simulation harness.

| Category | Metrics |
|---|---|
| Outcome | winner, victory reason, final turn, draw, eliminated slots, final score by category |
| Expansion | planets per slot/turn, first colony turn, contested colonisations, planet losses |
| Economy | stocks, production, mining, population, happiness, idle capacity, resource shortages, failed builds |
| Fleet | ship counts/classes, total mass, travel distance, fuel use, ship losses, fleet-value concentration |
| Combat | engagements, participants, rounds, casualties, damage, ammo/fighter use, retreats, stalemates |
| Orders/AI | orders submitted/accepted/rejected, reasons, readiness, AI timeouts, fallback orders |
| Fairness | homeworld neighborhood value, travel access to neutral worlds, map symmetry indicators |
| Runtime | turn CPU/wall time, peak memory, DB queries, snapshot/event bytes, exceptions |

Define `military_value` and `economic_infrastructure` scoring formulas in the pinned ruleset before using their metrics for victory or evaluation. Preserve turn-by-turn metrics for trend analysis, not just final outcomes.

### 14.5 Metrics, statistical treatment and initial acceptance targets

| Metric | Candidate acceptance rule | Notes |
|---|---|---|
| Determinism | 100% identical per-turn state hashes on exact reruns | Any mismatch blocks release |
| Safety invariants | Zero negative stocks, unauthorized reads, duplicated commits or invalid ownership | Any occurrence blocks release |
| Completion | ≥99% games complete without engine error within configured cap | Draws are valid completions |
| Duration | Median 40–70 turns for MPR-1 | Report p10/p50/p90, not median alone |
| First colony | Median turn 3–7 | Stratify by policy and starting position |
| Early economy | ≤10% of player-turns in turns 1–10 blocked by resource starvation | Define starvation as no affordable meaningful economy/expansion action |
| Position fairness | Maximum minus minimum slot win rate <10 percentage points in balanced mirror tournaments | Require confidence intervals; inconclusive is not a pass |
| Strategy diversity | No single policy dominates every other policy with statistically credible advantage | Diagnose rather than blindly nerf |
| Ship diversity | No single hull or loadout universally dominates at comparable total cost | Evaluate across roles and opponent mixes |
| Host performance | Meet §6 target on the specified reference hardware | Publish hardware and workload profile |

For win rates, report games, wins, ties, effective win share (ties split equally), Wilson 95% confidence intervals for binary outcomes, and bootstrap intervals for multi-player/paired estimates. Report effect sizes and uncertainty, not only p-values. Use paired differences for controlled comparisons. Apply a documented multiple-comparison correction when testing many factions, slots or policies. **A 500-game batch is a starting budget, not a guarantee of enough statistical power** to establish a 10-point fairness bound. If an interval overlaps the limit, mark the result `inconclusive` and run more games.

### 14.6 Automated evaluation pipeline

1. Validate manifest and ruleset/catalog compatibility; reject non-reproducible or malformed experiments.
2. Generate maps; run and record the map-fairness gate before any games start.
3. Schedule independent matches through bounded workers; enforce per-turn CPU/memory budgets and wall-clock timeouts.
4. For each turn, obtain each AI's authorized view, submit validated orders, resolve using the production host engine, and capture state hashes and metrics.
5. Assert invariants after each phase/turn; quarantine failures with minimal reproducer (seed, manifest, previous snapshot, orders, phase).
6. Aggregate per-game outcomes, slot-rotated paired comparisons, confidence intervals, policy matchups and performance distributions.
7. Produce an HTML/JSON report with charts/tables, regression deltas, outliers, failed seeds, and a recommendation: `pass`, `fail`, or `inconclusive`.
8. Require human approval before promoting a tuned ruleset/catalog version; never auto-apply balance changes to active games.

### 14.7 Balance tuning and overfitting controls

Tune one coherent parameter group at a time (starting resources, growth/mining, movement/fuel, hull costs, combat values, victory weights). Record hypotheses, changed parameters, expected impact and observed effects. Reserve at least 20% of seeds as a holdout set not used for iterative tuning. Recheck accepted candidates on unseen seeds, alternate policy mixes, 2- and 4-player games, and stress configurations. A balance fix that worsens determinism, security, performance or game clarity is not acceptable. Keep the previous ruleset as a rollback candidate for **new** games only.

### 14.8 Storage, interfaces and acceptance gate

Recommended tables/artifacts: `sim_experiments`, `sim_matches`, `sim_turn_metrics`, `sim_failures`, `sim_reports` (or partitioned files with indexed metadata). Provide CLI commands conceptually equivalent to `simulate:run --manifest ...`, `simulate:resume <id>`, `simulate:report <id>` and `simulate:compare <baseline> <candidate>`. Resume must not double-count games. Restrict evaluator/admin interfaces and retain manifests, hashes and aggregate reports longer than bulky turn-level traces according to a documented policy.

**M1 gate extension:** a reproducible 100-game regression batch completes with no invariant violations. **Pre-public-beta balance gate:** at least one 500-game evaluation plus any follow-up needed to resolve uncertainty; all mandatory safety/determinism checks pass; gameplay targets are met or exceptions are explicitly approved and documented.

---

### 14.9 Simulation viewer (new in v0.7)

An **internal** tool to inspect automated games (§14.2–14.6): watch individual AI-vs-AI matches turn by turn, and analyse whole experiment batches. It is a diagnostic and design instrument, not a player feature.

#### 14.9.1 Scope and delivery

| Component | M1 (minimal) | M4 (full) |
|---|---|---|
| **Game replay** | Omniscient starmap per turn, turn scrubber, order outcomes with reason codes, per-slot metrics table, state hash per turn | + slot-perspective view, phase stepping for quarantined failures, battle playback, AI decision traces, metric charts, annotations |
| **Experiment dashboard** | — (CLI reports from §14.6 suffice) | Experiment list, manifest view, outcome statistics, policy matchups, A/B comparison, failure triage, drill-down into replays |

#### 14.9.2 Game replay

- **Starmap:** reuses the production Canvas renderer (§5.2) in a read-only mode: planets with owner, population and structures; ships and fleets with mission and waypoint; movement trails between turns; minefields and scan ranges as toggleable layers.
- **Timeline:** scrubber across all turns with play/pause, step and speed; event markers for colonisations, battles, eliminations, invariant failures and AI timeouts. Clicking a marker jumps to that turn.
- **Perspective switch (M4):** *Omniscient* (full authoritative state) or *as seen by slot N* (exactly the known-world DTO that slot's AI received that turn). A diff overlay shows what slot N could not see. This doubles as a visual fog-of-war audit for §7.2.
- **Orders panel:** per slot, the submitted order set, priority order and outcome (`executed`/`partially_executed`/`failed`/`superseded`) with reason codes and tie-break records (§5.5).
- **Phase stepping (M4):** for quarantined failures (§14.6 step 5) the viewer steps through the state after each host phase, with the failing invariant highlighted. Normal games store only end-of-turn state.
- **Battle playback (M4):** embeds the combat replay viewer (§3.14) unchanged, fed from the authoritative battle log.
- **AI decision traces (M4, optional per experiment):** policies may emit structured annotations (goals, candidate actions, scores, chosen action). Traces are stored separately, are write-only from the policy's perspective and are never fed back into any AI controller.
- **Metrics:** per-slot time series from `sim_turn_metrics` (planets, population, production, fleet value, score) with the current turn marked.
- **Determinism check:** each turn shows its state hash; a *re-run* action replays the match from its manifest on a worker and reports the first diverging turn and phase, if any.
- **Deep links:** `experiment/match/turn[/phase][?slot=N]`, so failure reports and reviews can point to an exact situation.

#### 14.9.3 Experiment dashboard (M4)

- **Experiment list:** status (`running`, `pass`, `fail`, `inconclusive`), manifest hash, ruleset/profile, game counts, failure count.
- **Manifest view:** the full immutable manifest (§14.2, §15.12 fields) in readable form.
- **Outcomes:** effective win share per policy and per starting slot with Wilson 95 % intervals drawn as error bars; acceptance thresholds from §14.5 drawn as reference lines; results whose interval crosses a threshold are labelled `inconclusive`, never shown as pass.
- **Distributions:** game duration (p10/p50/p90 + histogram), first-colony turn, early-economy starvation, ship-class utilisation.
- **Policy matchup matrix:** paired win-share differences with intervals.
- **Comparison:** baseline vs. candidate (the view of `simulate:compare`), including MPR-1 vs. PHost-inspired cohorts; non-paired cohorts are visibly marked as such (§15.12).
- **Failure triage:** list of quarantined games with invariant, turn, phase and seed; one click opens the replay at the failing phase; downloadable minimal reproducer.
- **Outliers:** shortest/longest games, extreme snowballing, stalemates — each linked to its replay.
- **Review trail:** reviewers can annotate experiments and mark a recommendation as accepted/rejected; this feeds the human promotion approval in §14.6 step 8.

#### 14.9.4 Architecture and data

- Separate admin area (`/admin/sim/…`) on the same front controller, with read-only JSON endpoints under `/api/v1/admin/sim/…`. The only state-changing actions are *re-run*, annotations and review decisions, all audited.
- **Hard separation from live games:** viewer endpoints read only `sim_*` tables (ideally a separate schema and DB user). They cannot load live game IDs; live-game omniscient state is never reachable through this tool.
- Additional storage: `sim_turn_snapshots` (end-of-turn state, snapshot + delta per §5.6), `sim_phase_snapshots` (quarantined failures only), `sim_ai_traces` (optional). Turns are lazy-loaded; the client never downloads a whole match at once.
- **Retention:** full replay traces kept for failed, flagged, outlier and a sampled subset of games; aggregates and manifests kept longer (§14.8). An expired trace shows a clear "trace not retained" state with an option to regenerate it by re-running from the manifest.
- **Performance target (initial, to validate):** switching turns in a 500-planet match ≤ 500 ms p95 on the reference hardware; dashboard aggregates precomputed per experiment, not computed on page load.

#### 14.9.5 Access and security

- New roles `sim_viewer` (read) and `sim_reviewer` (read, annotate, review, re-run); added to the role/permission matrix (§7.5). Player, creator and moderator roles have no access.
- Same authentication baseline as §7.1; access to the admin area is audited.
- Simulation data contains no personal data (§14.4). A later public showcase of AI games would require a separate export with explicit visibility rules — out of scope for v1.

#### 14.9.6 Acceptance

- **M1:** any regression-batch match can be opened, stepped through to the final turn, and its displayed per-turn hashes match the stored hashes.
- **M4:** the slot-perspective view renders exactly the stored known-world DTO (automated comparison test); every quarantined failure opens at its failing phase; dashboard statistics match the CLI report for the same experiment; no viewer endpoint returns live-game data (negative authorization tests).

---

## Appendix A — MPR-1: Minimum Playable Ruleset (provisional)

### A.1 Scope and starting conditions

MPR-1 is the **headless-first test profile**, not the full public v1 feature set. It supports 2–4 mechanically identical players, 80 planets in a 1,000 × 1,000 light-year rectangular non-wrapping map, one homeworld and one starbase per player, 24-hour default human turn deadlines (no waiting in simulations), and a 70-turn limit. Minimum homeworld separation: 300 light-years, subject to map-generation feasibility; reject/reseed impossible layouts. No natives, hazards, custom races, cloaking, minefields, fighters in combat, ship capture, terraforming or alliance victory. Diplomacy: war, neutral, alliance (shared intelligence only; victory individual). All players start with equal technology and resources; map fairness must additionally consider reachable planets, not merely local mineral totals.

| Homeworld asset | Initial quantity |
|---|---:|
| Deuterite / Ferrite / Corundite / Iridium | 2,000 / 2,500 / 1,200 / 400 |
| Credits / Supplies / Colonists | 5,000 / 500 / 10,000 |
| Mines / Factories / Defence posts / Starbases | 30 / 40 / 10 / 1 |
| Hull / Engine / Beam / Torpedo technology | 3 / 3 / 2 / 1 |
| Starting fleet | 1 Pathfinder scout, 1 Wayfarer transport |

Starter loadouts: Pathfinder = Fusion Drive + 1 Pulse Laser; Wayfarer = Fusion Drive, unarmed, carrying 200 colonists and 100 supplies. **Cargo semantics:** colonists and supplies consume one cargo unit each in MPR-1; cargo and onboard fuel are tracked separately; the Wayfarer has 300 cargo units, so this initial load exactly fills it. Starting ships are fully fueled at launch from a *separate setup grant*; do not deduct that fuel twice from homeworld stocks. Tech prerequisites and build costs follow Appendix B.

### A.2 Units and arithmetic

- Distance in light-years (ly), mass in mass units (MU), minerals/fuel/supplies in integer resource units, population in colonist units, money in integer credits, damage in integer combat points.
- World positions use fixed-point coordinates at 0.001 ly resolution; movement distance and fuel calculations use integer/fixed-point arithmetic with specified rounding (ceil fuel, floor output unless stated). Avoid runtime-dependent floats in the authoritative host.
- One ship occupies one point at end of movement; cargo mass adds to movement mass using a pinned mass-per-resource factor (candidate: 1 MU per 10 cargo units, rounded up). Installed components and ammunition mass require explicit catalog values before the M1 engine freeze.
- Percentages are represented as integer basis points (10,000 = 100%); deterministic rounding and evaluation order are part of the ruleset schema.

### A.3 Economy candidate formulas

These formulas are **candidate defaults**. A ruleset validation gate must reject undefined capacity, negative stocks, and overflow.

- **Population:** `growth = floor(0.05 × P × max(0, 1 − P/K) × climate_factor)`; MPR-1 `climate_factor = 1` and `K = 100,000` per habitable planet; growth zero when `P ≥ K`. New colonies require at least 100 colonists. Planetary ownership is not transferred by merely passing over a planet.
- **Structure capacity:** candidate `max_structures_per_type = floor(5 + 2 × sqrt(P))`; apply independently to mines, factories and defence posts; existing excess structures remain but only the first `max_structures_per_type` are productive. Use integer square root.
- **Mining:** per ore `min(surface_stock, floor(active_mines × 2 × density_bps / 10,000 × mining_modifier))`, with density defined per ore; one mine may contribute to each ore pool (deliberate initial simplification). Core migration happens **after** mining: `min(core_stock, max(1, floor(core_stock × 0.002)))` when core stock >0, subject to surface cap. Core stock decreases by exactly the migrated amount.
- **Factories:** one supply per active factory per turn, capped by available production capacity. Credits from taxes: `floor(P × tax_rate_bps / 10,000 × 0.1)`; default tax rate 10% (1,000 bps). Happiness starts at 100 and is clamped to [0,100]; candidate change `+2 − floor(tax_rate_percent / 5)` per turn, with unrest/output penalties below 40/20. Exact unrest losses must be pinned before economy implementation.
- **Planet order:** apply population and happiness, tax, mining, factories, then spending/builds as prescribed by §5.5; do not permit spending resources created in a later phase. Starbase ship production occurs after planetary production and may spend current-turn output only if explicitly allowed by the pinned allocation contract (candidate: yes).
- **Resource priority queue:** when a planet's drafts exceed its stock, allocation follows the §3.4 fallback queue: structures → ammunition/supplies → ship repairs → starbase ship construction, then ascending order ID.
- **Economic costs (initial candidates):** mine 20 credits + 2 supplies + 5 Ferrite; factory 25 credits + 3 supplies + 5 Ferrite; defence post 35 credits + 5 supplies + 8 Ferrite; starbase 900 credits + 150 supplies + 300 Ferrite + 150 Corundite + 50 Iridium. Costs are paid atomically per unit; if insufficient, execute prioritized affordable units and report skipped units.

### A.4 Turn orders, movement and colonisation

- Player orders carry explicit priority and stable order IDs; shared-resource allocation uses the §5.5 conflict contract, not DB insertion order. Planet orders and starbase orders draw from their planet's stock unless an explicit transfer has already executed.
- `distance_per_turn = warp²` ly, limited by installed engine max warp; a waypoint persists until reached. Fuel: `ceil(total_mass_MU × actual_distance_ly × engine_multiplier / 1000)`; integer fixed-point implementation is mandatory. Engine multipliers: Ion 1.50, Fusion 1.00, Quantum 0.65. If insufficient fuel, reduce travel to the greatest affordable distance at fixed coordinate precision; fuel never goes negative. Distance is measured along the traveled segment; no teleportation.
- A ship can colonise a neutral planet only if it ends movement at that planet, carries at least 100 colonists, and issues a valid colonise mission. A colonisation order must specify colonists to unload (≥100); unloading happens in the colonisation phase. Contested attempts are resolved by the pinned conflict algorithm, not by ship ID or player slot. MPR-1 default: opposing colonisation attempts on the same neutral planet block all attempts that turn and generate a contested report; subsequent space combat may resolve control on a later turn.
- Colonists and supplies on a ship consume cargo capacity. Transport orders cannot overfill cargo; transfers occur only at eligible co-located destinations in the documented phase. No negative balances or duplicated cargo.

### A.5 Victory and scoring

- **Dominance:** control ≥60% of all map planets at the end of three consecutive turns; streak resets immediately on falling below threshold. Eliminated players cannot win.
- **Turn-limit:** at end of turn 70, highest score wins; exact tie is a draw. Earlier elimination of all opponents is an immediate win.
- **Score categories:** planets 40%, population 20%, military 25%, infrastructure 15%. Each is normalized to a pinned positive reference scale and capped at 1.0; `score = 10,000 × weighted sum`, rounded deterministically. Candidate reference scales: 48 planets, 2,000,000 colonists, 10,000 military-value points, 1,000 infrastructure-value points. **Military and infrastructure point formulas must be approved before using score-based victory**; until then, headless tests can use dominance/elimination and record provisional scores only.
- Individual victory only in MPR-1, including for allied players. Draws and wins must have explicit machine-readable reason codes.

### A.6 Test expectations

Run §14 tournaments and publish actual first-colony timing, turn length, resource starvation, position bias, and ship-class utilization. Do not declare MPR-1 balanced merely because its numbers appear plausible. The 300-ly starting separation versus 49-ly Fusion maximum travel per turn is an intentional opening pacing assumption to validate, not a guarantee.

---

## Appendix B — SCC-1: Ship and Component Catalog (provisional)

### B.1 Hulls and slots

All hulls use one engine slot. `Beams`, `Torps` and `Fighters` are maximum mount/bay capacities, not automatically installed equipment. Fighter capability is catalogued but **disabled in MPR-1 combat**. `Cargo` excludes the fuel tank. All costs are candidates in credits (Cr), supplies (Su), Ferrite (Fe), Corundite (Co) and Iridium (Ir).

| Hull | Hull tech | Base mass MU | Fuel | Cargo | Beams | Torps | Fighters | Hull HP | Shields | Crew | Candidate hull cost (Cr/Su/Fe/Co/Ir) |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---|
| Pathfinder (scout) | 1 | 40 | 120 | 20 | 1 | 0 | 0 | 70 | 30 | 15 | 100/20/50/20/0 |
| Wayfarer (transport) | 2 | 110 | 300 | 300 | 1 | 0 | 0 | 150 | 50 | 35 | 220/50/120/50/0 |
| Sentinel (corvette) | 3 | 90 | 160 | 40 | 2 | 1 | 0 | 160 | 90 | 50 | 300/60/150/90/10 |
| Vindicator (frigate) | 5 | 220 | 300 | 80 | 4 | 3 | 0 | 360 | 220 | 120 | 650/120/350/200/35 |
| Sovereign (battleship) | 8 | 600 | 650 | 150 | 8 | 6 | 0 | 950 | 600 | 350 | 1,900/300/900/650/180 |
| Horizon (carrier) | 7 | 450 | 500 | 200 | 4 | 0 | 60 | 650 | 450 | 260 | 1,450/260/700/450/140 |

### B.2 Engines

| Engine | Engine tech | Maximum warp | Fuel multiplier | Component cost (Cr/Su/Fe/Co/Ir) |
|---|---:|---:|---:|---|
| Ion Drive | 1 | 4 | 1.50 | 35/5/10/20/0 |
| Fusion Drive | 3 | 7 | 1.00 | 100/10/20/50/5 |
| Quantum Drive | 7 | 9 | 0.65 | 280/25/50/120/30 |

Movement uses the formula and rounding in Appendix A. Engine cost is additional to hull cost.

### B.3 Weapons and ammunition

| Component | Tech track / level | Candidate effect | Component cost (Cr/Su/Fe/Co/Ir) |
|---|---|---|---|
| Pulse Laser | Beam 1 | 12 base damage; shield ×1.0; low fighter defence | 35/5/10/20/0 |
| Plasma Beam | Beam 4 | 25 base damage; shield ×1.2; medium fighter defence | 110/12/20/55/8 |
| Particle Lance | Beam 8 | 45 base damage; shield ×1.5; high fighter defence | 320/30/50/120/35 |
| Kinetic Launcher | Torpedo 1 | 35 hull damage per hit | 70/10/25/30/0 |
| Antimatter Launcher | Torpedo 6 | 75 hull damage per hit | 250/25/40/100/30 |

A Kinetic torpedo costs 2 Ferrite + 1 Deuterite; an Antimatter torpedo costs 2 Iridium + 3 Deuterite. Ammunition is produced at a starbase and loaded via a transfer order, not conjured from ship cargo. Ammunition occupies one cargo unit per torpedo (candidate). Each launcher fires at most once per combat round if loaded and eligible. Actual hit probability, range, shield overflow, crew damage, initiative, fighter defence and retreat are governed by a separately pinned combat-rules schema and **must be finalized before M3**. Do not infer a complete battle algorithm from the candidate damage figures above.

### B.4 Ship design, construction and compatibility

- Build cost is hull + exactly one compatible engine + installed weapon components + initial ammunition/fighters if ordered; no hidden discounts in MPR-1. All costs and tech prerequisites are checked against the starbase's levels at build resolution.
- An empty weapon mount is legal. An installed beam/launcher consumes one matching mount; torpedo ammunition requires a launcher and fits within cargo capacity. Hull cargo must accommodate colonists, supplies, minerals and ammunition; fuel uses the dedicated tank.
- One starbase builds at most one ship per turn. Build orders are atomic: insufficient resources or tech means **no partial ship**, with a clear failure reason. All resource deltas are recorded.
- Initial hull mass excludes cargo, installed component mass and fuel mass; for MPR-1, candidate component mass is 5 MU per engine, 2 MU per beam, 4 MU per launcher, and fuel mass 1 MU per 10 fuel units (rounded up). Movement mass = base mass + component mass + cargo mass + fuel mass, computed at movement start.
- A starbase may produce ammunition independently of its ship build, subject to explicit resource allocation and a published ammunition production cap (candidate: 50 units per turn).
- The initial starter fleet is a scenario grant, not an ordinary build; its loadouts must still pass catalog validation. Wayfarer starting cargo is 200 colonists + 100 supplies and must not exceed capacity.

### B.5 Catalog versioning and validation

Catalogs have immutable IDs, semantic versions, content hashes, integer units, unique identifiers, nonnegative costs, technology prerequisites and JSON Schema validation. Games pin the exact catalog hash alongside the ruleset, map generator and combat rules. Tests must verify every starting design is constructible at its technology level, every installed loadout fits its slots/cargo, no build produces negative resources, and every valid engine can execute movement with bounded fuel consumption.

**Known balance risk:** with the proposed formula and a loaded Wayfarer, Fusion Drive fuel consumption may be low relative to a 300-unit tank, while large fleets may have different fuel pressure. Evaluate fuel scarcity and ship-class utility empirically before tuning either number.


## 15. PHost 4.1h reference baseline (consolidated from v0.4 §15 and v0.5 §17)

### 15.1 Purpose, sources and licensing

This chapter records **selected mechanics documented for PHost 4.1h**, used as an independently implemented reference baseline. **No full PHost compatibility is claimed.** It is an original summary of documented mathematical behaviour, not copied software or text.

**Sources** (retrieved/checked 2026-10-14): [Formulas](https://www.phost.de/phost4doc/formulas.html), [Combat / order of battle](https://www.phost.de/phost4doc/oob.html), [Configuration](https://www.phost.de/phost4doc/config.html), [Hosting stages](https://www.phost.de/phost4doc/pcontrol.html), [Hosting](https://www.phost.de/phost4doc/hosting.html), [Starbases](https://www.phost.de/phost4doc/base.html), [Legal notice](https://phost.de/phost4doc/readme.html).

**Licensing:** PHost documentation and software are copyrighted; documentation may be excerpted with attribution but not redistributed in modified form, and the host source is not published. Therefore: do not import PHost source, ship lists, engine fuel tables, original text or franchise assets; implement original code; attribute PHost in `NOTICE`/credits; obtain legal review before publishing derivative documentation. Faction- or native-specific source conditions (e.g. a native race that doubles mining) remain **reference-only** until replaced by original archetype traits.

### 15.2 Status tags, profiles and rounding

| Short tag (prose) | Machine value (stored per rule) | Meaning |
|---|---|---|
| `REF` | `PHOST_REFERENCE` | Mechanic directly supported by the cited PHost documentation |
| `ADAPT` | `CAELORN_ADAPTATION` | Deliberate simplification or change for this project |
| `PENDING` | `UNRESOLVED` | Needs source verification or design approval; must not be executable |

Each implemented rule stores: tag, source URL/section, formula version, parameter names, units, rounding, host phase and acceptance tests.

**Profiles:** `mpr1` and `phost-inspired` are separate ruleset profiles. Appendices A and B remain the `mpr1` defaults until an explicit profile migration is approved. A `phost-inspired` profile is a manifest of individually versioned formula modules (§15.9); unsupported modules are disabled, never silently approximated.

**Rounding:** PHost distinguishes `Trunc` (toward zero), `Round` (half up) and `ERnd` (ties to even). Use named, independently tested deterministic helpers; never PHP's unparameterised `round()`.

**Population units:** PHost counts integer **clans** (1 clan = 100 persons). This is *not* the MPR-1 colonist cargo unit. Every PHost-derived module requires an explicit conversion at the profile boundary, boundary tests, and rejection of fractional-clan transports unless explicitly supported.

### 15.3 Planets: buildings, production, happiness, taxes, population, losses

**REF: building caps** (C = colonist clans):

| Structure | Cap |
|---|---|
| Mines | `C` if C < 200, else `Round(200 + sqrt(C − 200))` |
| Factories | `C` if C < 100, else `Round(100 + sqrt(C − 100))` |
| Defence posts | `C` if C < 50, else `Round(50 + sqrt(C − 50))` |

Excess structures decay at a configured per-turn rate. ADAPT: optional `phost_building_caps` module.

**REF: mining and supplies.**

```
mining_rate = Round(race_mining_rate * density / 100) * native_factor   # native_factor 2 for one native type, else 1
extracted   = min(core_ore, Round(mine_count * mining_rate / 100))
```

PHost 4.1 uses `Round` where earlier versions truncated. Supplies come from factories (plus optional native contribution) scaled by a configured production rate; truncation is significant. New core ore from trans-uranium decay appears **after** mining and cannot be extracted until the next turn. ADAPT: optional core-based extraction module; the MPR-1 surface-migration model stays a separate option; native contribution disabled in MPR-1.

**REF: colonist happiness delta** (T = temperature, M/F = mines/factories, `tax` in percent), evaluated **before** tax collection:

```
happiness_delta = Trunc(10 - sqrt(C / 10000)
                        - abs(T - target_temp) / temp_divisor
                        - (M + F) / 300 - tax * 0.8)
```

Ordinary reference factions: `target_temp = 50`, `temp_divisor = 33`; a heat-preferring reference faction uses `100` / `66`. If pre-stage happiness < 30, the tax order is forced to zero; if resulting happiness < 30, no tax is collected.

**REF: colonist tax revenue:**

```
colonist_income = 0 if resulting_happiness < 30 else
    Round(Round(C * tax / 1000) * colonist_tax_rate / 100)
total_planet_income = min(max_planetary_income, colonist_income + native_income)
```

Native taxation uses separate native happiness, government factor, population and faction modifier, and is capped by colonist clans. Natives are excluded from MPR-1; enable the native formula only after defining an original government taxonomy.

**REF: carrying capacity** (ordinary colonists, 15 ≤ T ≤ 84, climate limits on):

```
max_colonists_people = Trunc(10000000 * sin(T * pi / 100))
supply_bonus         = Trunc(100 * supplies / 40) if eating_supplies else 0
max_colonist_clans   = Trunc((max_colonists_people + supply_bonus) / 100)
```

Below 15 / above 84 the reference uses slope-based limits; special factions have exceptions. With climate limits off the maximum is 25,000,000 colonists.

**REF: growth** — zero if happiness < 70, population at cap, or T outside 15–84; otherwise:

```
growth_rate_percent = 5 * sin(T * pi / 100) / (1 + tax / 5)
growth_clans = min(max(0, max_clans - C),
                   Trunc(Round(growth_rate_percent * C / 100) * race_growth_rate / 100))
```

**REF: planetary sub-phase order:** production → happiness → taxation → growth → losses. Overpopulation supply consumption happens before the effective limit is recalculated for climate deaths; then structural decay and riots. Thresholds: happiness < 40 can cost buildings, < 20 costs population.

**PENDING:** the published climate-death expression contains an apparent sign inconsistency (a `Min` term with `limit − population` while population exceeds the limit). Do not copy it literally into an executable rule; implement only after authoritative verification (c2ng source or reference runs).

ADAPT: optional `phost_happiness`, `phost_taxation`, `phost_climate_capacity`, `phost_population_growth` modules, all in integer clans. MPR-1 logistic growth and its happiness model remain the default.

### 15.4 Ground combat

**REF**, single attacking player (derived pseudocode, not copied code):

```
attack_power   = attacking_clans * attack_factor
weight_defence = defence_factor + defence_posts / 20
result         = attack_power - defending_clans * weight_defence
if result > 0: attacker_survivors = trunc(result / attack_factor)
if result < 0: defender_survivors = trunc(-result / weight_defence)
if result == 0 or winning_survivors == 0: planet becomes neutral
```

ADAPT: adopt the structure with original archetype factors, explicit zero-survivor neutrality, and deterministic multi-attacker arbitration. In the native profile ground invasion occurs **after** orbital combat (PHost resolves it at the start of the turn, before movement); a failed invasion never triggers peaceful colonisation. Ownership behaviour for every edge case still needs verification.

### 15.5 Movement, mass, fuel, towing and intercept

**REF: distance.** Maximum travel per turn is `warp²` ly (special gravitonic engines: `2 × warp²`). A fuel-less ship moves 0 ly unless `AllowNoFuelMovement` permits 1 ly. Damage caps warp at `min(9, 10 − Trunc(damage_percent / 10))` for ordinary ships. Actual distance can be shorter (nearer waypoint, fuel shortage, mine hits, towing). Wraparound uses the shortest displacement.

**REF: non-accurate fuel model** (per movement segment):

```
max_dist = warp * warp * gravitonic_multiplier
fuel_nonaccurate = Trunc(ERnd(total_ship_mass / 10) * actual_distance * engine_fuel_usage
                         / (10000 * max_dist))
```

**REF: accurate fuel model:**

```
fuel_accurate_real = total_ship_mass *
    (1 - exp(-engine_fuel_usage * actual_distance / (max_dist * 100000)))
```

`engine_fuel_usage` is speed-dependent engine data from the pinned catalog, not a constant. In the accurate model fractional burns accumulate across segments before final rounding; the non-accurate model rounds per segment. **Mass conventions differ:** in PHost engines and crew add no mass, unlike Appendix B — never combine PHost fuel with MPR-1 mass silently.

ADAPT: `movement_model = mpr1_linear | phost_nonaccurate | phost_accurate`; `ship_mass_model = mpr1 | phost_reference`; `engine_fuel_curve_id`; `coordinate_rounding_id`. A compatibility matrix prevents activating an incomplete engine curve. When fuel is insufficient, compute the maximum feasible distance via a bounded deterministic monotone search (monotonicity verified for every supported curve) and emit a partial-travel event. MPR-1 movement is unchanged.

**REF: tow strength:**

```
tow_strength = engine_tech² * effective_engines * warp * tow_engine_scale
             + estimated_movement_distance * tow_distance_scale
```

with additional special-engine and tractor modifiers in the source. Cyclic intercepts are resolved in the reference by a shared adjusted centroid — recorded as reference, not proposed default.

ADAPT (MPR-1): one tow order per ship; highest strength wins among competing towers, then a seeded deterministic tie-break; reject cycles and self-tows. PENDING: exact PHost tow-conflict and cyclic-intercept equivalence until worked examples and stage tests exist.

### 15.6 Starbases and ship building

**REF:** PHost has primary starbase missions (repair, recycle, refuel), production and build orders at distinct host stages, plus a build queue once ship slots are exhausted.

ADAPT: keep one ship per starbase per turn, atomic resource allocation, independent ammunition production, and a recorded per-order allocation sequence with failure reasons. Map economy stages via the crosswalk (§15.10); do not import friendly-code semantics. PENDING: the complete PHost starbase build sequence.

### 15.7 Minefields and score

**REF minefields:** radius `sqrt(mine_units)`; decay `Trunc(mine_units * (100 − decay_rate) / 100)`. ADAPT: optional post-MPR-1 module. PENDING: hit odds, sweeping and overlap explosion defaults (see §15.12).

**REF score:** `10 × planets + 10 × armed ships + freighters + 120 × starbases`. ADAPT: `phost_reference_score` for comparative telemetry only; MPR-1 weighted victory score is retained.

### 15.8 Combat: reference versus native fleet combat

**REF:** PHost standard combat is **sequential one-on-one**: an optional intercept-attack phase, then aggressor/opponent fights in battle-order sequence. Each fight runs in ticks with probabilistic recharge, launch, hit and fighter behaviour; damage, crew loss and ammunition use carry over between fights in the same turn; a no-progress timeout ends stalled fights; zero crew means capture. This cannot be copied into simultaneous fleet combat.

**ADAPT: native fleet-combat baseline** (original design, not a PHost formula):

(The authoritative, more detailed rules are in §3.7; this list is the summary.)

1. One engagement per connected hostile group at a common coordinate; planets/starbases join their coordinate's engagement. No radius-based combat in MPR-1; intercept moves toward the target and never triggers combat at range.
2. Freeze participants, stances, diplomacy and pre-combat inventories. Sort stable opaque unit IDs; derive an independent RNG stream per engagement and round.
3. Each round: determine eligible attackers, select targets by stance + threat weights with deterministic tie-breaks, resolve attacks from a **round-start snapshot** (mutual destruction possible).
4. Shields before hull; crew damage only from hull-penetrating hits. Torpedoes and fighters consume finite ammunition; beams do not. No negative inventory.
5. Hull ≤ 0 → destroyed. Crew ≤ 0 and hull > 0 → disabled, capturable only by a unit with the capture trait. Captured ships cannot attack again this turn.
6. End when no hostile attack-capable pairs remain, one side disengages, or `max_combat_rounds` is reached (→ stalemate outcome). Persist event-level replay data and a final combat-state hash.

**PENDING balance parameters** (pin in `combat_rules` before M3; not inferred from PHost): accuracy, recharge/cooldown, ranges, fighter movement and damage, retreat criteria, shields, crew-damage ratios, threat weights, maximum rounds. SCC-1 weapon damages remain provisional.

### 15.9 Module manifest and compatibility guardrails

1. Never silently replace MPR-1 logistic growth, surface-stock mining, fleet combat or weighted victory scoring with PHost mechanics.
2. A profile lists its enabled formula modules with semantic versions; the manifest is pinned for the life of each game.
3. Cross-module validation rejects incompatible units, missing race factors, undefined native rules or phase dependencies.
4. No `phost-inspired` production game until its module manifest is internally complete.
5. Independent unit, metamorphic and property tests; compare with official PHost examples only where authoritative examples exist.
6. All ship names, resources, artwork and faction archetypes stay original.

### 15.10 Host-sequence crosswalk

| PHost reference flow (abridged) | Project phase (§5.5) | Note |
|---|---|---|
| Order processing, own-unit transfers | Lock, validate, pre-movement transfers | Explicit per-order arbitration |
| Pre-movement missions, mine laying/sweeping, repairs | Pre-movement actions | MPR-1 has no mines; no friendly codes |
| Cargo drop / ground combat (before movement) | Orbital/ground combat (after space combat) | Deliberate deviation (§15.4) |
| Tow resolution, movement | Movement and intercept | PHost ordering more granular |
| Colonisation, refuel/unload, combat | Arrival, combat, planetary capture | Project resolves combat before colonisation |
| Sensor and special missions | Visibility-related actions | Avoid pre-final visibility leakage |
| Mining and supplies | Planet production | New core ore available next turn |
| Happiness, taxes, growth, losses | Planet economy | Preserve sub-phase order |
| Reporting, result generation | Known world, score, commit, outbox | Atomic commit, async notifications |

The crosswalk documents differences, not equivalence. Captured planets do not produce in their capture turn (project decision). Many PHost special-mission stages are deliberately out of scope.

### 15.11 Verification fixtures

Fixture format fields: `source_url`, `reference_version`, `module`, `input`, `expected`, `rounding`, `units`, `phase`, `verification_status`. The vectors below are **locally derived from the stated formulas, not official PHost fixtures**:

| Fixture | Inputs | Expected |
|---|---|---|
| Movement distance | warp 7, normal engine | 49 ly |
| Non-accurate fuel | mass 100, distance 49, engine usage 1000, max distance 49 | 1 |
| Accurate fuel | same inputs | `100 × (1 − e^−0.01)` ≈ 0.9950 before rounding |
| Happiness | 10,000 clans, T = 50, 0 mines/factories, tax 5 | +5 |
| Tax revenue | 10,000 clans, tax 5, rate 100 %, happiness ≥ 30 | 50 credits |
| Population growth | T = 50, tax 0, 10,000 clans, rate 100 %, happiness ≥ 70 | 500 clans |
| Temperature cap | T = 50, no supply bonus | 100,000 clans |
| Ground combat | 100 attackers, 80 defenders, 20 defence posts, factors 1 | 30 defending clans remain |
| Minefield | 400 units; 5 % decay | radius 20 ly; 380 units after decay |
| Reference score | 10 planets, 5 armed ships, 2 freighters, 1 starbase | 272 |

Also test building caps at clan thresholds 49/50, 99/100, 199/200, temperatures 14/15/84/85, and every half-integer rounding boundary. Each profile's fixture suite must pass before any headless tournament.

### 15.12 Comparative balance track (extends §14)

Run two experiment arms, **MPR-1 baseline** and **PHost-inspired profile**, only after the selected modules have valid engine curves, unit conversions, source mappings and passing compatibility checks. Use identical seeded map cohorts, policy versions, slot rotations and brackets where compatible; where rules produce fundamentally different maps/economies, report separate cohorts rather than claiming paired comparability. Starting budgets per candidate profile: 20 smoke, 100 deterministic regression, ≥ 500 exploratory balance games — engineering starting points, not statistical guarantees.

Additional manifest fields: `formula_profile`, `formula_manifest_hash`, `reference_source_version`, `catalog_hash`, `phase_order_version`, `seed`, `map_cohort`, `policy_version`, `simulation_engine_version`.

Telemetry per profile and turn: ownership, production per resource, core depletion, colony growth, happiness/tax trajectories, building utilisation, movement and fuel burn per ly, tow/intercept outcomes, ships built by class, fleet losses, engagement outcomes and duration, capture rate, ground invasions, score, winner, duration, eliminations, host execution time, economic starvation.

Analysis: win rates with uncertainty intervals, starting-position effects, policy × ruleset interactions, median and tail game length, economic bottlenecks, snowballing (early lead vs. final win), stalemate frequency, fleet-composition diversity. Do not assume a historical ruleset is intrinsically balanced and never promote a ruleset on win rate alone. Use holdout seeds and policy populations; tune only training cohorts. Each proposed change documents expected strategic effect, before/after distributions, reproducibility hashes and an explicit accept/reject decision.

### 15.13 Open verification backlog and gates

Resolved in this chapter (no longer backlog): both fuel models, happiness, taxation, carrying capacity, growth, planetary sub-phase order, ground combat, building caps, mining, score, minefield radius/decay.

Still required before marking the `phost-inspired` profile executable (transcribe from authoritative sections, preserve original parameter names in a source mapping, then document the project adaptation separately; never infer undocumented constants):

- Multi-segment accurate-fuel rounding; special-engine and tractor tow modifiers; tow ties/cycles; intercept special cases.
- Climate-death edge cases (sign inconsistency, §15.3); native taxation variants.
- Minefield hit odds, sweeping, overlap explosion and their configuration defaults.
- Complete starbase production and build-queue sequence.
- PHost combat configuration defaults (reference only; native fleet combat parameters per §15.8 need separate approval and golden battle fixtures).

**Legal gate:** original names and assets, no redistribution of PHost code/data, legal review of licensing and naming before launch.

## 16. Revision notes (v0.4–v0.9)

- **v0.4:** added a sourced PHost 4.1h reference baseline with licensing/provenance constraints, selected formulas and example tests; separated reference behaviour from MPR-1; added cross-profile simulation experiments.
- **v0.5:** expanded the reference with movement and both fuel models, towing strength, happiness, taxation, climate capacity, growth, economic phase order and combat distinctions; added host-sequence crosswalk, source-status labels, numerical fixtures and profile gates.
- **v0.6:** consolidated v0.4 §15–16 and v0.5 §17–18 into a single §15 with one tag scheme (`REF`/`ADAPT`/`PENDING` mapped to stored machine values), one source list, one fixture table and one backlog; removed rows superseded by v0.5 (movement, taxation, happiness and population no longer marked unresolved). No formula, parameter or MPR-1/SCC-1 value was changed.
- **v0.7:** added §14.9 simulation viewer (internal game replay with omniscient and per-slot views, experiment dashboard, failure triage), roles `sim_viewer`/`sim_reviewer`, viewer storage and retention; updated M1/M4 scope and gates (§10, §12) and open decision 17.
- **v0.8:** intercept never triggers combat at range (co-location only); newly laid minefields become active from the next turn; deterministic resource fallback queue (§3.4, A.3); fleet-combat target scoring, damage application, retreat and capture rules (§3.7).
- **v0.9:** named the game **Astris**, hosted at `astris.silverday.de`; v0.8's rule changes merged into the full v0.7 text (the v0.8 file had reduced §14, §15 and the appendices to stubs). Clarifications while merging: the fallback queue reserves stock at the start of phase 10 and leaves pre-movement refuelling outside the queue; retreat uses a seeded roll instead of a "deterministic probability"; combat tie-breaks rely on RNG-drawn opaque IDs; the undefined target-score terms are marked as open values. Added subdomain isolation (`__Host-` cookies, separate vhost/pool/credentials) and mail alignment for notifications. Tag `ORRERY_ADAPTATION` renamed to `ASTRIS_ADAPTATION`.
- **v0.10:** renamed Astris → **Caelorn** (Astris clashes with an existing game); host `caelorn.silverday.de`; tag renamed to `CAELORN_ADAPTATION`; open decision 1 now covers clearance of the new name. No rule changes.
