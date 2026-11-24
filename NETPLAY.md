# NETPLAY.md

Domain context for the Netplay Tournament Management System.
`CLAUDE.md` covers the stack, commands, and Laravel conventions.
**This file covers what the system is actually about.**

Read this before creating any migration, model, or component in the
tournament / registration / bracket domain.

---

## 1. What this system is

A web-based badminton tournament management system built as an
undergraduate capstone project for **OBC Badminton Facility** (client
contact: Bryan Ytang), based in Ozamiz City, Philippines.

It replaces a manual, paper-and-spreadsheet workflow for running local
badminton tournaments.

### Who uses it

| Role | What they do |
|---|---|
| Admin / Organizer | Creates tournaments and categories, approves applications, verifies payments, generates draws |
| Umpire | Records match results and scores |
| Player | Registers for tournaments, views draws and results |
| Guest player | Plays but never logs in — registered on their behalf by staff |

**Critical constraint:** the client has confirmed that **most OBC players
are walk-ins** who do not have accounts. The system must fully support
players who never log in, including accumulating their match history and
skill level over time.

---

## 2. Domain glossary

Use these terms consistently in code, comments, and commit messages.

| Term | Meaning |
|---|---|
| **Tournament** | A single event with a name, date range, and one format |
| **Category** | A reusable event type: Men's Singles, Mixed Doubles Level B, etc. Shared across tournaments |
| **Tournament Category** | A category *as offered in one specific tournament*. Carries the entry fee and slot count. Often called a "draw" |
| **Application** | A submitted registration for one tournament category, pending approval |
| **Entry** | An approved, paid application that occupies a slot in a bracket. **The competing unit** — one player in singles, a pair in doubles |
| **Seed** | An entry's ranked position used to place it in the draw |
| **Match** | One pairing within a draw, identified by round + position |
| **Match game** | An individual game inside a match. Badminton is best-of-3 to 21 |
| **BYE** | A round-1 match with only one competitor, auto-advanced |
| **Level** | A player's skill classification (used for category eligibility and seeding) |

Note the distinction between **player** and **user**:

- A **user** is someone who logs in (admin, organizer, umpire, or a player with an account).
- A **player** is someone who competes. Most players have no user account.

Every user who plays has a player record. Not every player has a user record.

---

## 3. Core data model

Twelve entities. Full DDL lives in `netplaydb_revised.sql` (design
reference — implement via Laravel migrations, not raw SQL).

```
users ──1:1── players ──1:1── player statistics
                 │
                 └──1:M── application players ──M:1── applications
                                                          │
tournaments ──1:M── tournament categories ──1:M──────────┘
       │                      │                           │
categories ──1:M──────────────┘                           │
                              │                    applications
                              │                      │      │
                              │                 payments  entries
                              │                              │
                              └──1:M── matches ──M:1─────────┘
                                          │
                                          └──1:M── match games
```

### Entity notes

- **tournament_categories** is an associative entity resolving the M:N
  between tournaments and categories. It carries `registration_fee`,
  `max_slots`, and `status`.
- **EVERYTHING downstream references `tournament_category_id`, never
  `category_id`.** This is what prevents registrations and matches from
  leaking across tournaments. Do not shortcut it.
- **application_players** holds one row per person in an entry. Singles = 1
  row, doubles = 2 rows. There is deliberately no "applicant" vs "partner"
  distinction — both players are equal and both accumulate history.
- **entries** is the bracket's competing unit. Matches reference entries,
  never players or applications directly.

---

## 4. Design decisions (do not undo these without asking)

Each was made deliberately. If a change seems to require reversing one,
raise it rather than working around it.

**4.1 — Tournament format is set at the tournament level, not per category.**
The client confirmed all categories in a tournament use the same format
(single elim / double elim / round robin). Only team size varies by
category.

**4.2 — Players are separate from users.**
Walk-ins are the majority. A player row is created once per human being and
reused across every tournament. `players.user_id` is nullable; it gets
filled in if that person later creates an account, and they keep their
whole history.

**4.3 — Player records are reused, never recreated.**
Before creating a player, staff must search existing players by name or
phone. Duplicate player rows split someone's match history and corrupt
their level. This search-first flow is required on both the registration
form and the partner field.

**4.4 — Team size lives on the category as an integer (1 or 2), not a string.**
Validation rule: the number of `application_players` rows must equal the
category's team size. One rule, no branching between singles and doubles.

