Dwelly  ●  Operations Process Automation Brief    May 2026 

## Dwelly **Operations Process** 

Automation Brief — End-to-End Requirements 

|||
|---|---|
|**Field**|**Value**|
|Prepared by|Dwelly — Kaushal Choudhury|
|Date|May 2026|
|Entities|Dwelly|
|Classification|Confidential|
|Purpose|Vendor briefing — Operations Process Automation|



Confidential — For vendor evaluation only    Page 1 of 20 

Dwelly  ●  Operations Process Automation Brief    May 2026 

## **01 Executive Summary** 

Dwelly is a tech-enabled property management company based in Guwahati, Assam that manages residential rental properties on behalf of owners. 

This document outlines Dwelly's full operations process and the requirements for its automation. The objective is to eliminate manual tracking in WhatsApp and Excel, ensure every operational task is gated and audited, automate financial reconciliation, and give every team member a roleappropriate view of the business in real time. 

**Goal: Every property-related event — maintenance request, rent collection, vendor job, payout, or owner communication — must be automatically logged, tracked through a defined workflow, and reconciled against the property's financial record without manual intervention.** 

## **1.1 The Problem Being Solved** 

|**Problem**|**Business impact**|
|---|---|
|||
|No audit trail|Repair approvals, payment confirmations, and owner decisions exist only<br>in WhatsApp history. Disputes cannot be resolved with evidence.|
|Manual financial<br>calculations|Prorated rent, security deposit instalments, deductions, and utility bills are<br>all calculated by hand every month.|
|Reactive communication|Owners must follow up with Dwelly for updates. Renewals discussed only<br>when owners ask — not proactively 60 days before expiry.|
|No unified financial view|No single screen shows rent collected, deductions, invoices, and net<br>payout for a property together.|
|Unapproved work|Repair work has been done and charged without owner approval,<br>creating retrospective disputes of ₹8,000–₹20,000 per incident.|
|Unscalable tracking|Ops data split across 9 property-specific Excel sheets. Agreement status<br>in a separate file. No consolidated portfolio view.|



Confidential — For vendor evaluation only    Page 2 of 20 

Dwelly  ●  Operations Process Automation Brief    May 2026 

## **02 Company & Business Context** 

## **2.1 What Dwelly Does** 

|||
|---|---|
|**Service**|**Description**|
|Owner onboarding|Documentation collection, SLA signing, property setup|
|Tenant acquisition|Lead management, screening, agreement, move-in audit|
|Rent collection|Monthly payout to owners with full financial reconciliation|
|Maintenance<br>coordination|Vendor management, repairs, cost allocation, gated approvals|
|Utility billing|Electricity submeter readings, water bill splitting across flats|
|Agreement<br>management|Creation, renewal, e-signature tracking, document vault|
|Move-in / move-out<br>audits|Photo and video documentation linked to property record|
|Security deposit<br>tracking|Collection in instalments, adjustments, and final settlement|



## **2.2 Revenue Models** 

||||
|---|---|---|
|**Model**|**Description**|**Fee collection**|
||||
|Rent share|Monthly % of rent|Auto-deducted from monthly owner<br>payout|
|Annual subscription|One month's rent|Charged at agreement signing or<br>renewal|



## **2.3 Property Lifecycle States** 

||||
|---|---|---|
|**State**|**Description**|**Active modules**|
||||
|Onboarding|Owner added. Documents being<br>collected. SLA unsigned.|Document checklist, SLA workflow|
|Vacant|All docs received, SLA signed. No<br>active tenant.|Tenant acquisition, property listing|
|Occupied|Active tenancy in progress.|All operations and financial modules|
|Archived|Owner exited Dwelly. Data<br>permanently preserved. Never<br>deleted.|Read-only historical view|



Confidential — For vendor evaluation only    Page 3 of 20 

Dwelly  ●  Operations Process Automation Brief    May 2026 

## **2.4 The Team** 

