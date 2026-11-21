# Business Rules — Confirmed Decisions

Decisions agreed before Phase 1. Later phases must follow these.

## Calendar
- **Off-days are maintained by the admin.** Two sources, both editable in the admin panel:
  - Weekly off-days: `system_settings.weekly_off_days` (0 = Sunday … 6 = Saturday). No day is off by default.
  - Specific holiday dates: `holidays` table.
- Daily Somiti deposits and daily Kisti installments are never scheduled on an off-day.
- Weekly Kisti falls on the same weekday each week; if that date is a holiday, the installment moves to the next working day (Phase 4).
- Past holidays cannot be deleted. Adding or removing a *future* holiday re-flows only **unpaid** future daily Kisti installments (Phase 4); paid installments never move.

## Somiti
- Daily deposit = number_of_shares × share_value. Share value starts at ৳20 and is admin-configurable.
- Each account stores the share value it was opened with; changing the setting affects new accounts only.
- **No partial Somiti payments.** Every Somiti collection covers whole days only (amount must equal days × daily amount).
- **Share count may change after opening, with an effective date. Earlier days stay exactly as they were.**
  Already-scheduled days before the effective date keep their old amount, paid or unpaid. New table in Phase 3:
  `somiti_share_changes (id, account_id, old_shares, new_shares, share_value, old_daily_amount, new_daily_amount, effective_date, changed_by, reason, created_at)`.
  Days already paid in advance beyond the effective date keep their paid amount; the difference is shown as due (if shares increased) — see Phase 3 confirmation question.
- Withdrawal / maturity: **out of scope for now.** `accounts.status` and `accounts.closed_at` leave room to add it.

## Kisti
- Loan disbursement happens **outside** the collector cash ledger. It does not reduce any collector's balance.
- Installment amount is fixed. Partial payments allowed. Last installment absorbs any rounding remainder.

## Who holds cash
- **Admins can also collect from members and send/receive handovers.** Every cash ledger, transaction and
  handover column named `*_collector_id` references `users.id` for *any* cash holder (admin or collector).
- Handover rules apply equally to admins: no one confirms their own handover; only the named receiver confirms.

## Allocation
- When an amount is entered, the system **pre-selects the oldest due installments first**.
- **The collector can edit that selection** (untick/tick dates, including future dates) before saving.
  The server re-validates: selected dates must be payable and the total must match the amount received
  (Somiti: exact whole days; Kisti: may end in one partial installment).

## Ledger convention (collector's point of view)
- `credit` = cash came into the holder's hand (collection, incoming handover, positive adjustment, opening balance).
- `debit` = cash left the holder's hand (outgoing handover, approved expense, negative adjustment, collection reversal).
- Balance = Σcredit − Σdebit. Available for handover = balance − pending outgoing handovers.

## Members & collectors (Phase 2)
- Member ID is generated automatically: `M-00001`, `M-00002` … (never reused).
- Every member has exactly one current collector. `members.collector_id` holds the current one; `collector_member_assignments` keeps the full history (who, from, to, by whom, why).
- A collector who creates a member is always assigned that member. An admin must pick an active collector.
- Collectors see and edit only members currently assigned to them. Only the admin changes a member's collector or status.
- Members and collectors are never deleted — only deactivated. A deactivated collector is signed out at once and cannot log in.
- Deactivating a collector does not move their members automatically; the admin uses "সব সদস্য স্থানান্তর" so every move is deliberate and recorded.
- A collector's opening cash is set once, at creation. Later corrections go through cash adjustments (Phase 6).
- Usernames are stored lowercase; login is case-insensitive.
- Mobile numbers: 11-digit Bangladeshi numbers (013–019). Bengali digits typed by users are converted automatically, in forms and in search.
- The same mobile number may belong to more than one member (family businesses share phones).

