# The Money Model, Before Any of It Is Code

Business & Financial Domain Specification — **Analysis only**. No code, migrations, models, or tables were created in producing this document. This is the specification Employees, Partners, Commission, Profit Sharing, and Accounting will be built against.

**Builds on**: Codebase audit → ERD documentation → Gap analysis
**Confirms**: 3 business rules given this turn

---

## Table of Contents

- [A. Confirmed business rules](#a-confirmed-business-rules)
- [B. Financial concepts](#b-financial-concepts)
- [C. Profit calculation model](#c-profit-calculation-model)
- [D. Employee + commission domain](#d-employee--commission-domain)
- [E. Partner + ownership domain](#e-partner--ownership-domain)
- [F. Capital vs. partner loan](#f-capital-vs-partner-loan)
- [G. Customer receivable model](#g-customer-receivable-model)
- [H. Supplier payable model](#h-supplier-payable-model)
- [I. Cash / bank / payment model](#i-cash--bank--payment-model)
- [J. Returns impact](#j-returns-impact)
- [K. Financial period / month-end model](#k-financial-period--month-end-model)
- [L. Accounting readiness](#l-accounting-readiness)
- [M. Remaining business decisions](#m-remaining-business-decisions)
- [N. Recommended next step](#n-recommended-next-step)

---

## A. Confirmed business rules

Three rules confirmed this turn — no longer interim assumptions. Everything below is designed to be consistent with these.

### Walk-in credit — ✅ Confirmed rule
Walk-in (no customer record) → must pay in full. Registered customer → partial payment / outstanding balance allowed.

This matches the current implementation exactly (`WalkInCreditNotAllowedException` in `ConfirmSale`) — no change needed there. What this confirmation actually resolves is the *status* of that code: it was written as an interim guess and is now a validated permanent rule.

### Employee commission is separate from salary — ✅ Confirmed rule
Salary 50,000 + Commission 5,000 = Total compensation 55,000. Commission never reduces or replaces salary.

This directly resolves the "salary-offset behavior" question left open in the gap analysis: there is no offset. They are two additive, independently-tracked compensation lines. Any future "offset" concept (commission counted as a draw against a minimum guarantee) is now out of scope unless separately requested.

### Partner capital contribution ≠ partner loan — ✅ Confirmed rule
Two financially distinct funding types that must never be confused with each other or with ownership percentage. Full treatment in [§F](#f-capital-vs-partner-loan).

---

## B. Financial concepts

Every concept named in the request, defined once, precisely, with its effect on profit, cash, equity, and liability stated explicitly rather than assumed.

### Revenue
The value of goods sold to a customer, recognized at the moment a sale is confirmed — not when payment is collected. This is already how `ConfirmSale` behaves today (a confirmed sale's `total` counts as revenue even if `balance_due` > 0), which is the correct accrual-style treatment for a business that extends credit to registered customers.

- Affects profit: **Yes**
- Affects cash directly: No
- Affects equity: Yes (via profit)
- Creates liability: No
- Ledger item: Yes
- Historical: Yes

### Sales
The transaction document, distinct from the revenue figure it produces. "Sales" is the event/record (`Sale` + `SaleItem` rows); "Revenue" is the financial amount that event recognizes. Created at sale confirmation.

- Affects profit: Yes · Affects cash directly: No · Creates liability: No · Ledger item: Yes · Historical: Yes (immutable)

### Cost of Goods Sold (COGS)
The cost of the specific inventory consumed by a sale, recognized at the same instant as the revenue it's matched against — already implemented as `sale_items.unit_cost_snapshot`, sourced from Inventory's weighted-average cost.

- Affects profit: Yes · Affects cash directly: No · Creates liability: No · Ledger item: Yes · Historical: Yes

### Gross Profit
**Revenue − COGS.** A derived figure, not a stored transaction — computed from Sales/SaleItems data, never entered directly. This is the number the confirmed commission rule is based on (see [§C](#c-profit-calculation-model)).

### Operating Expenses
Costs of running the business not tied to a specific sale's COGS — rent, utilities, and (per the careful treatment in §C) employee salary and commission. Recognized when incurred, whether or not paid yet.

- Affects profit: Yes · Affects cash: Yes (when paid) · Creates liability: Yes (if accrued, unpaid) · Ledger item: Yes · Historical: Yes

### Net Profit
**Gross Profit − Operating Expenses** (including salary and commission — see §C for why those two specifically belong here and partner distributions do not). A derived figure for a closed period.

### Employee Salary
Fixed periodic labor cost, effective-dated (so a raise doesn't rewrite what was true last month). A true operating expense.

- Affects profit: Yes · Affects cash: Yes (when paid) · Creates liability: Yes (accrued/unpaid) · Ledger item: Yes · Historical: Yes

### Employee Commission
Variable, performance-based labor cost, computed against gross profit per the confirmed rule. Also a true operating expense — additive to salary, never a substitute for it.

- Affects profit: Yes · Affects cash: Yes (when paid) · Creates liability: Yes (accrued/unpaid) · Ledger item: Yes · Historical: Yes

### Partner Capital Contribution — *Equity*
An owner injecting money into the business as owned equity — not a loan, not revenue, not repayable on demand.

- Affects profit: No · Affects cash: Yes (increases) · Affects equity: Yes (increases that partner's capital account) · Creates liability: No · Ledger item: Yes · Historical: Yes

### Partner Loan — *Liability*
An owner lending money to the business, to be repaid — a debt the business owes that specific partner, distinct from their ownership stake.

- Affects profit (principal): No · Affects cash: Yes (both directions) · Affects equity: No · Creates liability: Yes · Ledger item: Yes · Historical: Yes

### Partner Withdrawal / Drawing — *Equity*
A partner taking cash out against their own equity balance — not an expense, not tied to a specific distribution event.

- Affects profit: No · Affects cash: Yes (decreases) · Affects equity: Yes (decreases) · Creates liability: No · Ledger item: Yes · Historical: Yes

### Partner Profit Distribution — *Equity*
The formal allocation and payout of already-earned net profit, per ownership percentage effective during that period. Happens *after* net profit is computed — it does not participate in computing it.

- Affects profit: No (it's an outcome of profit, not an input) · Affects cash: Yes (when paid) · Affects equity: Yes · Creates liability: No, unless declared-but-unpaid · Ledger item: Yes · Historical: Yes

### Customer Receivable — *Asset*
Money a customer owes for goods already delivered on credit. An asset — money owed *to* the business, the opposite of a liability.

- Affects profit directly: No (revenue already recognized at sale) · Affects cash until collected: No · Creates liability: No · Ledger item: Yes · Historical: Yes

### Supplier Payable — *Liability*
Money the business owes a supplier for goods received on credit.

- Affects profit directly at time of purchase: No · Affects cash until paid: No · Creates liability: Yes · Ledger item: Yes · Historical: Yes

### Cash — *Asset*
Physical currency on hand. An asset account moved by nearly every other transaction on this page — sales collections, purchase payments, salary/commission payouts, partner funding and withdrawals.

- Is itself a profit event: No · Is the running total nearly everything else moves: Yes · Ledger item: Yes (tied to Cash Register sessions, see §I)

### Bank / Payment Accounts — *Asset*
The same idea as cash, for non-cash rails — each a separately reconcilable asset account.

- Is itself a profit event: No · Ledger item: Yes

---

## C. Profit calculation model

The precise flow, with the one rule the request specifically warned against silently assuming: salary, commission, and partner distributions are **not** the same kind of thing, and don't sit at the same place in this calculation.

```
    Sales Revenue
  − Cost of Goods Sold (COGS)
  ─────────────────────────────
  = Gross Profit                  (pure product margin — no labor cost of any kind in this figure)

  − Operating Expenses
      — Employee Salary           (true expense)
      — Employee Commission       (true expense — computed FROM gross profit, but itself sits below it as a cost)
      — Rent, utilities, other operating costs
  ─────────────────────────────
  = Net Profit                    (the figure partner distributions are calculated FROM, never a component OF)
```

### Why partner distributions and withdrawals are excluded entirely
They are not expenses of the business — they are what happens to profit *after* it has already been fully calculated. Net Profit is the business's result; distribution is partners deciding what to do with a result that already exists. Including a distribution as an "expense" would double-count it (net profit would fall by the distribution, and then equity would fall by the distribution again) and would misstate how profitable the business actually was in that period, which is exactly the kind of silent error the request asked not to introduce.

### Why the commission basis is confirmed, not assumed
Phase 1 flagged "sales revenue vs. gross profit vs. net profit" as an unresolved commission-basis ambiguity. This request's own worked example (Revenue 20,000 − COGS 14,000 = Gross profit 6,000 × 10% = Commission 600) resolves it directly: **the confirmed basis is Gross Profit**, not raw sales revenue and not net profit. Net profit would be circular anyway — commission is itself one of the expenses net profit subtracts, so net profit can't be computed until commission is already known.

---

## D. Employee + commission domain

### Employee vs. User — conceptual relationship
An Employee is a payroll/compensation subject. A User is a system-login identity. They overlap for some people and not others, so the domain models them as two independent entities linked by an optional reference, never as one collapsed into the other.

| Scenario | How the domain handles it |
|---|---|
| Employee with no login (e.g. a tailor who never touches the system) | Employee record exists; `user_id` on it is null. Salary/commission tracking works fully with no User at all. |
| Employee with a login | Employee record's `user_id` points at a User row. The User handles authentication; the Employee handles compensation. Neither owns the other. |
| Employee leaves the company | Employee record is archived (status change, not deleted — matches the immutability pattern already used everywhere else in this codebase), preserving all historical salary/commission history intact. |
| User account disabled while employment continues | Independent lifecycles: disabling the User's login access doesn't touch the Employee record or interrupt salary/commission accrual — they were never the same row. |
| One employee changes login account | Only the Employee's `user_id` reference changes; every historical sale/commission record that points at the Employee (not the User) is completely unaffected. |
| Sales attribution | A future `sales.sales_employee_id` would reference the Employee entity, not `users` directly — this is the specific retarget flagged in the prior gap analysis. |
| Commission attribution | Always via the Employee entity — commission is fundamentally a compensation concept, and compensation belongs to Employee, never to User. |

### Commission domain — conceptual components

| Component | Role |
|---|---|
| Commission Rule | The configurable definition: rate, basis (confirmed as Gross Profit), effective date range, optional scope (specific employee, or tenant-wide) — never hard-coded to one person, per the confirmed rule |
| Commission Period | A closed time window (monthly, per the business context) that eligible sales are grouped into for calculation |
| Eligible Sales | Confirmed sales attributed to the employee (via `sales_employee_id`) whose date falls within the period |
| Eligible Profit | Sum of (Revenue − COGS) across the employee's eligible sales for the period — i.e. their share of gross profit generated |
| Calculated Commission | Eligible Profit × the commission rate in effect for that period |
| Approval / Finalization | A status transition (computed → approved → paid) — mirrors the pattern already used for `sale_returns` and would reuse the same "someone reviews before it's payable" shape |
| Payment | A payout event against the employee's compensation ledger, separate from and additive to salary payment (per the confirmed rule) |
| Commission History | Every computed/approved/paid commission entry is immutable once created — corrections are new entries, matching the pattern used throughout this codebase (never edit a financial row) |

### The specific mechanics this request asked to be worked out

| Question | Answer |
|---|---|
| How are eligible sales identified? | Confirmed sales where `sales_employee_id` matches the employee and `confirmed_at` falls inside the commission period's date range |
| How do returns affect commission? | A return reduces the eligible profit for the sale it's against (revenue and COGS both reverse proportionally, per §J) — full treatment of the timing question (before vs. after finalization) is in §J and §M, since it's genuinely undetermined which correction mechanism to use |
| How do cancelled sales affect commission? | A cancelled sale is not eligible at all — cancellation reverses the entire sale, so it never contributes to eligible profit in the first place (assuming cancellation happens before the commission period closes; see §J for after-close handling) |
| How do discounts affect profit? | Already handled structurally — `sale_items.line_total` is already net of discount, so eligible profit computed from confirmed sale data automatically reflects discounts with no separate rule needed |
| How do partial payments affect eligibility? | ❓ genuinely undetermined — see §M. The confirmed walk-in rule settles who *can* owe a balance, but not whether an employee earns commission on the unpaid portion before it's collected. |
| Sale returned after commission already finalized? | ❓ genuinely undetermined — see §M for the two candidate mechanisms (retroactive correction vs. next-period offset) |
| How are monthly periods closed? | A period moves open → closed via an explicit action (mirrors `profit_periods`/`commission_periods` status pattern from the earlier architecture doc); once closed, its calculated entries are immutable — full treatment in §K |
| How are historical commission rates preserved? | Commission Rules are effective-dated, never edited in place — a rate change creates a new rule row with a new `effective_from`, exactly like the pattern already specified for partner ownership (§E) and already used for `employee_compensation` in the original schema design |

---

## E. Partner + ownership domain

### Why ownership must be historical, not a single current value
A partner's ownership percentage is a fact that is true *for a range of dates*, not a permanent attribute of the partner. The moment ownership is stored as a single mutable field on the Partner record, changing it retroactively corrupts every past profit calculation that should have used the old value.

**Worked example, matching the one in this request:**

**2026-01-01:** Partner A 50%, Partner B 50%. **2027-01-01:** Partner A 40%, Partner B 40%, Partner C 20%. A profit period closed in June 2026 must resolve ownership as "whatever was effective on that date" — 50/50 — regardless of what the ownership table says today, in 2027 or later. This is achieved by never overwriting an ownership row; a change creates a new row with a new `effective_from`, and the prior row's implicit `effective_to` becomes the new row's start date minus one day. A profit calculation for any historical period looks up whichever row was active on that period's dates — it never reads "current" ownership for a past period.

### Conceptual components

| Component | Role |
|---|---|
| Partner | The person/entity — name, contact, status (active/exited). Ownership itself does NOT live here. |
| Ownership Period | Effective-dated percentage per partner. All active partners' percentages must sum to 100% as of any given effective date — validated when a new period is recorded, not just at input time. |
| Partner Status | active / exited — a partner leaving doesn't delete their historical ownership periods or ledger entries, only marks them ineligible for new activity going forward |
| Partner Joining | A new Ownership Period starting on the join date; requires the other partners' percentages to be revised in the same transaction so the total still sums to 100% |
| Partner Leaving | Their final Ownership Period gets an `effective_to`, status flips to exited; remaining partners' percentages are revised the same way as joining, in the reverse direction |
| Percentage Change | Not an edit — a new Ownership Period row for every affected partner, all effective from the same date, all validated together to still sum to 100% |

### Profit distribution flow

1. **Profit Period closes** — Net Profit for the period is finalized (§C, §K)
2. **Determine distributable profit** — Typically the full Net Profit for the period, though a business could choose to retain some — this is a policy choice the domain should allow but not assume (❓ see §M on whether retention is needed now)
3. **Determine applicable ownership** — Look up each partner's effective percentage for the period's date range — if ownership changed mid-period, the period may need to be split into sub-ranges, each resolved against the ownership active during it (mirrors the approach already specified in the earlier architecture doc)
4. **Calculate partner allocation** — Distributable profit × each partner's applicable percentage (or sub-range-weighted percentage, if it changed mid-period)
5. **Record distribution** — A new, immutable Distribution entry per partner per period (or sub-range) — allocated amount tracked separately from amount actually paid out, since a distribution can be declared before it's physically paid
6. **Update partner ledger** — The distribution posts as a credit to that partner's equity ledger, distinct from any capital, loan, or withdrawal entries already there

### Four transactions that must never be conflated
**Profit allocation** is the calculated entitlement a partner has earned for a period, based on ownership %. **Profit distribution** is that entitlement actually being paid out (which could happen immediately, or be declared and paid later — two different moments). **Partner withdrawal** is a partner taking money against their equity independent of any specific period's profit — they could withdraw more or less than their latest allocation. **Partner loan repayment** is paying back debt, and has nothing to do with equity or ownership at all — it reduces a liability, not a capital account. Each needs its own transaction type; treating any two of these as interchangeable would misstate either the business's liabilities or a specific partner's equity.

---

## F. Capital vs. partner loan

| | Capital Contribution | Partner Loan |
|---|---|---|
| **What it is** | Owned equity — Partner A permanently adds 1,000,000 PKR as capital | Debt — Partner A lends 1,000,000 PKR, expecting it back |
| **Accounting impact** | Increases equity (a capital account) | Increases a liability (a payable specifically owed to that partner) |
| **Ownership impact** | None automatically — contributing capital does not itself change a partner's ownership %; a percentage change is always its own explicit Ownership Period record (§E), never inferred from a capital transaction | None — a loan never affects ownership, by definition |
| **Repayment behavior** | Not repayable on demand — capital is only recovered via withdrawal against equity, or if the business is wound down | Repayable — a Loan Repayment entry reduces the liability, symmetric to how the loan increased it |
| **Profit distribution impact** | Larger capital contribution does not by itself entitle a partner to a larger share of distributed profit — that's governed purely by ownership %, which is a separate decision (§E) | None — loan principal and interest (if any) are not profit distribution, they're debt service |
| **Partner balance impact** | Increases the partner's equity/capital ledger balance | Increases a separate loan-payable balance for that partner — never merged with their equity balance |
| **Interest** | N/A — equity doesn't earn interest, it earns a share of profit | **Future / optional** — not required by anything in the current business context; the domain should leave room for an optional interest rate on a loan record without assuming one is charged |
| **Interaction with withdrawals** | A withdrawal draws down the equity a contribution built up | A loan repayment draws down the liability a loan created — structurally a different transaction type from a withdrawal, even though both move cash out of the business to a partner |

---

## G. Customer receivable model

What the current single `customers.balance` cache would need to grow into — analysis only, no schema proposed.

| Future capability | What it needs conceptually |
|---|---|
| Sale on credit | Already works today — a confirmed sale with `balance_due` > 0 for a registered customer |
| Customer payment without a new sale | A standalone Payment entry against the customer, not attached to any specific Sale — doesn't exist today (flagged in the gap analysis); needed for "customer walks in and pays down their tab" |
| Sale return | A credit entry reducing what the customer owes, tied back to the specific sale/return it came from (§J) |
| Payment reversal / adjustment | A reversing entry (never an edit to a past entry, consistent with the immutability pattern already used everywhere) for a bounced payment or a correction |
| Historical statement | Requires every balance-affecting event to be its own row — an itemized ledger, not just a running total — so "everything that happened between two dates" can be reconstructed |
| Current balance | Still a fast cache, exactly as today — but reconcilable as the sum of the itemized ledger, the same relationship `stock_levels` already has to `inventory_movements` |
| Date-based balance ("what did they owe on March 1st") | Sum of ledger entries up to that date — only possible once individual entries exist instead of one mutable total |

**The confirmed walk-in rule is preserved by this design, not just compatible with it.** A customer ledger only ever gets entries for customers that exist as records — a walk-in has no Customer row to attach a ledger entry to, which is exactly what already makes the confirmed rule enforceable: there's structurally nowhere for walk-in credit to be recorded, not just a check that forbids it.

---

## H. Supplier payable model

1. **Purchase confirmed** — A payable is created for the unpaid portion, symmetric to how a sale creates a receivable
2. **Payable recorded** — Increases the supplier's balance — same itemized-ledger shape as §G, not a bare running total, for the same statement/reconciliation reasons
3. **Supplier payment** — A payment entry (possibly split across methods, mirroring how `sale_payments` already works) reduces the payable
4. **Purchase return** — Reduces the payable — goods sent back reduce what's owed for that purchase
5. **Payable adjusted** — Net effect of payment and return entries against the original payable, reconstructable from the ledger rather than tracked as a single mutable field

---

## I. Cash / bank / payment model

| Concept | Definition | Relationship to what exists today |
|---|---|---|
| Payment Method | A tenant-defined label for how money moves (Cash, Bank, JazzCash, ...) | Already exists — `payment_methods` |
| Payment Transaction | A single instance of money moving via a method, tied to what caused it (a sale, a purchase, a payroll payout, a partner transaction) | Already exists for sales specifically — `sale_payments`. The same shape would extend to purchase payments, customer standalone payments, and payroll/partner payouts. |
| Cash Account | The actual physical till — a real asset balance, distinct from the "Cash" payment method label | Does not exist yet — this is the gap a Cash Register module fills |
| Bank / Digital Wallet Account | A real, reconcilable balance for a non-cash rail — conceptually the same idea as a Cash Account, for a different medium | Does not exist yet |
| Cash Register (session) | A bounded period (open → close) during which a specific Cash Account's transactions are grouped for reconciliation — opening float, every cash movement during the session, a counted closing total | Does not exist yet — flagged as missing in both prior documents |

**The evolution this implies, stated without redesigning anything now.** Today, "Cash" is just a name in `payment_methods` — a label, not an account with its own balance. When Cash Register arrives, each Payment Method that represents cash-in-hand would need to be linked to a real Cash Account with its own running balance, fed by every `sale_payments`/future purchase-payment/payroll-payout row that used it. Bank and digital methods follow the same pattern without needing a physical till session wrapped around them. None of this requires renaming or restructuring `payment_methods` or `sale_payments` — it's an additive layer on top of what exists, not a rework.

---

## J. Returns impact

Worked against the request's own example: Revenue 10,000, COGS 7,000, Profit 3,000, fully or partially returned.

| Affected | Effect of a return |
|---|---|
| Revenue | Reduced by the returned line's value — a full return zeroes it, a partial return reduces it proportionally (already implemented this way in `ReturnSaleItems`) |
| COGS | Reduced proportionally along with revenue, since the matching principle requires cost and revenue to move together |
| Inventory | Increases — the returned stock comes back (via `RecordSaleReturn`, already implemented), assuming the sellable-condition path (the damaged-return path remains unwired, per the earlier audit) |
| Profit | Reduced by the same amount revenue and COGS both moved by — a return never creates a loss beyond reversing the original profit on that portion |
| Customer balance | Reduced — already implemented (`ReturnSaleItems` decrements `customers.balance` by the proportional refund) |
| Employee commission, if applicable | Should reduce eligible profit for that employee's period — mechanism depends on *when* the return happens relative to period close, addressed below |

### Partial vs. full return
Already correctly distinguished in the current implementation — a partial return reduces revenue/COGS/profit/balance proportionally to the quantity returned; a full return (or the cumulative effect of several partial returns reaching 100%) zeroes them out and flips the sale to `Refunded` status.

### Return after month-end, or after commission was already finalized

- **The problem**: A sale confirmed in March, commission calculated and paid for March, then the customer returns the goods in April. The commission already paid was calculated against profit that no longer exists.
- **Candidate A**: Retroactively correct the closed March period — requires "closed" periods to remain correctable, which conflicts with the immutability pattern used everywhere else and with the natural meaning of "finalized."
- **Candidate B**: Apply the reversal to the *current open* period as a negative adjustment — the employee's April commission is reduced by the March overpayment. Preserves period immutability; the employee "gives back" the excess through a lower future payout rather than a retroactive clawback.
- **Status**: ❓ Genuinely undetermined — see §M. Candidate B is more consistent with the rest of this codebase's design philosophy, but this is a real policy choice with real employee-relations implications, not a technical one.

---

## K. Financial period / month-end model

The same period concept, reused across Commission and Profit Distribution rather than invented twice.

1. **Open** — The period during which sales/purchases/expenses accrue normally — no calculation has happened yet, nothing is locked
2. **Calculation** — At period-end, eligible sales are aggregated (per employee, for commission; across the whole tenant, for profit), producing draft figures
3. **Review** — Draft figures are visible for a manager to check before anything becomes payable — mirrors the computed → approved pattern already specified for commission entries in the earlier architecture doc
4. **Finalization / locking** — The period moves to closed; its calculated entries become immutable, consistent with how every other financial record in this codebase behaves once confirmed
5. **Later correction** — Never an edit to a closed period — a correction is a new entry dated in whichever period is currently open, exactly the approach proposed for the return-after-close problem in §J

### Why one period concept, not three separate ones
Commission Period and Profit Period could be modeled as entirely separate mechanisms, but they share the identical lifecycle (open → calculate → review → lock → correct-forward-only) and very likely the identical monthly cadence for this business. Treating "period" as one reusable concept that Commission and Profit Sharing both close against — rather than each module inventing its own closing mechanism — is the kind of shared-concept opportunity worth deciding on purpose before either module exists, not discovering by accident after both are half-built differently.

---

## L. Accounting readiness

Which eventual accounting concepts this domain design implies — not designing them now, just naming what the money model above will need to feed.

| Future accounting concept | Fed by |
|---|---|
| Chart of Accounts | Would need at minimum: Cash, Bank, Accounts Receivable, Inventory, Accounts Payable, Partner Loans Payable, Partner Capital (per partner), Retained Earnings, Sales Revenue, COGS, Salary Expense, Commission Expense, Operating Expenses |
| Journal Entry / Journal Entry Lines | Every event in §B that has both a cash-or-asset side and a profit-or-equity side becomes a balanced debit/credit pair — the schema for this already exists per the earlier architecture doc, unpopulated |
| Receivables | Customer ledger (§G) |
| Payables | Supplier ledger (§H), Partner Loan balances (§F) |
| Cash / Bank | Cash Register / payment accounts (§I) |
| Inventory (as a balance-sheet asset) | Already computable today from `stock_levels.quantity_base_unit × average_cost` |
| Sales Revenue / COGS | Already captured per sale line today |
| Expenses | Not implemented yet (flagged in the gap analysis) — needed for rent/utilities/etc. |
| Salaries / Commission (as expense accounts) | §D |
| Partner Capital / Partner Loan (as distinct equity vs. liability accounts) | §F — the accounting distinction is the same distinction the business rule already draws |
| Drawings | §E, §F — a contra-equity account against partner capital |
| Profit Distribution | §E — posts against Retained Earnings when declared |

---

## M. Remaining business decisions

Only what genuinely cannot be determined from everything provided so far — not a restatement of things this document already resolved.

### 1. Does commission accrue on the unpaid portion of a partial payment, or only once collected?
- **Why it matters**: The confirmed rule settles *who* can carry a balance (registered customers), not whether an employee earns commission on revenue that hasn't been collected as cash yet
- **Affects**: Commission calculation (§D)
- **If accrual** (commission on full sale regardless of payment status): Simpler, matches how revenue itself is already recognized at confirmation — but an employee could earn commission on a sale that's later never fully collected
- **If cash-basis** (commission only on the collected portion): More conservative, protects against paying commission on bad debt — but requires re-triggering a commission recalculation every time a partial payment is later collected, adding real complexity

### 2. How is a return corrected after the commission period it belongs to has already closed?
- **Why it matters**: Directly affects whether closed periods can ever be reopened (a precedent that would ripple into Profit Periods too)
- **Affects**: Commission domain (§D), Period model (§K)
- **Option A — retroactive correction**: Reopens a "finalized" period, contradicting the immutability language used to describe finalization everywhere else
- **Option B — forward correction**: Applies the reversal to the current open period instead — preserves immutability, but means an employee's take-home in month N can be affected by a customer's decision in month N+1

### 3. Is retained (undistributed) profit a concept this business needs now, or is all net profit always distributed?
- **Why it matters**: Changes whether "distributable profit" in §E is always 100% of net profit, or a policy-driven subset
- **Affects**: Partner distribution flow (§E)
- **If always fully distributed**: Simpler — distribution = net profit × ownership %, no retention concept needed yet
- **If retention is possible**: Needs an explicit "how much of this period's profit are we distributing" decision point before allocation runs

### 4. Should Commission Period and Profit Period be the literal same period record, or two separately-closed periods that happen to share a cadence?
- **Why it matters**: Affects whether commission must be finalized before profit distribution can be calculated (since commission is an input to net profit — §C), or whether they can close independently
- **Affects**: Period model (§K), the dependency order between Commission and Profit Sharing modules
- **If one shared period**: Enforces the correct dependency automatically (profit can't close before commission does) but couples the two modules' closing schedules tightly
- **If separate periods**: More flexible, but requires an explicit rule ensuring profit distribution never runs against a still-open commission period for the same date range

---

## N. Recommended next step

**One recommendation only, as requested.**

Get the four decisions in §M answered before designing the Employee/Commission schema. All four sit specifically inside the Commission and Period domains — the very next module likely to be built — and every one of them changes what that schema needs to capture (an accrual-vs-cash flag, a correction-direction rule, a retention field, a period-coupling decision). Nothing else in this document is blocking; these four are, because they're schema-shaping, not just behavior-shaping.

---

*Ledger & Loom — Financial & Business Domain Specification · Analysis only · No code, migrations, models, or tables created*