||||
|---|---|---|
|**Role**|**Count**|**Responsibility**|
|Business Owner|1|Full visibility. Strategic oversight. Can release payouts.|
|Operations Manager|1|Task creation and assignment. Payout release. Full ops control.|
|Operations<br>Executives|2|Property-level task execution. Vendor coordination.|
|Accountant|1|Financial reconciliation. Payout processing. Export.|



Confidential — For vendor evaluation only    Page 4 of 20 

Dwelly  ●  Operations Process Automation Brief    May 2026 

## **03 Core Architectural Principle** 

**Every record in the system must have property_id as its primary reference. Not the owner. Not the tenant. Not the executive. The property is the single source of truth for every financial, operational, and communication record.** 

When you open a property in the platform, you see its complete history — every rent payment, every repair, every invoice, every conversation, every audit — past and present. When a tenant changes, a new tenancy record is created on the same property. The property persists. Everything else is a relationship to it. 

|||
|---|---|
|**Everything that maps to a**<br>**property**||
|||
|Agreements|Tenancy records, rent terms, SD, pricing model|
|Rent collections|Monthly payment tracking, split payments, running balance|
|Utility bills|Electricity meter readings, water bill splits|
|Maintenance tasks|All ops work, vendor jobs, approvals, completion proof|
|Invoices|All amounts owed or deducted — every one linked to a task|
|Payouts|Monthly net payout calculation with all deductions|
|Society fees & levies|Monthly fees, one-off levies, owner decisions|
|Security deposits|Collection instalments, adjustments, final refund|
|Documents|Agreements, KYC, audits, e-challans, receipts|
|Communications|All owner/tenant/Dwelly conversations in three channels|
|Advance payments|What Dwelly paid on owner's behalf — recovery scheduled|
|Annual charges|Recurring costs auto-scheduled per property|



Confidential — For vendor evaluation only    Page 5 of 20 

Dwelly  ●  Operations Process Automation Brief    May 2026 

## **04 User Roles & Access Control** 

Four user roles. Access enforced at the database level — not just the UI. An executive cannot query records for unassigned properties even with direct API access. 

||||
|---|---|---|
|**Role**|**Scope**|**Key permissions**|
||||
|Business Owner|All entities, all<br>properties|View everything. Release payouts. Cannot create or<br>assign tasks.|
|Operations<br>Manager|All entities, all<br>properties|Create and assign tasks. Manage invoices. Release<br>payouts. View team performance.|
|Operations<br>Executive|Assigned properties<br>only|Create tasks for own properties. Update task progress.<br>Cannot see financial data.|
|Accountant|All entities, all<br>properties (read-only)|Full payout ledger. Export. View task status for hold<br>context. Cannot release payouts.|



||||||
|---|---|---|---|---|
|**Module**|**Business**<br>**owner**|**Manager**|**Executive**|**Accountant**|
||||||
|Business KPIs|Full|Full|None|Read|
|All properties|Full|Full|Own only|Read|
|Create / edit tasks|None|Full|Own<br>properties|None|
|Assign / reassign tasks|None|Full|Self-assign<br>only|None|
|Invoices|Full|Full|None|Read|
|Payout ledger|Full|Full|None|Full|
|Release payout|Full|Full|None|None|
|Rent collection|Full|Full|None|Read|
|Utility billing|Full|Full|Own<br>properties|Read|
|Agreements & documents|Full|Full|Own<br>properties|Read|
|Security deposits|Full|Full|Own<br>properties|Read|
|Owner communication|Full|Full|Own<br>properties|None|
|Tenant communication|Full|Full|Own<br>properties|None|
|Vendor management|Read|Full|Own<br>properties|None|
|Export data|Full|Full|None|Full|



Confidential — For vendor evaluation only    Page 6 of 20 

Dwelly  ●  Operations Process Automation Brief    May 2026 

## **05 Operations Workflows** 