## Somiti accounts (Phase 3)
- Account number is generated automatically: `SM-00001`, `SM-00002` … A member may hold more than one Somiti account.
- Daily deposit = shares × the share value **at the time the account was opened** (stored on the account).
- A collector can open accounts only for their own active members; the admin for any active member.
- An account is always handled by the member's current collector. Reassigning a member moves their accounts too.
- Start date may be in the past (back to the member's joining date) or up to 30 days ahead.
- Daily rows are created for working days only, from the start date up to today. A scheduled task adds each
  new day (`somiti:roll`), and pages also roll schedules on the first visit of the day.
- Rows for future days are created only when a member pays in advance (Phase 5).
- **Share changes (admin only)** take effect from today or a later date — never backdated.
  Days before the effective date keep their amount. Days from the effective date use the new amount;
  if they were prepaid at the lower rate, the difference shows as due. A reduction is refused on days
  that already hold more than the new daily amount.
- **Holidays:** adding a holiday removes that day's Somiti rows that have no money on them; rows with money stay.
  Removing a future holiday re-creates the day for accounts already scheduled past it.
  Changing weekly off-days affects days scheduled from then on only.

## Existing members — opening savings balance (প্রারম্ভিক সঞ্চয়)
- For members who already saved on paper: open the account from **today** and enter their paper savings so far
  as one opening balance (with an optional note, e.g. "পুরনো খাতা, জানুয়ারি–অক্টোবর").
- The opening balance is added to the member's total savings (মোট সঞ্চয় = প্রারম্ভিক সঞ্চয় + সংগ্রহ).
- It is **not cash in any collector's hand**: it never creates a transaction, a receipt or a cash-ledger entry.
- Admin or collector can enter it when opening the account. Only the admin can correct it later, with a reason;
  the old and new amounts are kept in the audit log.
- Previous-date entry is still possible: an account may start on a past date (not before the member joined),
  and those days can then be collected individually in Phase 5. Both options can be combined.

## Kisti loan accounts (Phase 4)
- Account number is generated automatically: `KS-00001`, `KS-00002` …
- Total payable = loan amount + service charge (a fixed amount; the form shows it as a % of the loan for reference).
- The installment amount is entered; the number of installments = total payable ÷ installment amount, rounded up.
  If it does not divide exactly, **the last installment is smaller** and carries the remainder
  (e.g. ৳২৪,১০০ ÷ ৳২০০ → ১২০ × ৳২০০ + ১ × ৳১০০). Maximum 1,000 installments.
- Two dates: কিস্তি শুরুর তারিখ (loan given) and প্রথম কিস্তির তারিখ (not earlier). The form suggests the next
  day (daily) or one week later (weekly).
- The whole schedule is generated at once.
  - Daily: one installment per working day.
  - Weekly: same weekday each week; if that day is an off-day it moves to the next working day.
- Loan terms are **never edited**. A loan entered by mistake is cancelled by the admin (with a reason) and entered
  again — allowed only while no money has been received on it. Cancelled loans stay visible as "বাতিল".
- When the calendar changes (holiday added/removed, weekly off-days edited) only installments that are
  **unpaid and due today or later** move. Paid or part-paid installments and past dues never move.
- A collector can create loans only for their own active members; the admin for any active member.
- Loan disbursement is outside the collector cash ledger.
- A loan becomes "পরিশোধিত/বন্ধ" automatically when fully paid (Phase 5).
- The Somiti register's কিস্তি column shows the installment amount of each active loan the member holds.

## Collection engine (Phase 5)
- Members pay **in cash only**. Every cash collection is credited to the cash ledger of the person who received it
  (collector or admin) at the moment it is saved. Collectors cannot backdate a collection.
- One collection = one transaction (with a receipt number `RCP-2026-000001`, restarting each year)
  + one allocation per day / installment it covers. `collected_at` is when money was received; each allocation
  stores the date it covers and whether that was a previous due, today, or an advance.
- The screen pre-selects the **oldest dues first** from the amount typed; the collector can tick/untick any
  day or installment (including future days for advance) before saving.
- **Somiti:** whole days only — the amount must equal exactly what the chosen days still need. A day that is
  part-paid because shares increased can be topped up with just the difference. Advance up to one year.
- **Kisti:** chosen installments are filled in order; only the last one may be part-paid. Paying more than the
  chosen installments need is refused. A loan whose installments are all paid is closed automatically.
- **Duplicate protection:** each form carries a one-time key; a double-click or resubmit returns the first receipt
  instead of saving twice. The account and chosen rows are locked while saving, so two people cannot pay the
  same day at the same time.
- **Reversal (admin only, reason required):** the transaction stays, marked বাতিলকৃত; its allocations are marked
  reversed, the days/installments become due again, and the cash ledger gets a matching debit. A loan closed by
  that payment reopens. A transaction can be reversed once.
- **পুরনো খাতার এন্ট্রি (admin only):** records a payment from the old paper register with its real past date.
  It updates dues and gets a receipt marked as such, but adds **no** cash to anyone's ledger.
- Receipts: A4 and compact (80 mm) layouts, amount in words, covered dates/installments, the running
  savings total (Somiti) or remaining loan (Kisti) as at that receipt.

## Menu (after Phase 5 feedback)
- টাকা সংগ্রহ is an expandable menu with two pages: **সমিতি সংগ্রহ** and **কিস্তি সংগ্রহ**.

## Cash & handovers (Phase 6)
- **There are no expenses.** Cash in hand = opening cash + collections − reversed collections
  + handovers received − handovers given ± admin cash adjustments. Pending handovers never change a balance.
- Opening cash entered when a collector is created is posted to the ledger once (older collectors were back-filled).
- **Handover workflow:** sender requests (receiver, amount, date/time, note) → a unique number `HO-2026-000001`,
  status অপেক্ষমাণ, receiver notified → receiver either **confirms** (গৃহীত: sender debited and receiver credited
  together, linked by one entry group) or **rejects** (প্রত্যাখ্যাত, with reason, no money moves).
- The sender can **cancel** only while pending. A received handover can never be cancelled or deleted.
- Only the named receiver can confirm or reject; the sender cannot confirm their own.
- **Available to hand over** = cash in hand − the sender's own pending and disputed handovers, so the same money
  cannot be promised twice. On confirmation the sender's cash is checked again; if it has dropped (e.g. after a
  reversal) confirmation is refused and the receiver should reject or dispute.
