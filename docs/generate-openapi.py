"""Generates docs/openapi.yaml. Run: python3 docs/generate-openapi.py (requires PyYAML).

Edit this file, not openapi.yaml, when endpoints change; tests/Feature/Api/ApiAccessTest.php fails
if any API route is missing from the spec."""
import os
import yaml

def ref(name): return {"$ref": f"#/components/schemas/{name}"}
def resp(desc, schema=None, headers=None):
    r = {"description": desc}
    if schema is not None: r["content"] = {"application/json": {"schema": schema}}
    if headers: r["headers"] = headers
    return r
def data(schema): return {"type": "object", "properties": {"data": schema}, "required": ["data"]}
def paginated(item): return {"allOf": [{"type": "object", "properties": {"data": {"type": "array", "items": item}}}, ref("PaginationEnvelope")]}
E = {"401": {"$ref": "#/components/responses/Unauthenticated"}, "403": {"$ref": "#/components/responses/Forbidden"}, "429": {"$ref": "#/components/responses/TooManyRequests"}}
E404 = {**E, "404": {"$ref": "#/components/responses/NotFound"}}
E422 = {**E, "422": {"$ref": "#/components/responses/UnprocessableEntity"}}
E404_422 = {**E404, "422": {"$ref": "#/components/responses/UnprocessableEntity"}}
ID = {"$ref": "#/components/parameters/Id"}
PAGE = [{"$ref": "#/components/parameters/Page"}, {"$ref": "#/components/parameters/PerPage"}]

def op(tag, summary, perm, responses, params=None, body=None, desc=None, opid=None):
    o = {"tags": [tag], "summary": summary, "operationId": opid,
         "description": ((desc + "\n\n") if desc else "") + f"Permission: `{perm}`.",
         "responses": responses}
    if params: o["parameters"] = params
    if body: o["requestBody"] = {"required": True, "content": {"application/json": {"schema": body}}}
    return o

paths = {}
schemas = {
 "Money": {"type": "string", "pattern": r"^-?\d+\.\d{2}$", "example": "1500.00", "description": "Decimal amount as a string with two decimals (exact, no floating point)."},
 "Error": {"type": "object", "properties": {"message": {"type": "string"}}, "required": ["message"]},
 "ValidationError": {"type": "object", "properties": {"message": {"type": "string"}, "errors": {"type": "object", "additionalProperties": {"type": "array", "items": {"type": "string"}}}}, "required": ["message"]},
 "PaginationEnvelope": {"type": "object", "properties": {
     "links": {"type": "object", "properties": {k: {"type": ["string", "null"]} for k in ["first", "last", "prev", "next"]}},
     "meta": {"type": "object", "properties": {"current_page": {"type": "integer"}, "per_page": {"type": "integer"}, "total": {"type": "integer"}, "last_page": {"type": "integer"}, "from": {"type": ["integer", "null"]}, "to": {"type": ["integer", "null"]}}}}},
 "Account": {"type": "object", "properties": {
     "id": {"type": "integer"}, "account_code": {"type": "string", "example": "1101"}, "account_name": {"type": "string", "example": "Cash In Hand"},
     "normal_balance": {"type": "string", "enum": ["debit", "credit"]}, "is_group": {"type": "boolean"}, "is_active": {"type": "boolean"}, "is_system": {"type": "boolean"},
     "control_type": {"type": ["string", "null"], "enum": ["receivables", "payables", "inventory", "fixed_assets", "payroll", "tax", None], "description": "Set when the account controls a sub-ledger: only that module posts to it."},
     "parent_id": {"type": ["integer", "null"]}, "account_type_id": {"type": "integer"}, "currency_id": {"type": "integer"}, "description": {"type": ["string", "null"]},
     "account_type": {"type": "object"}, "currency": {"type": "object"},
     "children": {"type": "array", "items": ref("Account"), "description": "Only in /chart-of-accounts/tree"}}},
 "AccountInput": {"type": "object", "required": ["account_type_id", "currency_id", "account_code", "account_name"], "properties": {
     "parent_id": {"type": ["integer", "null"], "description": "Must be a group account of the same account type; no cycles."},
     "account_type_id": {"type": "integer"}, "currency_id": {"type": "integer"},
     "account_code": {"type": "string", "maxLength": 30, "description": "Unique. Locked once the account has journal lines."},
     "account_name": {"type": "string", "maxLength": 255},
     "normal_balance": {"type": "string", "enum": ["debit", "credit"], "description": "Defaults to the account type's normal balance."},
     "description": {"type": ["string", "null"]},
     "is_group": {"type": "boolean", "default": False, "description": "Omitted on update = unchanged."},
     "is_active": {"type": "boolean", "default": True, "description": "Omitted on update = unchanged."},
     "control_type": {"type": ["string", "null"], "description": "Requires `control-accounts.manage`; posting accounts only. Omitted = unchanged."}}},
 "AccountBalance": {"type": "object", "properties": {
     "account_id": {"type": "integer"}, "account_code": {"type": "string"}, "account_name": {"type": "string"},
     "normal_balance": {"type": "string"}, "includes_child_accounts": {"type": "boolean"}, "as_of_date": {"type": "string", "format": "date"},
     "total_debit": ref("Money"), "total_credit": ref("Money"), "balance": ref("Money")}},
 "JournalLine": {"type": "object", "properties": {
     "id": {"type": "integer"}, "line_no": {"type": "integer"}, "chart_of_account_id": {"type": "integer"}, "account_code": {"type": "string"}, "account_name": {"type": "string"},
     "cost_center_id": {"type": ["integer", "null"]}, "cost_center_code": {"type": ["string", "null"]},
     "debit": ref("Money"), "credit": ref("Money"), "description": {"type": ["string", "null"]}, "reconciliation_status": {"type": ["string", "null"]}}},
 "JournalEntry": {"type": "object", "properties": {
     "id": {"type": "integer"},
     "voucher_number": {"type": ["string", "null"], "example": "JV-2026-00012", "description": "Issued when the entry is posted (gapless per voucher type); null for drafts. Never changes afterwards."},
     "voucher_type_id": {"type": ["integer", "null"]},
     "voucher_type": {"type": ["object", "null"], "properties": {"id": {"type": "integer"}, "code": {"type": "string", "example": "JV"}, "name": {"type": "string"}}},
     "entry_date": {"type": "string", "format": "date"}, "reference": {"type": ["string", "null"]}, "description": {"type": ["string", "null"]},
     "source_document": {"type": ["object", "null"], "description": "The document the entry records; null when none.", "properties": {
         "type": {"type": ["string", "null"]}, "type_label": {"type": ["string", "null"], "example": "Purchase bill"}, "number": {"type": ["string", "null"]},
         "date": {"type": ["string", "null"], "format": "date"}, "sourceable_type": {"type": ["string", "null"], "description": "Application model the entry was recorded for (set from PHP code)."}, "sourceable_id": {"type": ["integer", "null"]}}},
     "status": {"type": "string", "enum": ["draft", "posted", "void"]}, "currency_id": {"type": "integer"}, "fx_rate_to_base": {"type": "string"},
     "accounting_period_id": {"type": ["integer", "null"]}, "posted_at": {"type": ["string", "null"], "format": "date-time"},
     "approval": {"type": "object", "properties": {
         "required": {"type": ["boolean", "null"], "description": "Whether posting needs approval (drafts with lines loaded)"},
         "status": {"type": ["string", "null"], "enum": ["pending", "approved", "rejected", None]},
         "submitted_at": {"type": ["string", "null"], "format": "date-time"}, "submitted_by": {"type": ["integer", "null"]},
         "approved_at": {"type": ["string", "null"], "format": "date-time"}, "approved_by": {"type": ["integer", "null"]},
         "rejected_at": {"type": ["string", "null"], "format": "date-time"}, "rejected_by": {"type": ["integer", "null"]},
         "rejection_reason": {"type": ["string", "null"]}}},
     "created_by": {"type": ["integer", "null"]}, "posted_by": {"type": ["integer", "null"]},
     "is_reversed": {"type": "boolean"}, "reversed_by_entry_id": {"type": ["integer", "null"]}, "reverses_entry_id": {"type": ["integer", "null"]},
     "total_debit": ref("Money"), "total_credit": ref("Money"), "currency": {"type": "object"}, "accounting_period": {"type": ["object", "null"]},
     "lines": {"type": "array", "items": ref("JournalLine")}, "created_at": {"type": "string", "format": "date-time"}}},
 "JournalLineInput": {"type": "object", "description": "Give either chart_of_account_id or account_code; either debit or credit must be > 0.", "properties": {
     "id": {"type": "integer", "description": "Existing line id (updates only); omitted lines are deleted."},
     "chart_of_account_id": {"type": "integer"}, "account_code": {"type": "string", "example": "1101"},
     "cost_center_id": {"type": ["integer", "null"]}, "cost_center_code": {"type": ["string", "null"], "example": "ADMIN"},
     "debit": {"type": "number", "minimum": 0, "multipleOf": 0.01, "example": 1500}, "credit": {"type": "number", "minimum": 0, "multipleOf": 0.01, "example": 0},
     "description": {"type": ["string", "null"], "maxLength": 255}}},
 "JournalEntryInput": {"type": "object", "required": ["entry_date", "lines"], "properties": {
     "voucher_type_id": {"type": ["integer", "null"], "description": "An active voucher type; default JV."},
     "voucher_type_code": {"type": ["string", "null"], "example": "CPV", "description": "Alternative to voucher_type_id."},
     "source_document_type": {"type": ["string", "null"], "enum": ["invoice", "credit_note", "bill", "debit_note", "receipt", "payment", "expense_claim", "payroll", "bank_statement", "contract", "other", None], "description": "Keys of config('accounting.source_documents.types'); required with source_document_number."},
     "source_document_number": {"type": ["string", "null"], "maxLength": 100, "example": "BILL-778", "description": "A document (type + number, case-insensitive) can be posted only once per company until its entry is reversed (422 otherwise)."},
     "source_document_date": {"type": ["string", "null"], "format": "date"},
     "entry_date": {"type": "string", "format": "date"}, "currency_id": {"type": ["integer", "null"]}, "currency_code": {"type": ["string", "null"], "example": "PKR", "description": "Alternative to currency_id. Defaults to the base currency."},
     "fx_rate_to_base": {"type": ["number", "null"], "exclusiveMinimum": 0, "default": 1}, "reference": {"type": ["string", "null"], "maxLength": 255}, "description": {"type": ["string", "null"]},
     "auto_post": {"type": "boolean", "default": False, "description": "Post immediately; requires `journal-entries.post`. Rejected (422) when maker-checker requires approval."},
     "lines": {"type": "array", "minItems": 2, "maxItems": 500, "items": ref("JournalLineInput")}},
     "example": {"entry_date": "2026-10-04", "reference": "INV-1001", "auto_post": True, "lines": [
         {"account_code": "1103", "debit": 1500, "credit": 0}, {"account_code": "4101", "debit": 0, "credit": 1500}]}},
 "SimpleEntryInput": {"type": "object", "required": ["debit_account_code", "credit_account_code", "amount"], "properties": {
     "debit_account_code": {"type": "string", "example": "5102"}, "credit_account_code": {"type": "string", "example": "1101"},
     "amount": {"type": "number", "exclusiveMinimum": 0, "multipleOf": 0.01, "example": 2500}, "entry_date": {"type": ["string", "null"], "format": "date"},
     "description": {"type": ["string", "null"], "example": "Office rent"}, "reference": {"type": ["string", "null"]},
     "source_document_type": {"type": ["string", "null"], "enum": ["invoice", "credit_note", "bill", "debit_note", "receipt", "payment", "expense_claim", "payroll", "bank_statement", "contract", "other", None], "description": "Keys of config('accounting.source_documents.types'); required with source_document_number."},
     "source_document_number": {"type": ["string", "null"], "maxLength": 100, "example": "BILL-778", "description": "A document (type + number, case-insensitive) can be posted only once per company until its entry is reversed (422 otherwise)."},
     "source_document_date": {"type": ["string", "null"], "format": "date"},
     "post": {"type": "boolean", "default": True, "description": "Requires `journal-entries.post` when true."}}},
 "Period": {"type": "object", "properties": {
     "id": {"type": "integer"}, "name": {"type": "string"}, "start_date": {"type": "string", "format": "date"}, "end_date": {"type": "string", "format": "date"},
     "status": {"type": "string", "enum": ["open", "closed", "archived"]}, "closed_at": {"type": ["string", "null"], "format": "date-time"},
     "closing_total_debits": {"type": ["string", "null"]}, "closing_total_credits": {"type": ["string", "null"]}, "closing_net_income": {"type": ["string", "null"]}, "closing_journal_entry_id": {"type": ["integer", "null"]}}},
 "ReportRows": {"type": "object", "properties": {"data": {"type": "array", "items": {"type": "object"}}, "totals": {"type": "object"}}},
 "LedgerPage": {"type": "object", "description": "Laravel paginator (data, current_page, per_page, total, …) plus totals.", "properties": {
     "data": {"type": "array", "items": {"type": "object"}}, "current_page": {"type": "integer"}, "per_page": {"type": "integer"}, "total": {"type": "integer"},
     "totals": {"type": "object", "properties": {"total_debit": {"type": "number"}, "total_credit": {"type": "number"}, "closing_balance": {"type": "number"}}}}},
 "CashFlowPage": {"type": "object", "description": "Laravel paginator of cash/bank lines (cash_in, cash_out, net_cash_flow) plus totals for the whole period.", "properties": {
     "data": {"type": "array", "items": {"type": "object"}}, "current_page": {"type": "integer"}, "per_page": {"type": "integer"}, "total": {"type": "integer"},
     "totals": {"type": "object", "properties": {"cash_in": {"type": "string"}, "cash_out": {"type": "string"}, "net_cash_flow": {"type": "string"}}}}},
 "StatementPage": {"type": "object", "description": "Laravel paginator of posted lines, each with running_balance (base currency, on the account's normal side), plus opening balance and totals.", "properties": {
     "data": {"type": "array", "items": {"type": "object", "properties": {"entry_date": {"type": "string"}, "journal_entry_id": {"type": "integer"}, "reference": {"type": ["string", "null"]}, "base_debit": {"type": "string"}, "base_credit": {"type": "string"}, "running_balance": {"type": "string", "example": "1250.00"}}}},
     "current_page": {"type": "integer"}, "per_page": {"type": "integer"}, "total": {"type": "integer"},
     "account": {"type": "object", "properties": {"id": {"type": "integer"}, "account_code": {"type": "string"}, "account_name": {"type": "string"}, "normal_balance": {"type": "string"}}},
     "opening_balance": {"type": "string", "example": "1000.00"},
     "totals": {"type": "object", "properties": {"debit": {"type": "string"}, "credit": {"type": "string"}, "closing_balance": {"type": "string"}}}}},
 "Health": {"type": "object", "properties": {"ok": {"type": "boolean"}, "missing_tables": {"type": "array", "items": {"type": "string"}}, "base_currency_count": {"type": "integer"}, "account_type_count": {"type": "integer"}, "chart_of_account_count": {"type": "integer"}}},
}

crud = {
 "account-types": ("Account types", "AccountType", "account-types", {"code": {"type": "string", "maxLength": 20, "example": "ASSET"}, "name": {"type": "string", "example": "Asset"}, "normal_balance": {"type": "string", "enum": ["debit", "credit"]}, "report_group": {"type": "string", "enum": ["BalanceSheet", "IncomeStatement"]}, "description": {"type": ["string", "null"]}, "is_active": {"type": "boolean"}}, ["code", "name", "normal_balance", "report_group"]),
 "currencies": ("Currencies", "Currency", "currencies", {"code": {"type": "string", "minLength": 3, "maxLength": 3, "example": "USD"}, "name": {"type": "string"}, "symbol": {"type": ["string", "null"]}, "exchange_rate_to_base": {"type": "number", "exclusiveMinimum": 0}, "is_base": {"type": "boolean", "description": "Exactly one base currency; cannot change once entries are posted."}, "is_active": {"type": "boolean"}}, ["code", "name", "exchange_rate_to_base"]),
 "periods": ("Periods", "PeriodInput", "periods", {"name": {"type": "string", "example": "FY 2027"}, "start_date": {"type": "string", "format": "date"}, "end_date": {"type": "string", "format": "date"}, "status": {"type": "string", "enum": ["open", "closed", "archived"], "description": "Changing it runs close (`periods.close`) / reopen (`periods.reopen`)."}}, ["name", "start_date", "end_date"]),
 "cost-centers": ("Cost centers", "CostCenter", "cost-centers", {"code": {"type": "string"}, "name": {"type": "string"}, "type": {"type": "string", "enum": ["cost_center", "project"]}, "description": {"type": ["string", "null"]}, "is_active": {"type": "boolean"}}, ["code", "name", "type"]),
 "bank-accounts": ("Bank accounts", "BankAccount", "bank-accounts", {"chart_of_account_id": {"type": ["integer", "null"], "description": "Posting (non-group) GL account, e.g. 1108."}, "account_name": {"type": "string"}, "account_number": {"type": "string"}, "bank_name": {"type": ["string", "null"]}, "branch": {"type": ["string", "null"]}, "iban": {"type": ["string", "null"]}, "swift_code": {"type": ["string", "null"]}, "description": {"type": ["string", "null"]}, "is_active": {"type": "boolean"}}, ["account_name", "account_number"]),
 "reconciliations": ("Reconciliations", "Reconciliation", "reconciliations", {"bank_account_id": {"type": "integer"}, "statement_date": {"type": "string", "format": "date"}, "statement_balance": {"type": "number"}, "book_balance": {"type": "number"}, "status": {"type": "string", "enum": ["draft", "completed", "void"]}}, ["bank_account_id", "statement_date", "statement_balance", "book_balance", "status"]),
 "tax-codes": ("Tax", "TaxCode", "tax-codes", {"code": {"type": "string"}, "name": {"type": "string"}, "kind": {"type": "string", "enum": ["output", "input", "withheld", "advance"], "description": "output: collected on sales; input: paid on purchases; withheld: deducted from payments we make; advance: deducted from payments to us"}, "tax_account_id": {"type": ["integer", "null"], "description": "The account the tax is booked to"}, "jurisdiction": {"type": ["string", "null"]}, "description": {"type": ["string", "null"]}, "is_active": {"type": "boolean"}}, ["code", "name", "kind"]),
 "tax-rates": ("Tax", "TaxRate", "tax-rates", {"tax_code_id": {"type": "integer"}, "rate": {"type": "number", "minimum": 0, "maximum": 100}, "effective_from": {"type": "string", "format": "date", "description": "Unique per tax code."}, "effective_to": {"type": ["string", "null"], "format": "date"}, "is_active": {"type": "boolean"}}, ["tax_code_id", "rate", "effective_from"]),
}
for uri, (tag, schema_name, perm, props, req) in crud.items():
    schemas[schema_name] = {"type": "object", "required": req, "properties": props}
    rec = {"allOf": [{"type": "object", "properties": {"id": {"type": "integer"}}}, ref(schema_name)]}
    paths[f"/{uri}"] = {
        "get": op(tag, f"List {uri}", f"{perm}.view", {"200": resp("Paginated list", paginated(rec)), **E}, params=PAGE + [{"name": "filter[code]", "in": "query", "schema": {"type": "string"}, "description": "Partial match on code/name where available"}], opid=f"list_{uri.replace('-', '_')}"),
        "post": op(tag, f"Create {uri}", f"{perm}.create", {"201": resp("Created", data(rec)), **E422}, body=ref(schema_name), opid=f"create_{uri.replace('-', '_')}"),
    }
    paths[f"/{uri}/{{id}}"] = {
        "parameters": [ID],
        "get": op(tag, f"Show {uri}", f"{perm}.view", {"200": resp("Record", data(rec)), **E404}, opid=f"show_{uri.replace('-', '_')}"),
        "put": op(tag, f"Update {uri} (PATCH also accepted)", f"{perm}.update", {"200": resp("Updated", data(rec)), **E404_422}, body=ref(schema_name), opid=f"update_{uri.replace('-', '_')}"),
        "delete": op(tag, f"Delete {uri}", f"{perm}.delete", {"204": resp("Deleted"), **E404_422}, desc="Returns 422 if the record is still referenced.", opid=f"delete_{uri.replace('-', '_')}"),
    }

