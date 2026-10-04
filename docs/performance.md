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

## Reproducing

Load the data with plain SQL inserts (drafts, then one `UPDATE … SET status = 'posted'`) so the
database triggers run exactly as in production, then time the endpoints with
`curl -w "%{time_total}"` and the concurrent posts with `xargs -P 24`.