Dwelly's operations are triggered at five moments in the property lifecycle. Each trigger has a defined multi-step workflow with mandatory gates — steps that cannot be skipped regardless of who performs them. 

|**Trigger**|**When it fires**|**Key outcome**|
|---|---|---|
||||
|Owner onboarding|New property added|All documents collected, SLA signed,<br>property goes live|
|Tenant move-in<br>(T1)|Tenant enters property|Audit documented, issues resolved, tenancy<br>started|
|Maintenance (T2)|Issue reported during tenancy|Issue resolved, cost allocated, invoice raised|
|Rent collection|Monthly — recurring|Rent collected, reconciled, owner payout<br>released|
|Tenant move-out<br>(T3)|Tenant vacates property|Audit done, deposit settled, property re-<br>listed|



## **5.1 Owner Onboarding** 

||||
|---|---|---|
|**Step**|**Action**|**Gate**|
||||
|1|Manager creates property record. Sets<br>entity, BHK type, furnishing, pricing model.|Required to proceed|
|2|System auto-selects correct SLA: entity ×<br>furnishing × pricing model. 6+ variants<br>centrally managed.|No manual file selection|
|3|Welcome message, document checklist, and<br>SLA auto-sent to owner via WhatsApp.|Templated — never typed manually|
|4|Five documents collected: Government ID,<br>PAN, electricity bill, cancelled cheque,<br>contact details.|All 5 required|
|5|Signed SLA uploaded to document vault.|Required|
|6|Property status changes onboarding →<br>vacant. Available for tenant acquisition.|Only when steps 4 + 5 complete|



## **5.2 Tenant Move-In (Trigger 1)** 

Every step is gated — the next step cannot proceed without the previous being marked complete. 

Confidential — For vendor evaluation only    Page 7 of 20 

Dwelly  ●  Operations Process Automation Brief    May 2026 

|**Step**|**Action**|**Mandatory gate**|
|---|---|---|
||||
|1|Full property audit — photos and videos of<br>every detail: switches, taps, fixtures, walls,<br>appliances, doors.|Minimum 2 photos and 1 video per room<br>required|
|2|Share audit report with both owner and<br>tenant via platform.|Both parties acknowledge receipt|
|3|Identify and classify all issues: solvable or<br>not solvable.|All issues classified before proceeding|
|4|For solvable issues: photograph specifically,<br>share with vendor, obtain written quotation.|Vendor quotation uploaded before step 5|
|5|Notify owner: issue photos + Dwelly quote<br>(vendor cost + configurable Dwelly margin<br>applied automatically by the system).|Owner approval recorded before step 6|
|6|Owner decides: fix themselves (Dwelly<br>follows up) OR use Dwelly vendor (exec<br>coordinates).|Owner decision recorded in system|
|7|On completion: photo/video proof captured.<br>Responsible party cross-verifies.|Completion photos required before invoice|
|8|Invoice raised. Owner: pay now or deduct<br>from next payout.|Party verification confirmed before task closes|



## **Key rule: Dwelly margin — configurable by manager, applied automatically** 

The Dwelly margin rate is configured by the Operations Manager or Business Owner. When an exec enters a vendor cost, the system automatically applies the current margin rate and displays the Dwelly-quoted amount separately. Executives cannot modify the margin — it is applied uniformly across all vendor jobs. 

## **5.3 Maintenance — During Tenancy (Trigger 2)** 

|**Step**|**Action**|**Gate**|
|---|---|---|
||||
|1|Tenant reports issue via tenant channel with<br>photos and videos.|Photos and/or short videos required — task<br>cannot be created without evidence|
|2|Exec determines: natural wear and tear<br>(owner cost) OR tenant fault (tenant cost).|Decision explicitly recorded|
|3|Decision communicated to responsible party.|Communication logged against property|
|4|Vendor quotation obtained. Dwelly margin<br>applied automatically by the system. Quote<br>shared with responsible party.|Quotation uploaded before notification|
|5|Party decides: own vendor (Dwelly follows<br>up) OR Dwelly vendor.|Decision recorded|
|6|Work executed. Photo and video proof<br>captured.|Evidence required before invoice|