paths["/periods/{id}/close"] = {"parameters": [ID], "post": op("Periods", "Close a period", "periods.close", {"200": resp("Closed period", data(ref("Period"))), **E404_422}, desc="Writes balance snapshots and stores totals and net income. Fails (422) while drafts are dated in the period.", opid="close_period")}
paths["/periods/{id}/reopen"] = {"parameters": [ID], "post": op("Periods", "Reopen a closed period", "periods.reopen", {"200": resp("Reopened period", data(ref("Period"))), **E404_422},
    body={"type": "object", "properties": {"reason": {"type": "string", "maxLength": 500, "description": "Kept in the audit trail."}}},
    desc="Periods are reopened newest first (422 while a later period is closed). A year-end closing entry is reversed automatically.", opid="reopen_period")}
schemas["CloseChecklist"] = {"type": "object", "properties": {
    "period": ref("Period"), "year_end": {"type": "boolean"}, "can_close": {"type": "boolean"},
    "checks": {"type": "array", "items": {"type": "object", "properties": {
        "key": {"type": "string", "example": "drafts"}, "label": {"type": "string"}, "status": {"type": "string", "enum": ["pass", "fail", "warn", "info"]},
        "detail": {"type": ["string", "null"]}, "count": {"type": ["integer", "null"]}, "link": {"type": ["string", "null"], "description": "Web path under the accounting prefix"}}}},
    "summary": {"type": "object", "properties": {"posted_entries": {"type": "integer"}, "net_income": ref("Money"), "total_debits": ref("Money"), "total_credits": ref("Money")}},
    "closing_entry": {"type": ["object", "null"], "description": "Year end only: the lines the year-end close would post.", "properties": {
        "lines": {"type": "array", "items": {"type": "object"}}, "net_income": ref("Money"), "retained_earnings": {"type": ["object", "null"]}}}}}
paths["/periods/{id}/close-checklist"] = {"parameters": [ID], "get": op("Periods", "Pre-close checklist", "periods.view", {"200": resp("Checklist", data(ref("CloseChecklist"))), **E404},
    params=[{"name": "year_end", "in": "query", "schema": {"type": "boolean"}, "description": "Year-end checks and the closing entry preview."}],
    desc="'fail' items block closing; 'warn' items are worth reviewing.", opid="period_close_checklist")}
paths["/periods/generate-monthly"] = {"post": op("Periods", "Create twelve monthly periods", "periods.create", {"201": resp("Created periods", data({"type": "array", "items": ref("Period")})), **E422},
    body={"type": "object", "required": ["start_date"], "properties": {"start_date": {"type": "string", "format": "date", "description": "First day of the fiscal year."}}},
    desc="Refused (422) if any month overlaps an existing period.", opid="generate_monthly_periods")}
paths["/periods/{id}/close-fiscal-year"] = {"parameters": [ID], "post": op("Periods", "Year-end close", "periods.close", {"200": resp("Closed period with its closing entry", data(ref("Period"))), **E404_422}, desc="Posts a closing entry moving income-statement balances to retained earnings, then closes the period.", opid="close_fiscal_year")}
paths["/dashboard"] = {"get": op("System", "Dashboard overview", "accounting.view", {"200": resp("KPIs, trend, ageing, alerts and recent entries", data({"type": "object", "properties": {"as_of": {"type": "string", "format": "date"}, "months": {"type": "integer"}, "performance": {"type": "object", "nullable": True, "description": "this_month / last_month / year_to_date {income, expense, net}, trend by month, top_expenses. Needs reports.income-statement.view."}, "cash": {"type": "object", "nullable": True, "description": "Bank-account balances. Needs reports.balance-sheet.view."}, "receivables": {"type": "object", "nullable": True, "description": "total, overdue, ageing buckets, top parties, ledger vs sub-ledger difference. Needs parties.view."}, "payables": {"type": "object", "nullable": True}, "alerts": {"type": "array", "items": {"type": "object", "properties": {"key": {"type": "string"}, "level": {"type": "string", "enum": ["critical", "warning", "info"]}, "count": {"type": "integer"}, "label": {"type": "string"}, "amount": {"type": "string", "nullable": True}}}}, "recent_entries": {"type": "array", "nullable": True, "items": {"type": "object"}}}})), **E, "422": {"$ref": "#/components/responses/UnprocessableEntity"}}, params=[{"name": "as_of", "in": "query", "schema": {"type": "string", "format": "date"}, "description": "Default today."}, {"name": "months", "in": "query", "schema": {"type": "integer", "minimum": 1, "maximum": 24, "default": 6}, "description": "Months in the income/expense trend."}], desc="Each section appears only if the caller holds the permission it needs; otherwise it is null.", opid="dashboard")}
paths["/health"] = {"get": op("System", "Installation health", "accounting.view", {"200": resp("Healthy", ref("Health")), "503": resp("Unhealthy", ref("Health")), **E}, opid="health")}

paths["/chart-of-accounts"] = {
 "get": op("Chart of accounts", "List accounts", "chart-of-accounts.view", {"200": resp("Paginated accounts", paginated(ref("Account"))), **E}, params=PAGE + [{"name": f"filter[{f}]", "in": "query", "schema": {"type": "string"}} for f in ["account_code", "account_name", "account_type_id", "currency_id", "is_group", "is_active"]], opid="list_accounts"),
 "post": op("Chart of accounts", "Create an account", "chart-of-accounts.create", {"201": resp("Created", data(ref("Account"))), **E422}, body=ref("AccountInput"), opid="create_account")}
paths["/chart-of-accounts/tree"] = {"get": op("Chart of accounts", "Whole chart as a nested tree", "chart-of-accounts.view", {"200": resp("Root accounts with nested children", data({"type": "array", "items": ref("Account")})), **E}, opid="account_tree")}
paths["/chart-of-accounts/{id}/balance"] = {"parameters": [ID], "get": op("Chart of accounts", "Account balance", "chart-of-accounts.view", {"200": resp("Balance", data(ref("AccountBalance"))), **E404_422}, params=[{"name": "as_of_date", "in": "query", "schema": {"type": "string", "format": "date"}}], desc="Posted entries only; a group account includes all of its child accounts.", opid="account_balance")}
paths["/chart-of-accounts/{id}"] = {"parameters": [ID],
 "get": op("Chart of accounts", "Show an account", "chart-of-accounts.view", {"200": resp("Account", data(ref("Account"))), **E404}, opid="show_account"),
 "put": op("Chart of accounts", "Update an account (PATCH also accepted)", "chart-of-accounts.update", {"200": resp("Updated", data(ref("Account"))), **E404_422}, body=ref("AccountInput"), opid="update_account"),
 "delete": op("Chart of accounts", "Delete an account", "chart-of-accounts.delete", {"204": resp("Deleted"), **E404_422}, desc="422 for system accounts, accounts with children or journal lines.", opid="delete_account")}

idem = [{"$ref": "#/components/parameters/IdempotencyKey"}]
created = {"201": resp("Created", data(ref("JournalEntry"))), "200": resp("Replay of an earlier request with the same Idempotency-Key", data(ref("JournalEntry")), {"Idempotent-Replayed": {"schema": {"type": "string", "enum": ["true"]}}}), **E422}
paths["/journal-entries"] = {
 "get": op("Journal entries", "List entries", "journal-entries.view", {"200": resp("Paginated entries", paginated(ref("JournalEntry"))), **E}, params=PAGE + [
    {"name": "filter[status]", "in": "query", "schema": {"type": "string", "enum": ["draft", "posted", "void"]}},
    {"name": "filter[reference]", "in": "query", "schema": {"type": "string"}}, {"name": "filter[description]", "in": "query", "schema": {"type": "string"}},
    {"name": "filter[voucher_number]", "in": "query", "schema": {"type": "string"}, "description": "Partial match, e.g. JV-2026-"},
    {"name": "filter[source_document_number]", "in": "query", "schema": {"type": "string"}, "description": "Partial match"}, {"name": "filter[source_document_type]", "in": "query", "schema": {"type": "string"}}, {"name": "filter[voucher_type_id]", "in": "query", "schema": {"type": "integer"}},
    {"name": "filter[currency_id]", "in": "query", "schema": {"type": "integer"}}, {"name": "filter[accounting_period_id]", "in": "query", "schema": {"type": "integer"}},
    {"name": "filter[entry_date_from]", "in": "query", "schema": {"type": "string", "format": "date"}}, {"name": "filter[entry_date_to]", "in": "query", "schema": {"type": "string", "format": "date"}},
    {"name": "sort", "in": "query", "schema": {"type": "string", "enum": ["entry_date", "-entry_date", "id", "-id", "reference", "-reference", "voucher_number", "-voucher_number", "created_at", "-created_at"]}},
    {"name": "include", "in": "query", "schema": {"type": "string", "enum": ["lines"]}}], opid="list_journal_entries"),
 "post": op("Journal entries", "Create (and optionally post) an entry", "journal-entries.create", created, params=idem, body=ref("JournalEntryInput"), desc="Lines may use account codes. Rule violations (unbalanced, group account, closed period) return 422 with a message.", opid="create_journal_entry")}
paths["/journal-entries/simple"] = {"post": op("Journal entries", "Two-line entry by account codes", "journal-entries.create", created, params=idem, body=ref("SimpleEntryInput"), desc="Debits one account and credits another with the same amount; posts by default.", opid="create_simple_journal_entry")}
paths["/journal-entries/{id}"] = {"parameters": [ID],
 "get": op("Journal entries", "Show an entry with lines", "journal-entries.view", {"200": resp("Entry", data(ref("JournalEntry"))), **E404}, opid="show_journal_entry"),
 "put": op("Journal entries", "Update a draft (PATCH also accepted)", "journal-entries.update", {"200": resp("Updated draft", data(ref("JournalEntry"))), **E404_422}, body=ref("JournalEntryInput"), desc="Only drafts; lines with an id are updated, lines without one are added, missing lines are removed.", opid="update_journal_entry")}
paths["/journal-entries/{id}/post"] = {"parameters": [ID], "post": op("Journal entries", "Post a draft", "journal-entries.post", {"200": resp("Posted entry", data(ref("JournalEntry"))), **E404_422}, opid="post_journal_entry")}
paths["/journal-entries/{id}/reverse"] = {"parameters": [ID], "post": op("Journal entries", "Reverse a posted entry", "journal-entries.reverse", {"200": resp("The posted reversal entry", data(ref("JournalEntry"))), **E404_422}, body={"type": "object", "properties": {"description": {"type": ["string", "null"]}, "reversal_date": {"type": ["string", "null"], "format": "date", "description": "Default today; not before the original date."}}}, opid="reverse_journal_entry")}
paths["/journal-entries/{id}/submit"] = {"parameters": [ID], "post": op("Journal entries", "Submit a draft for approval (maker)", "journal-entries.create", {"200": resp("Entry awaiting approval", data(ref("JournalEntry"))), **E404_422}, desc="Maker-checker (ACCOUNTING_APPROVALS_ENABLED). Validates the entry now; 422 if approvals are off or the entry is below the threshold.", opid="submit_journal_entry")}
paths["/journal-entries/{id}/approve"] = {"parameters": [ID], "post": op("Journal entries", "Approve and post (checker)", "journal-entries.approve", {"200": resp("Approved and posted entry", data(ref("JournalEntry"))), **E404_422}, desc="The checker must be a different user from the maker and the submitter.", opid="approve_journal_entry")}
paths["/journal-entries/{id}/reject"] = {"parameters": [ID], "post": op("Journal entries", "Reject back to the maker (checker)", "journal-entries.approve", {"200": resp("Rejected draft", data(ref("JournalEntry"))), **E404_422}, body={"type": "object", "required": ["reason"], "properties": {"reason": {"type": "string", "maxLength": 2000}}}, opid="reject_journal_entry")}
paths["/journal-entries/{id}/void"] = {"parameters": [ID], "post": op("Journal entries", "Void a draft", "journal-entries.void", {"200": resp("Voided entry", data(ref("JournalEntry"))), **E404_422}, desc="Posted entries cannot be voided — reverse them.", opid="void_journal_entry")}

vt_props = {"code": {"type": "string", "maxLength": 20, "example": "SV", "description": "Upper-case; fixed once entries are numbered."},
    "name": {"type": "string", "example": "Sales Voucher"}, "prefix": {"type": "string", "maxLength": 20, "example": "SV"},
    "format": {"type": "string", "default": "{PREFIX}-{FY}-{SEQ:5}", "description": "Tokens {PREFIX} {FY} {YYYY} {YY} {MM} {SEQ} {SEQ:n}. Yearly series need {FY}; monthly ones {MM} and a year token."},
    "reset": {"type": "string", "enum": ["yearly", "monthly", "never"], "default": "yearly", "description": "When numbering restarts; fixed once entries are numbered."},
    "description": {"type": ["string", "null"]}, "is_active": {"type": "boolean", "default": True}}
schemas["VoucherType"] = {"type": "object", "properties": {"id": {"type": "integer"}, **vt_props, "is_system": {"type": "boolean", "description": "The default type (JV): cannot be deleted or deactivated."},
    "next_number": {"type": "string", "example": "SV-2026-00001", "description": "What the next posting today would get (not reserved)."}, "entries_count": {"type": "integer"}}}
paths["/voucher-types"] = {
 "get": op("Voucher types", "List voucher types with their next numbers", "voucher-types.view", {"200": resp("Voucher types", data({"type": "array", "items": ref("VoucherType")})), **E}, params=[{"name": "filter[is_active]", "in": "query", "schema": {"type": "boolean"}}], opid="list_voucher_types"),
 "post": op("Voucher types", "Create a voucher type", "voucher-types.create", {"201": resp("Created", data(ref("VoucherType"))), **E422}, body={"type": "object", "required": ["code", "name", "prefix"], "properties": vt_props}, opid="create_voucher_type")}
paths["/voucher-types/{id}"] = {"parameters": [ID],
 "get": op("Voucher types", "Show a voucher type", "voucher-types.view", {"200": resp("Voucher type", data(ref("VoucherType"))), **E404}, opid="show_voucher_type"),
 "put": op("Voucher types", "Update a voucher type (PATCH also accepted)", "voucher-types.update", {"200": resp("Updated", data(ref("VoucherType"))), **E404_422}, body={"type": "object", "properties": vt_props}, desc="Send only the fields to change. 422 when changing the code or reset of a numbered series, or deactivating JV.", opid="update_voucher_type"),
 "delete": op("Voucher types", "Delete an unused voucher type", "voucher-types.delete", {"204": resp("Deleted"), **E404_422}, desc="422 for the default type and for types used by entries (deactivate them instead).", opid="delete_voucher_type")}
paths["/voucher-types/{id}/next-number"] = {"parameters": [ID], "get": op("Voucher types", "Preview the next number", "voucher-types.view", {"200": resp("Next number", data({"type": "object", "properties": {"voucher_type": {"type": "string"}, "date": {"type": "string", "format": "date"}, "next_number": {"type": "string"}}})), **E404_422}, params=[{"name": "date", "in": "query", "schema": {"type": "string", "format": "date"}, "description": "Default today."}], opid="next_voucher_number")}

user_schema = {"type": "object", "properties": {"id": {"type": "integer"}, "name": {"type": "string"}, "email": {"type": "string", "format": "email"},
    "roles": {"type": "array", "items": {"type": "string"}}, "direct_permissions": {"type": ["array", "null"], "items": {"type": "string"}}, "created_at": {"type": ["string", "null"], "format": "date-time"}}}
schemas["User"] = user_schema
schemas["Role"] = {"type": "object", "properties": {"id": {"type": "integer"}, "name": {"type": "string"}, "permissions": {"type": ["array", "null"], "items": {"type": "string"}},
    "permissions_count": {"type": ["integer", "null"]}, "users_count": {"type": ["integer", "null"]}, "is_super_admin": {"type": "boolean"}}}
user_input = {"type": "object", "properties": {"name": {"type": "string"}, "email": {"type": "string", "format": "email"}, "password": {"type": "string", "minLength": 8},
    "password_confirmation": {"type": "string"}, "roles": {"type": "array", "items": {"type": "string"}, "description": "Requires `user.assign-role`; only roles whose permissions you hold."}}}
guard_note = "Privilege-checked (you grant, revoke and manage only what you hold; only a super-admin touches super-admin) and audited (who, old and new values)."
paths["/users"] = {
 "get": op("Users & roles", "List users", "user.view", {"200": resp("Users", {"type": "object", "properties": {"data": {"type": "array", "items": ref("User")}, "meta": {"type": "object"}}}), **E},
    params=PAGE + [{"name": f"filter[{f}]", "in": "query", "schema": {"type": "string"}} for f in ["name", "email", "role"]], opid="list_users"),
 "post": op("Users & roles", "Create a user", "user.create", {"201": resp("Created", data(ref("User"))), **E422}, body={**user_input, "required": ["name", "email", "password", "password_confirmation"]}, desc=guard_note, opid="create_user")}
paths["/users/{id}"] = {"parameters": [ID],
 "get": op("Users & roles", "Show a user with all effective permissions", "user.view", {"200": resp("User", data({"allOf": [ref("User"), {"type": "object", "properties": {"all_permissions": {"type": "array", "items": {"type": "string"}}}}]})), **E404}, opid="show_user"),
 "put": op("Users & roles", "Update a user (PATCH also accepted)", "user.update", {"200": resp("Updated", data(ref("User"))), **E404_422}, body=user_input, desc="Send only what changes; `roles` replaces the roles. "+guard_note, opid="update_user"),
 "delete": op("Users & roles", "Delete a user", "user.delete", {"204": resp("Deleted"), **E404}, desc="Not yourself. "+guard_note, opid="delete_user")}
paths["/users/{id}/roles"] = {"parameters": [ID], "put": op("Users & roles", "Replace a user's roles", "user.assign-role", {"200": resp("User", data(ref("User"))), **E404_422},
    body={"type": "object", "required": ["roles"], "properties": {"roles": {"type": "array", "items": {"type": "string"}}}}, desc=guard_note+" The audit row lists added and removed roles.", opid="sync_user_roles")}
paths["/users/{id}/permissions"] = {"parameters": [ID], "put": op("Users & roles", "Replace a user's direct permissions", "user.assign-permission", {"200": resp("User", data(ref("User"))), **E404_422},
    body={"type": "object", "required": ["permissions"], "properties": {"permissions": {"type": "array", "items": {"type": "string"}}}}, desc="On top of the roles. "+guard_note, opid="sync_user_permissions")}
paths["/roles"] = {
 "get": op("Users & roles", "List roles with permission and user counts", "accounting.manage-settings", {"200": resp("Roles", data({"type": "array", "items": ref("Role")})), **E}, opid="list_roles"),
 "post": op("Users & roles", "Create a role", "accounting.manage-settings", {"201": resp("Created", data(ref("Role"))), **E422},
    body={"type": "object", "required": ["name"], "properties": {"name": {"type": "string"}, "permissions": {"type": "array", "items": {"type": "string"}}}}, desc=guard_note, opid="create_role")}
paths["/roles/{id}"] = {"parameters": [ID],
 "get": op("Users & roles", "Show a role", "accounting.manage-settings", {"200": resp("Role", data(ref("Role"))), **E404}, opid="show_role"),
 "put": op("Users & roles", "Update a role (PATCH also accepted)", "accounting.manage-settings", {"200": resp("Updated", data(ref("Role"))), **E404_422},
    body={"type": "object", "properties": {"name": {"type": "string"}, "permissions": {"type": "array", "items": {"type": "string"}}}}, desc="super-admin cannot be renamed (422). "+guard_note, opid="update_role"),
 "delete": op("Users & roles", "Delete a role", "accounting.manage-settings", {"204": resp("Deleted"), **E404_422}, desc="super-admin cannot be deleted (422). "+guard_note, opid="delete_role")}
