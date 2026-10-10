<div dir="rtl" align="right">

# ضمیمہ: بقیہ اسکرینیں — نیا، تفصیل اور ترمیم کے صفحات

[← پچھلا باب](12-commands-faq.md) · [فہرست](README.md)

پچھلے ابواب میں ہر ماڈیول کی بنیادی اسکرینیں آ چکی ہیں۔ یہاں وہ **عمومی** صفحات ہیں جو کئی ماڈیولز میں اسی انداز سے دہرائے جاتے ہیں۔ ہر ماسٹر ڈیٹا (Account Types، Currencies، Periods، Cost Centers، Bank Accounts، Reconciliations، Tax Codes، Tax Rates) میں **چار** صفحے ہوتے ہیں:

| صفحہ | کب | کام |
|---|---|---|
| فہرست (Index) | ہمیشہ | تلاش/فلٹر، `Create` کا بٹن، ہر قطار سے تفصیل/ترمیم |
| نیا (Create) | `.create` اجازت | فارم بھر کر `Save` |
| تفصیل (Show) | `.view` اجازت | مکمل ریکارڈ، متعلقہ ڈیٹا |
| ترمیم (Edit) | `.update` اجازت | فارم میں تبدیلی؛ استعمال میں آ چکے ریکارڈ کے کچھ فیلڈ مقفل ہوتے ہیں |

حذف (`Delete`) کا بٹن صرف اسی ریکارڈ پر آتا ہے جو کہیں استعمال نہ ہوا ہو۔

## اکاؤنٹ ٹائپس

![نیا Account Type](../images/accounting-account-types-create.jpg)

![Account Type کی تفصیل](../images/accounting-account-types-show.jpg)

![Account Type میں ترمیم](../images/accounting-account-types-edit.jpg)

## کرنسیاں

![Currency کی تفصیل](../images/accounting-currencies-show.jpg)

![Currency میں ترمیم](../images/accounting-currencies-edit.jpg)

## مالی مدتیں

![نئی مدت](../images/accounting-periods-create.jpg)

![مدت کی تفصیل](../images/accounting-periods-show.jpg)

![مدت میں ترمیم](../images/accounting-periods-edit.jpg)

## کھاتے، بینک اکاؤنٹ، مفاہمت

![کھاتے میں ترمیم](../images/accounting-chart-of-accounts-edit.jpg)

![بینک اکاؤنٹ میں ترمیم](../images/accounting-bank-accounts-edit.jpg)

![مفاہمت میں ترمیم](../images/accounting-reconciliations-edit.jpg)

## ٹیکس کوڈ اور شرح

![Tax Code کی تفصیل](../images/accounting-tax-codes-show.jpg)

![Tax Code میں ترمیم](../images/accounting-tax-codes-edit.jpg)

![Tax Rate کی تفصیل](../images/accounting-tax-rates-show.jpg)

![Tax Rate میں ترمیم](../images/accounting-tax-rates-edit.jpg)

## Party، دہرائے جانے والے واؤچر، بیلنس اسنیپ شاٹ

![Customer/Supplier میں ترمیم](../images/accounting-parties-edit.jpg)

![Recurring Entry میں ترمیم](../images/accounting-recurring-entries-edit.jpg)

![Balance Snapshot کی تفصیل](../images/accounting-account-balance-snapshots-show.jpg)

## ذاتی ترتیبات

![Security](../images/security-edit.jpg)

![Appearance](../images/appearance-edit.jpg)

## ڈیٹا کے راستے جو براؤزر میں JSON دکھاتے ہیں

پے رول کے کچھ راستے (مثلاً `/accounting/payroll/loans/{id}`، `/payroll/grades/{id}`، `/payroll/schemes/{id}`، `/payroll/settlements/{id}`) اسکرین نہیں بلکہ **ڈیٹا کے راستے** ہیں: ان پر براؤزر میں JSON ملتا ہے۔ یہ اسکرینوں کے اندر کی ٹیبلوں اور API کو ڈیٹا دیتے ہیں؛ ان کی مکمل فہرست [Help Catalog](../../help-catalog.md) میں ہے۔

</div>