Confidential — For vendor evaluation only    Page 8 of 20 

Dwelly  ●  Operations Process Automation Brief    May 2026 

|**Step**|**Action**|**Gate**|
|---|---|---|
||||
|7|Responsible party cross-verifies completed<br>work.|Verification required before task closes|
|8|Invoice raised. Payment: immediate or next<br>scheduled payment.|Invoice blocked without steps 6 and 7|



## **Dispute escalation — when tenant contests fault** 

||||
|---|---|---|
|**Step**|**Action**|**Outcome**|
|1|Tenant shares counter-evidence via tenant<br>channel.|Evidence logged against property|
|2|Ops Manager reviews against original move-<br>in audit — the authoritative baseline.|Move-in audit is the evidence source|
|3a|Issue pre-existed at move-in.|Owner bears cost. Return to main workflow.|
|3b|Property was clean at move-in.|Tenant fault upheld. Return to main workflow.|
|3c|Genuinely unclear.|Cost split proposed. Both parties must agree.|
|4|Still unresolved after senior review.|Issue deferred. Flagged for move-out<br>settlement.|



## **Infrastructure and capital works** 

For large multi-phase projects (water supply systems, major renovation, rewiring), the infrastructure task type includes named project phases, materials lists, and mandatory owner briefing confirmation at each phase change. If the plan changes, the owner must be re-briefed and reconfirm before work continues. 

## **5.4 Rent Collection — Monthly** 

||||
|---|---|---|
|**Day**|**Action**|**Who**|
|1|Auto WhatsApp reminder to tenant: rent due.|System|
|3|Follow-up reminder if still unpaid.|System|
|5|Exec notified in app. Owner CC'd on tenant reminder.|System + exec|
|7|Manager alerted. Exec must log direct call outcome.|Manager|
|10|Formal notice sent — manager approval required before<br>sending.|Manager-approved|
|15|Property flagged as default risk. Dwelly 2-month default cover<br>activated.|System|



Confidential — For vendor evaluation only    Page 9 of 20 

Dwelly  ●  Operations Process Automation Brief    May 2026 

## **Payment confirmation workflow** 

|||
|---|---|
|**Step**|**Action**|
|Tenant marks sent|Uploads screenshot to tenant channel, or exec records on their behalf|
|Exec confirms|Verifies: amount, date, payment mode, transaction reference|
|System updates|Payout calculator updated. Split payment running balance tracked.|
|Receipt sent|Payment confirmation message auto-sent to tenant|



## **Society fees — three models** 

|||
|---|---|
|**Model**|**How it works**|
|Tenant pays directly|Tenant pays society monthly. Dwelly tracks receipt upload. Follows up if<br>missed.|
|Bundled in payout|Rent + society fee = net payout. Dwelly responsible for payment.|
|Dwelly advances,<br>recovers|Dwelly pays on owner request. Logged as advance. Deducted from next<br>payout.|



## **5.5 Tenant Move-Out (Trigger 3)** 

||||
|---|---|---|
|**Phase**|**Steps**|**Key rules**|
||||
|1 —<br>Handover &<br>audit|Tenant hands over keys. Full move-out<br>audit — photos and videos. Report<br>shared with owner and tenant.|Tenant does not re-enter after key<br>handover. Deferred T2 disputes reviewed<br>here.|
|2 —<br>Damage<br>assessment|Move-out audit compared against move-<br>in audit. Damage documented. Vendor<br>quote obtained. Both parties agree.|Move-in audit is the evidence baseline —<br>no subjective assessment.|
|3 —<br>Deposit<br>settlement|Repair cost deducted from SD. Balance<br>refunded. If damage exceeds SD, tenant<br>invoiced. If unpaid, escalated.|All deductions linked to invoices. No free-<br>form deductions.|
|4 —<br>Closure|Final report to owner. Reconciliation<br>statement raised. Property status →<br>vacant.|Property cannot be re-listed until all four<br>phases complete.|