paths["/permissions"] = {"get": op("Users & roles", "All permissions grouped by area", "accounting.manage-settings", {"200": resp("Permissions", data({"type": "object", "additionalProperties": {"type": "array", "items": {"type": "string"}}})), **E}, opid="list_permissions")}

schemas["Attachment"] = {"type": "object", "properties": {"id": {"type": "integer"}, "original_name": {"type": "string", "example": "bill-778.pdf"},
    "mime_type": {"type": ["string", "null"]}, "size": {"type": "integer", "description": "Bytes"}, "sha256": {"type": "string"},
    "description": {"type": ["string", "null"]}, "uploaded_by": {"type": ["string", "null"]}, "uploaded_at": {"type": ["string", "null"], "format": "date-time"}}}
paths["/journal-entries/{id}/attachments"] = {"parameters": [ID],
 "get": op("Journal entries", "Supporting documents of an entry", "attachments.view", {"200": resp("Attachments", data({"type": "array", "items": ref("Attachment")})), **E404}, opid="list_attachments"),
 "post": op("Journal entries", "Attach a document (multipart/form-data)", "attachments.create",
    {"201": resp("Attached", {"type": "object", "properties": {"data": ref("Attachment"), "duplicates": {"type": "array", "description": "Other entries carrying the very same file (possible duplicate bill)", "items": {"type": "object", "properties": {"id": {"type": "integer"}, "voucher_number": {"type": ["string", "null"]}}}}}}), **E404_422},
    desc="Fields: `file` (max `ACCOUNTING_ATTACHMENTS_MAX_KB`, types from `accounting.attachments.mimes`) and optional `description`. Stored on a private disk.", opid="attach_document")}
paths["/journal-entries/{id}/attachments"]["post"]["requestBody"] = {"required": True, "content": {"multipart/form-data": {"schema": {"type": "object", "required": ["file"], "properties": {"file": {"type": "string", "format": "binary"}, "description": {"type": "string"}}}}}}
paths["/attachments/{id}/download"] = {"parameters": [ID], "get": op("Journal entries", "Download a document", "attachments.view", {"200": {"description": "The file", "content": {"application/octet-stream": {"schema": {"type": "string", "format": "binary"}}}}, **E404}, opid="download_attachment")}
paths["/attachments/{id}"] = {"parameters": [ID], "delete": op("Journal entries", "Remove a document of a draft", "attachments.delete", {"204": resp("Removed"), **E404_422}, desc="422 for posted or voided entries: their documents are kept as evidence.", opid="remove_attachment")}

ctl_row = {"type": "object", "properties": {"id": {"type": "integer"}, "account_code": {"type": "string"}, "account_name": {"type": "string"}, "control_type": {"type": "string"},
    "control_label": {"type": "string"}, "balance": ref("Money"), "manual_postings": {"type": "integer", "description": "Posted entries that did not come from the account's module"}}}
paths["/control-accounts"] = {"get": op("Chart of accounts", "Control accounts with balances and manual postings", "chart-of-accounts.view",
    {"200": resp("Control accounts", {"type": "object", "properties": {"data": {"type": "array", "items": ctl_row}, "types": {"type": "object", "additionalProperties": {"type": "string"}}}}), **E},
    desc="A control account summarises a sub-ledger (customers, suppliers, stock …). Manual journal entries to it need `control-accounts.post-manual`.", opid="list_control_accounts")}
paths["/control-accounts/recommended"] = {"post": op("Chart of accounts", "Mark the recommended control accounts", "control-accounts.manage",
    {"200": resp("Accounts marked now", data({"type": "array", "items": {"type": "object"}})), **E422}, desc="Uses config('accounting.control_accounts.recommended'); accounts already marked are skipped.", opid="recommended_control_accounts")}
paths["/control-accounts/{id}/manual-postings"] = {"parameters": [ID], "get": op("Chart of accounts", "Manual postings to a control account", "chart-of-accounts.view",
    {"200": resp("Latest 50 entries", data({"type": "array", "items": {"type": "object"}})), **E404}, opid="control_account_manual_postings")}
paths["/chart-of-accounts/{id}/control-type"] = {"parameters": [ID], "put": op("Chart of accounts", "Set or clear an account's control type", "control-accounts.manage",
    {"200": resp("Account", data({"type": "object"})), **E404_422}, body={"type": "object", "required": ["control_type"], "properties": {"control_type": {"type": ["string", "null"]}}}, opid="set_control_type")}

paths["/account-balance-snapshots"] = {"get": op("Periods", "List balance snapshots", "account-balance-snapshots.view", {"200": resp("Paginated snapshots", paginated({"type": "object"})), **E}, params=PAGE + [{"name": "filter[chart_of_account_id]", "in": "query", "schema": {"type": "integer"}}, {"name": "filter[accounting_period_id]", "in": "query", "schema": {"type": "integer"}}], opid="list_snapshots")}
paths["/account-balance-snapshots/{id}"] = {"parameters": [ID], "get": op("Periods", "Show a balance snapshot", "account-balance-snapshots.view", {"200": resp("Snapshot", data({"type": "object"})), **E404}, opid="show_snapshot")}

date_q = [{"name": "date_from", "in": "query", "schema": {"type": "string", "format": "date"}}, {"name": "date_to", "in": "query", "schema": {"type": "string", "format": "date"}}]
asof_q = [{"name": "as_of_date", "in": "query", "schema": {"type": "string", "format": "date"}}]
ledger_q = PAGE + date_q + [{"name": "account_id", "in": "query", "schema": {"type": "integer"}}, {"name": "status", "in": "query", "schema": {"type": "string", "enum": ["posted", "draft", "void", "all"], "default": "posted"}}]
reports = {
 "trial-balance": ("Trial balance", [], ref("ReportRows"), "All posted activity per account; totals.difference is 0 when balanced."),
 "balance-sheet": ("Balance sheet", asof_q, ref("ReportRows"), "Includes a 'Current Earnings (unclosed)' row; totals: assets, liabilities_and_equity, difference."),
 "income-statement": ("Income statement", date_q, ref("ReportRows"), "Defaults to the period containing today; closing entries excluded. totals: revenue, expenses, net_income."),
 "general-ledger": ("General ledger", ledger_q, ref("LedgerPage"), None),
 "cash-flow": ("Cash flow", date_q + PAGE, ref("CashFlowPage"), "Direct method: movements on cash and bank accounts (and their child accounts), paginated; totals cover the whole period."),
 "bank-book": ("Bank book", ledger_q + [{"name": "bank_account_id", "in": "query", "schema": {"type": "integer"}}], ref("LedgerPage"), None),
 "cash-book": ("Cash book", ledger_q, ref("LedgerPage"), None),
 "aged-receivables": ("Aged receivables", asof_q, ref("ReportRows"), "Buckets: current, 1-30, 31-60, 61-90, over 90 days."),
 "aged-payables": ("Aged payables", asof_q, ref("ReportRows"), "Buckets: current, 1-30, 31-60, 61-90, over 90 days."),
 "account-statement": ("Account statement", [{"name": "account_code", "in": "query", "schema": {"type": "string"}}, {"name": "account_id", "in": "query", "schema": {"type": "integer"}}] + date_q + PAGE, ref("StatementPage"), "account_id or account_code is required (404 if it does not exist). Opening balance, paginated lines with running balance, closing balance."),
}
for uri, (summary, params, schema, desc) in reports.items():
    errors = {**E404_422} if uri == "account-statement" else E422
    paths[f"/reports/{uri}"] = {"get": op("Reports", summary, f"reports.{uri}.view", {"200": resp(summary, schema), **errors}, params=params or None, desc=desc, opid=f"report_{uri.replace('-', '_')}")}


# ── Companies (multi-company) ────────────────────────────────────────────────
schemas["Company"] = {"type": "object", "properties": {
    "id": {"type": "integer"}, "code": {"type": "string", "example": "SUB"}, "name": {"type": "string", "example": "Subsidiary Ltd"},
    "legal_name": {"type": ["string", "null"]}, "tax_number": {"type": ["string", "null"], "description": "e.g. NTN"},
    "registration_number": {"type": ["string", "null"]}, "email": {"type": ["string", "null"]}, "phone": {"type": ["string", "null"]},
    "address": {"type": ["string", "null"]}, "fiscal_year_start_month": {"type": "integer", "minimum": 1, "maximum": 12}, "is_active": {"type": "boolean"}}}
company_input = {"type": "object", "required": ["code", "name"], "properties": {
    "code": {"type": "string", "maxLength": 30, "pattern": "^[A-Za-z0-9_-]+$", "description": "Stored upper-case; unique."},
    "name": {"type": "string"}, "legal_name": {"type": "string"}, "tax_number": {"type": "string"}, "registration_number": {"type": "string"},
    "email": {"type": "string", "format": "email"}, "phone": {"type": "string"}, "address": {"type": "string"},
    "fiscal_year_start_month": {"type": "integer", "minimum": 1, "maximum": 12, "default": 1},
    "seed": {"type": "boolean", "default": True, "description": "Create with the standard chart of accounts, current fiscal year, cost centers and tax codes (create only)."}}}
members = {"type": "array", "items": {"type": "object", "properties": {"id": {"type": "integer"}, "name": {"type": "string"}, "email": {"type": "string"}, "is_default": {"type": "boolean"}}}}
company_id = {"name": "company", "in": "path", "required": True, "schema": {"type": "integer"}}
paths["/companies"] = {
    "get": op("Companies", "Companies you can work in", "accounting.view", {"200": resp("Companies; current marks the one this request runs in", {"type": "object", "properties": {
        "data": {"type": "array", "items": {"allOf": [ref("Company"), {"type": "object", "properties": {"current": {"type": "boolean"}}}]}},
        "multi_company": {"type": "boolean"}}}), **E}, opid="list_companies"),
    "post": op("Companies", "Create a company", "companies.manage", {"201": resp("Created company", data(ref("Company"))), **E422}, body=company_input,
               desc="The creator gets access to the new company.", opid="create_company"),
}
paths["/companies/{company}"] = {"parameters": [company_id],
    "get": op("Companies", "Show a company with its members", "companies.manage", {"200": resp("Company", data({"allOf": [ref("Company"), {"type": "object", "properties": {"members": members}}]})), **E404}, opid="show_company"),
    "put": op("Companies", "Update a company", "companies.manage", {"200": resp("Updated company", data(ref("Company"))), **E404_422}, body={**company_input, "required": []}, opid="update_company"),
}
paths["/companies/{company}/users"] = {"parameters": [company_id],
    "post": op("Companies", "Give a user access", "companies.manage", {"200": resp("Members", data(members)), **E404_422},
               body={"type": "object", "required": ["user_id"], "properties": {"user_id": {"type": "integer"}, "is_default": {"type": "boolean", "description": "Make it the user's default company."}}},
               desc="You can only grant access to companies you can access yourself. Recorded in the audit log.", opid="grant_company_access")}
paths["/companies/{company}/users/{user}"] = {"parameters": [company_id, {"name": "user", "in": "path", "required": True, "schema": {"type": "integer"}}],
    "delete": op("Companies", "Remove a user's access", "companies.manage", {"200": resp("Members", data(members)), **E404}, opid="revoke_company_access")}
schemas["ConsolidatedReport"] = {"type": "object", "properties": {
    "report": {"type": "string"},
    "companies": {"type": "array", "items": {"type": "object", "properties": {"id": {"type": "integer"}, "code": {"type": "string"}, "name": {"type": "string"}}}},
    "data": {"type": "array", "items": {"type": "object", "properties": {
        "account_code": {"type": "string"}, "account_name": {"type": "string"},
        "companies": {"type": "object", "additionalProperties": ref("Money"), "description": "Balance per company code"},
        "balance": ref("Money")}}},
    "totals": {"type": "object", "properties": {"companies": {"type": "object"}, "group": {"type": "object"}}}}}
paths["/reports/consolidated/{report}"] = {"parameters": [{"name": "report", "in": "path", "required": True, "schema": {"type": "string", "enum": ["trial-balance", "balance-sheet", "income-statement"]}}],
    "get": op("Reports", "Consolidated report", "reports.consolidated.view", {"200": resp("Each company side by side plus the group total", ref("ConsolidatedReport")), **E404_422},
              params=[{"name": "companies", "in": "query", "schema": {"type": "string", "example": "MAIN,SUB"}, "description": "Codes or ids; default: every company you can access."}] + date_q + asof_q,
              desc="Companies share the base currency, so amounts add up directly. Intercompany balances are not eliminated.", opid="consolidated_report")}

# Exports (CSV / Excel / PDF), background exports and voucher PDFs.
REPORT_NAMES = ["general-ledger", "trial-balance", "balance-sheet", "income-statement", "cash-flow", "aged-receivables", "aged-payables", "bank-book", "cash-book", "account-statement", "statement-balance-sheet", "statement-income-statement", "statement-cash-flow"]
REPORT_P = {"name": "report", "in": "path", "required": True, "schema": {"type": "string", "enum": REPORT_NAMES}}
FORMAT_P = {"name": "format", "in": "path", "required": True, "schema": {"type": "string", "enum": ["csv", "xlsx", "pdf"]}}
FILE_200 = {"description": "The file", "content": {"text/csv": {"schema": {"type": "string", "format": "binary"}}, "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet": {"schema": {"type": "string", "format": "binary"}}, "application/pdf": {"schema": {"type": "string", "format": "binary"}}}}
schemas["ReportExport"] = {"type": "object", "properties": {
    "id": {"type": "integer"}, "report": {"type": "string", "enum": REPORT_NAMES}, "title": {"type": "string"},
    "format": {"type": "string", "enum": ["csv", "xlsx", "pdf"]}, "filters": {"type": "object", "description": "The report filters the export was asked with"},
    "status": {"type": "string", "enum": ["queued", "running", "ready", "failed"]}, "rows": {"type": ["integer", "null"]}, "size": {"type": ["integer", "null"], "description": "Bytes"},
    "error": {"type": ["string", "null"]}, "created_at": {"type": "string", "format": "date-time"}, "finished_at": {"type": ["string", "null"], "format": "date-time"}}}
report_filters_q = [{"name": n, "in": "query", "schema": {"type": "string"}} for n in ["date_from", "date_to", "as_of", "account_id", "status"]]
paths["/reports/{report}/export/{format}"] = {"parameters": [REPORT_P, FORMAT_P],
    "get": op("Reports", "Download a report as CSV, Excel or a typeset PDF", "reports.<report>.view", {"200": FILE_200, "202": resp("Too large to build now: queued as a background export", data(ref("ReportExport"))), **E404_422}, params=report_filters_q,
              desc="Takes the same filters as the report. PDFs carry the company letterhead, the filters, totals and page numbers (dompdf; built-in fallback without it). "
                   "Excel and PDF exports above `accounting.exports.max_rows` are queued instead (202) when `accounting.exports.queue_large` is on.", opid="export_report")}
paths["/reports/{report}/exports/{format}"] = {"parameters": [REPORT_P, FORMAT_P],
    "post": op("Reports", "Queue a background export", "reports.<report>.view", {"202": resp("Queued", data(ref("ReportExport"))), **E404_422}, params=report_filters_q,
               desc="The job re-checks your permission when it runs. Poll `GET /exports` for the status, then download it.", opid="queue_report_export")}
paths["/exports"] = {"get": op("Reports", "Your background exports", "accounting.view", {"200": resp("Exports, newest first", data({"type": "array", "items": ref("ReportExport")}))}, opid="list_exports")}
paths["/exports/{id}/download"] = {"parameters": [ID], "get": op("Reports", "Download a finished export", "accounting.view", {"200": FILE_200, **E404}, desc="Only the user who asked for the export can download it (404 for anyone else).", opid="download_export")}
paths["/exports/{id}"] = {"parameters": [ID], "delete": op("Reports", "Delete one of your exports and its file", "accounting.view", {"204": resp("Deleted"), **E404}, opid="delete_export")}
paths["/journal-entries/{id}/pdf"] = {"parameters": [ID], "get": op("Journal entries", "Printable voucher PDF", "journal-entries.view",
    {"200": {"description": "The voucher", "content": {"application/pdf": {"schema": {"type": "string", "format": "binary"}}}}, **E404, "501": resp("PDF engine (dompdf) not installed")},
    desc="Letterhead, voucher number, source document, lines, totals, amount in words, signature boxes; DRAFT / VOID watermark when not posted.", opid="voucher_pdf")}

# Chart of accounts import / export.
COA_FILE = {"description": "The chart in import layout", "content": {"text/csv": {"schema": {"type": "string", "format": "binary"}}, "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet": {"schema": {"type": "string", "format": "binary"}}, "application/pdf": {"schema": {"type": "string", "format": "binary"}}}}
schemas["ChartImportResult"] = {"type": "object", "properties": {
    "committed": {"type": "boolean", "description": "true when the accounts were saved"},
    "summary": {"type": "object", "properties": {k: {"type": "integer"} for k in ["create", "update", "unchanged", "error"]}},
    "rows": {"type": "array", "items": {"type": "object", "properties": {
        "line": {"type": "integer", "description": "Line in the file (the header is line 1)"},
        "account_code": {"type": "string"}, "account_name": {"type": "string"},
        "action": {"type": "string", "enum": ["create", "update", "unchanged", "error"]},
        "changes": {"type": "object", "additionalProperties": {"type": "array", "items": {}, "minItems": 2, "maxItems": 2}, "description": "field → [old, new] for updates"},
        "errors": {"type": "array", "items": {"type": "string"}}}}}}}
paths["/chart-of-accounts/export/{format}"] = {"parameters": [{"name": "format", "in": "path", "required": True, "schema": {"type": "string", "enum": ["csv", "xlsx", "pdf"]}}],
    "get": op("Chart of accounts", "Export the chart (re-importable)", "chart-of-accounts.view", {"200": COA_FILE, **E},
              desc="Columns: account_code, account_name, parent_code, account_type, normal_balance, currency, is_group, is_active, control_type, description — the import layout, so an export can be edited and imported back.", opid="export_chart")}
paths["/chart-of-accounts/import/template/{format}"] = {"parameters": [{"name": "format", "in": "path", "required": True, "schema": {"type": "string", "enum": ["csv", "xlsx"]}}],
    "get": op("Chart of accounts", "Import template with example rows", "chart-of-accounts.import", {"200": COA_FILE, **E}, opid="chart_import_template")}
paths["/chart-of-accounts/import"] = {
    "post": op("Chart of accounts", "Import accounts from CSV or Excel (multipart/form-data)", "chart-of-accounts.import",
               {"200": resp("Preview (dry_run) or nothing to change", data(ref("ChartImportResult"))), "201": resp("Imported", data(ref("ChartImportResult"))), **E422},
               desc="Fields: `file` (.csv/.xlsx, max `ACCOUNTING_CHART_IMPORT_MAX_KB`), `mode` (`upsert` updates existing codes — default; `create` leaves them unchanged) and `dry_run` (preview, nothing saved). "
                    "Parents may appear anywhere in the file. Every row passes the same rules as the API and the database guards; if any row is in error nothing is imported (422 with the rows).", opid="import_chart")}
paths["/chart-of-accounts/import"]["post"]["requestBody"] = {"required": True, "content": {"multipart/form-data": {"schema": {"type": "object", "required": ["file"], "properties": {
    "file": {"type": "string", "format": "binary"}, "mode": {"type": "string", "enum": ["upsert", "create"], "default": "upsert"}, "dry_run": {"type": "boolean", "default": False}}}}}}

# Renumber and merge accounts.
schemas["MergePlan"] = {"type": "object", "properties": {
    "source": {"type": "object"}, "target": {"type": "object"},
    "transfers": {"type": "array", "items": {"type": "object", "properties": {"cost_center": {"type": ["string", "null"]}, "amount": ref("Money"), "side": {"type": "string", "enum": ["debit", "credit"]}}}, "description": "The source's balance per cost center, moved by the transfer entry"},
    "balance": ref("Money"), "draft_lines": {"type": "integer"},
    "children": {"type": "array", "items": {"type": "string"}}, "bank_accounts": {"type": "array", "items": {"type": "string"}},
    "problems": {"type": "array", "items": {"type": "string"}, "description": "Why the merge cannot run (empty when it can)"}}}
