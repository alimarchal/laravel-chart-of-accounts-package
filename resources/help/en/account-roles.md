---
title: Account roles
summary: Tell the system which account is your cash, your bank, your receivables and so on.
---
## What it is

A module never asks for "account 1101"; it asks for a **role**: *which account is the cash book?*, *which accounts are the receivables in the ageing report?* Here you answer, once, for your company.

## When to use it

At set-up, and whenever you restructure the chart.

## How to

For each role (cash, bank, retained earnings, rounding, exchange gain/loss, opening balance equity, receivables and payables for ageing, payroll accounts …) pick the account or accounts. A role that takes one account refuses two. Leave it empty to go back to the chart's default.

## Good to know

- Roles follow the money: merging an account moves its roles, and an account that plays a role cannot be deleted or deactivated until another takes the role.
- A value fixed in the configuration file is shown as fixed.

## Related

[[chart-of-accounts]] · [[control-accounts]] · [[cash-book]]