Confidential — For vendor evaluation only    Page 10 of 20 

Dwelly  ●  Operations Process Automation Brief    May 2026 

## **06 Financial Reconciliation & Payout** 

**Net payout = Rent collected − Dwelly fee + Society fees (bundled) + Owner-reported variable charges − Invoice deductions − Advance payment recoveries** 

## **6.1 Per-Property Monthly Ledger** 

||||
|---|---|---|
|**Line item**|**Source**|**When it appears**|
||||
|Rent collected|Confirmed from tenant<br>screenshot|After payment is confirmed by exec|
|Dwelly fee|Auto-calculated (rent × fee %)|Computed from agreement — no<br>manual entry|
|Society fees added|Bundled model properties only|Per property configuration|
|Owner-reported<br>charges|Variable bills shared by owner<br>(e.g. water)|When owner uploads bill via owner<br>channel|
|Invoice deductions|Maintenance invoices agreed by<br>owner|When invoice is approved for payout<br>deduction|
|Advance recoveries|Previous advances paid by<br>Dwelly|Per scheduled recovery month|
|Net payout|Auto-computed|After all inputs are confirmed|



## **6.2 Payout Holds & Proactive Notification** 

Payouts are automatically held when: rent is not yet confirmed, an invoice is awaiting owner approval, damage assessment is pending, or a variable charge (e.g. water bill) has not been shared by the owner. The owner is notified proactively with the hold reason and expected release date — before they have to ask. 

## **6.3 Deferred Deductions** 

Deductions can be pinned to a specific future month — not just 'next payout'. Annual society maintenance, subscription renewals, and deferred repair costs are auto-scheduled and visible on the accountant dashboard 30 days in advance. 

## **6.4 Owner Portfolio View** 

For owners with multiple properties (e.g. one owner has 4 units in the same building), the platform shows individual payout per unit and a combined total. Payouts can be sent as one NEFT transfer or per-unit — configurable per owner. 

Confidential — For vendor evaluation only    Page 11 of 20 

Dwelly  ●  Operations Process Automation Brief    May 2026 

## **07 Utility Billing** 

## **7.1 Electricity — Submeter Based** 

||||
|---|---|---|
|**Step**|**Action**|**Automation**|
|1|Exec records opening and closing meter<br>readings with a meter photo.|Readings stored against the property|
|2|Bill amount auto-calculated.|units = closing − opening; bill = units × rate per<br>unit|
|3|Bill shared with tenant via tenant channel.|Template message auto-populated|
|4|Rate changes are versioned — historical<br>bills always use the rate in effect at billing<br>time.|Changing rate does not recalculate past bills|



## **7.2 Water Bill Splitting** 

|**Step**|**Action**|
|---|---|
|1|Exec enters total bill amount and number of flats sharing.|
|2|Each flat's share auto-calculated (equal split or area-based — configurable).|
|3|Each tenant billed their individual share separately.|
|4|Society receipt uploaded against the bill record.|



## **7.3 Owner-Reported Variable Charges** 

Some properties have variable water or utility charges the owner reports each month. Owner uploads the bill directly into the owner channel. Exec confirms. Amount added to that month's payout automatically — no manual arithmetic required. If the bill is not yet shared, the payout is held with reason shown to owner. 

Confidential — For vendor evaluation only    Page 12 of 20 

Dwelly  ●  Operations Process Automation Brief    May 2026 

## **08 Communication Hub** 

**Every communication thread belongs to a property — not to an owner, not to a tenant, not to an executive. When the exec changes, the history stays with the property.** 

## **8.1 Three Channels Per Property** 