CODE_MAP = data({"type": "object", "properties": {"map": {"type": "object", "additionalProperties": {"type": "string"}, "description": "old code → new code"}}})
renumber_body = {"type": "object", "required": ["account_code"], "properties": {"account_code": {"type": "string", "maxLength": 30}, "with_children": {"type": "boolean", "default": True, "description": "Also renumber sub-accounts sharing the code prefix (5100 → 6100 turns 5101 into 6101)"}}}
paths["/chart-of-accounts/{id}/renumber-preview"] = {"parameters": [ID],
    "get": op("Chart of accounts", "Preview a renumbering", "chart-of-accounts.restructure", {"200": resp("Codes", CODE_MAP), **E404_422},
              params=[{"name": "account_code", "in": "query", "required": True, "schema": {"type": "string"}}, {"name": "with_children", "in": "query", "schema": {"type": "boolean"}}], opid="renumber_preview")}
paths["/chart-of-accounts/{id}/renumber"] = {"parameters": [ID],
    "post": op("Chart of accounts", "Renumber an account (used accounts too)", "chart-of-accounts.restructure", {"200": resp("Renumbered", CODE_MAP), **E404_422}, body=renumber_body,
               desc="Journal lines point to the account, not its code, so history follows. 422 for a code in use or an account referenced by `config('accounting.defaults')`. Audited (ACCOUNT_RENUMBERED).", opid="renumber_account")}
paths["/chart-of-accounts/{id}/merge-preview"] = {"parameters": [ID],
    "get": op("Chart of accounts", "Preview merging this account into another", "chart-of-accounts.restructure", {"200": resp("What the merge would do", data(ref("MergePlan"))), **E404_422},
              params=[{"name": "target_account_id", "in": "query", "required": True, "schema": {"type": "integer"}}], opid="merge_preview")}
paths["/chart-of-accounts/{id}/merge"] = {"parameters": [ID],
    "post": op("Chart of accounts", "Merge this account into another", "chart-of-accounts.restructure",
               {"200": resp("Merged", data({"type": "object", "properties": {"transfer_entry_id": {"type": ["integer", "null"]}, "voucher_number": {"type": ["string", "null"]}, "moved_draft_lines": {"type": "integer"}, "moved_children": {"type": "integer"}, "moved_bank_accounts": {"type": "integer"}}})), **E404_422},
               body={"type": "object", "properties": {"target_account_id": {"type": "integer"}, "target_code": {"type": "string"}, "date": {"type": "string", "format": "date", "description": "Transfer date (default today, must be in an open period)"}, "description": {"type": "string"}}},
               desc="Same type, normal balance, currency and control type only. The balance moves with a posted transfer entry (one pair of lines per cost center) through the normal posting rules — 422 when it needs approval. Draft lines, sub-accounts and bank accounts move to the target; the source is deactivated with `metadata.merged_into`. Audited (ACCOUNT_MERGED).", opid="merge_account")}

# Report mapping and financial statements.
SECTIONS_BS = ["current_assets", "non_current_assets", "current_liabilities", "non_current_liabilities", "equity"]
SECTIONS_IS = ["revenue", "cost_of_sales", "other_income", "operating_expenses", "finance_costs", "income_tax"]
CF = ["cash", "operating", "non_cash", "investing", "financing"]
schemas["ReportLine"] = {"type": "object", "properties": {
    "id": {"type": "integer"}, "statement": {"type": "string", "enum": ["balance_sheet", "income_statement"]}, "code": {"type": "string"}, "name": {"type": "string"},
    "section": {"type": "string", "enum": SECTIONS_BS + SECTIONS_IS}, "cash_flow_category": {"type": ["string", "null"], "enum": CF + [None], "description": "Balance sheet lines only: where the line's movements go in the indirect cash flow"},
    "sort_order": {"type": "integer"}, "is_system": {"type": "boolean", "description": "Standard lines can be renamed but not deleted"}, "accounts_count": {"type": "integer"}}}
schemas["AccountMapping"] = {"type": "object", "properties": {
    "id": {"type": "integer"}, "account_code": {"type": "string"}, "account_name": {"type": "string"}, "parent_id": {"type": ["integer", "null"]}, "is_group": {"type": "boolean"},
    "statement": {"type": "string", "enum": ["balance_sheet", "income_statement"]},
    "report_line_id": {"type": ["integer", "null"], "description": "Own mapping (null: inherits from the parent)"}, "cash_flow_category": {"type": ["string", "null"]},
    "resolved_line_id": {"type": ["integer", "null"]}, "resolved_cash_flow_category": {"type": ["string", "null"]}, "inherited": {"type": "boolean"}}}
line_body = {"type": "object", "properties": {"statement": {"type": "string", "enum": ["balance_sheet", "income_statement"], "description": "Create only"}, "code": {"type": "string"}, "name": {"type": "string"}, "section": {"type": "string"}, "cash_flow_category": {"type": ["string", "null"], "enum": CF + [None]}, "sort_order": {"type": "integer"}}}
paths["/report-mapping"] = {"get": op("Reports", "Report lines and the mapping of every account", "report-mapping.manage",
    {"200": resp("Lines and accounts", data({"type": "object", "properties": {"lines": {"type": "array", "items": ref("ReportLine")}, "accounts": {"type": "array", "items": ref("AccountMapping")}, "unmapped": {"type": "integer", "description": "Posting accounts without a line"}}}))}, opid="report_mapping")}
paths["/report-mapping/recommended"] = {"post": op("Reports", "Map the seeded accounts to their standard lines", "report-mapping.manage", {"200": resp("Accounts mapped", data({"type": "array", "items": {"type": "object"}}))}, desc="Accounts that already have a line are left alone. Audited.", opid="report_mapping_recommended")}
paths["/chart-of-accounts/{id}/report-mapping"] = {"parameters": [ID], "put": op("Reports", "Map an account (and its sub-accounts) to a line", "report-mapping.manage",
    {"200": resp("Mapped", data(ref("AccountMapping"))), **E404_422}, body={"type": "object", "properties": {"report_line_id": {"type": ["integer", "null"], "description": "null: inherit from the parent"}, "cash_flow_category": {"type": ["string", "null"], "enum": CF + [None], "description": "Balance sheet accounts only; null: the line's"}}},
    desc="The line must belong to the account's statement. Audited (REPORT_MAPPING_CHANGED).", opid="map_account")}
paths["/report-lines"] = {"post": op("Reports", "Add a statement line", "report-mapping.manage", {"201": resp("Created", data(ref("ReportLine"))), **E422}, body={**line_body, "required": ["statement", "code", "name", "section"]}, opid="create_report_line")}
paths["/report-lines/{id}"] = {"parameters": [ID],
    "put": op("Reports", "Rename, move or reclassify a line", "report-mapping.manage", {"200": resp("Updated", data(ref("ReportLine"))), **E404_422}, body=line_body, desc="The code of a standard line cannot change.", opid="update_report_line"),
    "delete": op("Reports", "Delete a custom line", "report-mapping.manage", {"204": resp("Deleted"), **E404_422}, desc="422 for standard lines and for lines with accounts mapped.", opid="delete_report_line")}
paths["/reports/statements/{type}"] = {"parameters": [{"name": "type", "in": "path", "required": True, "schema": {"type": "string", "enum": ["balance-sheet", "income-statement", "cash-flow"]}}],
    "get": op("Reports", "Financial statement by report lines", "reports.financial-statements.view", {"200": resp("Statement", data({"type": "object"})), **E404},
              params=[{"name": n, "in": "query", "schema": {"type": "string", "format": "date"}} for n in ["as_of_date", "compare_as_of", "date_from", "date_to", "compare_from", "compare_to"]],
              desc="balance-sheet: sections → lines → accounts with `totals` (assets, liabilities, equity, difference) as of `as_of_date`, optional `compare_as_of` column. "
                   "income-statement: sections with `subtotals` (gross_profit, operating_profit, profit_before_tax, net_profit), optional comparative period. "
                   "cash-flow: indirect method — profit, non-cash adjustments, working capital, investing, financing, opening/closing cash and `difference` (0 when it reconciles). "
                   "Unmapped accounts appear on 'unmapped' lines / as unclassified. Export with `/reports/statement-{type}/export/{format}`.", opid="financial_statement")}

# Industry chart templates.
TEMPLATE_KEY = {"name": "template", "in": "path", "required": True, "schema": {"type": "string", "enum": ["general", "trading", "manufacturing", "services", "school", "ngo", "healthcare"], "description": "Or a key from `accounting.chart_templates`"}}
schemas["ChartTemplate"] = {"type": "object", "properties": {"key": {"type": "string"}, "name": {"type": "string"}, "description": {"type": "string"}, "base": {"type": "string", "enum": ["general", "school"]}, "accounts": {"type": "integer"}, "extras": {"type": "integer", "description": "Accounts specific to the industry (on top of the base chart)"}}}
schemas["ChartTemplatePreview"] = {"type": "object", "properties": {
    "template": {"type": "object", "properties": {"key": {"type": "string"}, "name": {"type": "string"}, "description": {"type": "string"}}},
    "summary": {"type": "object", "properties": {k: {"type": "integer"} for k in ["new", "exists", "different", "blocked"]}},
    "rows": {"type": "array", "items": {"type": "object", "properties": {
        "account_code": {"type": "string"}, "account_name": {"type": "string"}, "parent_code": {"type": ["string", "null"]}, "type": {"type": "string"}, "is_group": {"type": "boolean"}, "extra": {"type": "boolean"},
        "status": {"type": "string", "enum": ["new", "exists", "different", "blocked"], "description": "different: the code exists under another name (left as it is); blocked: its parent is missing"},
        "existing_name": {"type": ["string", "null"]}, "line": {"type": ["string", "null"], "description": "Statement line code it is mapped to"}}}}}}
paths["/chart-templates"] = {"get": op("Chart of accounts", "Industry chart templates", "chart-templates.apply", {"200": resp("Templates", data({"type": "array", "items": ref("ChartTemplate")}))}, opid="list_chart_templates")}
paths["/chart-templates/{template}"] = {"parameters": [TEMPLATE_KEY], "get": op("Chart of accounts", "What a template would add to this company", "chart-templates.apply", {"200": resp("Preview", data(ref("ChartTemplatePreview"))), **E404_422}, opid="show_chart_template")}
paths["/chart-templates/{template}/apply"] = {"parameters": [TEMPLATE_KEY], "post": op("Chart of accounts", "Add a template's accounts to this company", "chart-templates.apply",
    {"200": resp("Nothing to add, or a preview (dry_run)", data({"type": "object"})), "201": resp("Accounts added", data({"type": "object", "properties": {"created": {"type": "array", "items": {"type": "string"}}, "skipped": {"type": "integer"}}})), **E422},
    body={"type": "object", "properties": {"dry_run": {"type": "boolean", "default": False}}},
    desc="Adds the accounts the company lacks, parents first, mapped to statement lines; accounts that exist are never changed, so it is safe on a chart in use and can be repeated. Audited (CHART_TEMPLATE_APPLIED).", opid="apply_chart_template")}

# Recurring entries.
FREQ = ["daily", "weekly", "monthly", "quarterly", "yearly"]
schemas["RecurringEntry"] = {"type": "object", "properties": {
    "id": {"type": "integer"}, "name": {"type": "string"}, "status": {"type": "string", "enum": ["active", "paused", "finished"]},
    "frequency": {"type": "string", "enum": FREQ}, "interval": {"type": "integer", "description": "Every N days / weeks / months …"}, "day_of_month": {"type": ["integer", "null"], "description": "Monthly, quarterly and yearly: the day each entry is dated (31 = month end; short months are clamped)"},
    "start_date": {"type": "string", "format": "date"}, "end_date": {"type": ["string", "null"], "format": "date"}, "next_run_date": {"type": ["string", "null"], "format": "date", "description": "null once finished"},
    "max_runs": {"type": ["integer", "null"]}, "runs_count": {"type": "integer"}, "mode": {"type": "string", "enum": ["draft", "post"]}, "is_active": {"type": "boolean"},
    "voucher_type_id": {"type": ["integer", "null"]}, "reference": {"type": ["string", "null"]}, "description": {"type": ["string", "null"]}, "last_run_at": {"type": ["string", "null"], "format": "date-time"},
    "amount": ref("Money"), "lines": {"type": "array", "items": {"type": "object", "properties": {"chart_of_account_id": {"type": "integer"}, "account": {"type": ["string", "null"]}, "cost_center_id": {"type": ["integer", "null"]}, "debit": ref("Money"), "credit": ref("Money"), "description": {"type": ["string", "null"]}}}, "description": "In detail responses"}}}
rec_props = {"name": {"type": "string"}, "frequency": {"type": "string", "enum": FREQ}, "interval": {"type": "integer", "minimum": 1}, "day_of_month": {"type": "integer", "minimum": 1, "maximum": 31},
    "start_date": {"type": "string", "format": "date", "description": "Not in the past for a new template; locked once it has generated entries"}, "end_date": {"type": "string", "format": "date"}, "max_runs": {"type": "integer"},
    "mode": {"type": "string", "enum": ["draft", "post"], "description": "post: posted as the template's creator when they may post; otherwise (closed period, approval needed …) it stays a draft or goes for approval"},
    "voucher_type_id": {"type": "integer"}, "reference": {"type": "string"}, "description": {"type": "string"},
    "lines": {"type": "array", "minItems": 2, "items": {"type": "object", "required": ["chart_of_account_id"], "properties": {"chart_of_account_id": {"type": "integer"}, "cost_center_id": {"type": "integer"}, "debit": {"type": "number"}, "credit": {"type": "number"}, "description": {"type": "string"}}}}}
paths["/recurring-entries"] = {
    "get": op("Recurring entries", "Recurring entry templates", "recurring-entries.view", {"200": resp("Templates, next run first", data({"type": "array", "items": ref("RecurringEntry")}))}, opid="list_recurring_entries"),
    "post": op("Recurring entries", "Create a template", "recurring-entries.create", {"201": resp("Created", data(ref("RecurringEntry"))), **E422}, body={"type": "object", "required": ["name", "frequency", "start_date", "mode", "lines"], "properties": rec_props}, desc="The lines must balance (debits = credits, one side per line, active posting accounts). Entries are generated daily by `accounting:run-recurring`.", opid="create_recurring_entry")}
paths["/recurring-entries/{id}"] = {"parameters": [ID],
    "get": op("Recurring entries", "A template with its generated entries and upcoming dates", "recurring-entries.view", {"200": resp("Template", data({"type": "object", "properties": {"entry": ref("RecurringEntry"), "runs": {"type": "array", "items": {"type": "object"}}, "upcoming": {"type": "array", "items": {"type": "string", "format": "date"}}}})), **E404}, opid="show_recurring_entry"),
    "put": op("Recurring entries", "Change a template (PATCH also accepted)", "recurring-entries.update", {"200": resp("Updated", data(ref("RecurringEntry"))), **E404_422}, body={"type": "object", "required": ["name", "frequency", "start_date", "mode", "lines"], "properties": rec_props}, opid="update_recurring_entry"),
    "delete": op("Recurring entries", "Delete a template that has not generated anything", "recurring-entries.delete", {"204": resp("Deleted"), **E404_422}, desc="422 once it has generated entries: pause it instead.", opid="delete_recurring_entry")}
paths["/recurring-entries/{id}/pause"] = {"parameters": [ID], "post": op("Recurring entries", "Pause a template", "recurring-entries.update", {"200": resp("Paused", data(ref("RecurringEntry"))), **E404}, opid="pause_recurring_entry")}
paths["/recurring-entries/{id}/resume"] = {"parameters": [ID], "post": op("Recurring entries", "Resume a paused template", "recurring-entries.update", {"200": resp("Resumed", data(ref("RecurringEntry"))), **E404_422},
    body={"type": "object", "properties": {"skip_missed": {"type": "boolean", "default": True, "description": "Skip the occurrences missed while paused (default) or generate them"}}}, opid="resume_recurring_entry")}
paths["/recurring-entries/{id}/run"] = {"parameters": [ID], "post": op("Recurring entries", "Generate the next entry now", "recurring-entries.run",
    {"201": resp("Generated", data({"type": "object", "properties": {"run": {"type": "object", "properties": {"id": {"type": "integer"}, "run_date": {"type": "string", "format": "date"}, "status": {"type": "string", "enum": ["posted", "submitted", "draft", "failed"]}, "journal_entry_id": {"type": ["integer", "null"]}, "error": {"type": ["string", "null"]}}}, "entry": ref("RecurringEntry")}})), **E404_422},
    desc="Generates the next scheduled occurrence immediately, even if it is not due yet (dated on its scheduled day). 422 when the entry could not be created.", opid="run_recurring_entry")}

# Foreign-currency revaluation.
schemas["FxRevaluationRow"] = {"type": "object", "properties": {
    "chart_of_account_id": {"type": "integer"}, "account_code": {"type": "string"}, "account_name": {"type": "string"}, "currency_id": {"type": "integer"}, "currency_code": {"type": "string"},
    "foreign_balance": ref("Money"), "rate": {"type": "string", "description": "Closing rate used (8 decimals)"}, "carrying_base": ref("Money"), "revalued_base": ref("Money"),
    "adjustment": ref("Money")}}
schemas["FxRevaluation"] = {"type": "object", "properties": {
    "id": {"type": "integer"}, "as_of_date": {"type": "string", "format": "date"}, "total_gain": ref("Money"), "total_loss": ref("Money"),
    "journal_entry_id": {"type": "integer"}, "voucher_number": {"type": ["string", "null"]}, "reversal_entry_id": {"type": ["integer", "null"]}, "reversal_voucher_number": {"type": ["string", "null"]},
    "reversal_date": {"type": ["string", "null"], "format": "date"}, "notes": {"type": ["string", "null"]}, "gain_loss_account": {"type": "string", "description": "Only on the single-revaluation response"},
    "lines": {"type": "array", "items": {"type": "object", "properties": {"account": {"type": "string"}, "currency": {"type": ["string", "null"]}, "foreign_balance": ref("Money"), "rate": {"type": "string"}, "carrying_base": ref("Money"), "revalued_base": ref("Money"), "adjustment": ref("Money")}}}}}
schemas["ExchangeRate"] = {"type": "object", "properties": {"id": {"type": "integer"}, "currency_id": {"type": "integer"}, "currency": {"type": "string"}, "rate_date": {"type": "string", "format": "date"}, "rate": {"type": "string"}, "source": {"type": ["string", "null"]}}}
fx_rates_body = {"type": "object", "description": "Closing rate per currency id; omitted currencies use the latest dated rate on or before the date, else the currency's own rate", "additionalProperties": {"type": "number"}}
paths["/fx-revaluation"] = {
    "get": op("Currency revaluation", "Past revaluations and the dated exchange rates", "fx-revaluation.view", {"200": resp("Revaluations (newest first) and rates", data({"type": "object", "properties": {"revaluations": {"type": "array", "items": ref("FxRevaluation")}, "rates": {"type": "array", "items": ref("ExchangeRate")}}}))}, opid="list_fx_revaluations"),
    "post": op("Currency revaluation", "Post a revaluation", "fx-revaluation.run", {"201": resp("Posted", data(ref("FxRevaluation"))), **E422},
        body={"type": "object", "required": ["as_of_date", "gain_loss_account_id"], "properties": {"as_of_date": {"type": "string", "format": "date"}, "gain_loss_account_id": {"type": "integer", "description": "An active income or expense posting account"}, "rates": fx_rates_body,
            "auto_reverse": {"type": "boolean", "default": False}, "reversal_date": {"type": "string", "format": "date", "description": "After as_of_date (default: the next day); needs an open period"}, "notes": {"type": "string"}}},
        desc="Restates every foreign-currency asset and liability account to balance x closing rate with one adjusting entry in the base currency; the difference is an unrealised gain or loss. Running it again at the same rates adjusts nothing (422).", opid="post_fx_revaluation")}
