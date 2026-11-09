# Netplay Tournament Management System — User Manual

Netplay is the web system used by **OBC Badminton Facility** (Ozamiz City) to run local tournaments: publishing events, taking registrations, verifying GCash payments, drawing brackets or round-robin schedules, and recording match scores.

This manual explains **who can do what** and **how to do it**.

---

## 1. Roles at a glance

| Role | Needs an account? | What they do |
|---|---|---|
| **Visitor / Walk-in player** | No | Registers and pays for a tournament using a public link and a reference code |
| **Player** | Yes | Browses events, applies, pays, tracks applications, views matches and personal stats |
| **Umpire** | Yes | Views assigned matches, checks teams in, scores matches live, prints scoresheets |
| **Admin / Organizer** | Yes | Creates events and categories, approves applications, verifies payments, builds draws, assigns umpires, manages users, exports reports |

After login, the system sends each person to the right dashboard automatically (`/admin/dashboard`, `/player/dashboard` or `/umpire/dashboard`).

> **Players vs. users.** A *user* is someone who logs in. A *player* is someone who competes. Most OBC players are walk-ins with no account — staff or the player register them without one, and their match history is still kept. If they later sign up, their history can be linked to their account (see §3.6).

---

## 2. Everyone: accounts and sign-in

### 2.1 Create an account (Players and Umpires)
1. Go to **Register** (`/register`).
2. Choose your account type: **Player** or **Umpire**.
3. Fill in your details (name, email, birth date, phone, gender, address, club, optional photo) and set a password — or continue with **Google**.
4. **Umpires only:** a 6-digit code is emailed to you. Enter it within **10 minutes** (max 5 attempts; you can request a new code after 60 seconds). Nothing is saved until the code is verified.
5. Verify your email if prompted.

> Admin accounts are not self-registered; an existing admin creates them (see §5.9).

### 2.2 Sign in / out
- **Sign in** with email + password, or **Sign in with Google**. (If your account was created through Google it has no password — use the Google button.)
- **Forgot password?** Request a reset link by email, then set a new password.
- **Log out** from the account menu in the sidebar.

### 2.3 Settings (all logged-in users)
Open **Settings** to change **Profile** (name/email), **Password**, and **Appearance** (light/dark). Players can also edit their profile and password from the profile window on their dashboard.

---

## 3. Walk-in players and Players

### 3.1 Registering for a tournament without an account
1. Open the tournament's public registration link (`/tournaments/{tournament}/register`), usually shared by OBC.
2. **Step 1 – Your profile:** pick the category, then enter name, birth date, gender, address, skill level, club, email and phone. Only categories you are eligible for (age, gender, level) can be chosen; full categories are marked unavailable.
3. **Step 2 – Partner (doubles only):**
   - **Search** for your partner by name first — existing player records must be reused so history isn't split.
   - Only if they are not found, switch to **manual** and enter their details.
4. **Documents step:** choose the *type of document* and upload an ID/proof for each player (needed for eligibility checks).
5. **Review** everything and **Submit**.
6. You receive an email and an on-screen **reference code**. **Keep it** — it is your only way back in without an account.

### 3.2 Checking, paying and cancelling with your reference code
- **Lookup:** enter your reference code in the application lookup box on the home page to see your status.
- **Pay:** when the status reads *"Verification confirmed, proceed to payment"*, open the payment page (`/applications/{reference}/payment`), then:
  1. Pay the registration fee via **GCash** (scan the QR code shown).
  2. Enter the **GCash reference number** and **date paid**.
  3. Upload a **screenshot** of the payment (image, up to 4 MB).
  4. Submit. Staff will verify it.
- **If a proof is rejected,** you can submit a new one; earlier attempts stay on record.
- **Cancel:** while an application is still pending and **no payment is awaiting review or verified**, you can cancel it from the lookup window. This frees your slot and notifies your partner by email.

### 3.3 Application status guide

| Status shown | Meaning | What to do |
|---|---|---|
| Waiting for organizers verification | Submitted; admin hasn't reviewed it | Wait (you can cancel) |
| Verification confirmed, proceed to payment | Admin approved the application | Upload your GCash proof |
| Payment pending review | Proof uploaded | Wait for staff to verify |
| Approved | Paid and verified — you hold a slot in the draw | Watch for your match schedule |
| Rejected | Application or payment was declined | Read the reason in your email; reapply / resubmit if allowed |

