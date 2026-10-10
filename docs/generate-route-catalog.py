"""Generates docs/help-catalog.md and the API reference of the README from the routes the package really registers.

Run: python3 docs/generate-route-catalog.py   (needs PHP, PyYAML and `composer install`; docs/openapi.yaml should be up to date)

The catalog lists every web screen (React/Inertia and Blade) and every API endpoint with the permission it needs and what it does.
"""
import json
import os
import re
import subprocess
import sys

import yaml

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
API_PREFIX = "api/v1/accounting"


def routes(driver):
    env = {**os.environ, "ACCOUNTING_UI_DRIVER": driver}
    raw = subprocess.run(["php", os.path.join(ROOT, "docs/dump-routes.php")], cwd=ROOT, env=env, capture_output=True, text=True, check=True).stdout
    rows = []
    for r in json.loads(raw):
        uri = "/" + r["uri"].lstrip("/")
        methods = [m for m in r["method"].split("|") if m != "HEAD"]
        perms = [m[4:] for m in r["middleware"] if isinstance(m, str) and m.startswith("can:")]
        rows.append({"methods": methods, "uri": uri, "name": r["name"] or "", "action": r["action"], "perm": ", ".join(perms)})
    return rows


def norm(uri):
    return re.sub(r"\{[^}]+\}", "{}", uri)


def humanize(name, uri):
    name = re.sub(r"^(api\.)?accounting\.", "", name or "")
    name = re.sub(r"^settings\.", "", name)
    parts = [p.replace("-", " ") for p in name.split(".") if p]
    if not parts:
        return uri.strip("/").replace("-", " ")
    verb = {"index": "list", "show": "open", "create": "new form", "store": "save new", "edit": "edit form", "update": "save changes", "destroy": "delete"}
    last = parts[-1]
    return (verb.get(last, last) + " — " + " / ".join(parts[:-1])).strip(" —") if len(parts) > 1 else verb.get(last, last)