paths["/fx-revaluation/preview"] = {"get": op("Currency revaluation", "What a revaluation would adjust (nothing is posted)", "fx-revaluation.view",
    {"200": resp("Plan", data({"type": "object", "properties": {"as_of_date": {"type": "string", "format": "date"}, "rows": {"type": "array", "items": ref("FxRevaluationRow")}, "total_gain": ref("Money"), "total_loss": ref("Money")}})), **E422},
    params=[{"name": "as_of_date", "in": "query", "required": True, "schema": {"type": "string", "format": "date"}}, {"name": "rates[<currency id>]", "in": "query", "schema": {"type": "number"}, "description": "Closing rate override per currency id"}], opid="preview_fx_revaluation")}
paths["/fx-revaluation/rates"] = {"post": op("Currency revaluation", "Save a dated exchange rate (replaces the rate of that currency and date)", "fx-revaluation.rates", {"201": resp("Saved", data(ref("ExchangeRate"))), **E422},
    body={"type": "object", "required": ["currency_id", "rate_date", "rate"], "properties": {"currency_id": {"type": "integer"}, "rate_date": {"type": "string", "format": "date"}, "rate": {"type": "number", "exclusiveMinimum": 0}, "source": {"type": "string"}}}, opid="save_exchange_rate")}
paths["/fx-revaluation/rates/{id}"] = {"parameters": [ID], "delete": op("Currency revaluation", "Remove a dated rate", "fx-revaluation.rates", {"204": resp("Removed"), **E404}, opid="delete_exchange_rate")}
paths["/fx-revaluation/{id}"] = {"parameters": [ID], "get": op("Currency revaluation", "One revaluation with its accounts", "fx-revaluation.view", {"200": resp("Revaluation", data(ref("FxRevaluation"))), **E404}, opid="show_fx_revaluation")}
paths["/fx-revaluation/{id}/reverse"] = {"parameters": [ID], "post": op("Currency revaluation", "Reverse a revaluation", "fx-revaluation.run", {"200": resp("Reversed", data(ref("FxRevaluation"))), **E404_422},
    body={"type": "object", "properties": {"reversal_date": {"type": "string", "format": "date", "description": "After the revaluation date (default: the next day)"}}}, opid="reverse_fx_revaluation")}

# Bank statements.
LINE_STATUS = ["unmatched", "matched", "created", "ignored"]
schemas["BankStatement"] = {"type": "object", "properties": {
    "id": {"type": "integer"}, "bank_account_id": {"type": "integer"}, "bank": {"type": ["string", "null"]}, "file_name": {"type": "string"},
    "from_date": {"type": ["string", "null"], "format": "date"}, "to_date": {"type": ["string", "null"], "format": "date"}, "closing_balance": {"type": ["string", "null"], "description": "From the file's last balance, or entered"},
    "lines_count": {"type": "integer"}, "unmatched_count": {"type": "integer"}, "reconciliation_id": {"type": ["integer", "null"]}}}
schemas["BankStatementLine"] = {"type": "object", "properties": {
    "id": {"type": "integer"}, "line_no": {"type": "integer"}, "txn_date": {"type": "string", "format": "date"}, "description": {"type": ["string", "null"]}, "reference": {"type": ["string", "null"]},
    "deposit": ref("Money"), "withdrawal": ref("Money"), "balance": {"type": ["string", "null"]}, "status": {"type": "string", "enum": LINE_STATUS},
    "journal_entry_id": {"type": ["integer", "null"]}, "voucher_number": {"type": ["string", "null"]}, "journal_status": {"type": ["string", "null"]}}}
paths["/bank-statements"] = {"get": op("Bank statements", "Imported statements", "bank-statements.view", {"200": resp("Statements, newest first", data({"type": "array", "items": ref("BankStatement")}))}, opid="list_bank_statements")}
paths["/bank-statements/import"] = {"post": op("Bank statements", "Import a statement (CSV or XLSX)", "bank-statements.import",
    {"201": resp("Imported", data(ref("BankStatement"))), "200": resp("Preview (dry_run)", {"type": "object"}), **E422},
    body={"type": "object", "required": ["file", "bank_account_id"], "properties": {"file": {"type": "string", "format": "binary"}, "bank_account_id": {"type": "integer", "description": "Must be linked to a chart of accounts account"},
        "dry_run": {"type": "boolean", "default": False}, "closing_balance": {"type": "number"}, "auto_match": {"type": "boolean", "default": False}}},
    desc="Columns are recognised by name: Date; Description / Narration; Reference; Withdrawal / Debit and Deposit / Credit, or one signed Amount; Balance. Transactions already imported (same date, amount, reference and description) are skipped; 422 when any row has an error (nothing is imported) or when nothing is new.", opid="import_bank_statement")}
paths["/bank-statements/import"]["post"]["requestBody"]["content"] = {"multipart/form-data": paths["/bank-statements/import"]["post"]["requestBody"]["content"]["application/json"]}
paths["/bank-statements/{id}"] = {"parameters": [ID],
    "get": op("Bank statements", "A statement with its transactions", "bank-statements.view", {"200": resp("Statement", data({"type": "object", "properties": {"statement": ref("BankStatement"), "lines": {"type": "array", "items": ref("BankStatementLine")}}})), **E404}, opid="show_bank_statement"),
    "put": op("Bank statements", "Set the closing balance (PATCH also accepted)", "bank-statements.match", {"200": resp("Saved", data(ref("BankStatement"))), **E404_422}, body={"type": "object", "required": ["closing_balance"], "properties": {"closing_balance": {"type": "number"}}}, opid="update_bank_statement"),
    "delete": op("Bank statements", "Delete a statement and release its matches", "bank-statements.match", {"204": resp("Deleted"), **E404_422}, desc="422 once it has been reconciled.", opid="delete_bank_statement")}
paths["/bank-statements/{id}/auto-match"] = {"parameters": [ID], "post": op("Bank statements", "Match every transaction that has exactly one ledger candidate", "bank-statements.match", {"200": resp("Matched count", {"type": "object"}), **E404}, desc="A candidate is a posted, unreconciled ledger line on the bank's account with the same amount, dated within `ACCOUNTING_BANK_MATCH_DAYS` (5). Ambiguous transactions are left for you.", opid="auto_match_bank_statement")}
paths["/bank-statements/{id}/reconcile"] = {"parameters": [ID], "post": op("Bank statements", "Reconcile the statement", "bank-statements.match", {"200": resp("Reconciled", {"type": "object"}), **E404_422}, desc="Every transaction must be matched, booked or ignored, and the closing balance known. Marks the matched ledger lines reconciled in a bank reconciliation dated at the statement end.", opid="reconcile_bank_statement")}
paths["/bank-statement-lines/{id}/candidates"] = {"parameters": [ID], "get": op("Bank statements", "Ledger lines that could be this transaction", "bank-statements.view", {"200": resp("Candidates", data({"type": "array", "items": {"type": "object"}})), **E404}, opid="bank_statement_line_candidates")}
paths["/bank-statement-lines/{id}/match"] = {"parameters": [ID], "post": op("Bank statements", "Match a transaction to a ledger line", "bank-statements.match", {"200": resp("Matched", data(ref("BankStatementLine"))), **E404_422}, body={"type": "object", "required": ["journal_entry_line_id"], "properties": {"journal_entry_line_id": {"type": "integer"}}}, opid="match_bank_statement_line")}
paths["/bank-statement-lines/{id}/unmatch"] = {"parameters": [ID], "post": op("Bank statements", "Release a matched or booked transaction", "bank-statements.match", {"200": resp("Unmatched", data(ref("BankStatementLine"))), **E404_422}, desc="An entry booked from the line stays in the ledger; only the link is released.", opid="unmatch_bank_statement_line")}
paths["/bank-statement-lines/{id}/ignore"] = {"parameters": [ID], "post": op("Bank statements", "Ignore (or restore) a transaction", "bank-statements.match", {"200": resp("Updated", data(ref("BankStatementLine"))), **E404_422}, body={"type": "object", "properties": {"ignored": {"type": "boolean", "default": True}}}, opid="ignore_bank_statement_line")}
paths["/bank-statement-lines/{id}/create-entry"] = {"parameters": [ID], "post": op("Bank statements", "Book a transaction as a journal entry", "bank-statements.match", {"201": resp("Booked", data({"type": "object", "properties": {"journal_entry_id": {"type": "integer"}, "status": {"type": "string", "enum": ["posted", "draft"]}, "line": ref("BankStatementLine")}})), **E404_422},
    body={"type": "object", "required": ["chart_of_account_id"], "properties": {"chart_of_account_id": {"type": "integer"}, "description": {"type": "string"}}},
    desc="A deposit debits the bank account and credits the chosen one; a withdrawal the reverse. Posted when possible; a closed period, approval or evidence requirement leaves a draft (the transaction is still linked).", opid="book_bank_statement_line")}

# Budgets.
BUDGET_STATUS = ["draft", "approved", "closed"]
schemas["Budget"] = {"type": "object", "properties": {
    "id": {"type": "integer"}, "name": {"type": "string"}, "status": {"type": "string", "enum": BUDGET_STATUS}, "start_date": {"type": "string", "format": "date"}, "end_date": {"type": "string", "format": "date"},
    "notes": {"type": ["string", "null"]}, "lines_count": {"type": ["integer", "null"], "description": "Amounts (account x month) in the list"}, "approved_at": {"type": ["string", "null"], "format": "date-time"},
    "lines": {"type": "array", "description": "Only on single-budget responses", "items": {"type": "object", "properties": {"chart_of_account_id": {"type": "integer"}, "cost_center_id": {"type": ["integer", "null"]}, "annual": ref("Money"), "amounts": {"type": "object", "additionalProperties": ref("Money"), "description": "Month (YYYY-MM) to amount"}}}}}}
schemas["BudgetReportRow"] = {"type": "object", "properties": {
    "account_id": {"type": "integer"}, "account_code": {"type": "string"}, "account_name": {"type": "string"}, "type": {"type": "string", "enum": ["INCOME", "EXPENSE"]},
    "budget": ref("Money"), "actual": ref("Money"), "variance": {"type": "string", "description": "Favourable when positive: income above plan, expense below it"},
    "used_percent": {"type": ["number", "null"]}, "status": {"type": "string", "enum": ["ok", "warning", "over", "behind", "unbudgeted"]},
    "monthly": {"type": "array", "items": {"type": "object", "properties": {"month": {"type": "string"}, "budget": ref("Money"), "actual": ref("Money")}}}}}
bud_props = {"name": {"type": "string"}, "start_date": {"type": "string", "format": "date", "description": "Moved to the first day of its month"}, "end_date": {"type": "string", "format": "date", "description": "Moved to the last day of its month; at most 24 months"}, "notes": {"type": "string"},
    "lines": {"type": "array", "items": {"type": "object", "required": ["chart_of_account_id"], "properties": {"chart_of_account_id": {"type": "integer", "description": "An active income or expense posting account"}, "cost_center_id": {"type": "integer"},
        "annual": {"type": "number", "description": "Spread evenly over the months (used when amounts is empty)"}, "amounts": {"type": "object", "additionalProperties": {"type": "number"}, "description": "Month (YYYY-MM) to amount"}}}},
    "from_actuals": {"type": "object", "description": "Also add every account that had income or expenses in the same months a year earlier", "properties": {"uplift_percent": {"type": "number", "default": 0}}}}
paths["/budgets"] = {
    "get": op("Budgets", "Budgets", "budgets.view", {"200": resp("Budgets, newest period first", data({"type": "array", "items": ref("Budget")}))}, opid="list_budgets"),
    "post": op("Budgets", "Create a draft budget", "budgets.create", {"201": resp("Created", data(ref("Budget"))), **E422}, body={"type": "object", "required": ["name", "start_date", "end_date"], "properties": bud_props}, desc="Needs lines, `from_actuals`, or both.", opid="create_budget")}
paths["/budgets/{id}"] = {"parameters": [ID],
    "get": op("Budgets", "Budget against actual", "budgets.view", {"200": resp("The report", data({"type": "object", "properties": {"budget": ref("Budget"), "date_from": {"type": "string", "format": "date"}, "date_to": {"type": "string", "format": "date"}, "months": {"type": "array", "items": {"type": "string"}}, "rows": {"type": "array", "items": ref("BudgetReportRow")}, "totals": {"type": "object", "additionalProperties": ref("Money")}}})), **E404},
        params=[{"name": "date_from", "in": "query", "schema": {"type": "string", "format": "date"}}, {"name": "date_to", "in": "query", "schema": {"type": "string", "format": "date"}}, {"name": "cost_center_id", "in": "query", "schema": {"type": "integer"}}],
        desc="Actuals are posted entries in base currency (closing entries excluded), in the account's natural direction.", opid="show_budget"),
    "put": op("Budgets", "Change a draft budget (PATCH also accepted)", "budgets.update", {"200": resp("Updated", data(ref("Budget"))), **E404_422}, body={"type": "object", "required": ["name", "start_date", "end_date"], "properties": bud_props}, opid="update_budget"),
    "delete": op("Budgets", "Delete a budget that is not approved", "budgets.delete", {"204": resp("Deleted"), **E404_422}, opid="delete_budget")}
paths["/budgets/{id}/approve"] = {"parameters": [ID], "post": op("Budgets", "Approve a draft budget", "budgets.approve", {"200": resp("Approved", data(ref("Budget"))), **E404_422}, desc="Only one approved budget may cover a month. With `ACCOUNTING_BUDGET_CONTROL=block` an approved budget refuses postings that take a budgeted expense account past its cumulative budget (`budgets.override` may still post).", opid="approve_budget")}
paths["/budgets/{id}/reopen"] = {"parameters": [ID], "post": op("Budgets", "Reopen an approved or closed budget as a draft", "budgets.update", {"200": resp("Reopened", data(ref("Budget"))), **E404_422}, opid="reopen_budget")}
paths["/budgets/{id}/close"] = {"parameters": [ID], "post": op("Budgets", "Close an approved budget", "budgets.approve", {"200": resp("Closed", data(ref("Budget"))), **E404_422}, opid="close_budget")}
paths["/budgets/{id}/copy"] = {"parameters": [ID], "post": op("Budgets", "Copy as a draft, optionally shifted and raised", "budgets.create", {"201": resp("Copy", data(ref("Budget"))), **E404_422},
    body={"type": "object", "required": ["name"], "properties": {"name": {"type": "string"}, "start_date": {"type": "string", "format": "date", "description": "New first month (the budget is shifted)"}, "uplift_percent": {"type": "number"}}}, opid="copy_budget")}
paths["/budgets/{id}/export/{format}"] = {"parameters": [ID, {"name": "format", "in": "path", "required": True, "schema": {"type": "string", "enum": ["csv", "xlsx", "pdf"]}}], "get": op("Budgets", "Export budget against actual", "budgets.view", {"200": {"description": "The file"}, **E404}, opid="export_budget")}

# Fixed assets.
fa_props = {"code": {"type": "string"}, "name": {"type": "string"}, "category": {"type": "string", "nullable": True}, "description": {"type": "string", "nullable": True},
            "acquisition_date": {"type": "string", "format": "date"}, "in_service_date": {"type": "string", "format": "date", "description": "Depreciation starts in this month (default: acquisition date)."},
            "cost": {"type": "number"}, "salvage_value": {"type": "number"}, "useful_life_months": {"type": "integer", "minimum": 1}, "method": {"type": "string", "enum": ["straight_line", "declining_balance"]},
            "declining_rate": {"type": "number", "nullable": True, "description": "Annual percent for declining balance (default: double the straight-line rate)."},
            "asset_account_id": {"type": "integer"}, "accumulated_account_id": {"type": "integer"}, "expense_account_id": {"type": "integer"},
            "offset_account_id": {"type": "integer", "description": "On create: books the purchase (debit the asset account, credit this account)."}}
schemas["FixedAsset"] = {"type": "object", "properties": {**fa_props, "id": {"type": "integer"}, "status": {"type": "string", "enum": ["active", "disposed"]}, "accumulated_depreciation": {"type": "string"}, "book_value": {"type": "string"}, "locked": {"type": "boolean"}, "disposed_at": {"type": "string", "format": "date", "nullable": True}, "disposal_proceeds": {"type": "string", "nullable": True}, "disposal_gain_loss": {"type": "string", "nullable": True}}}
paths["/fixed-assets"] = {
    "get": op("Fixed assets", "Fixed asset register at a date", "fixed-assets.view", {"200": resp("Register: rows, totals and the ledger-against-register difference", data({"type": "object"})), **E422}, params=[{"name": "as_of", "in": "query", "schema": {"type": "string", "format": "date"}}, {"name": "status", "in": "query", "schema": {"type": "string", "enum": ["active", "disposed"]}}], opid="list_fixed_assets"),
    "post": op("Fixed assets", "Register an asset", "fixed-assets.create", {"201": resp("Created", data(ref("FixedAsset"))), **E422}, body={"type": "object", "required": ["code", "name", "acquisition_date", "cost", "useful_life_months", "method", "asset_account_id", "accumulated_account_id", "expense_account_id"], "properties": fa_props}, opid="create_fixed_asset")}
paths["/fixed-assets/{id}"] = {"parameters": [ID],
    "get": op("Fixed assets", "An asset with its depreciation history", "fixed-assets.view", {"200": resp("The asset", data({"type": "object", "properties": {"asset": ref("FixedAsset"), "depreciation": {"type": "array", "items": {"type": "object"}}}})), **E404}, opid="get_fixed_asset"),
    "put": op("Fixed assets", "Change an asset (PATCH also accepted)", "fixed-assets.update", {"200": resp("Updated", data(ref("FixedAsset"))), **E404_422}, body={"type": "object", "properties": fa_props}, desc="Cost, life, method, dates and accounts are locked once the asset has an acquisition or depreciation entry.", opid="update_fixed_asset"),
    "delete": op("Fixed assets", "Delete an asset without entries", "fixed-assets.delete", {"204": resp("Deleted"), **E404_422}, opid="delete_fixed_asset")}
paths["/fixed-assets/{id}/dispose"] = {"parameters": [ID], "post": op("Fixed assets", "Sell or scrap an asset", "fixed-assets.dispose", {"200": resp("Disposed", data(ref("FixedAsset"))), **E404_422}, body={"type": "object", "required": ["disposal_date", "gain_loss_account_id"], "properties": {"disposal_date": {"type": "string", "format": "date"}, "proceeds": {"type": "number"}, "proceeds_account_id": {"type": "integer"}, "gain_loss_account_id": {"type": "integer"}, "notes": {"type": "string"}}}, desc="Brings depreciation up to the month before the disposal date, removes cost and accumulated depreciation, books the proceeds and the gain or loss.", opid="dispose_fixed_asset")}
paths["/fixed-assets/depreciation"] = {
    "get": op("Fixed assets", "Preview the depreciation due", "fixed-assets.view", {"200": resp("Months and amounts per asset", data({"type": "object"})), **E422}, params=[{"name": "up_to", "in": "query", "schema": {"type": "string", "format": "date"}, "description": "Default: end of last month."}], opid="preview_depreciation"),
    "post": op("Fixed assets", "Book the depreciation due", "fixed-assets.depreciate", {"200": resp("Booked", data({"type": "object", "properties": {"months": {"type": "integer"}, "total": {"type": "string"}, "entries": {"type": "array", "items": {"type": "integer"}}}})), **E422}, body={"type": "object", "required": ["up_to"], "properties": {"up_to": {"type": "string", "format": "date"}}}, desc="One journal entry per month, dated on the month's last day; a month already booked for an asset is never booked twice.", opid="run_depreciation")}
paths["/fixed-assets/export/{format}"] = {"parameters": [{"name": "format", "in": "path", "required": True, "schema": {"type": "string", "enum": ["csv", "xlsx", "pdf"]}}], "get": op("Fixed assets", "Export the register", "fixed-assets.view", {"200": {"description": "The file"}, **E}, params=[{"name": "as_of", "in": "query", "schema": {"type": "string", "format": "date"}}], opid="export_fixed_assets")}

