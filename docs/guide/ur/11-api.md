<div dir="rtl" align="right">

# باب 11: API گائیڈ — ہر ماڈیول کا REST انٹرفیس

[← پچھلا باب](10-admin.md) · [فہرست](README.md) · [اگلا باب: کمانڈیں اور عام سوالات →](12-commands-faq.md)

ہر وہ کام جو اسکرین پر ہوتا ہے، API سے بھی ہو سکتا ہے — موبائل ایپ، ویب سائٹ، یا دوسرے سافٹ ویئر (مثلاً POS) کو جوڑنے کے لیے۔ تمام **311 endpoints** کی مکمل فہرست (انگریزی میں، اجازت کے ساتھ) [Help Catalog](../../help-catalog.md) میں ہے؛ اس باب میں استعمال کا طریقہ ہے۔

## 11.1 بنیادی معلومات

| چیز | قدر |
|---|---|
| Base URL | `https://your-app.test/api/v1/accounting` (`ACCOUNTING_API_PREFIX` سے بدلتا ہے) |
| تصدیق | Laravel **Sanctum** ٹوکن: `Authorization: Bearer <token>` |
| ہیڈر | `Accept: application/json` اور `Content-Type: application/json` |
| کمپنی (Multi-company) | `X-Company: <id یا code>` — نہ دیں تو آپ کی ڈیفالٹ کمپنی |
| رفتار کی حد | ڈیفالٹ 120 درخواستیں فی منٹ فی صارف (`ACCOUNTING_API_RATE_LIMIT`) |
| اجازت | ہر endpoint ایک Permission مانگتا ہے؛ نہ ہو تو **403** |

## 11.2 ٹوکن بنانا

```bash
php artisan tinker --execute="echo App\Models\User::first()->createToken('erp')->plainTextToken;"
```

اس ٹوکن کو محفوظ رکھیں؛ یہ صارف کے تمام اختیارات کے ساتھ آتا ہے۔ الگ ایپ کے لیے الگ صارف (کم اختیارات والا رول) بنائیں۔

## 11.2.1 پہلی کال

```bash
TOKEN=1|xxxxxxxx
API=http://localhost:8000/api/v1/accounting
H=(-H "Accept: application/json" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json")

# is everything installed?
curl "${H[@]}" $API/health
```

## 11.3 واؤچر بنانا اور پوسٹ کرنا

```bash
# simplest entry: debit one account, credit another (posts at once)
curl "${H[@]}" -X POST $API/journal-entries/simple \
  -H "Idempotency-Key: rent-2026-10" \
  -d '{"debit_account_code":"5102","credit_account_code":"1101","amount":2500,"description":"Office rent"}'

# several lines, by account code
curl "${H[@]}" -X POST $API/journal-entries -d '{
  "entry_date": "2026-10-04", "reference": "INV-1001", "auto_post": true,
  "lines": [
    {"account_code": "1103", "debit": 1500, "credit": 0, "cost_center_code": "ADMIN"},
    {"account_code": "4101", "debit": 0, "credit": 1500}
  ]}'
```

- **`Idempotency-Key`**: ایک ہی درخواست دوبارہ بھیجی جائے (نیٹ ورک ٹوٹنے پر) تو سسٹم واؤچر دوبارہ نہیں بناتا بلکہ پہلا ہی لوٹاتا ہے (`Idempotent-Replayed: true`)۔
- `auto_post: true` — فوراً پوسٹ؛ ورنہ Draft۔ بعد میں `POST /journal-entries/{id}/post`۔
- منظوری کا نظام آن ہو تو `POST /journal-entries/{id}/submit` اور پھر approver `…/approve` یا `…/reject`۔
- غلطی درست کرنے کے لیے `…/reverse`؛ Draft کے لیے `…/void`۔

## 11.4 رپورٹیں

```bash
curl "${H[@]}" "$API/chart-of-accounts/tree"
curl "${H[@]}" "$API/reports/trial-balance"
curl "${H[@]}" "$API/reports/income-statement?date_from=2026-01-01&date_to=2026-12-31"
curl "${H[@]}" "$API/reports/balance-sheet?as_of_date=2026-12-31"
curl "${H[@]}" "$API/reports/general-ledger?account_id=1&per_page=50&page=2"
# download (same filters)
curl "${H[@]}" -o tb.csv "$API/reports/trial-balance/export/csv"
```

بڑی Excel/PDF پر جواب **202** کے ساتھ آتا ہے؛ اس کی فائل `GET /exports` سے ڈھونڈ کر `…/download` کریں۔

## 11.5 فہرستوں کے فلٹر، ترتیب اور صفحہ بندی

- فلٹر: `?filter[name]=cash&filter[status]=posted`
- ترتیب: `?sort=entry_date` یا الٹی `?sort=-entry_date`
- صفحہ: `?page=2&per_page=50` (زیادہ سے زیادہ `ACCOUNTING_API_MAX_PER_PAGE`، ڈیفالٹ 100)
- واؤچر پر اضافی: `filter[approval_status]=pending`، `filter[voucher_number]`، `filter[entry_date_from]`/`filter[entry_date_to]`، `include=lines`

## 11.6 غلطیاں

| کوڈ | مطلب |
|---|---|
| `401` | ٹوکن نہیں / غلط |
| `403` | اجازت نہیں |
| `404` | ریکارڈ نہیں ملا، یا اس **فیچر** کو Features میں بند کیا گیا ہے |
| `422` | ڈیٹا غلط (`errors` میں فیلڈ وار) یا حسابی اصول کی خلاف ورزی (`message` میں وجہ) |
| `429` | رفتار کی حد |

