<div dir="rtl" align="right">

# باب 12: کمانڈیں، عام سوالات اور اصطلاحات

[← پچھلا باب](11-api.md) · [فہرست](README.md) · [ضمیمہ →](13-zameema.md)

## 12.1 Artisan کمانڈیں

سرور کے ٹرمنل میں پروجیکٹ کے فولڈر میں چلائیں:

| کمانڈ | کام |
|---|---|
| `php artisan accounting:install` | پیکیج کی تنصیب: سوالات، `.env`، ایڈمن یوزر، مائیگریشن، رولز، ڈیفالٹ ڈیٹا اور سائیڈ بار کا مینو (`--chart=erpnext\|general\|school`، `--ui`، `--currency`، `--prefix`، `--admin-email`، `--setup-only`) (باب 16) |
| `php artisan accounting:update` | اپ ڈیٹ کے بعد نئی مائیگریشن/اجازتیں لگانا (`--views` سے شائع شدہ Blade اسکرینیں بھی نئی) |
| `php artisan accounting:seed` | ڈیفالٹ چارٹ/اقسام/کرنسیاں لگانا (`--company=` کے ساتھ ایک کمپنی کے لیے) |
| `php artisan accounting:health-check` | جانچ کہ سب کچھ درست انسٹال ہے (`503` اگر نہیں) |
| `php artisan accounting:verify` | حسابات کی سالمیت کی جانچ: ہر واؤچر متوازن، ٹرائل بیلنس برابر |
| `php artisan accounting:roles` | رولز اور اجازتیں دکھانا/لگانا (`--config` سے ڈیفالٹ) |
| `php artisan accounting:create-company` | نئی کمپنی بنانا (Multi-company) |
| `php artisan accounting:close-period {id}` | مہینے کی کلوزنگ |
| `php artisan accounting:open-period {id}` | بند مدت دوبارہ کھولنا |
| `php artisan accounting:close-fiscal-year {id}` | سال کے اختتام کی کلوزنگ |
| `php artisan accounting:rebuild-snapshots` | بیلنس اسنیپ شاٹ دوبارہ بنانا |
| `php artisan accounting:run-recurring` | دہرائے جانے والے واؤچر بنانا (شیڈولر روزانہ خود چلاتا ہے) |
| `php artisan accounting:payroll-run` | پچھلے مہینے کا پے رول Run (Draft) بنانا |
| `php artisan accounting:account-roles` | کون سا کھاتہ کس کام (کیش، بینک، تنخواہ ...) کے لیے ہے: فہرست، `set`، `seed` (باب 14) |
| `php artisan accounting:whatsapp` | WhatsApp کی سیٹنگ دیکھیں (`status`)، آزمائشی پیغام (`test +92300…`)، ٹیمپلیٹ فہرست (`templates`) (باب 15) |
| `php artisan accounting:features` | فیچرز کی فہرست / `enable` / `disable` / `reset` |
| `php artisan accounting:chart-templates` | صنعتی چارٹ ٹیمپلیٹ لگانا |
| `php artisan accounting:prune-exports` | پرانی ایکسپورٹ فائلیں صاف کرنا |
| `php artisan accounting:sync-db-objects` | ڈیٹا بیس کی سطح کے اصول (triggers) دوبارہ ہم آہنگ کرنا |

**شیڈولر:** سرور کی cron میں `* * * * * php /path/artisan schedule:run` ہونا چاہیے۔ اس سے Recurring اور (اگر آن ہو) خودکار پے رول Run چلتے ہیں۔

## 12.2 عام سوالات

**س: واؤچر پوسٹ نہیں ہو رہا — "period is closed" یا "not balanced"؟**
ج: تاریخ جس مدت میں ہے وہ بند ہے (`Periods` میں دیکھیں، یا کھولیں)، یا Debit ≠ Credit۔

**س: میں کسی ماڈیول (مثلاً Inventory) کا لنک نہیں دیکھ پا رہا؟**
ج: دو وجوہات: (1) آپ کے رول کے پاس اس کی `view` اجازت نہیں؛ (2) **Features** میں وہ فیچر بند ہے۔ بند ہو تو ایڈمن سے `Features` میں آن کروائیں۔