### 3.4 Player dashboard (logged-in players)
- **Dashboard:** your upcoming tournament, open tournaments, and the progress of your applications.
- **Events:** two tabs — **Accepting Registration** (open events) and **My Applications**.
  - Click an event to see its categories, fees and slots, then **Register**. The wizard is the same as §3.1, except your profile is pre-filled and you can mark whether your partner has an account.
  - In **My Applications**, open an entry to see status, **pay**, **cancel** (if still allowed) or **delete** it (if rejected/closed).
- **Matches:** **Upcoming** and **Previous** tabs with date, time, court, opponent and result.
- **Profile window:** view your stats (matches, wins, win rate, level), edit your details, verify your email, and change your password (current password → new password → confirm).

### 3.5 Eligibility and fairness rules to know
- Singles = 1 player per entry; doubles = 2.
- A player can be in **only one entry per category** in a tournament (you can't enter alone and also as someone's partner).
- Your **skill level is frozen at application time** so seeding stays fair even if you're promoted mid-event.

### 3.6 Linking walk-in history to your new account
If you used to play as a walk-in and now have an account, the **link prompt** on your dashboard lists matching player records. Choose yours and send a **link request**. An admin approves or rejects it (you're emailed the decision); on approval your past matches and level join your account.

---

## 4. Umpires

### 4.1 Dashboard
Shows your assigned tournament, how many matches are assigned and how many you've completed, upcoming matches, and your latest completed match.

### 4.2 Finding your matches
- **Matches:** **Upcoming** and **Previous** tabs for everything assigned to you.
- **Events:** lists tournaments you officiate; open one to see its matches per category.

### 4.3 Scoring a match
Open a match to enter the **scoring screen**.

1. **Check teams in.** Tap **Ready** for each team once all of its players are present (you can undo this until the match starts).
2. **Start match.** Enabled once *both* teams are ready.
3. **Record points.** Tap the player/team that won the rally. Use **Undo last point** / **Redo point** to correct mistakes.
4. **Scoring rules are applied automatically** according to the event setting:
   - **Badminton – Best of 3 to 21:** a game is won at 21 with a 2-point lead, or outright at 30.
   - **Pickleball – 11-point, best of 3, win by 2.**
   - Single-set formats are also supported.
5. **Add set** to begin the next game when required.
6. **Line judges:** type the line judges' names (used on the scoresheet).
7. **Confirm results** when the match is decided. The result is saved and standings/brackets update.

### 4.4 No-shows (walkover)
If a team isn't ready by the **scheduled start time** and the other team is, a **Walkover** option appears for the team that showed up. Using it completes the match immediately with that team as winner.

### 4.5 Scoresheets
Open the **scoresheet** to view the rally-by-rally record, and **download** it as a document/PDF for printing or filing.

---

## 5. Admins / Organizers

The admin sidebar contains **Dashboard**, **Events**, **Categories**, and **Users**. Each event also has its own tabs (details, schedule, officials, reports).

### 5.1 Dashboard — approvals and payments
The dashboard has two tabs:
- **Approvals** — new applications waiting for review.
- **Payments** — uploaded payment proofs waiting for verification.

Use the search box and event/category filters to narrow the list, then click a row to open it.

**To process an application:** open it, review the player(s) and attached documents, then **Approve** (the player is emailed and can pay) or **Reject** (add an optional reason; it is shown to the applicant).

**To verify a payment:** open the proof, check the GCash reference, amount, date and screenshot, then **Approve** (entry becomes *Approved* and takes a slot) or **Reject** (the player is told to resubmit).

### 5.2 Categories (reusable event types)
**Categories** lists the library of event types (e.g. Men's Singles, Mixed Doubles Level B).
- **Create:** set the **name, gender, player level, minimum age, maximum age** and team size (singles/doubles), then Save.
- **Edit** (pencil) or **Delete** (bin) existing ones. Duplicates are blocked.

Categories are *shared*; fees and slot counts are set per tournament (see §5.4).

### 5.3 Creating an event (3-step wizard)
**Events → New Event**
1. **Details:** Event name, cover image, **sport** (Badminton or Pickleball), venue/location, **event days and date/time range**, registration start/end, **format** (*Single Elimination* or *Round Robin*), **scoring system**, and for round robin the **pointing system** and **1st / 2nd tiebreaker** (Head-to-Head, Point Differential).
2. **Review:** check the summary and **Confirm**.
3. **Categories:** choose **Add Categories** now or **Add Later**.

> The format applies to the **whole tournament**; only team size varies per category.

### 5.4 Managing an event
Open an event from **Events** (tabs: All / Published / Draft / Concluded, plus search).
- **Add categories:** *Event Categories → Add*, then set the **registration fee** and **maximum entries** for each. Edit or **Remove category** later.
- **Publish / Unpublish:** a draft is invisible to players. At least one category is required to publish.
- **View a category:** see its applicants/entries, and **cancel** a pending application with no payment (frees the slot and emails the players).
- **Edit event:** change details while allowed.
- **Conclude event:** closes registration and unlocks reports (published events only). **Reopen** is possible until the end date has passed.
- **Delete event:** only if it is not concluded and has no finalized records.
- **Cancelled events** notify applicants by email.

### 5.5 Schedule and draw (per category)
**Event → Schedule →** choose a category.
1. **Arrange pairings (single elimination only).** Drag entries or click-to-swap them between round-1 slots; **Reset** returns to seed order. Unplaced entries fill in seed order.
2. **Generate.** Optionally enable **auto-date** and enter **match length (min)**, **break between matches (min)** and **number of courts**; the system assigns date, time and court automatically. If matches don't fit the available days, you can **generate anyway** and set the leftovers by hand.
3. The full bracket (or round-robin fixtures) is created at once. Single elimination uses automatic advancement and BYEs for odd entry counts; round robin shows a live **standings table** (Rank, Entry, Point Diff, etc.).
4. **Edit a match:** change its date/time, court and umpire, then **Save**.
5. **Notify players:** emails scheduled matches to players; the page shows how many notifications are pending.
6. **Re-route an entry** to another category if needed (their draw position is cleared).
7. **Regenerate / reset draw** to start over — only before play has been recorded.

> Once matches exist the bracket is **read-only** for arrangement purposes.

### 5.6 Assigning umpires
**Event → Officials:** for each category pick an umpire from the list. That umpire is placed on all not-yet-played matches in the category. Choose blank to unassign.

### 5.7 Reports
**Event → Reports** (available once the event is concluded):
- **Sales Report:** by category — entries, paid, fee, collected, outstanding — plus payment lines (date, reference no., code, players, amount). Export as **CSV**.
- **Player Participation:** each player's category, gender, age, level, club and partner, with champion and runner-up. Export as **CSV**.

### 5.8 Users
**Users** has two tabs:
- **Users:** filter by role (Admin / Umpire / Player), search, **Add User**, **Edit** or **Delete**. You cannot demote yourself from admin.
- **Resolve Duplicates:** the system flags likely duplicate player records. **Merge** them (combining history) or **Dismiss** the suggestion. **Prune** removes unused guest records.
- **Link requests:** approve or reject players asking to link their old walk-in record to their account (a rejection reason can be added). You can also link a player to a record manually.

### 5.9 Typical day-to-day workflow
1. Create categories once; create the event; add categories with fees/slots; **Publish**.
2. Share the registration link with players.
3. Process **Approvals**, then **Payments** on the dashboard.
4. When registration closes, arrange pairings → **Generate** the draw → assign umpires → **Notify players**.
5. On tournament day, umpires score; results advance automatically.
6. **Conclude** the event and export reports.

---

## 6. Emails the system sends

Application received · Application approved / rejected / cancelled · Payment verified / rejected · Match scheduled · Event cancelled · Player-link decision · Password changed · Umpire sign-up code.

Make sure your email address is correct — it is how you receive status updates.

---

## 7. Tips and troubleshooting

| Problem | What to do |
|---|---|
| Lost my reference code | Check the "Application received" email, or ask OBC staff to look you up |
| Can't pick a category | You may not meet its age/gender/level rules, or it is full |
| "Partner not found" | Try other spelling or phone; only add manually as a last resort |
| Google account has no password | Always use **Sign in with Google**, or use *Forgot password* to set one |
| Can't cancel my application | Cancellation is blocked once a payment is pending review or verified — contact staff |
| Payment screenshot won't upload | Use an image file under 4 MB |
| Too many attempts message | Lookup, payment and sign-up codes are rate-limited; wait a minute and retry |
| Can't publish event | Add at least one category first |
| Can't delete event | It is concluded or already has finalized records |

For anything else, contact OBC Badminton Facility staff.