## 11.7 ماڈیول وار API — آپ کیا کر سکتے ہیں

| ماڈیول | مثالیں (راستہ `/api/v1/accounting` کے بعد) |
|---|---|
| **چارٹ اور ماسٹر ڈیٹا** | `/chart-of-accounts` (+ `/tree`, `/{id}/balance`, `/import`, `/export/{format}`, `/{id}/merge`, `/{id}/renumber`, `/{id}/control-type`), `/account-types`, `/currencies`, `/cost-centers`, `/voucher-types`, `/periods` (+ `/generate-monthly`, `/{id}/close-checklist`, `/{id}/close`, `/{id}/reopen`, `/{id}/close-fiscal-year`) |
| **واؤچر** | `/journal-entries` (+ `/simple`, `/{id}/post`, `/reverse`, `/void`, `/submit`, `/approve`, `/reject`, `/attachments`, `/pdf`) |
| **رپورٹیں** | `/reports/{trial-balance، balance-sheet، income-statement، general-ledger، cash-flow، bank-book، cash-book، aged-receivables، aged-payables، account-statement}` اور `/export/{csv\|xlsx\|pdf}` |
| **بینک** | `/bank-accounts`, `/reconciliations`, `/bank-statements` (+ `/import`, `/{id}/auto-match`, `/{id}/reconcile`) |
| **گاہک و سپلائر** | `/parties`, `/party-documents` (+ `/{id}/post`, `/{id}/void`, `/{id}/apply`), `/party-payments` (+ `/{id}/allocate`, `/{id}/void`), `/reports/aged-receivables` |
| **ٹیکس / FBR** | `/tax-codes`, `/tax-rates`, `/tax/calculate`, `/tax/entries`, `/tax/returns`, `/fbr`, `/fbr/documents/{id}/submit` |
| **بجٹ** | `/budgets` (+ `/{id}/approve`, `/{id}/close`, `/{id}/reopen`, `/{id}/copy`, `/{id}/export/{format}`) |
| **تکرار والے واؤچر / FX** | `/recurring-entries` (+ `/{id}/run`, `/{id}/pause`, `/{id}/resume`)، `/fx-revaluation` (+ `/preview`, `/rates`, `/{id}/reverse`) |
| **اثاثے / انوینٹری** | `/fixed-assets` (+ `/depreciation`, `/{id}/dispose`, `/export/{format}`)، `/inventory` (+ `/items`, `/warehouses`, `/movements`, `/export/{format}`) |
| **پے رول** | `/payroll/employees`, `/payroll/components`, `/payroll/grades`, `/payroll/runs` (+ `/{id}/recalculate`, `/post`, `/pay`, `/void`, `/submit`, `/withdraw`, `/approve`, `/reject`, `/bank-file/{format}`, `/email-payslips`), `/payroll/attendance`, `/payroll/leaves`, `/payroll/loans`, `/payroll/schemes`, `/payroll/arrears`, `/payroll/adjustments`, `/payroll/settlements`, `/payroll/reports`, `/payroll/tax` |
| **انتظام** | `/users`, `/roles`, `/permissions`, `/companies`, `/features`, `/account-balance-snapshots`, `/exports`, `/attachments` |

> تفصیلی فہرست (ہر endpoint کا طریقہ، اجازت اور مختصر وضاحت): [Help Catalog](../../help-catalog.md)۔ پیرامیٹر اور جوابات کی مکمل شکل: [`openapi.yaml`](../../openapi.yaml) — اسے Swagger UI/Redoc میں کھولیں یا Postman/Insomnia میں امپورٹ کریں۔

## 11.8 پے رول کا مکمل API مثال

```bash
# 1) create the month's run
curl "${H[@]}" -X POST $API/payroll/runs -d '{"period_month":"2026-10-01"}'

# 2) (when approval is on) HR submits, finance approves
curl "${H[@]}" -X POST $API/payroll/runs/12/submit
curl "${H[@]}" -X POST $API/payroll/runs/12/approve

# 3) post and pay
curl "${H[@]}" -X POST $API/payroll/runs/12/post
curl "${H[@]}" -X POST $API/payroll/runs/12/pay -d '{"account_id": 7, "date": "2026-10-31"}'

# 4) bank file and payslip e-mail
curl "${H[@]}" -o salary.csv "$API/payroll/runs/12/bank-file/csv?layout=standard"
curl "${H[@]}" -X POST $API/payroll/runs/12/email-payslips

# 5) Eid bonus for everybody: 50% of basic salary
curl "${H[@]}" -X POST $API/payroll/adjustments/bulk \
  -d '{"month":"2026-10-01","description":"Eid bonus","method":"percent_of_basic","value":50}'
```

## 11.9 فیچرز کا API

```bash
curl "${H[@]}" $API/features                                      # list
curl "${H[@]}" -X PUT $API/features -d '{"features":{"inventory":false,"payroll_loans":false}}'
```

بند فیچر کے endpoints پھر `404` دیں گے۔

## 11.10 ویب ہکس اور ایونٹس

ہر اہم واقعہ (واؤچر پوسٹ، رد، پیرئیڈ بند وغیرہ) پر Laravel Event چلتا ہے؛ ترتیب `accounting.webhooks.urls` میں ایڈریس دیں تو سسٹم دستخط (HMAC) کے ساتھ آپ کے سرور کو POST بھی کرتا ہے۔ وصول کنندہ دستخط جانچ کر درخواست کی تصدیق کرے۔

</div>