||||
|---|---|---|
|**Channel**|**Participants**|**What it handles**|
|Owner ↔ Dwelly|Owner(s) + Dwelly team<br>only|Payouts, repair approvals, levy decisions,<br>renewals, delay notices, plan updates|
|Tenant ↔ Dwelly|Tenant + Dwelly team<br>only|Rent reminders, maintenance updates, move-in/out<br>coordination, receipts|
|Three-way|Owner + tenant + Dwelly|Property handover, audit sharing, damage<br>disputes, notice period, agreement signing|



## **8.2 Key Requirements** 

- Every message tagged to a property — never mixed with another property even if same owner 

- Messages link to records: tasks, invoices, approvals, payouts. Context never lost. 

- Pre-built templates auto-populate with correct property figures for all standard communications 

- Records tab per property: complete linked history of all invoices, tasks, payouts, disputes, exec handovers 

- Co-owners get individual notification preferences: every transaction, summary only, or none 

## **8.3 Follow-Up Action Items** 

Any message in any channel can generate a tracked follow-up task in two taps — converting verbal commitments into assigned, dated action items visible to the manager. Overdue follow-ups escalate identically to overdue ops tasks. 

## **8.4 WhatsApp Automation Requirements** 

|**Trigger**|**WhatsApp action**|**Recipients**|
|---|---|---|
|Rent due (Day 1)|Reminder with amount and due date|Tenant|
|Rent overdue (Day 3)|Follow-up reminder|Tenant|
|Rent overdue (Day 5)|Escalation message|Tenant + owner CC|
|Payment confirmed|Confirmation with amount and reference|Tenant|
|Payout sent|Amount, reference, and breakdown|Owner|



Confidential — For vendor evaluation only    Page 13 of 20 

Dwelly  ●  Operations Process Automation Brief    May 2026 

|**Trigger**|**WhatsApp action**|**Recipients**|
|---|---|---|
||||
|Payout hold|Reason and expected release date —<br>proactive|Owner|
|Banking delay|Apology with resolution date|All affected owners<br>simultaneously|
|Owner approval required|Repair details, quote, and options|Owner|
|Agreement ready to sign|E-sign link|Owner first, then tenant<br>after owner signs|
|Renewal reminder|Agreement expiring in N days|Tenant + owner|
|New exec assigned|Introduction of new exec|Owner|
|Society levy decision|Levy details and three options|Owner|
|Infrastructure plan changed|Updated plan requiring owner re-<br>confirmation|Owner|
|E-signature reminder|Reminder if unsigned after 48 hours|Signing party|



Confidential — For vendor evaluation only    Page 14 of 20 

Dwelly  ●  Operations Process Automation Brief    May 2026 

## **09 Document Management** 

||||
|---|---|---|
|**Document**<br>**category**|**Documents**|**Key rule**|
||||
|Owner KYC|Government ID, PAN, cancelled<br>cheque|All 3 required before property goes live|
|Tenant KYC|Aadhaar, PAN, Voter ID|Each tracked independently with upload<br>status|
|Agreements|Draft, owner-signed, tenant-signed|Owner must sign before tenant receives<br>— enforced by system|
|Police verification|e-challan PDF with reference and<br>amount|Stored and retrievable instantly|
|Payment receipts|Move-in payment, SD instalments,<br>rent screenshots|Linked to financial records|
|Audit reports|Move-in and move-out photo/video<br>collections|Linked to specific audit task|
|SLA/MoU|Correct variant auto-selected by<br>system|6+ variants: entity × furnishing × pricing<br>model|



## **9.1 E-Signature Tracking** 

|**Event**|**System action**|
|---|---|
|||
|Agreement sent for<br>signature|Record created with timestamp and recipient|
|48 hours — unsigned|Auto WhatsApp reminder. Exec notified in app.|
|96 hours — still unsigned|Second reminder. Manager notified.|
|Owner signs|Exec prompted: 'Owner signed. Share copy with tenant now.'|
|Signed copy shared|Exec confirms sharing. Record closed.|



Confidential — For vendor evaluation only    Page 15 of 20 

Dwelly  ●  Operations Process Automation Brief    May 2026 

