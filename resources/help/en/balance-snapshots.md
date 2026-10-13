---
title: Balance snapshots
summary: Stored period-end balances of every account, kept for fast reports.
---
## What it is

When a period is closed, the opening balance, the movement and the closing balance of every account are stored. Reports for past periods read these snapshots instead of adding up every entry again, which keeps them fast on large books.

## When to use it

You rarely need to open it. It is useful to an accountant who wants to see exactly what was stored when a period was closed.

## Good to know

- Snapshots are created by the period close and rebuilt with `php artisan accounting:rebuild-snapshots` if ever needed.
- They never change figures; they are a copy.

## Related

[[periods]] · [[trial-balance]]
