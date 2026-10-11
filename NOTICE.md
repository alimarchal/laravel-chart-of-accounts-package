# Notice

## ERPNext standard chart of accounts

`src/Support/Charts/ErpNextChart.php` lists the groups, account names and account numbers of the *standard chart of accounts with
account numbers* that ships with **ERPNext** (Frappe Technologies Pvt. Ltd., released under the GNU General Public License v3.0,
<https://github.com/frappe/erpnext>).

What was taken is the structure of the chart: which groups exist, what the accounts are called and how they are numbered, which is the
common vocabulary of double-entry bookkeeping. No ERPNext program code is included. The package is released under the MIT license.

Changes made to the chart for this package:

- ERPNext numbers a range on its two current-asset / current-liability groups; here each group has one number (Current Assets `1050`,
  Current Liabilities `2050`).
- Leaf accounts that ERPNext leaves for the user to create were added so every module works out of the box: Primary Bank Account (`1210`),
  Input Tax Receivable (`1510`), Output Sales Tax Payable (`2320`), Long-term Investments (`1810`), Payroll Payable (`2120`),
  TDS Payable (`2310`) and the payroll expenses.
- Every account is mapped to a statement line of this package's financial statements.

If your licensing policy does not allow data derived from a GPL project, install with `php artisan accounting:install --chart=general`
(or set `ACCOUNTING_CHART_PRESET=general`): that chart is the package's own and has no ERPNext content.
