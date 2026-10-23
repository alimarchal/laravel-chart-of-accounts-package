# Contributing

- Run the tests with `vendor/bin/pest` (in-memory SQLite by default; set `DB_CONNECTION=mysql|mariadb|pgsql` to run against a real server) and format with `vendor/bin/pint`.
- Every behaviour change comes with a regression test.
- The API spec `docs/openapi.yaml` is generated: edit `docs/generate-openapi.py` and run `python3 docs/generate-openapi.py`; `ApiAccessTest` fails when a route is missing from the spec.

## Windows checkouts and line endings

`.gitattributes` forces LF line endings, which works for fresh clones. A Windows checkout made **before** that file existed keeps CRLF files, and two tests then fail (`ApiAccessTest › documents every API route in docs/openapi.yaml` and `InstallWizardTest › moves an installation that has the two separate links`). Re-clone, or renormalise the checkout (commit or stash local changes first):

```bash
git rm -rq --cached . && git reset --hard
```