def main():
    spec = yaml.safe_load(open(os.path.join(ROOT, "docs/openapi.yaml")))
    ops = {}
    for path, item in spec["paths"].items():
        for method, op in item.items():
            if method in ("get", "post", "put", "delete", "patch"):
                ops[(method.upper(), norm("/" + API_PREFIX + path))] = op

    api = [r for r in routes("inertia") if r["uri"].startswith("/" + API_PREFIX)]
    inertia = [r for r in routes("inertia") if r["uri"].startswith(("/accounting", "/settings"))]
    blade = [r for r in routes("blade") if r["uri"].startswith(("/accounting", "/settings"))]

    # API grouped by the OpenAPI tag
    groups = {}
    for r in api:
        for m in r["methods"]:
            if m == "PATCH":
                continue
            op = ops.get((m, norm(r["uri"])))
            tag = op["tags"][0] if op else "Other"
            summary = op["summary"] if op else humanize(r["name"], r["uri"])
            groups.setdefault(tag, []).append((m, r["uri"][len("/" + API_PREFIX):] or "/", r["perm"], summary))
    tag_order = [t["name"] for t in spec.get("tags", [])]
    tags = sorted(groups, key=lambda t: (tag_order.index(t) if t in tag_order else 999, t))

    api_md = []
    for tag in tags:
        api_md.append(f"### {tag}\n\n| Method | Endpoint | What it does | Permission |\n|--------|----------|--------------|------------|")
        for m, uri, perm, summary in groups[tag]:
            api_md.append(f"| {m} | `{uri}` | {summary} | {('`' + perm + '`') if perm else ''} |")
        api_md.append("")
    api_text = "\n".join(api_md)
    n_api = sum(len(v) for v in groups.values())

    # Web screens, one row per URL; Blade differences are shown as a second URL
    titles = {"": "Home and overview", "overview": "Home and overview", "chart-of-accounts": "Chart of accounts", "account-types": "Account types", "currencies": "Currencies and exchange rates", "periods": "Periods and closing",
              "journal-entries": "Journal entries", "reports": "Reports", "cost-centers": "Cost centers", "bank-accounts": "Bank accounts", "reconciliations": "Reconciliations", "tax-codes": "Tax codes and rates", "tax-rates": "Tax codes and rates",
              "tax": "Tax", "voucher-types": "Voucher types", "control-accounts": "Control accounts", "attachments": "Attachments", "users": "Users and roles", "roles": "Users and roles", "permissions": "Users and roles", "features": "Feature switches",
              "companies": "Companies", "company": "Companies", "locale": "Language", "audit-logs": "Audit log", "exports": "My exports", "account-balance-snapshots": "Balance snapshots", "recurring-entries": "Recurring entries",
              "fx-revaluation": "Currency revaluation", "bank-statements": "Bank statements", "bank-statement-lines": "Bank statements", "budgets": "Budgets", "parties": "Customers and suppliers", "party-documents": "Invoices and bills",
              "party-payments": "Receipts and payments", "party-allocations": "Receipts and payments", "receivables": "Customers and suppliers", "fixed-assets": "Fixed assets", "inventory": "Inventory", "payroll": "Payroll", "fbr": "FBR invoices",
              "chart-templates": "Chart templates", "report-mapping": "Statement layout", "report-lines": "Statement layout", "dashboard": "Home and overview"}

    def module(uri):
        seg = uri.strip("/").split("/")
        seg = seg[1:] if seg[0] in ("accounting", "settings") else seg
        if seg and seg[0] == "settings":
            seg = seg[1:]
        return titles.get(seg[0] if seg else "", "Other")

    screens = {}
    for r in inertia:
        for m in r["methods"]:
            key = (module(r["uri"]), m, r["name"].replace("accounting.", "", 1) if r["name"] else r["uri"])
            screens[key] = {"react": r["uri"], "blade": None, "perm": r["perm"], "name": r["name"]}
    for r in blade:
        for m in r["methods"]:
            n = re.sub(r"^(accounting|settings)\.", "", r["name"] or "")
            hit = next((k for k in screens if k[1] == m and re.sub(r"^(accounting|settings)\.", "", screens[k]["name"] or "") == n), None)
            if hit:
                if screens[hit]["react"] != r["uri"]:
                    screens[hit]["blade"] = r["uri"]
            else:
                screens[(module(r["uri"]), m, "blade:" + (r["name"] or r["uri"]))] = {"react": None, "blade": r["uri"], "perm": r["perm"], "name": r["name"]}

    by_module = {}
    for (mod, m, _), v in screens.items():
        by_module.setdefault(mod, []).append((m, v))
    mod_order = list(dict.fromkeys(titles.values())) + ["Other"]

    lines = ["# Help catalog: every screen and endpoint", "",
             "Generated from the routes the package registers (`python3 docs/generate-route-catalog.py`); do not edit by hand.", "",
             f"- **{sum(len(v) for v in by_module.values())} web routes** (screens and the form actions behind them; the same screens exist for the React/Inertia and the Blade driver) and **{n_api} API endpoints**.",
             "- Web screens live under `/accounting` (users, roles and the feature switches under `/settings` with the Blade driver); the API under `/api/v1/accounting` (see the prefixes in `config/accounting.php`).",
             "- *Permission* is the Spatie permission a user needs; a user without it gets 403. A feature that is switched off answers 404 (see *Feature switches* in the README).",
             "- The API is described in full in [`openapi.yaml`](openapi.yaml) and the [Postman collection](postman_collection.json).", "", "## Contents", ""]
    for mod in mod_order:
        if mod in by_module:
            lines.append(f"- [{mod}](#{re.sub(r'[^a-z0-9 -]', '', mod.lower()).replace(' ', '-')}) ({len(by_module[mod])})")
    lines += ["- [API endpoints](#api-endpoints)", ""]
    for mod in mod_order:
        if mod not in by_module:
            continue
        lines += [f"## {mod}", "", "| Method | Screen or action (React) | Blade driver | What it does | Permission |", "|--------|--------|--------------|--------------|------------|"]
        for m, v in sorted(by_module[mod], key=lambda x: ((x[1]["react"] or x[1]["blade"] or ""), x[0])):
            what = ops.get((m, norm((v["react"] or v["blade"]).replace("/accounting", "/" + API_PREFIX, 1))))
            lines.append(f"| {m} | `{v['react'] or '—'}` | {('`' + v['blade'] + '`') if v['blade'] else ''} | {(what['summary'] if what else humanize(v['name'], v['react'] or v['blade']))} | {('`' + v['perm'] + '`') if v['perm'] else ''} |")
        lines.append("")
    lines += ["## API endpoints", "", "Base URL `/api/v1/accounting`; every request needs a Sanctum token (`Authorization: Bearer ...`).", "", api_text]
    open(os.path.join(ROOT, "docs/help-catalog.md"), "w").write("\n".join(lines).rstrip() + "\n")

    # README: the complete API reference between the markers
    readme_path = os.path.join(ROOT, "README.md")
    readme = open(readme_path).read()
    start, end = "<!-- api-reference:start -->", "<!-- api-reference:end -->"
    block = f"{start}\n_{n_api} endpoints, generated from the routes by `docs/generate-route-catalog.py`. Screens: see the [help catalog](docs/help-catalog.md)._\n\n{api_text}\n{end}"
    if start in readme:
        readme = re.sub(re.escape(start) + r".*?" + re.escape(end), lambda _: block, readme, flags=re.S)
        open(readme_path, "w").write(readme)
    print(f"{sum(len(v) for v in by_module.values())} screens, {n_api} endpoints")


if __name__ == "__main__":
    sys.exit(main())
