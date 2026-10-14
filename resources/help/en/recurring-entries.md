---
title: Recurring entries
summary: Rent, subscriptions and accruals that repeat on a schedule, generated for you.
---
## What it is

A template of a balanced set of lines plus a schedule (daily, weekly, monthly, quarterly or yearly). The scheduler turns it into journal entries on the due dates.

## When to use it

For anything that repeats with the same amounts: monthly rent, insurance, a loan instalment, a standing accrual.

## How to

1. **New** recurring entry: give the lines, and set how often, from which date, and optionally until when or after how many entries.
2. Choose whether each entry is created as a **draft** (you post it) or **posted automatically**.
3. Use **Generate now** to create the next one immediately, or **Pause** and **Resume** the schedule.

## Good to know

- Needs the Laravel scheduler running (`php artisan schedule:run` every minute), as for all scheduled work.
- If the scheduler was off, missed dates are caught up the next time it runs.
- A monthly template on the 31st uses the last day in shorter months.
- If an entry cannot be posted (closed period, no permission) it is left as a draft with the reason shown on the run.

## Related

[[journal-entries]] · [[periods]]