**س: پوسٹ شدہ واؤچر میں غلطی ہو گئی؟**
ج: ایڈٹ/ڈیلیٹ ممکن نہیں۔ `Reverse` کریں اور درست واؤچر نیا بنائیں۔

**س: دو لوگوں نے ایک ہی بل کا واؤچر بنا دیا؟**
ج: Source document (قسم+نمبر) کی وجہ سے دوسرا واؤچر بنتا ہی نہیں؛ سسٹم پہلے والے کا لنک دکھاتا ہے۔

**س: پے رول Run میں کسی ملازم کا نام نہیں؟**
ج: وہ فعال (Active) نہیں، یا اس کی `Joined` تاریخ اس مہینے کے بعد ہے، یا `Left` تاریخ مہینے کے آغاز سے پہلے ہے۔

**س: Run میں "negative net salary" کی غلطی؟**
ج: کٹوتیاں (قرض کی قسط، شراکتیں، جرمانے) تنخواہ سے زیادہ ہیں۔ قسط کو `Skip next` کریں، یا کٹوتی گھٹائیں، پھر `Recalculate`۔

**س: میں نے Run Post کر دیا، اب حاضری یا بونس بدلنا ہے؟**
ج: Post شدہ مہینے کی حاضری/بونس نہیں بدلتی۔ `Void` کریں (approver)، تبدیلی کریں، پھر نیا Run بنائیں؛ یا اگلے مہینے میں بونس/جرمانہ ڈالیں۔

**س: پے سلپ ای میل نہیں گئی؟**
ج: ملازم کا `E-mail` درج نہیں ہوگا — وہ "skipped" میں شمار ہوتا ہے۔ سرور پر ای میل (SMTP) کی ترتیب بھی چیک کریں۔

**س: API 404 دیتی ہے لیکن راستہ درست ہے؟**
ج: متعلقہ فیچر `Features` میں بند ہوگا (404 کا مطلب "یہ فیچر موجود نہیں")۔

**س: انگریزی الفاظ اردو اسکرین پر آ رہے ہیں؟**
ج: کچھ فقرے ابھی ترجمہ کی فہرست (`resources/lang/ur.json`) میں نہیں۔ فہرست میں انگریزی فقرے کے سامنے اردو لکھ دیں؛ اسکرین پر وہ فوراً بدل جائے گا۔

## 12.3 اہم اصطلاحات

| انگریزی | اردو مفہوم |
|---|---|
| Journal Entry / Voucher | جرنل اندراج / واؤچر |
| Debit / Credit | ڈیبٹ (مدین) / کریڈٹ (دائن) |
| Chart of Accounts | کھاتوں کا چارٹ |
| Posting / Post | کھاتوں میں درج کرنا |
| Reverse / Void | الٹانا / منسوخ کرنا |
| Draft | مسودہ (ابھی درج نہیں) |
| Period / Fiscal Year | مالی مدت / مالی سال |
| Trial Balance | ٹرائل بیلنس (کھاتوں کا میزان) |
| Balance Sheet | بیلنس شیٹ (مالی حیثیت کا گوشوارہ) |
| Income Statement | آمدنی کا گوشوارہ (منافع و نقصان) |
| Cash Flow | نقدی کا بہاؤ |
| Receivable / Payable | وصولی / قابل ادائیگی (ادھار) |
| Ageing | بقایا کی عمر کا تجزیہ |
| Reconciliation | مفاہمت (بینک سے ملانا) |
| Accrual / Prepayment | واجب الادا خرچ / پیشگی ادا شدہ |
| Depreciation | فرسودگی |
| Gratuity | گریجویٹی |
| Arrears | بقایا جات |
| Payslip | پے سلپ (تنخواہ کی پرچی) |
| Gross / Net | مجموعی / خالص |
| Withholding Tax | روکا ہوا (کٹوتی شدہ) ٹیکس |
| Maker–Checker | بنانے والا اور جانچنے والا (دو آنکھیں) |
| Permission / Role | اجازت / رول |
| Feature switch | فیچر آن/آف کا سوئچ |

</div>
