# Current Implementation vs. Business Requirements — Gap Analysis

Analysis only — no code, migrations, or configuration was changed in producing this document. Every finding below was re-verified directly against the repository (commit `2fc0885` on `main`, working tree clean, 45/45 tests passing) rather than carried forward from a prior conversation.

**Business context**: Clothing/fabric shop, multiple partners, employees, one main shop today with future branches/warehouses possible, evolving into a multi-tenant SaaS product where each tenant is fully isolated.

---

## Table of Contents

1. [Step 1 — Implementation verification](#step-1--implementation-verification)
2. [Step 2 — Business requirement comparison](#step-2--business-requirement-comparison)
3. [Step 3 — Critical existing decisions](#step-3--critical-existing-decisions)
4. [Step 4 — Business flow gaps](#step-4--business-flow-gaps)
5. [Step 5 — Database gaps](#step-5--database-gaps)
6. [Step 6 — Architecture gaps](#step-6--architecture-gaps)
7. [Step 7 — Prioritized findings](#step-7--prioritized-findings)
8. [Step 8 — Project score](#step-8--project-score)
9. [Step 9 — Bottom line](#step-9--bottom-line)

---

## Step 1 — Implementation verification

Re-checked against the live repository for this task.

| Area | Status | Evidence |
|---|---|---|
| Tenancy | ✅ | Database-per-tenant, `TenantResolver`/`Context`/`ConnectionFactory`/`IdentifyTenant`, fail-closed query guard on every model |
| Users | ⚠️ | Stock `App\Models\User`, tenant-scoped, no invitation/profile flow |
| Authentication | ❌ | `config/auth.php` stock and unreferenced; no login route anywhere |
| Products | ⚠️ | `products` table: base_unit_id, name, sku, status only — no category |
| Units | ✅ | `units` table, generic — no seeded Suit/Meter/Roll rows exist, but nothing prevents creating them |
| Unit conversion | ✅ | `unit_conversions` + `UnitConversionService::toBaseUnit()`, tested with a 1 Roll = 50 Meter case |
| Warehouses | ⚠️ | `warehouses` table exists, no `branch_id` — Branches doesn't exist |
| Inventory | ✅ | Full movement ledger, 9 reasons, atomic concurrency-safe stock-out, weighted average cost |
| Purchases | ❌ | No table, no model. Only `RecordPurchaseStockIn`/`RecordPurchaseReturn` exist on the Inventory side, called only by tests with fabricated reference IDs |
| Suppliers | ❌ | No table, no model, zero references anywhere |
| Customers | ⚠️ | `customers` table with a `balance` cache column, no itemized ledger table |
| Sales/POS | ✅ | Full domain layer — `ConfirmSale`/`CancelSale`/`ReturnSaleItems`, 16 passing tests. No UI/controller. |
| Payments | ⚠️ | `payment_methods` + `sale_payments` only — no standalone customer/supplier payment concept, no Cash register |
| Returns | ✅ (sales side) | `sale_returns`/`sale_return_items`, partial and full return tested. Purchase returns exist only as an Inventory primitive with no Purchase document to attach to. |
| Employees | ❌ | No table. `sales.sales_employee_id` is a bare FK to `users`, not an Employee entity |
| Partners | ❌ | No table, no model, zero references anywhere |
| Expenses | ❌ | Not implemented |
| Cash | ❌ | Not implemented — no register/session concept |
| Accounting | ❌ | No chart of accounts, no journal — not even schema-only |
| Reports | ❌ | Not implemented — nothing computes gross/net profit anywhere |
| Audit | ❌ | No `audit_logs` table, no observer, no logging of any sensitive action |
| SaaS (tenant provisioning mechanism) | ✅ | `tenants:create`/`tenants:migrate` commands, full DB isolation — the mechanism is real |
| SaaS (plans/subscriptions/billing) | ❌ | No table on either database |

---

## Step 2 — Business requirement comparison

### ✅ Units: Suit / Meter / Roll — Matches
- **Current**: Generic `units` + `unit_conversions` tables, no hard-coded unit names anywhere in code
- **Requirement**: Sell in Suit, Meter, or Roll
- **Gap**: None architecturally — these are just rows you'd create, not a code change. No Suit/Meter/Roll rows exist yet in any seeder, but that's a data question, not an implementation gap.

### ✅ Barcode not required for v1 — Matches
- **Current**: `products.sku` nullable, unique, unused by any flow
- **Gap**: None — reserved without being required, exactly as specified.

### ✅ Customers optional on sales — Matches
- **Current**: `sales.customer_id` nullable, walk-in sales tested and working

### ✅ Multiple payment methods per sale — Matches
- **Current**: `sale_payments`, one row per method, tested with a cash+bank split on one sale

### ✅ Partial payments — Matches
- **Current**: `balance_due` computed and stored, tested

### ⚠️ Accounting-ready architecture — Partial
- **Current**: COGS is captured per sale line (`unit_cost_snapshot`), but there is no chart of accounts, no journal entry concept, not even as an empty schema
- **Requirement**: "Eventually support proper accounting-ready architecture"
- **Gap**: The data needed to eventually feed accounting exists (costs, totals, dates) but nothing structures it toward a ledger yet
- **Severity**: Medium — acceptable at this stage since the requirement says "eventually," not "now"

### ❌ Partner ownership (50/50, non-hard-coded, changeable) — Missing
- **Current**: No Partners module exists at all
- **Requirement**: Two partners today, 50/50, must support more/fewer partners and changing percentages over time without a code change
- **Gap**: Total absence — but this is good news for the "must not hard-code" rule specifically: since nothing exists, nothing has been hard-coded incorrectly either. The constraint is inherited as a requirement for whenever this module is built, not violated today.
- **Severity**: High for business completeness, zero risk of the specific bad pattern (hard-coding) it warns against

### ❌ Partner capital contributions / withdrawals — Missing
- **Current**: No table, no model

### ❌ Employee fixed salary + sales-profit commission, not hard-coded to one employee — Missing
- **Current**: No Employee entity, no salary field, no commission rule/calculation anywhere
- **Requirement**: One employee today gets salary + 10% of eligible sales profit; the percentage and the salary-offset behavior must be configurable, not hard-coded to that one person
- **Gap**: Same situation as Partners — total absence means the specific anti-pattern (hard-coding for one employee) hasn't happened, but the actual capability doesn't exist either
- **Severity**: High — this is one of the two named, specific compensation flows the business runs today, not a hypothetical future feature

### ⚠️ One employee responsible for both shop sales and external purchases — Partial
- **Current**: The sales side has a place for this — `sales.sales_employee_id`. The purchasing side has nothing to attach an employee to, since Purchases doesn't exist.

### ✅ Multi-tenant SaaS, each tenant fully isolated — Matches (mechanism)
- **Current**: Database-per-tenant is real and tested — verified in the prior audit and unchanged since
- **Gap**: The isolation mechanism is solid; what it isolates is incomplete, since most business modules a second tenant would need (Purchases, Suppliers, Employees, Partners, Accounting) don't exist yet. Not a tenancy gap — a completeness gap that happens to be described using tenancy language in the request.

---

## Step 3 — Critical existing decisions

### A. Walk-in sales must be paid in full — ❓ Business decision required

- **What it is**: An **interim implementation assumption**, explicitly labeled as such in the code — `WalkInCreditNotAllowedException`, with a docblock stating it enforces "the safer interim answer to a gap the Phase 1 requirements review flagged and left explicitly open."
- **Not**: A confirmed business requirement. Nothing in the business context provided says walk-in customers can never owe money — that was Claude's own safest-default choice during Sales implementation, made because the code had to do *something* and an unowned receivable is unbookkeepable.
- **Why it needs your input**: A real fabric shop plausibly extends informal credit to known walk-in customers without formally registering them. If that happens in practice, this rule is wrong and would need the customer to be registered first — a UX/workflow question, not just a code question.

### B. Weighted Average Cost — ✅ Compatible, with one caveat

- **Compatibility**: WAC is a reasonable fit for a fabric business specifically — fungible stock (bolts/rolls of the same fabric) sold in continuously variable cut lengths doesn't map cleanly onto "which exact unit was this" the way FIFO or specific-cost would. No conflict found with the business model.
- **The caveat**: The original architecture doc said to "evaluate WAC as default while keeping the architecture open for FIFO." The current implementation doesn't keep that door open — the WAC formula is inlined directly into raw SQL inside `PostInventoryMovement`, not behind a swappable costing-strategy interface. Switching to FIFO later would mean rewriting this class, not selecting a different implementation of an interface.
- **Severity**: Low today (WAC is very likely the right permanent choice for this business), worth knowing before it's load-bearing across many more sales.

### C. sales_employee_id → users is not sufficient for the compensation model — 🔴 Conflicts with requirement

- **Current**: A bare nullable FK to `users`. No salary, no commission rate, no employment status — nothing about "employee" as a concept exists.
- **Requirement**: An employee needs a fixed salary, a configurable commission rule tied to their sales, and (per the master prompt generally) should be trackable even if they never get system login access.
- **Gap**: Conflating "who gets credit for this sale" with "which system user is this" is the same category of problem flagged earlier for Partners vs. system access — a person can be an employee without needing a login, and the current FK assumes every commissioned employee is also a `users` row.
- **Severity**: High — this will need to change (a real Employees table, `sales_employee_id` retargeted) before commission can be built at all, not just extended.

### D. Customer balance without an itemized ledger — ⚠️ Insufficient for stated future needs

- **Current**: One `balance` column, incremented/decremented by Sales actions. No row-by-row history of what changed it.
- **Sufficient for**: Today's actual behavior — showing "does this customer currently owe money."
- **Insufficient for**: Statements ("what did they owe on a given date"), accounting (a balance with no paper trail can't be reconciled after a dispute or a bug), and reporting that needs to break receivables down by period or by source sale.
- **Severity**: Medium now, would become high the moment real money and real customers depend on this balance being provably correct.

### E. Payment design — supports today's needs, not tomorrow's — ⚠️ Partial

- **Multiple payment methods**: ✅ — genuinely supported and tested
- **Partial payment**: ✅ — genuinely supported and tested
- **Future customer payments** (paying down a balance outside of a specific sale): ❌ — `sale_payments` only ever attaches to one sale; there's no way to record "customer walked in and paid 5,000 against their tab" with no sale happening
- **Future supplier payments**: ❌ — doesn't exist, since Purchases doesn't exist
- **Cash/bank reconciliation**: ❌ — no Cash register/session concept exists at all; there's nothing to reconcile against

---

## Step 4 — Business flow gaps

### Purchase flow

| Stage | Status | Note |
|---|---|---|
| Employee | ❌ | No Employee entity to attribute a purchase to (see §3C) |
| Supplier | ❌ | No table at all |
| Purchase | ❌ | No header table — cannot record that a purchase happened, only that stock arrived |
| Purchase Items | ❌ | No table |
| Payment | ❌ | No supplier-payment concept |
| Inventory | ✅ | The receiving mechanism exists — `RecordPurchaseStockIn` — but nothing calls it except tests |
| Supplier Balance | ❌ | No Supplier entity to hold a balance on |
| Accounting | ❌ | Nothing exists |

One out of eight stages exists, and only as an internal primitive with nothing upstream calling it for a real reason.

### Sale flow

| Stage | Status | Note |
|---|---|---|
| Employee | ⚠️ | Bare FK to users, no compensation model behind it (§3C) |
| POS | ❌ | No UI, no controller, no route — domain layer only |
| Sale | ✅ | `ConfirmSale`, tested |
| Sale Items | ✅ | tested |
| Multiple Payments | ✅ | tested |
| Inventory | ✅ | Correctly bounded through Inventory's own Actions, tested |
| Customer Balance | ⚠️ | Updated, but see §3D on the missing ledger |
| Profit | ❌ | COGS is captured per line, but nothing sums it into gross/net profit anywhere |
| Employee Commission | ❌ | No mechanism exists to compute or accrue it |
| Cash | ❌ | No register/session concept |
| Accounting | ❌ | Nothing exists |
| Audit | ❌ | Nothing exists — a cancelled or returned sale today leaves no record of who did it |

### Employee commission flow

| Stage | Status | Note |
|---|---|---|
| Sales employee | ❌ | Needs to become a real entity, not a bare users FK (§3C) |
| Eligible sales | ❌ | The raw data exists (`sales.sales_employee_id`) but nothing queries or filters by it for this purpose |
| Sales cost | ✅ | `unit_cost_snapshot` already captured per line — this is the one piece already in place |
| Profit | ❌ | Not computed anywhere (same gap as the Sale flow above) |
| Commission calculation | ❌ | No rule engine, no percentage storage, no basis (sales/gross-profit/net-profit) distinction exists |
| Monthly period | ❌ | No period/closing concept exists anywhere in the codebase |
| Salary/commission relationship | ❌ | Neither salary nor commission exists yet, so their interaction (the "configurable offset behavior" the business context calls out) is entirely undesigned |
| Final payable | ❌ | No employee ledger exists to post it to |

### Partner flow

| Stage | Status | Note |
|---|---|---|
| Partner | ❌ | No entity exists |
| Capital contribution | ❌ | No table |
| Ownership % | ❌ | No table — and critically, no effective-dating concept exists anywhere in the codebase yet (needed so a percentage change doesn't retroactively alter how a past period was split) |
| Business activity | ❌ | The underlying sales/inventory data exists to eventually derive profit from, but nothing connects it to partners today |
| Profit allocation | ❌ | Depends on profit calculation existing first (it doesn't) and ownership existing first (it doesn't) |
| Withdrawal | ❌ | No table |
| Distribution | ❌ | No table |
| Partner ledger | ❌ | No table |

Zero of eight stages exist. This is the least-built major business area relative to how concretely the business context describes it (named partners, named percentage, explicit "must not hard-code" instruction).

---

## Step 5 — Database gaps

Concepts, not schemas — no columns or migrations proposed here.

| Missing concept | Why it matters given the business context |
|---|---|
| Employee entity (distinct from User) | Salary and commission need somewhere to live that isn't a login account |
| Supplier entity | No way to know who fabric was bought from, or what's owed to them |
| Purchase document + purchase items | No way to record a purchase happened at all, only that stock arrived |
| Customer ledger (itemized, not just a balance) | Needed for statements and for the balance to be provably correct, not just currently correct |
| Supplier ledger | Symmetric need to the customer ledger, for payables instead of receivables |
| Cash register / session | No way to reconcile what's physically in the till against what the system thinks was collected |
| Expense records | Net profit is uncomputable without them |
| Partner ownership history (effective-dated) | Explicitly required by the business context — percentages change over time and must not silently rewrite the past |
| Partner capital / withdrawal records | Explicitly required by the business context |
| Commission rules + commission periods | Required for the named employee's compensation to be computed at all, and to avoid hard-coding it |
| Accounting entries (even schema-only) | Named as an eventual requirement; nothing exists to grow into yet |
| Audit logs | Required by the master prompt for every sensitive action; currently nothing is logged anywhere |

---

## Step 6 — Architecture gaps

### ✅ The Modules + Actions + Models + Tenancy pattern is proven and sufficient — No change needed

Four real modules (Tenancy, Products, Inventory, Sales) follow the exact same shape consistently, and each one a future phase would add (Purchases, Suppliers, Employees, Partners, Accounting) fits the identical pattern without inventing anything new — a table-backed Model, single-purpose Actions, module-owned Exceptions. There's no demonstrated case for converting Actions into a Services layer; the current granularity (one Action per verb) has kept every class small and readable so far, and nothing about the missing modules changes that.

### ⚠️ The event-driven boundary the architecture already committed to on paper doesn't exist in code yet — Gap, worth closing before it compounds

- **What was decided**: The approved system architecture specified that cross-cutting future modules (Commission, Accounting, Audit) should react to a `SaleConfirmed`-style event rather than being called directly by Sales — "publishers never know who's listening."
- **What exists**: Zero Events, zero Listeners, anywhere in the codebase. `ConfirmSale` currently only knows about Inventory (correctly, since Inventory is a strict lower layer — see the earlier module boundary audit). But it has no hook point at all for same-layer/cross-cutting concerns.
- **Why it matters**: The longer Commission/Accounting/Audit stay unbuilt, the more tempting it becomes to wire them into `ConfirmSale` directly when the pressure to ship them arrives — which would violate the boundary that's already been designed and documented. This isn't urgent today (nothing needs the event yet), but it's cheaper to add the dispatch scaffold now, with zero listeners, than to retrofit it once three modules are already calling into Sales directly.

---

## Step 7 — Prioritized findings

### 🔴 Must fix before continuing
None. Nothing found rises to "the foundation itself is unsafe" — every gap identified is an absence, not a defect in what already exists. This is consistent with the prior audit's conclusion.

### 🟠 Should fix soon
- Retarget employee attribution off a bare `users` FK onto a real Employee entity, before Commission is built on top of it (§3C) — building Commission against the current FK would need to be redone, not extended.
- Confirm the walk-in-credit rule with the actual business (§3A) — it's your call to make, not a code fix, but it's blocking in the sense that Sales behavior depends on an unconfirmed assumption today.
- Add the event-dispatch scaffold for Sales (§6) before Commission/Accounting/Audit get built — cheap now, expensive to retrofit.

### 🟡 Can wait
- Customer ledger itemization (§3D) — the current balance cache is fine until real reporting/statements are needed.
- Abstracting the WAC costing formula behind a swappable interface (§3B) — only matters if FIFO ever actually becomes necessary.

### 🟢 Future
- Everything in §4/§5 that depends on modules not yet started (Accounting, Reports, Cash reconciliation, SaaS billing) — correctly sequenced as later work, not neglected.

---

## Step 8 — Project score

| Area | Score | Why |
|---|---|---|
| Multi-tenancy | 9/10 | Structural isolation, well-tested; one minor middleware-group gap noted in the prior audit, unrelated to this analysis |
| Security | 7/10 | No auth exists to secure yet; what exists (tenant isolation, connection-mismatch Gate backstop) is sound |
| Database foundation | 8/10 | Consistent typing, restrict-by-default FKs, immutability genuinely enforced — narrow in coverage, not shaky where it exists |
| Inventory | 9/10 | Complete for what it covers; costing strategy not abstracted (§3B) is the only real ding |
| Sales | 7/10 | Solid domain layer, but sits on an insufficient employee reference and has no event hook for what comes next |
| Payments | 6/10 | Sale-attached payments are genuinely correct; standalone customer/supplier payments and cash reconciliation don't exist |
| Extensibility | 8/10 | The module pattern scales cleanly to what's missing; the un-built event boundary is the one thing that could erode this if ignored much longer |
| Accounting readiness | 3/10 | Raw data (costs, totals) exists; no ledger structure, no partner/employee financial entities, no audit trail |
| Testability | 8/10 | Everything that exists is well-tested (45/45); nothing exists yet for the untested areas to be a coverage gap in |
| Overall foundation | 8/10 | What's built is built correctly and consistently; the score reflects quality of the foundation, not how much of the building sits on it yet |

---

## Step 9 — Bottom line

### Is the foundation safe to continue building on?

**Yes.** Nothing found in this analysis is a defect in the existing code — every gap is something that hasn't been built yet, not something built wrong. The module pattern established across Tenancy/Products/Inventory/Sales is consistent and would extend cleanly to Purchases, Suppliers, Employees, Partners, and Accounting without needing an architecture change.

### 1. Critical issues
None. No finding in this analysis rises to "must fix before building the next module."

### 2. Important gaps
- Employee attribution on sales is a bare `users` FK, not a real Employee entity — will block Commission from being built correctly if addressed after the fact rather than before.
- No event-dispatch boundary exists yet for the cross-cutting modules (Commission, Accounting, Audit) that the approved architecture already designed around one — cheap to add now, more disruptive later.
- Partners and Employees-as-a-compensation-subject are the two most concretely-specified business areas in your own description and are currently at zero implementation.

### 3. Decisions that need your confirmation
- **Walk-in credit rule** (§3A) — is "walk-in sales must be paid in full" actually correct for how the shop operates, or was that too strict a default?
- **Salary/commission offset behavior** — the business context says this "must remain configurable" but doesn't say what the current employee's actual arrangement is; needed before Commission can be designed, not just built.
- **Partner capital vs. loan distinction** — carried over from the original Phase 1 gap list, still unresolved, still relevant now that Partners is the next likely major area.

### 4. One recommended next action

Before writing any more code: **get the three decisions above answered**. Every one of them changes the shape of the next module you'd build (Employees or Partners), and answering them now costs a conversation — answering them after the module exists costs a rebuild.

---

*Ledger & Loom — Requirements Gap Analysis · Analysis only · No code, migrations, or configuration changed*
