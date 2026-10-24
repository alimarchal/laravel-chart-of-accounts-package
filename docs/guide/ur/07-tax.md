<div dir="rtl" align="right">

# باب 7: ٹیکس

[← پچھلا باب](06-parties.md) · [فہرست](README.md) · [اگلا باب: منصوبہ بندی →](08-planning.md)

## 7.1 ٹیکس کوڈ اور شرحیں

**Tax Code** ٹیکس کی قسم ہے (مثلاً `GST17` سیلز ٹیکس، Withholding)؛ ہر کوڈ کی **Tax Rate** تاریخ کے ساتھ درج ہوتی ہے تاکہ شرح بدلنے پر پرانے لین دین پرانی شرح ہی پر رہیں۔ کوڈ کی نوعیت: Output (فروخت پر واجب)، Input (خریداری پر قابل واپسی)، Withheld (کٹوتی) اور Advance (پیشگی ٹیکس)۔

![Tax Codes](../images/accounting-tax-codes-index.jpg)

![نیا Tax Code](../images/accounting-tax-codes-create.jpg)

![Tax Rates](../images/accounting-tax-rates-index.jpg)

![نئی شرح](../images/accounting-tax-rates-create.jpg)

## 7.2 ٹیکس والا اندراج (Taxed Document)

انوائس/بل کے بغیر بھی فوری ٹیکس والا اندراج بن سکتا ہے: `Tax` ← `Taxed Document`۔ قسم (Sale، Purchase، Credit/Debit Note، Withholding) منتخب کریں؛ رقم ٹیکس **شامل** (inclusive) یا **علاوہ** (exclusive) لکھیں؛ سسٹم Base، Tax اور Total الگ کر کے واؤچر بنا دیتا ہے (پیسے تک درست، کوئی فرق نہیں)۔

![Tax](../images/accounting-tax-index.jpg)

![ٹیکس والا دستاویز](../images/accounting-tax-entries-create.jpg)

## 7.3 ٹیکس رپورٹ اور ریٹرن

`Tax Report` کسی مدت کا Output ٹیکس، Input ٹیکس، کٹوتی شدہ ٹیکس اور **خالص قابل ادائیگی** دکھاتا ہے۔ ریٹرن تیار ہونے پر `Return` بنا کر ٹیکس کھاتے کو سرکاری ادائیگی کے کھاتے میں منتقل کیا جاتا ہے؛ ایک مدت کا ریٹرن دوبارہ نہیں بنتا۔

![Tax Report](../images/accounting-tax-returns-report.jpg)


</div>