# Inventory.
item_props = {"sku": {"type": "string"}, "name": {"type": "string"}, "unit": {"type": "string"}, "category": {"type": "string", "nullable": True}, "reorder_level": {"type": "number"},
              "inventory_account_id": {"type": "integer"}, "cogs_account_id": {"type": "integer"}, "is_active": {"type": "boolean"}}
schemas["InventoryItem"] = {"type": "object", "properties": {**item_props, "id": {"type": "integer"}, "on_hand_quantity": {"type": "string"}, "on_hand_value": {"type": "string"}}}
schemas["Warehouse"] = {"type": "object", "properties": {"id": {"type": "integer"}, "code": {"type": "string"}, "name": {"type": "string"}, "address": {"type": "string", "nullable": True}, "is_active": {"type": "boolean"}}}
wh_body = {"type": "object", "required": ["code", "name"], "properties": {"code": {"type": "string"}, "name": {"type": "string"}, "address": {"type": "string"}, "is_active": {"type": "boolean"}}}
paths["/inventory"] = {"get": op("Inventory", "Stock valuation at a date", "inventory.view", {"200": resp("Items with quantity, average cost and value per warehouse, totals and the ledger-against-stock difference", data({"type": "object"})), **E422}, params=[{"name": "as_of", "in": "query", "schema": {"type": "string", "format": "date"}}, {"name": "warehouse_id", "in": "query", "schema": {"type": "integer"}}], opid="stock_valuation")}
paths["/inventory/export/{format}"] = {"parameters": [{"name": "format", "in": "path", "required": True, "schema": {"type": "string", "enum": ["csv", "xlsx", "pdf"]}}], "get": op("Inventory", "Export the stock valuation", "inventory.view", {"200": {"description": "The file"}, **E}, opid="export_stock_valuation")}
paths["/inventory/items"] = {"post": op("Inventory", "Create an item", "inventory.manage", {"201": resp("Created", data(ref("InventoryItem"))), **E422}, body={"type": "object", "required": ["sku", "name", "inventory_account_id", "cogs_account_id"], "properties": item_props}, opid="create_inventory_item")}
paths["/inventory/items/{id}"] = {"parameters": [ID],
    "get": op("Inventory", "An item with its stock card", "inventory.view", {"200": resp("Item and card", data({"type": "object", "properties": {"item": ref("InventoryItem"), "card": {"type": "array", "items": {"type": "object"}}}})), **E404}, opid="get_inventory_item"),
    "put": op("Inventory", "Change an item (PATCH also accepted)", "inventory.manage", {"200": resp("Updated", data(ref("InventoryItem"))), **E404_422}, body={"type": "object", "properties": item_props}, desc="The accounts cannot change once the item has stock movements.", opid="update_inventory_item"),
    "delete": op("Inventory", "Delete an item without movements", "inventory.manage", {"204": resp("Deleted"), **E404_422}, opid="delete_inventory_item")}
paths["/inventory/warehouses"] = {
    "get": op("Inventory", "Warehouses", "inventory.view", {"200": resp("Warehouses", data({"type": "array", "items": ref("Warehouse")}))}, opid="list_warehouses"),
    "post": op("Inventory", "Add a warehouse", "inventory.manage", {"201": resp("Created", data(ref("Warehouse"))), **E422}, body=wh_body, opid="create_warehouse")}
paths["/inventory/warehouses/{id}"] = {"parameters": [ID],
    "put": op("Inventory", "Change a warehouse (PATCH also accepted)", "inventory.manage", {"200": resp("Updated", data(ref("Warehouse"))), **E404_422}, body=wh_body, opid="update_warehouse"),
    "delete": op("Inventory", "Delete a warehouse without movements", "inventory.manage", {"204": resp("Deleted"), **E404_422}, opid="delete_warehouse")}
paths["/inventory/movements"] = {
    "get": op("Inventory", "The latest 200 stock movements", "inventory.view", {"200": resp("Movements", data({"type": "array", "items": {"type": "object"}})), **E422}, params=[{"name": "item_id", "in": "query", "schema": {"type": "integer"}}, {"name": "warehouse_id", "in": "query", "schema": {"type": "integer"}}, {"name": "type", "in": "query", "schema": {"type": "string", "enum": ["receipt", "issue", "adjustment", "transfer_in", "transfer_out"]}}], opid="list_stock_movements"),
    "post": op("Inventory", "Receive, issue, adjust or transfer stock", "inventory.move", {"201": resp("The movement(s); a transfer returns the out and in lines", data({"type": "array", "items": {"type": "object"}})), **E422}, body={"type": "object", "required": ["type", "item_id", "warehouse_id", "movement_date", "quantity"], "properties": {"type": {"type": "string", "enum": ["receipt", "issue", "adjustment", "transfer"]}, "item_id": {"type": "integer"}, "warehouse_id": {"type": "integer"}, "to_warehouse_id": {"type": "integer", "description": "Transfers."}, "movement_date": {"type": "string", "format": "date"}, "quantity": {"type": "number", "description": "Negative only for an adjustment that removes stock."}, "unit_cost": {"type": "number", "description": "Required for a receipt; an adjustment that adds stock defaults to the average cost."}, "offset_account_id": {"type": "integer", "description": "Receipt: paid from / owed to. Adjustment: gain/loss account. Issue: cost account (default the item's)."}, "reference": {"type": "string"}, "notes": {"type": "string"}}}, desc="Booked with its journal entry in one transaction (not for transfers). Stock never goes negative, in total or in a warehouse; issues leave at the moving average cost.", opid="move_stock")}

# Payroll.
emp_props = {"code": {"type": "string"}, "name": {"type": "string"}, "national_id": {"type": "string", "nullable": True}, "designation": {"type": "string", "nullable": True}, "cost_center_id": {"type": "integer", "nullable": True},
             "join_date": {"type": "string", "format": "date"}, "leave_date": {"type": "string", "format": "date", "nullable": True}, "base_salary": {"type": "number", "description": "Monthly basic salary; may be left out when salary_grade_id is given (the grade's salary is used). Changing it records a salary revision (effective_from, reason)."}, "salary_grade_id": {"type": "integer", "nullable": True}, "effective_from": {"type": "string", "format": "date", "description": "When a changed base_salary applies from (default today); a date in the past is paid as arrears."}, "reason": {"type": "string"}, "withhold_tax": {"type": "boolean"}, "overtime_eligible": {"type": "boolean", "description": "Paid overtime hours from the attendance sheet."}, "schemes": {"type": "array", "items": {"type": "integer"}, "description": "Replaces the contribution schemes of the employee when sent (schemes that apply to all need not be listed)."},
             "bank_name": {"type": "string"}, "bank_account": {"type": "string"}, "is_active": {"type": "boolean"},
             "components": {"type": "array", "description": "Replaces the allowances and deductions of the employee when sent.", "items": {"type": "object", "required": ["pay_component_id"], "properties": {"pay_component_id": {"type": "integer"}, "value": {"type": "number", "nullable": True, "description": "Overrides the component's value."}}}}}
comp_props = {"code": {"type": "string"}, "name": {"type": "string"}, "kind": {"type": "string", "enum": ["earning", "deduction"]}, "method": {"type": "string", "enum": ["fixed", "percent_of_basic", "quantity_rate"]}, "value": {"type": "number", "description": "An amount, a percent of the basic, or the quantity (litres) for quantity_rate."},
              "rate": {"type": "number", "description": "Price per unit for quantity_rate (required then): amount = quantity x rate. Change it and everybody follows on the next calculation."}, "unit": {"type": "string", "nullable": True},
              "taxable": {"type": "boolean"}, "account_id": {"type": "integer", "description": "An expense account for an earning, a liability account for a deduction."}, "is_active": {"type": "boolean"}}
schemas["Employee"] = {"type": "object", "properties": {**emp_props, "id": {"type": "integer"}}}
schemas["PayComponent"] = {"type": "object", "properties": {**comp_props, "id": {"type": "integer"}}}
schemas["PayrollRun"] = {"type": "object", "properties": {"id": {"type": "integer"}, "period_month": {"type": "string", "example": "2026-10"}, "status": {"type": "string", "enum": ["draft", "posted", "paid", "void"]}, "gross": {"type": "string"}, "deductions": {"type": "string"}, "tax": {"type": "string"}, "net": {"type": "string"},
                                          "journal_entry_id": {"type": "integer", "nullable": True}, "payment_entry_id": {"type": "integer", "nullable": True}, "posted_on": {"type": "string", "format": "date", "nullable": True}, "paid_on": {"type": "string", "format": "date", "nullable": True}, "employees": {"type": "integer"}}}
run_ok = lambda text: {"200": resp(text, data(ref("PayrollRun"))), **E404_422}
paths["/payroll"] = {"get": op("Payroll", "Payroll runs, newest first", "payroll.view", {"200": resp("Runs", data({"type": "array", "items": ref("PayrollRun")})), **E}, opid="list_payroll_runs")}
paths["/payroll/runs"] = {"post": op("Payroll", "Start a payroll run for a month", "payroll.run", {"201": resp("Created with payslips worked out", data(ref("PayrollRun"))), **E422}, body={"type": "object", "required": ["period_month"], "properties": {"period_month": {"type": "string", "format": "date"}, "notes": {"type": "string"}}}, desc="One run per month (a voided run does not count). Joiners and leavers are paid for the days employed; flagged employees have income tax withheld from the configured slabs.", opid="create_payroll_run")}
paths["/payroll/runs/{id}"] = {"parameters": [ID],
    "get": op("Payroll", "A run with its payslips and their lines", "payroll.view", {"200": resp("The run", data({"type": "object", "properties": {"run": ref("PayrollRun"), "payslips": {"type": "array", "items": {"type": "object"}}}})), **E404}, opid="get_payroll_run"),
    "delete": op("Payroll", "Delete a draft run", "payroll.run", {"204": resp("Deleted"), **E404_422}, opid="delete_payroll_run")}
paths["/payroll/runs/{id}/recalculate"] = {"parameters": [ID], "post": op("Payroll", "Work the payslips out again (draft runs)", "payroll.run", run_ok("Recalculated"), opid="recalculate_payroll_run")}
paths["/payroll/runs/{id}/post"] = {"parameters": [ID], "post": op("Payroll", "Post the run to the books", "payroll.post", run_ok("Posted"), body={"type": "object", "properties": {"payable_account_id": {"type": "integer", "description": "Default: the net payable account in config."}, "date": {"type": "string", "format": "date", "description": "Default: the month's last day."}}}, desc="One entry in the payroll module: expense for basic pay and earnings, liabilities for deductions and tax, and the net pay owed to employees.", opid="post_payroll_run")}
paths["/payroll/runs/{id}/pay"] = {"parameters": [ID], "post": op("Payroll", "Pay the net salaries", "payroll.post", run_ok("Paid"), body={"type": "object", "required": ["account_id"], "properties": {"account_id": {"type": "integer", "description": "The bank or cash account paid from."}, "date": {"type": "string", "format": "date"}}}, opid="pay_payroll_run")}
paths["/payroll/runs/{id}/void"] = {"parameters": [ID], "post": op("Payroll", "Void a posted or paid run", "payroll.void", run_ok("Voided"), desc="Reverses the payment and the salary entry.", opid="void_payroll_run")}
paths["/payroll/runs/{id}/payslips/{payslip}"] = {"parameters": [ID, {"name": "payslip", "in": "path", "required": True, "schema": {"type": "integer"}}], "get": op("Payroll", "One payslip", "payroll.view", {"200": resp("Payslip with the employee and lines", data({"type": "object"})), **E404}, opid="get_payslip")}
paths["/payroll/employees"] = {
    "get": op("Payroll", "Employees", "payroll.view", {"200": resp("Employees", data({"type": "array", "items": ref("Employee")})), **E}, opid="list_employees"),
    "post": op("Payroll", "Add an employee", "payroll.manage", {"201": resp("Created", data(ref("Employee"))), **E422}, body={"type": "object", "required": ["code", "name", "join_date", "base_salary"], "properties": emp_props}, opid="create_employee")}
paths["/payroll/employees/{id}"] = {"parameters": [ID],
    "get": op("Payroll", "An employee with their allowances and deductions", "payroll.view", {"200": resp("The employee", data(ref("Employee"))), **E404}, opid="get_employee"),
    "put": op("Payroll", "Change an employee (PATCH also accepted)", "payroll.manage", {"200": resp("Updated", data(ref("Employee"))), **E404_422}, body={"type": "object", "required": ["code", "name", "join_date", "base_salary"], "properties": emp_props}, opid="update_employee"),
    "delete": op("Payroll", "Delete an employee without payslips", "payroll.manage", {"204": resp("Deleted"), **E404_422}, opid="delete_employee")}
paths["/payroll/components"] = {
    "get": op("Payroll", "Allowances and deductions", "payroll.view", {"200": resp("Components", data({"type": "array", "items": ref("PayComponent")})), **E}, opid="list_pay_components"),
    "post": op("Payroll", "Add an allowance or deduction", "payroll.manage", {"201": resp("Created", data(ref("PayComponent"))), **E422}, body={"type": "object", "required": ["code", "name", "kind", "method", "value", "account_id"], "properties": comp_props}, opid="create_pay_component")}
paths["/payroll/components/{id}"] = {"parameters": [ID],
    "put": op("Payroll", "Change a component (PATCH also accepted)", "payroll.manage", {"200": resp("Updated", data(ref("PayComponent"))), **E404_422}, body={"type": "object", "required": ["code", "name", "kind", "method", "value", "account_id"], "properties": comp_props}, opid="update_pay_component"),
    "delete": op("Payroll", "Delete a component that is not in use", "payroll.manage", {"204": resp("Deleted"), **E404_422}, opid="delete_pay_component")}

# Payroll structure: grades, bulk changes, salary revisions, arrears.
grade_props = {"code": {"type": "string"}, "name": {"type": "string"}, "base_salary": {"type": "number"}, "is_active": {"type": "boolean"},
               "components": {"type": "array", "description": "Replaces the allowances and deductions of the grade when sent.", "items": {"type": "object", "required": ["pay_component_id"], "properties": {"pay_component_id": {"type": "integer"}, "value": {"type": "number", "nullable": True}}}}}
schemas["SalaryGrade"] = {"type": "object", "properties": {**grade_props, "id": {"type": "integer"}, "employees": {"type": "integer"}}}
schemas["PayrollArrear"] = {"type": "object", "properties": {"id": {"type": "integer"}, "employee_id": {"type": "integer"}, "employee_code": {"type": "string"}, "employee_name": {"type": "string"}, "from_month": {"type": "string", "example": "2026-01"}, "to_month": {"type": "string"}, "payment_month": {"type": "string"},
    "amount": {"type": "string"}, "status": {"type": "string", "enum": ["draft", "approved", "included", "cancelled"]}, "payroll_run_id": {"type": "integer", "nullable": True}, "notes": {"type": "string", "nullable": True},
    "months": {"type": "array", "items": {"type": "object", "properties": {"month": {"type": "string"}, "paid": {"type": "string"}, "due": {"type": "string"}, "difference": {"type": "string"}}}}}}
who_props = {"employee_ids": {"type": "array", "items": {"type": "integer"}, "description": "Employees to change; when empty, everybody on salary_grade_id, else every active employee."}, "salary_grade_id": {"type": "integer"}}
revision_props = {"mode": {"type": "string", "enum": ["percent", "increase", "set"], "description": "percent = raise by value %, increase = add value, set = everybody to value."}, "value": {"type": "number"}, "effective_from": {"type": "string", "format": "date"}, "reason": {"type": "string"}, "round_to": {"type": "integer", "description": "Round new salaries to the nearest multiple (default 1)."}, **who_props}
arrears_props = {"from_month": {"type": "string", "format": "date", "description": "First month the arrears cover."}, "payment_month": {"type": "string", "format": "date", "description": "Month of the payroll run that pays them; the arrears cover the months before it."}, "notes": {"type": "string"}, **who_props}
changed = lambda text: {"200": resp(text, {"type": "object", "properties": {"message": {"type": "string"}, "data": {"type": "object", "properties": {"changed": {"type": "integer"}}}}}), **E422}
paths["/payroll/grades"] = {
    "get": op("Payroll", "Salary grades", "payroll.view", {"200": resp("Grades", data({"type": "array", "items": ref("SalaryGrade")})), **E}, opid="list_salary_grades"),
    "post": op("Payroll", "Add a salary grade", "payroll.manage", {"201": resp("Created", data(ref("SalaryGrade"))), **E422}, body={"type": "object", "required": ["code", "name", "base_salary"], "properties": grade_props}, desc="A grade is a basic salary with its allowances. Employees on it follow it: change an allowance of the grade and everybody on it is paid the new amount in the next calculation.", opid="create_salary_grade")}
paths["/payroll/grades/{grade}"] = {"parameters": [{"name": "grade", "in": "path", "required": True, "schema": {"type": "integer"}}],
    "get": op("Payroll", "A grade with its allowances", "payroll.view", {"200": resp("The grade", data(ref("SalaryGrade"))), **E404}, opid="get_salary_grade"),
    "put": op("Payroll", "Change a grade (PATCH also accepted)", "payroll.manage", {"200": resp("Updated", data(ref("SalaryGrade"))), **E404_422}, body={"type": "object", "required": ["code", "name", "base_salary"], "properties": grade_props}, opid="update_salary_grade"),
    "delete": op("Payroll", "Delete a grade nobody is on", "payroll.manage", {"204": resp("Deleted"), **E404_422}, opid="delete_salary_grade")}
paths["/payroll/grades/{grade}/assign"] = {"parameters": [{"name": "grade", "in": "path", "required": True, "schema": {"type": "integer"}}],
    "post": op("Payroll", "Put a group of employees on the grade", "payroll.manage", {**changed("Moved"), "404": {"$ref": "#/components/responses/NotFound"}}, body={"type": "object", "properties": {"employee_ids": who_props["employee_ids"], "apply_salary": {"type": "boolean", "description": "Also move their salary to the grade's (recorded as a revision)."}, "effective_from": {"type": "string", "format": "date"}}}, opid="assign_salary_grade")}
paths["/payroll/bulk/components"] = {"post": op("Payroll", "Give a component to a group of employees, or take it away", "payroll.manage", changed("Done"), body={"type": "object", "required": ["pay_component_id", "mode"], "properties": {"pay_component_id": {"type": "integer"}, "mode": {"type": "string", "enum": ["assign", "remove"]}, "value": {"type": "number", "description": "Employees' own value; blank keeps what they have (or the component's)."}, **who_props}}, opid="bulk_pay_component")}
paths["/payroll/employees/{id}/revisions"] = {"parameters": [ID],
    "get": op("Payroll", "The salary history of an employee", "payroll.view", {"200": resp("Revisions, newest first", data({"type": "array", "items": {"type": "object", "properties": {"id": {"type": "integer"}, "effective_from": {"type": "string", "format": "date"}, "old_salary": {"type": "string"}, "new_salary": {"type": "string"}, "reason": {"type": "string", "nullable": True}}}})), **E404}, opid="list_salary_revisions"),
    "post": op("Payroll", "Revise an employee's salary from a date", "payroll.manage", {"201": resp("Revised; returns the history", data({"type": "array", "items": {"type": "object"}})), **E404_422}, body={"type": "object", "required": ["new_salary", "effective_from"], "properties": {"new_salary": {"type": "number"}, "effective_from": {"type": "string", "format": "date"}, "reason": {"type": "string"}}}, desc="A date in the middle of a month pays the old salary up to the day before and the new one from that day. A date in the past is paid with arrears.", opid="revise_salary")}
