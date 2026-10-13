---
title: Roles
summary: Six standard roles designed so that nobody approves their own work.
---
## What it is

A role is a bundle of permissions. The six standard roles:

| Role | Who | Can | Cannot |
|---|---|---|---|
| **super-admin** | The owner | Everything | Approve their own entries under maker–checker |
| **admin** | IT / user administrator | Users, roles, companies, voucher types | Record, post, approve or close anything |
| **accountant** | The *maker* | Record, edit, post, reverse, void drafts, close periods, bank and tax work | Approve, reopen periods, manage users |
| **approver** | The *checker* | Approve or reject entries, post and pay payroll, read ledger and reports | Create or edit entries |
| **auditor** | Internal or external audit | Read everything, including the audit log | Change anything |
| **viewer** | Management | Read the ledger and reports | Change anything |

## How to

Open a role to see and change its permissions. Changes apply to everyone with that role the next time they load a page.

## Good to know

- Keep the **maker** and the **checker** separate: the person who records is not the person who approves.
- `php artisan accounting:roles` prints a who-can-what table and warns if a role can both create and approve entries.
- Updating the package never removes permissions you changed; it only adds new ones.

## Related

[[users]] · [[permissions]] · [[journal-entries]]
