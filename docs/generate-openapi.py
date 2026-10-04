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
     "is_active": {"type": "boolean", "default": True, "description": "Omitted on update = unchanged."}}},
 "AccountBalance": {"type": "object", "properties": {
     "account_id": {"type": "integer"}, "account_code": {"type": "string"}, "account_name": {"type": "string"},
     "normal_balance": {"type": "string"}, "includes_child_accounts": {"type": "boolean"}, "as_of_date": {"type": "string", "format": "date"},
     "total_debit": ref("Money"), "total_credit": ref("Money"), "balance": ref("Money")}},
 "JournalLine": {"type": "object", "properties": {
     "id": {"type": "integer"}, "line_no": {"type": "integer"}, "chart_of_account_id": {"type": "integer"}, "account_code": {"type": "string"}, "account_name": {"type": "string"},
     "cost_center_id": {"type": ["integer", "null"]}, "cost_center_code": {"type": ["string", "null"]},
     "debit": ref("Money"), "credit": ref("Money"), "description": {"type": ["string", "null"]}, "reconciliation_status": {"type": ["string", "null"]}}},
 "JournalEntry": {"type": "object", "properties": {
     "id": {"type": "integer"}, "entry_date": {"type": "string", "format": "date"}, "reference": {"type": ["string", "null"]}, "description": {"type": ["string", "null"]},
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
    {"name": "filter[currency_id]", "in": "query", "schema": {"type": "integer"}}, {"name": "filter[accounting_period_id]", "in": "query", "schema": {"type": "integer"}},
    {"name": "filter[entry_date_from]", "in": "query", "schema": {"type": "string", "format": "date"}}, {"name": "filter[entry_date_to]", "in": "query", "schema": {"type": "string", "format": "date"}},
    {"name": "sort", "in": "query", "schema": {"type": "string", "enum": ["entry_date", "-entry_date", "id", "-id", "reference", "-reference", "created_at", "-created_at"]}},
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

# Every operation can name its company.
for path_item in paths.values():
    for method, operation in path_item.items():
        if method in ("get", "post", "put", "patch", "delete"):
            operation.setdefault("parameters", []).append({"$ref": "#/components/parameters/Company"})

spec = {
 "openapi": "3.1.0",
 "info": {"title": "Laravel Chart of Accounts API", "version": "2.4.0",
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
   ("Companies", "Companies (multi-company), access and consolidation."),
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