paths["/payroll/revisions/preview"] = {"post": op("Payroll", "Preview a raise for a group (nothing is saved)", "payroll.manage", {"200": resp("Old and new salary of each employee", data({"type": "array", "items": {"type": "object"}})), **E422}, body={"type": "object", "required": ["mode", "value", "effective_from"], "properties": revision_props}, opid="preview_salary_revisions")}
paths["/payroll/revisions"] = {"post": op("Payroll", "Apply a raise to a group", "payroll.manage", {**changed("Applied"), "422": {"$ref": "#/components/responses/UnprocessableEntity"}}, body={"type": "object", "required": ["mode", "value", "effective_from"], "properties": revision_props}, desc="Every employee whose salary changes gets a revision; the salary on the employee becomes the new one.", opid="apply_salary_revisions")}
paths["/payroll/arrears"] = {
    "get": op("Payroll", "The arrears register with totals", "payroll.view", {"200": resp("Arrears and totals by status and by month of payment", {"type": "object", "properties": {"data": {"type": "array", "items": ref("PayrollArrear")}, "totals": {"type": "object"}}}), **E}, params=[{"name": "status", "in": "query", "schema": {"type": "string", "enum": ["draft", "approved", "included", "cancelled"]}}, {"name": "payment_month", "in": "query", "schema": {"type": "string", "format": "date"}}], opid="list_payroll_arrears"),
    "post": op("Payroll", "Create arrears (drafts) from the posted payslips", "payroll.run", {"201": resp("Created, one per employee owed something", data({"type": "array", "items": ref("PayrollArrear")})), **E422}, body={"type": "object", "required": ["from_month", "payment_month"], "properties": arrears_props}, desc="For each month from from_month to the month before payment_month that has a posted or paid payslip, the basic pay due (from the salary history) less the basic paid; allowances that are a percent of the basic follow. Months already claimed are skipped. Approve them to have the payroll run of payment_month pay them.", opid="create_payroll_arrears")}
paths["/payroll/arrears/preview"] = {"post": op("Payroll", "Work arrears out without saving", "payroll.run", {"200": resp("One row per employee owed something, with the months behind it", data({"type": "array", "items": {"type": "object"}})), **E422}, body={"type": "object", "required": ["from_month", "payment_month"], "properties": arrears_props}, opid="preview_payroll_arrears")}
paths["/payroll/arrears/approve-all"] = {"post": op("Payroll", "Approve every arrears draft", "payroll.post", {"200": resp("Approved", {"type": "object", "properties": {"message": {"type": "string"}, "data": {"type": "object", "properties": {"approved": {"type": "integer"}}}}}), **E}, body={"type": "object", "properties": {"payment_month": {"type": "string", "format": "date"}}}, opid="approve_all_payroll_arrears")}
paths["/payroll/arrears/{id}"] = {"parameters": [ID], "get": op("Payroll", "One arrears record with its months", "payroll.view", {"200": resp("The arrears", data(ref("PayrollArrear"))), **E404}, opid="get_payroll_arrear")}
paths["/payroll/arrears/{id}/approve"] = {"parameters": [ID], "post": op("Payroll", "Approve arrears", "payroll.post", {"200": resp("Approved", data(ref("PayrollArrear"))), **E404_422}, desc="Approved arrears are paid by the next payroll run of the payment month (or later) as an Arrears line, taxed as if paid in the months they belong to.", opid="approve_payroll_arrear")}
paths["/payroll/arrears/{id}/cancel"] = {"parameters": [ID], "post": op("Payroll", "Cancel arrears not yet in a run", "payroll.post", {"200": resp("Cancelled", data(ref("PayrollArrear"))), **E404_422}, opid="cancel_payroll_arrear")}

# Payroll operations: attendance, leave, loans, contribution schemes, bank file.
leave_type_props = {"code": {"type": "string"}, "name": {"type": "string"}, "is_paid": {"type": "boolean", "description": "Unpaid leave comes off the salary by the day."}, "annual_days": {"type": "number", "description": "Yearly entitlement; 0 = not limited."}, "is_active": {"type": "boolean"}}
schemas["LeaveType"] = {"type": "object", "properties": {**leave_type_props, "id": {"type": "integer"}}}
schemas["Loan"] = {"type": "object", "properties": {"id": {"type": "integer"}, "employee_id": {"type": "integer"}, "employee_code": {"type": "string"}, "employee_name": {"type": "string"}, "kind": {"type": "string", "enum": ["loan", "advance"]}, "principal": {"type": "string"}, "installments": {"type": "integer"},
    "start_month": {"type": "string", "example": "2026-10"}, "issued_on": {"type": "string", "format": "date", "nullable": True}, "status": {"type": "string", "enum": ["draft", "active", "closed", "cancelled"]}, "outstanding": {"type": "string"}, "journal_entry_id": {"type": "integer", "nullable": True}, "notes": {"type": "string", "nullable": True},
    "schedule": {"type": "array", "items": {"type": "object", "properties": {"id": {"type": "integer"}, "due_month": {"type": "string"}, "amount": {"type": "string"}, "status": {"type": "string", "enum": ["scheduled", "included", "cancelled"]}, "payroll_run_id": {"type": "integer", "nullable": True}}}}}}
scheme_props = {"code": {"type": "string"}, "name": {"type": "string"}, "base": {"type": "string", "enum": ["basic", "gross", "fixed"]}, "employee_rate": {"type": "number", "description": "Percent taken from the employee."}, "employer_rate": {"type": "number", "description": "Percent the employer adds."},
    "employee_fixed": {"type": "number"}, "employer_fixed": {"type": "number"}, "ceiling": {"type": "number", "nullable": True, "description": "The most of the base that counts each month."}, "employee_account_id": {"type": "integer", "nullable": True, "description": "Liability account the employees' share is owed to."},
    "employer_expense_account_id": {"type": "integer", "nullable": True}, "employer_liability_account_id": {"type": "integer", "nullable": True}, "on_arrears": {"type": "boolean", "description": "Also take it on arrears paid in a run."}, "applies_to_all": {"type": "boolean"}, "is_active": {"type": "boolean"}}
schemas["ContributionScheme"] = {"type": "object", "properties": {**scheme_props, "id": {"type": "integer"}, "employees": {"type": "integer"}}}
LID = [{"name": "id", "in": "path", "required": True, "schema": {"type": "integer"}}]
paths["/payroll/attendance"] = {
    "get": op("Payroll", "The attendance sheet of a month", "payroll.view", {"200": resp("Every active employee employed in the month with absent days, overtime hours and the unpaid leave that counts too", {"type": "object", "properties": {"data": {"type": "array", "items": {"type": "object"}}, "month": {"type": "string"}}}), **E}, params=[{"name": "month", "in": "query", "schema": {"type": "string", "example": "2026-10"}}], opid="get_attendance_sheet"),
    "post": op("Payroll", "Save the attendance sheet of a month", "payroll.manage", {"200": resp("Saved", {"type": "object", "properties": {"message": {"type": "string"}, "data": {"type": "array", "items": {"type": "object"}}}}), **E422}, body={"type": "object", "required": ["month", "rows"], "properties": {"month": {"type": "string", "format": "date"}, "rows": {"type": "array", "items": {"type": "object", "required": ["employee_id"], "properties": {"employee_id": {"type": "integer"}, "absent_days": {"type": "number"}, "overtime_hours": {"type": "number"}, "holiday_overtime_hours": {"type": "number"}, "notes": {"type": "string"}}}}}}, desc="Absent days and unpaid leave come off the basic and the fixed allowances by the day (percent allowances follow the basic); overtime hours are paid at salary / hours_per_month x multiplier to employees flagged overtime_eligible. A row of zeros clears the entry. A month with a posted payroll is refused.", opid="save_attendance_sheet")}
paths["/payroll/leave-types"] = {
    "get": op("Payroll", "Leave types", "payroll.view", {"200": resp("Leave types", data({"type": "array", "items": ref("LeaveType")})), **E}, opid="list_leave_types"),
    "post": op("Payroll", "Add a leave type", "payroll.manage", {"201": resp("Created", data(ref("LeaveType"))), **E422}, body={"type": "object", "required": ["code", "name"], "properties": leave_type_props}, opid="create_leave_type")}
paths["/payroll/leave-types/{id}"] = {"parameters": LID,
    "put": op("Payroll", "Change a leave type (PATCH also accepted)", "payroll.manage", {"200": resp("Updated", data(ref("LeaveType"))), **E404_422}, body={"type": "object", "required": ["code", "name"], "properties": leave_type_props}, opid="update_leave_type"),
    "delete": op("Payroll", "Delete a leave type nobody took", "payroll.manage", {"204": resp("Deleted"), **E404_422}, opid="delete_leave_type")}
paths["/payroll/leaves"] = {
    "get": op("Payroll", "Leaves, newest first", "payroll.view", {"200": resp("Leaves", data({"type": "array", "items": {"type": "object"}})), **E}, opid="list_leaves"),
    "post": op("Payroll", "Record leave", "payroll.manage", {"201": resp("Recorded", data({"type": "object"})), **E422}, body={"type": "object", "required": ["employee_id", "leave_type_id", "from_date", "to_date"], "properties": {"employee_id": {"type": "integer"}, "leave_type_id": {"type": "integer"}, "from_date": {"type": "string", "format": "date"}, "to_date": {"type": "string", "format": "date"}, "days": {"type": "number", "description": "Default: the calendar days of the dates; 0.5 for half a day."}, "notes": {"type": "string"}}}, desc="Refused when the dates overlap other leave or the leave type's yearly entitlement cannot cover it.", opid="create_leave")}
paths["/payroll/leaves/balances"] = {"get": op("Payroll", "Leave balances of a year", "payroll.view", {"200": resp("Entitlement, taken and balance of every active employee for every active leave type", data({"type": "array", "items": {"type": "object"}})), **E}, params=[{"name": "year", "in": "query", "schema": {"type": "integer"}}, {"name": "employee_id", "in": "query", "schema": {"type": "integer"}}], opid="leave_balances")}
paths["/payroll/leaves/{id}/cancel"] = {"parameters": LID, "post": op("Payroll", "Cancel a leave", "payroll.manage", {"200": resp("Cancelled", data({"type": "object"})), **E404}, opid="cancel_leave")}
paths["/payroll/loans"] = {
    "get": op("Payroll", "Loans and advances", "payroll.view", {"200": resp("Loans", data({"type": "array", "items": ref("Loan")})), **E}, opid="list_loans"),
    "post": op("Payroll", "Record a loan or advance with its instalment schedule", "payroll.manage", {"201": resp("Created as a draft", data(ref("Loan"))), **E422}, body={"type": "object", "required": ["employee_id", "kind", "principal", "installments", "start_month"], "properties": {"employee_id": {"type": "integer"}, "kind": {"type": "string", "enum": ["loan", "advance"]}, "principal": {"type": "number"}, "installments": {"type": "integer"}, "start_month": {"type": "string", "format": "date"}, "notes": {"type": "string"}}}, desc="Equal instalments from start_month, the last one takes the rounding. Nothing is recovered until the loan is paid out.", opid="create_loan")}
paths["/payroll/loans/{id}"] = {"parameters": LID, "get": op("Payroll", "A loan with its schedule", "payroll.view", {"200": resp("The loan", data(ref("Loan"))), **E404}, opid="get_loan")}
paths["/payroll/loans/{id}/disburse"] = {"parameters": LID, "post": op("Payroll", "Pay the loan out", "payroll.post", {"200": resp("Paid out; recovery from salary starts", data(ref("Loan"))), **E404_422}, body={"type": "object", "required": ["account_id"], "properties": {"account_id": {"type": "integer", "description": "The bank or cash account paid from."}, "date": {"type": "string", "format": "date"}}}, desc="Debits the employee loans account (accounting.payroll.employee_loans_account), credits the bank.", opid="disburse_loan")}
paths["/payroll/loans/{id}/settle"] = {"parameters": LID, "post": op("Payroll", "The employee pays back what is left in cash", "payroll.post", {"200": resp("Settled and closed", data(ref("Loan"))), **E404_422}, body={"type": "object", "required": ["account_id"], "properties": {"account_id": {"type": "integer", "description": "The bank or cash account received into."}, "date": {"type": "string", "format": "date"}}}, opid="settle_loan")}
paths["/payroll/loans/{id}/skip"] = {"parameters": LID, "post": op("Payroll", "Move the next instalment to the end of the schedule", "payroll.manage", {"200": resp("Skipped", data(ref("Loan"))), **E404_422}, opid="skip_loan_installment")}
paths["/payroll/loans/{id}/cancel"] = {"parameters": LID, "post": op("Payroll", "Cancel a loan not yet paid out", "payroll.manage", {"200": resp("Cancelled", data(ref("Loan"))), **E404_422}, opid="cancel_loan")}
paths["/payroll/schemes"] = {
    "get": op("Payroll", "Contribution schemes (EOBI, PESSI/SESSI, provident fund)", "payroll.view", {"200": resp("Schemes", data({"type": "array", "items": ref("ContributionScheme")})), **E}, opid="list_contribution_schemes"),
    "post": op("Payroll", "Add a contribution scheme", "payroll.manage", {"201": resp("Created", data(ref("ContributionScheme"))), **E422}, body={"type": "object", "required": ["code", "name", "base"], "properties": scheme_props}, desc="The employee's share is a deduction from pay owed to employee_account_id; the employer's share is an expense (employer_expense_account_id) owed to employer_liability_account_id and is not part of the net pay.", opid="create_contribution_scheme")}
paths["/payroll/schemes/{id}"] = {"parameters": LID,
    "get": op("Payroll", "A scheme", "payroll.view", {"200": resp("The scheme", data(ref("ContributionScheme"))), **E404}, opid="get_contribution_scheme"),
    "put": op("Payroll", "Change a scheme (PATCH also accepted)", "payroll.manage", {"200": resp("Updated", data(ref("ContributionScheme"))), **E404_422}, body={"type": "object", "required": ["code", "name", "base"], "properties": scheme_props}, opid="update_contribution_scheme"),
    "delete": op("Payroll", "Delete a scheme", "payroll.manage", {"204": resp("Deleted"), **E404}, opid="delete_contribution_scheme")}
paths["/payroll/schemes/{id}/assign"] = {"parameters": LID, "post": op("Payroll", "Give a scheme to employees or take it away", "payroll.manage", changed("Done"), body={"type": "object", "properties": {"mode": {"type": "string", "enum": ["assign", "remove"]}, "employee_ids": {"type": "array", "items": {"type": "integer"}, "description": "Default: every active employee."}}}, opid="assign_contribution_scheme")}
paths["/payroll/runs/{id}/bank-file/{format}"] = {"parameters": [ID, {"name": "format", "in": "path", "required": True, "schema": {"type": "string", "enum": ["csv", "xlsx"]}}],
    "get": op("Payroll", "The bank salary file of a posted run", "payroll.post", {"200": {"description": "The file, or with preview=1 the rows, the employees left out for want of a bank account, and the total", "content": {"text/csv": {"schema": {"type": "string"}}, "application/json": {"schema": {"type": "object"}}}}, **E404_422}, params=[{"name": "layout", "in": "query", "schema": {"type": "string", "default": "standard"}, "description": "A layout of accounting.payroll.bank_file.layouts."}, {"name": "preview", "in": "query", "schema": {"type": "boolean"}}], opid="payroll_bank_file")}

# FBR.
schemas["FbrSubmission"] = {"type": "object", "properties": {"id": {"type": "integer"}, "document_id": {"type": "integer"}, "status": {"type": "string", "enum": ["pending", "accepted", "failed"]}, "mode": {"type": "string", "enum": ["fake", "live"]}, "attempts": {"type": "integer"}, "fbr_invoice_number": {"type": "string", "nullable": True}, "error": {"type": "string", "nullable": True}, "submitted_at": {"type": "string", "format": "date-time", "nullable": True}}}
paths["/fbr"] = {"get": op("FBR", "Posted sales invoices and credit notes with their FBR status", "fbr.view", {"200": resp("Documents (status none = not sent yet)", data({"type": "object", "properties": {"enabled": {"type": "boolean"}, "mode": {"type": "string"}, "documents": {"type": "array", "items": {"type": "object"}}}})), **E422}, params=[{"name": "status", "in": "query", "schema": {"type": "string", "enum": ["none", "pending", "accepted", "failed"]}}], opid="list_fbr_documents")}
paths["/fbr/documents/{id}/submit"] = {"parameters": [ID], "post": op("FBR", "Send a sales invoice or credit note to FBR (or retry a failed one)", "fbr.submit", {"200": resp("The outcome: accepted (with FBR's invoice number) or failed (with the reason); a refusal by FBR is a result, not an HTTP error", data(ref("FbrSubmission"))), **E404_422}, desc="Needs accounting.fbr.enabled. A posted invoice or credit note is sent once: an accepted one is never sent again (422), a failed one can be retried. Mode fake accepts locally; live posts to the configured URL.", opid="submit_fbr_document")}

# Tax engine.
TAX_TYPES = ["sale", "sale_return", "purchase", "purchase_return", "withholding_payment", "withholding_receipt"]
schemas["TaxCalculation"] = {"type": "object", "properties": {"code": {"type": "string"}, "kind": {"type": "string"}, "rate": {"type": "string", "description": "Percent, four decimals"}, "inclusive": {"type": "boolean"}, "base": ref("Money"), "tax": ref("Money"), "gross": ref("Money")}}
schemas["TaxReturn"] = {"type": "object", "properties": {
    "id": {"type": "integer"}, "period_from": {"type": "string", "format": "date"}, "period_to": {"type": "string", "format": "date"}, "output_tax": ref("Money"), "input_tax": ref("Money"),
    "net_payable": {"type": "string", "description": "Negative when a refund is due"}, "journal_entry_id": {"type": ["integer", "null"]}, "voucher_number": {"type": ["string", "null"]}, "reference": {"type": ["string", "null"]}, "notes": {"type": ["string", "null"]}}}
schemas["TaxReport"] = {"type": "object", "properties": {"date_from": {"type": "string", "format": "date"}, "date_to": {"type": "string", "format": "date"},
    "rows": {"type": "array", "items": {"type": "object", "properties": {"tax_code_id": {"type": "integer"}, "code": {"type": "string"}, "name": {"type": "string"}, "kind": {"type": "string", "enum": ["output", "input", "withheld", "advance"]}, "base": ref("Money"), "tax": ref("Money"), "documents": {"type": "integer"}}}},
    "totals": {"type": "object", "properties": {"output_tax": ref("Money"), "input_tax": ref("Money"), "net_payable": {"type": "string"}, "withheld": ref("Money"), "advance": ref("Money")}}}}
paths["/tax/calculate"] = {"post": op("Tax", "Tax on an amount", "tax-codes.view", {"200": resp("Calculation", data(ref("TaxCalculation"))), **E422},
    body={"type": "object", "required": ["tax_code_id", "amount"], "properties": {"tax_code_id": {"type": "integer"}, "amount": {"type": "number"}, "inclusive": {"type": "boolean", "default": False, "description": "The amount already contains the tax"}, "date": {"type": "string", "format": "date", "description": "The rate in force on this date (default today)"}}},
    desc="Rounded to the cent, half away from zero; for an inclusive amount base + tax equals the amount exactly.", opid="calculate_tax")}
paths["/tax/entries"] = {"post": op("Tax", "Book a taxed document as a journal entry", "tax-entries.create", {"201": resp("Created", data({"type": "object", "properties": {"id": {"type": "integer"}, "status": {"type": "string"}, "voucher_number": {"type": ["string", "null"]}}})), **E422},
    body={"type": "object", "required": ["type", "entry_date", "amount", "tax_code_id", "account_id", "counter_account_id"], "properties": {
        "type": {"type": "string", "enum": TAX_TYPES, "description": "sale / sale_return need an output code, purchase / purchase_return an input code, withholding_payment a withheld code, withholding_receipt an advance code"},
        "entry_date": {"type": "string", "format": "date"}, "amount": {"type": "number"}, "tax_inclusive": {"type": "boolean"}, "tax_code_id": {"type": "integer"},
        "account_id": {"type": "integer", "description": "Revenue / expense account (the party account for withholding)"}, "counter_account_id": {"type": "integer", "description": "Customer / supplier / bank account"},
        "cost_center_id": {"type": "integer"}, "reference": {"type": "string"}, "description": {"type": "string"}, "auto_post": {"type": "boolean", "default": False}}},
    desc="Journal entry lines may also carry `tax_code_id` (+ `tax_inclusive`): such a line is split into its taxable amount and a tax line to the code's tax account, marked for the tax report.", opid="create_taxed_entry")}
