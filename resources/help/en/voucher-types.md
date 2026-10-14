---
title: Voucher types
summary: The kinds of voucher and the numbering of each.
---
## What it is

Each entry has a voucher type (*Journal*, *Cash payment*, *Cash receipt*, *Bank payment*, *Bank receipt*), and each type has its own number series, for example *CPV-2026-00007*.

## When to use it

To add a type (a *Sales voucher*), or change the prefix and the number format.

## How to

Set the **code**, **prefix**, **format** (tokens such as `{PREFIX}`, `{FY}`, `{SEQ:5}`) and when the numbering **restarts** (every fiscal year, every month or never).

## Good to know

- Numbers are given when an entry is **posted**, in order and without gaps; a voided draft never leaves a gap.
- A type with numbered entries keeps its code and restart rule. The default *Journal* type cannot be deleted.

## Related

[[journal-entries]]