## **10 Security Deposit Tracking** 

||||
|---|---|---|
|**Stage**|**Description**|**Automation**|
||||
|Collection|SD in 1, 2, or 3 instalments per<br>agreement|Each tranche tracked with due date,<br>screenshot, and payment reference|
|Held by|SD held by owner or Dwelly —<br>recorded explicitly|Shown on property financial view|
|Notice period<br>adjustment|Last month's rent adjusted<br>against SD when notice given|Auto-calculated. One-tap reversal if tenant<br>stays.|
|Damage<br>deduction|Damage cost deducted post<br>move-out audit|Must be agreed by both parties. Linked to<br>invoice.|
|Refund|Remaining balance refunded to<br>tenant|Exec prompted after move-out closure|
|Shortfall|If damage exceeds SD, tenant<br>invoiced for difference|Escalated for recovery if unpaid|



Every adjustment is an immutable log entry — entries cannot be edited or deleted. The current SD balance is computed in real time from the full adjustment history. 

Confidential — For vendor evaluation only    Page 16 of 20 

Dwelly  ●  Operations Process Automation Brief    May 2026 

## **11 Automation Requirements Summary** 

The primary requirements list for vendor evaluation. 

## **11.1 Workflow Automation** 

||||
|---|---|---|
|**Requirement**|**Priority**|**Description**|
||||
|Gated workflow steps|Critical|Each step can only advance when its gate condition is met<br>— enforced at data level, not UI.|
|Configurable margin auto-<br>calculation|High|System applies a configurable margin (set by Operations<br>Manager or Business Owner) to vendor cost. Applied<br>automatically — cannot be bypassed by executives.|
|Owner approval gate|Critical|No vendor assigned until owner approval formally recorded.<br>Blocked at DB level.|
|SD adjustment on notice|High|Last month's rent auto-adjusted against SD on notice. One-<br>tap reversal if tenant stays.|
|Renewal auto-trigger|High|Renewal task created 60 days before agreement end.<br>Configurable per property.|
|Move-in payment<br>calculator|High|Prorated rent, SD instalments, deposits auto-calculated<br>from move-in date.|
|Bulk issue grouping|High|Multiple simultaneous issues grouped: one owner<br>notification, combined vendor quote.|
|Annual charges<br>scheduling|High|Recurring annual charges auto-flagged 30 days before due.|
|Infrastructure plan re-<br>briefing|Medium|Owner_briefed flag resets on plan change. Re-confirmation<br>required before work continues.|



## **11.2 Communication Automation** 

||||
|---|---|---|
|**Requirement**|**Priority**|**Description**|
|WhatsApp Business API|Critical|All automated messages via Meta WhatsApp Business API<br>with approved template IDs.|
|Rent reminder sequence|Critical|5-step automated escalation Day 1–15. Steps 1–3 fully<br>automated. Steps 4–5 need manager action.|
|Payout notification|Critical|Owner notified automatically when payout sent, held, or<br>delayed — proactively.|
|E-signature reminders|High|48hr and 96hr reminders. Prompt to share signed copy<br>immediately after signing.|
|Follow-up from message|High|Any message generates a follow-up task in two taps —<br>assigned exec, due date, pre-filled note.|



Confidential — For vendor evaluation only    Page 17 of 20 

Dwelly  ●  Operations Process Automation Brief    May 2026 

|**Requirement**|**Priority**|**Description**|
|---|---|---|
||||
|Bulk delay notification|High|Banking delay logged once — auto-notifies all affected<br>owners simultaneously.|
|Owner plan change alert|Medium|Auto-notification when infrastructure plan update requires<br>owner re-briefing.|



## **11.3 Financial Automation** 