paths["/tax/returns/report"] = {"get": op("Tax", "The tax ledger of a period", "tax-returns.view", {"200": resp("Report", data(ref("TaxReport")))},
    params=[{"name": "date_from", "in": "query", "schema": {"type": "string", "format": "date"}}, {"name": "date_to", "in": "query", "schema": {"type": "string", "format": "date"}}, {"name": "tax_code_id", "in": "query", "schema": {"type": "integer"}}],
    desc="Per tax code: taxable base and tax from posted entries in base currency (default: this month).", opid="tax_report")}
paths["/tax/returns/report/export/{format}"] = {"parameters": [{"name": "format", "in": "path", "required": True, "schema": {"type": "string", "enum": ["csv", "xlsx", "pdf"]}}], "get": op("Tax", "Export the tax report", "tax-returns.view", {"200": {"description": "The file"}}, opid="export_tax_report")}
paths["/tax/returns"] = {
    "get": op("Tax", "Filed tax returns", "tax-returns.view", {"200": resp("Returns, newest period first", data({"type": "array", "items": ref("TaxReturn")}))}, opid="list_tax_returns"),
    "post": op("Tax", "File a return for a period", "tax-returns.file", {"201": resp("Filed", data(ref("TaxReturn"))), **E422},
        body={"type": "object", "required": ["period_from", "period_to", "payable_account_id"], "properties": {"period_from": {"type": "string", "format": "date"}, "period_to": {"type": "string", "format": "date"}, "payable_account_id": {"type": "integer", "description": "An active liability (or asset) posting account"}, "reference": {"type": "string"}, "notes": {"type": "string"}}},
        desc="Posts one entry dated at the period end: output tax debited and input tax credited on their tax accounts, the difference on the payable account (a refund debits it). 422 when the period overlaps a filed return or there is no output or input tax.", opid="file_tax_return")}
paths["/tax/returns/{id}"] = {"parameters": [ID],
    "get": op("Tax", "A filed return", "tax-returns.view", {"200": resp("Return", data(ref("TaxReturn"))), **E404}, opid="show_tax_return"),
    "delete": op("Tax", "Void a return (its entry is reversed)", "tax-returns.file", {"204": resp("Voided"), **E404_422}, opid="void_tax_return")}

# Receivables and payables.
DOC_KINDS = ["invoice", "bill", "credit_note", "debit_note"]
schemas["Party"] = {"type": "object", "properties": {
    "id": {"type": "integer"}, "type": {"type": "string", "enum": ["customer", "supplier", "both"]}, "code": {"type": "string"}, "name": {"type": "string"}, "email": {"type": ["string", "null"]}, "phone": {"type": ["string", "null"]},
    "address": {"type": ["string", "null"]}, "tax_number": {"type": ["string", "null"]}, "payment_terms_days": {"type": "integer"}, "credit_limit": {"type": ["string", "null"]},
    "receivable_account_id": {"type": ["integer", "null"]}, "payable_account_id": {"type": ["integer", "null"]}, "is_active": {"type": "boolean"}, "notes": {"type": ["string", "null"]}}}
party_props = {k: v for k, v in schemas["Party"]["properties"].items() if k != "id"}
schemas["PartyDocument"] = {"type": "object", "properties": {
    "id": {"type": "integer"}, "kind": {"type": "string", "enum": DOC_KINDS}, "number": {"type": ["string", "null"], "description": "Assigned when posted: INV-2026-00001"}, "party_id": {"type": "integer"}, "party": {"type": ["string", "null"]},
    "issue_date": {"type": "string", "format": "date"}, "due_date": {"type": "string", "format": "date"}, "reference": {"type": ["string", "null"]}, "prices_include_tax": {"type": "boolean"},
    "subtotal": ref("Money"), "tax_total": ref("Money"), "total": ref("Money"), "open": {"type": "string", "description": "Posted documents: what is still open (invoice, bill) or available (credit, debit note)"},
    "status": {"type": "string", "enum": ["draft", "posted", "void"]}, "journal_entry_id": {"type": ["integer", "null"]}, "notes": {"type": ["string", "null"]},
    "lines": {"type": "array", "description": "Only on single-document responses", "items": {"type": "object", "properties": {"chart_of_account_id": {"type": "integer"}, "account": {"type": ["string", "null"]}, "description": {"type": ["string", "null"]}, "quantity": {"type": "string"}, "unit_price": {"type": "string"}, "tax_code": {"type": ["string", "null"]}, "tax_rate": {"type": ["string", "null"]}, "net_amount": ref("Money"), "tax_amount": ref("Money")}}}}}
doc_body = {"type": "object", "required": ["party_id", "kind", "issue_date", "lines"], "properties": {
    "party_id": {"type": "integer"}, "kind": {"type": "string", "enum": DOC_KINDS, "description": "invoice / credit_note need a customer, bill / debit_note a supplier"}, "issue_date": {"type": "string", "format": "date"},
    "due_date": {"type": "string", "format": "date", "description": "Default: issue date plus the party's payment terms (credit and debit notes: the issue date)"}, "reference": {"type": "string"}, "prices_include_tax": {"type": "boolean"}, "notes": {"type": "string"},
    "lines": {"type": "array", "minItems": 1, "items": {"type": "object", "required": ["chart_of_account_id", "unit_price"], "properties": {"chart_of_account_id": {"type": "integer", "description": "An active posting account that is not a control account"}, "description": {"type": "string"}, "quantity": {"type": "number", "default": 1}, "unit_price": {"type": "number"}, "cost_center_id": {"type": "integer"}, "tax_code_id": {"type": "integer", "description": "An output code for invoices and credit notes, an input code for bills and debit notes"}}}}}}
schemas["PartyPayment"] = {"type": "object", "properties": {
    "id": {"type": "integer"}, "kind": {"type": "string", "enum": ["receipt", "payment"]}, "number": {"type": ["string", "null"]}, "party_id": {"type": "integer"}, "party": {"type": ["string", "null"]}, "payment_date": {"type": "string", "format": "date"},
    "amount": ref("Money"), "unapplied": ref("Money"), "account_id": {"type": "integer"}, "method": {"type": ["string", "null"]}, "reference": {"type": ["string", "null"]}, "status": {"type": "string", "enum": ["posted", "void"]}, "journal_entry_id": {"type": ["integer", "null"]},
    "allocations": {"type": "array", "description": "Only on single-payment responses", "items": {"type": "object", "properties": {"id": {"type": "integer"}, "document_id": {"type": "integer"}, "document": {"type": "string"}, "amount": ref("Money"), "allocated_on": {"type": "string", "format": "date"}}}}}}
alloc_items = {"type": "array", "items": {"type": "object", "required": ["document_id", "amount"], "properties": {"document_id": {"type": "integer"}, "amount": {"type": "number"}}}}
SIDE = {"name": "side", "in": "query", "schema": {"type": "string", "enum": ["receivable", "payable"]}, "description": "receivable: what customers owe; payable: what is owed to suppliers (default: by the party's type)"}
paths["/parties"] = {
    "get": op("Receivables & payables", "Customers and suppliers", "parties.view", {"200": resp("Parties", data({"type": "array", "items": ref("Party")}))}, params=[{"name": "type", "in": "query", "schema": {"type": "string", "enum": ["customer", "supplier", "both"]}}, {"name": "search", "in": "query", "schema": {"type": "string"}}, {"name": "active", "in": "query", "schema": {"type": "boolean"}}], opid="list_parties"),
    "post": op("Receivables & payables", "Create a customer or supplier", "parties.create", {"201": resp("Created", data(ref("Party"))), **E422}, body={"type": "object", "required": ["type", "code", "name"], "properties": party_props}, opid="create_party")}
paths["/parties/{id}"] = {"parameters": [ID],
    "get": op("Receivables & payables", "A party with its balance and open items", "parties.view", {"200": resp("Party", data({"type": "object", "properties": {"balance": ref("Money"), "open_items": {"type": "array", "items": {"type": "object"}}, "unapplied": {"type": "array", "description": "Credit / debit notes and payments not yet applied", "items": {"type": "object"}}}})), **E404},
        params=[SIDE, {"name": "as_of", "in": "query", "schema": {"type": "string", "format": "date"}}], opid="show_party"),
    "put": op("Receivables & payables", "Change a party (PATCH also accepted)", "parties.update", {"200": resp("Updated", data(ref("Party"))), **E404_422}, body={"type": "object", "required": ["type", "code", "name"], "properties": party_props}, opid="update_party"),
    "delete": op("Receivables & payables", "Delete a party without documents or payments", "parties.delete", {"204": resp("Deleted"), **E404_422}, opid="delete_party")}
paths["/parties/{id}/statement"] = {"parameters": [ID], "get": op("Receivables & payables", "Statement of account with a running balance", "parties.view", {"200": resp("Statement", data({"type": "object", "properties": {"opening": ref("Money"), "rows": {"type": "array", "items": {"type": "object"}}, "closing": ref("Money")}})), **E404},
    params=[SIDE, {"name": "date_from", "in": "query", "schema": {"type": "string", "format": "date"}}, {"name": "date_to", "in": "query", "schema": {"type": "string", "format": "date"}}], opid="party_statement")}
paths["/party-documents"] = {
    "get": op("Receivables & payables", "Invoices, bills, credit and debit notes", "party-documents.view", {"200": resp("Documents, newest first", data({"type": "array", "items": ref("PartyDocument")}))},
        params=[{"name": "kind", "in": "query", "schema": {"type": "string", "enum": DOC_KINDS}}, {"name": "status", "in": "query", "schema": {"type": "string", "enum": ["draft", "posted", "void"]}}, {"name": "party_id", "in": "query", "schema": {"type": "integer"}}], opid="list_party_documents"),
    "post": op("Receivables & payables", "Create a draft document", "party-documents.create", {"201": resp("Created", data(ref("PartyDocument"))), **E422}, body=doc_body, desc="Line amounts and tax are computed on the issue date; nothing is booked until the document is posted.", opid="create_party_document")}
paths["/party-documents/{id}"] = {"parameters": [ID],
    "get": op("Receivables & payables", "A document with its lines", "party-documents.view", {"200": resp("Document", data(ref("PartyDocument"))), **E404}, opid="show_party_document"),
    "put": op("Receivables & payables", "Change a draft (PATCH also accepted)", "party-documents.update", {"200": resp("Updated", data(ref("PartyDocument"))), **E404_422}, body=doc_body, opid="update_party_document"),
    "delete": op("Receivables & payables", "Delete a draft", "party-documents.delete", {"204": resp("Deleted"), **E404_422}, opid="delete_party_document")}
paths["/party-documents/{id}/post"] = {"parameters": [ID], "post": op("Receivables & payables", "Number and post a draft", "party-documents.post", {"200": resp("Posted", data(ref("PartyDocument"))), **E404_422},
    desc="Books one journal entry: the party's control account against the lines and their tax (marked for the tax report). The number is gapless per kind and year; a posting that fails gives it back.", opid="post_party_document")}
paths["/party-documents/{id}/void"] = {"parameters": [ID], "post": op("Receivables & payables", "Void a posted document (its entry is reversed)", "party-documents.void", {"200": resp("Voided", data(ref("PartyDocument"))), **E404_422}, desc="422 while payments or credits are applied to it.", opid="void_party_document")}
paths["/party-documents/{id}/apply"] = {"parameters": [ID], "post": op("Receivables & payables", "Apply a credit or debit note to invoices or bills", "party-payments.create", {"200": resp("Applied", data(ref("PartyDocument"))), **E404_422}, body={"type": "object", "required": ["allocations"], "properties": {"allocations": alloc_items}}, opid="apply_credit_note")}
paths["/party-allocations/{id}"] = {"parameters": [ID], "delete": op("Receivables & payables", "Undo an allocation", "party-payments.create", {"204": resp("Removed"), **E404}, opid="undo_allocation")}
paths["/party-payments"] = {
    "get": op("Receivables & payables", "Receipts and payments", "party-payments.view", {"200": resp("Payments, newest first", data({"type": "array", "items": ref("PartyPayment")}))}, params=[{"name": "kind", "in": "query", "schema": {"type": "string", "enum": ["receipt", "payment"]}}, {"name": "party_id", "in": "query", "schema": {"type": "integer"}}], opid="list_party_payments"),
    "post": op("Receivables & payables", "Record a receipt from a customer or a payment to a supplier", "party-payments.create", {"201": resp("Recorded", data(ref("PartyPayment"))), **E422},
        body={"type": "object", "required": ["party_id", "kind", "payment_date", "amount", "account_id"], "properties": {"party_id": {"type": "integer"}, "kind": {"type": "string", "enum": ["receipt", "payment"]}, "payment_date": {"type": "string", "format": "date"}, "amount": {"type": "number"},
            "account_id": {"type": "integer", "description": "The bank or cash account"}, "method": {"type": "string"}, "reference": {"type": "string"}, "notes": {"type": "string"}, "allocations": alloc_items, "auto_allocate": {"type": "boolean", "description": "Settle the oldest invoices (bills) first, as far as the amount goes"}}},
        desc="Posted at once and numbered (RCT-… / PAY-…): the bank account against the party's control account. What is not allocated stays on the party's account.", opid="create_party_payment")}
paths["/party-payments/{id}"] = {"parameters": [ID], "get": op("Receivables & payables", "A payment with its allocations", "party-payments.view", {"200": resp("Payment", data(ref("PartyPayment"))), **E404}, opid="show_party_payment")}
paths["/party-payments/{id}/allocate"] = {"parameters": [ID], "post": op("Receivables & payables", "Allocate (more of) a payment to documents", "party-payments.create", {"200": resp("Allocated", data(ref("PartyPayment"))), **E404_422},
    body={"type": "object", "properties": {"allocations": alloc_items, "auto_allocate": {"type": "boolean"}}}, opid="allocate_party_payment")}
paths["/party-payments/{id}/void"] = {"parameters": [ID], "post": op("Receivables & payables", "Void a payment (entry reversed, allocations released)", "party-payments.void", {"200": resp("Voided", data(ref("PartyPayment"))), **E404_422}, opid="void_party_payment")}
paths["/receivables/aging"] = {"get": op("Receivables & payables", "Ageing by customer or supplier, checked against the control account", "party-documents.view",
    {"200": resp("Report", data({"type": "object", "properties": {"as_of": {"type": "string", "format": "date"}, "side": {"type": "string"}, "rows": {"type": "array", "items": {"type": "object", "description": "not_due, days_1_30, days_31_60, days_61_90, over_90 (by days past due), unapplied (negative), total"}}, "totals": {"type": "object"},
        "reconciliation": {"type": "object", "properties": {"ledger": ref("Money"), "subledger": ref("Money"), "difference": ref("Money")}}}}))}, params=[SIDE, {"name": "as_of", "in": "query", "schema": {"type": "string", "format": "date"}}], opid="party_aging")}
paths["/receivables/aging/export/{format}"] = {"parameters": [{"name": "format", "in": "path", "required": True, "schema": {"type": "string", "enum": ["csv", "xlsx", "pdf"]}}], "get": op("Receivables & payables", "Export the ageing", "party-documents.view", {"200": {"description": "The file"}}, params=[SIDE, {"name": "as_of", "in": "query", "schema": {"type": "string", "format": "date"}}], opid="export_party_aging")}

# Every operation can name its company.
for path_item in paths.values():
    for method, operation in path_item.items():
        if method in ("get", "post", "put", "patch", "delete"):
            operation.setdefault("parameters", []).append({"$ref": "#/components/parameters/Company"})

spec = {
 "openapi": "3.1.0",
 "info": {"title": "Laravel Chart of Accounts API", "version": "2.30.0",
  "description": "Double-entry accounting REST API for `alimarchal/laravel-chart-of-accounts`.\n\n"
   "* **Auth:** `Authorization: Bearer <Sanctum token>` (configurable with `ACCOUNTING_API_MIDDLEWARE`).\n"
   "* **Permissions:** every endpoint requires a Spatie permission (listed per operation).\n"
   "* **Rate limit:** `ACCOUNTING_API_RATE_LIMIT` requests/minute per user (default 120) → 429.\n"
   "* **Pagination:** `?page=` and `?per_page=` (max `ACCOUNTING_API_MAX_PER_PAGE`, default 100).\n"
   "* **Errors:** 422 with `message` (accounting rules) and `errors` (validation).\n"
   "* **Money:** amounts are returned as decimal strings with two decimals.\n"
   "* **Retries:** send `Idempotency-Key` when creating journal entries.\n"
   "* **Companies:** with multi-company enabled, send `X-Company: <code or id>`; records of other companies are never visible (404).\n"
   "* **Maker-checker:** with approvals enabled, entries at/above the threshold go submit → approve (a different user) → posted.",
  "license": {"name": "MIT", "identifier": "MIT"}},
 "servers": [{"url": "{host}/api/v1/accounting", "variables": {"host": {"default": "http://localhost:8000"}}}],
 "security": [{"bearerAuth": []}],
 "tags": [{"name": t, "description": d} for t, d in [
   ("Journal entries", "Create, post, reverse and void double-entry journal entries."),
   ("Chart of accounts", "Hierarchical accounts, the account tree and account balances."),
   ("Reports", "Financial statements and ledgers (posted entries only)."),
   ("Periods", "Accounting periods, closing and balance snapshots."),
   ("Account types", "Asset, liability, equity, income and expense types."),
   ("Currencies", "Currencies and exchange rates (exactly one base currency)."),
   ("Cost centers", "Cost centers and projects for line-level analysis."),
   ("Bank accounts", "Bank accounts linked to GL accounts."),
   ("Reconciliations", "Bank statement reconciliations."),
   ("Tax", "Tax codes and dated tax rates."),
   ("Recurring entries", "Journal entries that repeat on a schedule: templates, runs, pause and resume."),
   ("Voucher types", "Voucher types (JV, CPV, CRV, BPV, BRV, …) and their gapless number series."),
   ("Companies", "Companies (multi-company), access and consolidation."),
   ("Users & roles", "Users, roles and permissions — privilege-checked and audited."),
   ("System", "Installation health.")]],
 "paths": paths,
 "components": {
  "securitySchemes": {"bearerAuth": {"type": "http", "scheme": "bearer", "description": "Laravel Sanctum personal access token"}},
  "parameters": {
   "Id": {"name": "id", "in": "path", "required": True, "schema": {"type": "integer"}},
   "Page": {"name": "page", "in": "query", "schema": {"type": "integer", "minimum": 1, "default": 1}},
   "PerPage": {"name": "per_page", "in": "query", "schema": {"type": "integer", "minimum": 1, "maximum": 100, "default": 15}},
   "Company": {"name": "X-Company", "in": "header", "schema": {"type": "string"}, "description": "Company code or id (multi-company). Default: your default company. 403 if you have no access, 404 if unknown."},
   "IdempotencyKey": {"name": "Idempotency-Key", "in": "header", "schema": {"type": "string", "maxLength": 100}, "description": "Same key + same body → original entry is returned (200). Same key + different body → 422."}},
  "responses": {
   "Unauthenticated": resp("Missing or invalid token", ref("Error")),
   "Forbidden": resp("The user lacks the required permission", ref("Error")),
   "NotFound": resp("Record not found", ref("Error")),
   "TooManyRequests": resp("Rate limit exceeded", ref("Error")),
   "UnprocessableEntity": resp("Validation failed or an accounting rule was violated", ref("ValidationError"))},
  "schemas": schemas}}

class D(yaml.SafeDumper):
    def ignore_aliases(self, data): return True
out = yaml.dump(spec, Dumper=D, sort_keys=False, allow_unicode=True, width=120)
open(os.path.join(os.path.dirname(os.path.abspath(__file__)), "openapi.yaml"), "w").write(out)
print(len(paths), "paths")
