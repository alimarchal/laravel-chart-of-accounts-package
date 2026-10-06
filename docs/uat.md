# User acceptance test (UAT) checklist

Run this with an accountant before go-live, on a copy of the real chart with a few months of sample data.
Tick each line; write down the screen, the entry number and what you saw when something differs.

## 1. Set-up
- [ ] Sign in as the **accountant** role: only the screens you need appear; Users and Roles do not.
- [ ] Company switcher shows only your companies; switching changes every figure.
- [ ] Open the chart of accounts: codes, types and opening balances match your books.
- [ ] Language switcher: Urdu shows right-to-left screens, English returns.

## 2. Daily work
- [ ] Create a journal entry that does not balance: it cannot be posted.
- [ ] Post a balanced entry: voucher number is next in sequence, no gap.
- [ ] Try to edit the posted entry: refused; reverse it instead and see the reversal linked.
- [ ] Attach a bill (PDF) to an entry; attach the same file to another entry: duplicate warning appears.
- [ ] Entry above the evidence threshold cannot be posted without an attachment.
- [ ] Post in a closed period: refused.

## 3. Sales and purchases
- [ ] Create a customer invoice with tax, post it, receive part payment, see ageing move.
- [ ] Create a supplier bill with withholding, post it, pay it.
- [ ] Customer and supplier ledgers agree with the control accounts (reconciliation shows no difference).
- [ ] Sales invoice shows FBR status; submit it and see the FBR invoice number.

## 4. Assets, stock, payroll
- [ ] Add a fixed asset, run depreciation for a month, check the journal entry, dispose of the asset.
- [ ] Receive stock, issue stock: moving-average value and the inventory account agree.
- [ ] Prepare a payroll run: totals, tax and net pay are right for two sample employees; post and pay it.
- [ ] Accountant cannot post a payroll run (approver only).

## 5. Banking and close
- [ ] Import a bank statement, auto-match, reconcile: difference is zero.
- [ ] Run foreign-currency revaluation and check the gain/loss entry.
- [ ] Trial balance debits equal credits; balance sheet balances; income statement matches your own figures.
- [ ] Close the month; close the year; opening balances of the next year are right.

## 6. Exports
- [ ] Export reports to PDF, Excel and CSV; totals match the screen.
- [ ] A narration starting with `=` shows as text in the exported file (it is never run as a formula).

## 7. Security spot checks
- [ ] A viewer cannot create, edit or delete anything (screens hide buttons, direct URLs answer 403).
- [ ] A user of company A cannot open a record of company B by changing the id in the address.
- [ ] Audit trail shows who did what, when, for every posting, void and role change.

Sign-off: name ______________  date ____________  result  PASS / FAIL