||||
|---|---|---|
|**Requirement**|**Priority**|**Description**|
|Payout auto-calculation|Critical|Net payout computed from rent, fee, society fees,<br>deductions, advance recoveries.|
|Payout hold with reason|Critical|Payout blocked if rent unconfirmed or invoice unapproved.<br>Reason visible to owner.|
|Deferred deduction<br>scheduling|High|Deductions scheduled to specific future months — not just<br>'next payout'.|
|Advance payment tracking|High|Advances logged, recovery months set, auto-deducted from<br>scheduled payout.|
|Tenant risk score update|High|On-time rate, avg days late, split count recalculated on<br>every payment recorded.|
|Utility bill calculation|High|Electricity auto-calculated: readings × rate. Rate versioned;<br>historical bills preserved.|
|Water bill split|High|Equal or area-based split from total bill and unit count.|
|Multi-unit combined<br>payout|Medium|Individual payout per unit and combined total for multi-<br>property owners.|



## **11.4 Access Control** 

||||
|---|---|---|
|**Requirement**|**Priority**|**Description**|
|Role-based access at DB<br>level|Critical|Row-Level Security at database query level. Not UI hiding<br>alone.|
|Property assignment<br>drives visibility|Critical|Exec sees only assigned properties. Changes take effect<br>immediately.|
|Financial data blocked<br>from executives|Critical|Rent amounts, invoices, payouts not visible to executives at<br>any level.|
|Formal notice manager<br>approval|High|Escalation steps 4+ blocked until manager approval<br>recorded.|
|Owner expense threshold<br>enforcement|High|Above threshold: approval required from all co-owners<br>before proceeding.|
|Archive not delete|High|Properties cannot be deleted. Archived with full historical<br>data preserved.|



Confidential — For vendor evaluation only    Page 18 of 20 

Dwelly  ●  Operations Process Automation Brief    May 2026 

## **Requirement Priority Description** 

## **12 Tenant Risk Scoring** 

Every tenant has an automatically calculated risk score updated each time a payment is recorded. Used at renewal, in manager dashboard, and for escalation decisions. 

||||
|---|---|---|
|**Score component**|**Calculation**|**Risk tier**|
||||
|On-time rate|% of months with full rent by 5th|High: < 20%  |  Medium: 20–60%  |<br>Low: > 60%|
|Average days late|Mean days after 5th across all late<br>months|Shown numerically alongside tier|
|Split payment count|Number of months with 2+<br>transactions|Indicates cash flow instability|



Full payment history is available per tenant — every month, exact dates, days late, split or single — exportable as a report for sharing with the property owner. Replaces the manual WhatsApp compilation currently done when disputes arise. 

## **14 Key Business Rules** 

These rules must be enforced by the system — not documented as guidelines. The vendor must confirm implementation method for each. 

|||
|---|---|
|**Rule**|**What it prevents**|
|||
|Owner must approve before vendor<br>is assigned|Retroactive disputes where work is charged without owner<br>knowledge|
|Owner signs agreement before<br>tenant receives it|Tenants receiving unsigned documents — a compliance and<br>trust risk|
|Completion photos required before<br>invoice is raised|Invoices for work that cannot be proven as completed|
|Responsible party must verify<br>before task closes|Dwelly marking work done before owner or tenant has confirmed|
|Property cannot go live without 5<br>documents + SLA|Properties managed without basic legal and financial protection|
|New tenant requires property to be<br>in vacant state|Two active tenancies running simultaneously on one property|
|Payout deduction must be linked to<br>an invoice|Arbitrary deductions from owner payouts without a documented<br>source|
|SD adjustments are immutable log<br>entries|Deposit audit trail being altered — full history must be<br>permanent|



Confidential — For vendor evaluation only    Page 19 of 20 

Dwelly  ●  Operations Process Automation Brief    May 2026 

|**Rule**|**What it prevents**|
|---|---|
|||
|Historical utility bills retain their rate<br>at billing time|Rate changes retrospectively altering past bills|
|Margin is system-calculated from a<br>configurable rate|Inconsistent or incorrect margins applied by different executives<br>— rate is set centrally and applied uniformly|



Confidential — For vendor evaluation only    Page 20 of 20 

