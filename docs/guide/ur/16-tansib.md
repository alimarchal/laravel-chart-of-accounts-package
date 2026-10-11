<div dir="rtl" align="right">

# باب 16: تنصیب — سوالات، ایڈمن یوزر اور مینو

[← پچھلا باب](15-whatsapp.md) · [فہرست](README.md)

## 16.1 تنصیب کے 3 قدم

```bash
composer require alimarchal/laravel-chart-of-accounts
```

پھر `app/Models/User.php` میں Spatie کا ٹریٹ لگائیں:

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;
}
```

اور انسٹالر چلائیں:

```bash
php artisan accounting:install
```

## 16.2 انسٹالر کیا پوچھتا ہے

انسٹالر خود سوال پوچھتا ہے اور جواب `.env` میں لکھ دیتا ہے، اس لیے ہاتھ سے کچھ بدلنا نہیں پڑتا:

| سوال | مطلب |
|------|------|
| Which screens do you want? | **Livewire / Blade**، **React / Inertia** یا صرف **REST API**۔ آپ کے اسٹارٹر کٹ کے مطابق خود تجویز دیتا ہے (Vue اور Svelte کٹ والے Blade چنیں) |
| Base currency | بنیادی کرنسی، مثلاً PKR |
| Which chart of accounts | ERPNext، عمومی یا **School chart** |
| Multi-company | کیا ایک سے زیادہ کمپنیوں کا حساب رکھنا ہے، اور پہلی کمپنی کا مختصر کوڈ |
| Maker-checker | کیا انٹری دوسرا شخص منظور کرے، کس رقم سے اوپر، اور کیا کوئی اپنی انٹری خود منظور کر سکے (مناسب نہیں) |
| URL of the accounting screens | صفحات کا پتہ، مثلاً `accounting`۔ `settings`، `dashboard`، `login` جیسے آپ کی ایپ کے اپنے نام **قبول نہیں** ہوتے |
| Email، name، password | ایڈمن یوزر۔ اگر یہ یوزر نہیں ہے تو انسٹالر **خود بنا دیتا ہے** (پاس ورڈ دو بار لکھیں، کم از کم 8 حروف) اور اسے `super-admin` دیتا ہے |

اس کے بعد ہر موضوع پر **ایک ہاں/نہیں** کا سوال آتا ہے: REST API، **WhatsApp**، **FBR**، ویب ہُک، پے رول، بجٹ اور بینک اسٹیٹمنٹ، PDF/ایکسپورٹ/اٹیچمنٹ، زبان، اور وہ ماڈیول جو آپ استعمال نہیں کریں گے۔ "ہاں" کہیں تو اس موضوع کی ساری سیٹنگز پوچھی جاتی ہیں (موجودہ یا ڈیفالٹ ویلیو سامنے دکھائی جاتی ہے؛ خالی چھوڑیں تو ڈیفالٹ رہتا ہے)۔ WhatsApp کا ٹوکن وغیرہ صرف `live` موڈ میں پوچھا جاتا ہے، اور ویب ہُک/ویریفائی ٹوکن آپ کے لیے خود بنایا جا سکتا ہے۔ سب کچھ ایک ساتھ پوچھنا ہو تو `--advanced` لگائیں۔

اس کے بعد انسٹالر ٹیبلز بناتا، کھاتوں کا چارٹ، کرنسی، مالی سال، رولز اور ٹیکس کوڈ بھر دیتا اور جانچ کرتا ہے۔ دوبارہ چلانا محفوظ ہے۔

## 16.3 مینو خود لگ جاتا ہے

انسٹالر آپ کے اسٹارٹر کٹ کے سائیڈ بار میں **Dashboard** کے برابر **Accounting** کا لنک خود لگا دیتا ہے:

- **Livewire:** `resources/views/layouts/app/sidebar.blade.php`
- **React:** `resources/js/components/app-sidebar.tsx` اور `app-header.tsx`

Blade کے لیے `resources/css/app.css` میں پیکیج کے صفحات کا Tailwind source بھی لگ جاتا ہے؛ ایک بار `npm run build` چلا دیں۔ مینو نہ چاہیے تو `--no-menu` لگائیں۔

## 16.4 لاگ اِن کیسے کریں

1. براؤزر میں اپنی ایپ کا `/login` کھولیں (لاگ اِن آپ کے اسٹارٹر کٹ کا ہے، پیکیج کا نہیں)۔
2. وہی ای میل اور پاس ورڈ لکھیں جو انسٹالر میں دیا تھا۔
3. سائیڈ بار میں **Accounting** پر دبائیں، یا `/accounting` کھولیں۔

## 16.5 بغیر سوال کے (اسکرپٹ سے)

```bash
php artisan accounting:install --no-interaction --ui=blade --currency=PKR --chart=school \
  --prefix=accounting --admin-email=you@school.test --admin-name="Ali" --admin-password="مضبوط-پاس-ورڈ"
```

`--setup-only` صرف سوال، `.env`، ایڈمن یوزر اور مینو کرتا ہے (ٹیبلز دوبارہ نہیں بناتا)۔ `--no-env` سے `.env` کو چھوا نہیں جاتا۔

## 16.6 عام مسئلہ: "Allowed memory size exhausted" (500)

پرانے ورژن میں Livewire اسٹارٹر کٹ پر ہر Blade صفحہ میموری ختم ہو کر 500 دیتا تھا۔ **ورژن 2.36.0 میں یہ ٹھیک ہے**: `composer update alimarchal/laravel-chart-of-accounts` چلائیں، پھر `php artisan view:clear`۔ صفحات اب Livewire کٹ کی اپنی لے آؤٹ (سائیڈ بار سمیت) میں کھلتے ہیں۔ اپنی لے آؤٹ منتخب کرنی ہو تو `.env` میں `ACCOUNTING_BLADE_LAYOUT=layouts::app` لکھیں۔

</div>