- **Disputes:** sender or receiver can dispute a pending or rejected handover. The amount stays reserved. All admins
  and the other party are notified. The admin investigates and resolves as গৃহীত (money moves now) or প্রত্যাখ্যাত
  (nothing moves), with a note.
- Admins can also send and receive handovers, and see every handover.
- **Cash adjustment (admin only, reason required)** corrects a balance (e.g. counting mistake) with its own ledger row.
- A reversal can make a collector's balance negative if the money was already handed over; it is shown in red on the
  cash overview for the admin to settle with an adjustment.
- Notifications (bell in the top bar) for: request, confirmation, rejection, cancellation, dispute, resolution.

## Reports & dashboards (Phase 7)
- 13 reports, each viewable on screen, printable, and downloadable as PDF (Bengali, Hind Siliguri font with
  proper conjunct shaping) and Excel (Bengali headings; money kept as numbers so Excel can total it).
- Collection reports count **cash collections only** (reversed transactions and পুরনো খাতার এন্ট্রি excluded).
  Dues/advance/savings reports use the installment records, so they include পুরনো খাতার এন্ট্রি.
- Dues are "as of today". Advance = money already paid on days/installments after today.
- Collectors get 9 of the reports, always limited to their own receipts, accounts and cash; the
  organisation-wide ones (কালেক্টরভিত্তিক সংগ্রহ, শেয়ারভিত্তিক হিসাব, কালেক্টর ব্যালেন্স, দৈনিক ক্যাশ ক্লোজিং for all) stay admin-only.
- Dashboard "today's collection" = completed cash receipts collected today.