**4.5 — Skill level is snapshotted at application time.**
`application_players.level_at_apply` is frozen so seeding stays fair if a
player is promoted mid-tournament. The player's *current* level lives on
their statistics record.

**4.6 — Bracket logic is written in PHP. brackets-manager.js is NOT used.**
Only **brackets-viewer.js** is used, for display. The generation and
advancement algorithm is implemented server-side in Laravel. Do not add
brackets-manager as a dependency.

**4.7 — Brackets are generated in full, up front.**
When a draw is created, ALL match rows are inserted immediately — every
round, mostly empty. Matches are never created incrementally as rounds
finish.

Advancement is arithmetic:
```
next_round    = round + 1
next_position = ceil(position / 2)
```

A BYE is a round-1 match with a null second competitor: mark it complete
immediately and advance the lone competitor using the same formula.

**4.7a — Pairings are arranged before generation, then locked.**
For single elimination, the admin can drag (or click-to-swap) entries
between round-1 slots on the schedule page before generating. The
arrangement is stored in `player_applications.draw_position` (1-based
round-1 slot; null = not placed, filled in seed order). Generation reads
it via `SingleEliminationScheduler::arrangement()`. Once matches exist the
bracket is read-only. Moving an entry to another category clears its
`draw_position`.

**4.8 — Payment is manual, by design.**
Players upload a GCash screenshot; staff verifies it. There is no payment
gateway integration (it would require a registered business account).
`payment_details` is deliberately 1:M per application so rejected proofs
can be resubmitted with a full audit trail. Payment state is derived from
the latest verified row — there is no duplicate `has_paid` flag.

**4.9 — Uploaded files must not go to the local filesystem in production.**
The deploy target's filesystem is ephemeral; uploads would be lost on
redeploy. Use a persistent volume or object storage via Laravel's
filesystem abstraction.

---

## 5. External interfaces

| Interface | Protocol | Notes |
|---|---|---|
| Google OAuth | HTTPS / OAuth 2.0 | Via Laravel Socialite. Two paths: browser redirects to Google; server exchanges the code. Users may have NO password — keep it nullable |
| brackets-viewer.js | HTTPS (CDN) | Loaded by the browser, not the server |
| SMTP provider | SMTP 587 | Email verification and application status notifications |
| File storage | Filesystem or S3 API | Payment proof images |
| Database | TCP 3306 / file | See open decision 6.1 |

No payment gateway. No SMS provider.

---

## 6. Open decisions — ask before assuming

**6.1 — Database engine and naming convention.**
The Laravel app currently runs on SQLite with the starter kit's default
`users` table (snake_case, `id` primary key). The separately-designed
schema uses MariaDB with PascalCase table and column names
(`TournamentID`, `ApplicationID`).

These conflict. The starter kit's Volt auth components and settings pages
depend on the default `users` schema. **Do not create a PascalCase `users`
table** — it would break existing authentication.

Recommended: adopt Laravel conventions throughout (snake_case, plural
tables, `id`, `created_at`/`updated_at`) and treat the `.sql` file as a
design reference rather than a literal target. Confirm with the team
before generating migrations either way.

**6.2 — Contact person for a doubles entry.**
Both players are equal in `application_players`, so there is no inherent
"primary" contact. Pending client input on whether one is needed.

**6.3 — Printing.**
Unclear whether OBC prints draw sheets or match slips. Affects whether a
print-friendly view is required.

---

## 7. Gotchas

- **Do not put registration fees on `categories`.** The fee varies per
  tournament and belongs on `tournament_categories`.
- **Do not store player names, ages, or addresses on applications.** Those
  live on `players` and are referenced. The only snapshot is level.
- **Money uses `decimal`, never `float`.**
- **Index every foreign key column.** The original draft schema had primary
  keys only, which would cause full table scans on any lookup by tournament
  or category.
- **Eager-load bracket queries.** Rendering a 32-entry draw naively causes
  N+1 queries, and brackets are refreshed constantly during tournament days.
- **A player can only be in one entry per draw.** Enforced by a unique
  constraint on `(tournament_category_id, player_id)` in
  `application_players`. Without it, someone can enter once directly and
  again as another person's partner.

---

## 8. Non-functional targets

- Must handle ~100 concurrent registrations (registration windows are bursty).
- Concurrent payment-proof uploads are the known bottleneck — the reason
  file storage is offloaded.
- Bracket generation runs a handful of times per tournament, triggered by an
  admin. It is not a hot path and does not need optimizing.
