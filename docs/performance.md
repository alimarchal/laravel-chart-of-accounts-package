# Performance & stress test

Measured on a fresh Laravel 13 application with the package installed (`ui_driver=api`,
`APP_ENV=production`, config and routes cached, API rate limit disabled for the test).

| | |
|---|---|
| Hardware | 4 vCPU cloud container, shared disk |
| Software | PHP 8.3 (built-in server, 8 workers), PostgreSQL 16 with default settings |
| Data | **200,000 posted journal entries, 400,000 lines, 800,000 audit rows (973 MB)**, 83 accounts |

## Read endpoints (median of 3, full HTTP round trip)

| Endpoint | Time |
|---|---|
| `GET /reports/trial-balance` (all 400k lines aggregated) | 0.77 s |
| `GET /reports/balance-sheet` | 0.32 s |
| `GET /reports/income-statement` | 0.19 s |
| `GET /reports/cash-flow` | 0.29 s |
| `GET /reports/general-ledger?per_page=50` (unfiltered, with totals) | 0.38 s |
| `GET /reports/general-ledger?account_id=…` (busiest account, 6k lines) | 0.09 s |
| `GET /reports/general-ledger?date_from=…&date_to=…` (one month) | 0.17 s |
| `GET /reports/account-statement?account_id=…` | 0.11 s |
| `GET /reports/aged-receivables` | 0.10 s |
| `GET /chart-of-accounts/{id}/balance` | 0.06 s |
| `GET /chart-of-accounts/tree` | 0.05 s |
| `GET /journal-entries?per_page=50` (page 1 / page 3000) | 0.13 s / 0.19 s |

The trial balance, balance sheet and cash flow scan every posted line, so their time grows linearly
with the ledger (roughly 2 s per million lines on this hardware). Ledger-style reports are paginated
and use indexes, so they stay fast regardless of size. Closed periods keep balance snapshots
(`accounting:rebuild-snapshots`).

## Write path under concurrency

| Test | Result |
|---|---|
| Single `POST /journal-entries/simple` (create + post) | 74 ms |
| 500 posts, 24 concurrent clients | 500 × `201`, 10.8 s (≈ 46 posts/s), p50 0.47 s, p95 0.63 s |
| 40 concurrent requests with the **same `Idempotency-Key`** | 1 × `201`, 39 × `200` (replayed), **exactly 1 entry created** |
| Unbalanced entries after the run | **0** |
| 300 posts as JV and CPV vouchers, 24 concurrent clients (2.5.0) | 300 × `201` in 11.6 s; JV 1…151 and CPV 1…150: **no duplicate and no gap** in either series |
| Trial balance difference after the run | **0.00** |

The voucher number is taken inside the posting transaction under a row lock on its series
(`accounting_voucher_sequences`), so postings of the same voucher type queue only for that one row update;
other types and the rest of the posting still run in parallel.

Posting takes a *shared* lock on the accounting period (closing/reopening takes an exclusive one), so
postings run in parallel but can never interleave with a period close. Throughput was CPU-bound on
the 4 vCPUs; a production setup (PHP-FPM or Octane, OPcache) will be faster.

## Bulk load

| Step (PostgreSQL, triggers enabled) | Time |
|---|---|
| Insert 200,000 entries (+ audit rows) | 16.2 s |
| Insert 400,000 lines (+ audit rows) | 36.3 s |
| Post 200,000 entries (immutability + audit triggers) | 22.1 s |

## Sub-ledgers, inventory, payroll and bank matching at scale (2.26.0)

The 2.20–2.25 modules were measured on the same kind of server (PostgreSQL 16, 4 vCPU, default settings) with
`ACCOUNTING_PERF=1 vendor/bin/pest tests/Performance` (see below). The first run found four places that did a query per
customer, asset or statement line, or work quadratic in the number of items; they were rewritten to fetch everything at once and
group in memory.

| Operation (data) | Before | After |
|---|---|---|
| Receivables ageing (1,000 customers, 10,000 invoices, 3,334 allocations) | 7.65 s, 3,001 queries | **0.28 s, 4 queries** |
| Ledger-vs-sub-ledger check (same data) | 5.82 s, 3,004 queries | **0.30 s, 7 queries** |
| Dashboard overview (same data) | 18.01 s, 9,024 queries | **0.38 s, 27 queries** |
| Stock valuation (2,000 items, 12,000 movements) | 11.64 s | **0.22 s** |
| Depreciation plan for 12 months (1,500 assets) | 1.94 s, 1,501 queries | **0.93 s, 2 queries** |
| Auto-match a bank statement (1,500 lines against 1,500 ledger lines) | 115.65 s, 15,962 queries | **12.1 s, 6,005 queries** |
| Payroll run (2,000 employees, one allowance each) | not measured before the change | 2.8 s, 2,027 queries |

What changed: ageing and the dashboard read documents, allocations and payments for all parties in three queries (the dashboard
ages each side once instead of up to three times); the stock valuation grouped movements by item once instead of filtering the whole
list per item; the depreciation plan reads what is booked on every asset in one query; bank auto-match reads the ledger lines that
can match once per bank account and matches by amount and date in memory (the remaining queries are the two writes and their audit
rows per matched line); payroll payslip lines are inserted in batches. Indexes were added for the allocation, payslip and stock
movement lookups that PostgreSQL does not index on its own (`2026_10_24_000001_add_scale_indexes`); their effect is not visible at
these sizes and was not measured.

The query counts are guarded in the normal test suite (`tests/Performance/ScaleTest.php` runs on small data and asserts that the number
of queries does not grow with the data), so a query-per-row regression fails the build.

### Reproducing

```bash
# large data, timings printed (add DB_CONNECTION=pgsql DB_HOST=… DB_DATABASE=… to use a real server)
ACCOUNTING_PERF=1 vendor/bin/pest tests/Performance
# sizes: ACCOUNTING_PERF_PARTIES, _ITEMS, _ASSETS, _EMPLOYEES, _STATEMENT_LINES
```

## Reproducing the ledger benchmark

Load the data with plain SQL inserts (drafts, then one `UPDATE … SET status = 'posted'`) so the
database triggers run exactly as in production, then time the endpoints with
`curl -w "%{time_total}"` and the concurrent posts with `xargs -P 24`.
