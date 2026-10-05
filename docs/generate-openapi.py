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
 "tax-codes": ("Tax", "TaxCode", "tax-codes", {"code": {"type": "string"}, "name": {"type": "string"}, "description": {"type": ["string", "null"]}, "is_active": {"type": "boolean"}}, ["code", "name"]),
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

# Every operation can name its company.
for path_item in paths.values():
    for method, operation in path_item.items():
        if method in ("get", "post", "put", "patch", "delete"):
            operation.setdefault("parameters", []).append({"$ref": "#/components/parameters/Company"})

spec = {
 "openapi": "3.1.0",
 "info": {"title": "Laravel Chart of Accounts API", "version": "2.13.0",
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
