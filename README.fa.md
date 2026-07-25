# PHP Payment (فارسی)

> این نسخه‌ی فارسیِ [README.md](README.md) است. برای مستندات کامل و به‌روز، همیشه نسخه‌ی انگلیسی مرجع اصلی است.

یک پکیج PHP مستقل از فریم‌ورک برای کار با درگاه‌های پرداخت (زرین‌پال، آیدی‌پی،
پی، جیبیت، سپ، پی‌پینگ، بهامتا، پی‌استار و ده‌ها درگاه دیگر) با پشتیبانی
اختصاصی از **Laravel** و **Symfony** و امکان استفاده در PHP ساده.

## نصب

```bash
composer require mbpcoder/php-payment
```

نیازمند PHP نسخه ۸.۴ یا بالاتر.

## معماری

هسته‌ی پکیج (`MbpCoder\Payment\PaymentChannelService` و درایورهای درگاه‌ها)
هیچ وابستگی‌ای به فریم‌ورک خاصی ندارد. دو نقطه‌ی اتصال کوچک این قابلیت
حمل‌پذیری را فراهم می‌کنند:

- **Config** – کلاس `MbpCoder\Payment\Config\Config` یک accessor استاتیک است
  که روی یک `ConfigRepositoryInterface` قابل تعویض کار می‌کند. هر یکپارچه‌سازی
  (Laravel/Symfony) آن را به تنظیمات خودِ فریم‌ورک متصل می‌کند.
- **Redirect** – کلاس `MbpCoder\Payment\Support\Redirect` ریدایرکت‌های درگاه
  را از طریق یک handler قابل تعویض resolve می‌کند تا هر فریم‌ورک شیء
  ریدایرکت بومی خودش را دریافت کند.

## استفاده (در هر فریم‌ورکی)

```php
use MbpCoder\Payment\PaymentChannelService;

$payment = new PaymentChannelService('zarinpal'); // یا null برای انتخاب خودکار
$response = $payment->initial(amount: 10000, trackingCode: 'order-42');

if ($response->isSuccess()) {
    return $payment->pay($response->paymentToken); // ریدایرکت به درگاه
}
```

اگر نام درگاه را پاس ندهید (`null` یا سازنده‌ی بدون آرگومان)، نیازی نیست نام
درگاه به کلاس Manager/Service پاس داده شود؛ به‌جای آن، انتخاب به‌صورت خودکار
و بر اساس درگاه‌های **فعال (enabled)** و **وزن (weight)** هرکدام انجام می‌شود
(به بخش زیر مراجعه کنید).

## فعال/غیرفعال‌سازی درگاه‌ها و توزیع خودکار بر اساس وزن

هر درگاه در `config/channels.php` دو کلید دارد:

```php
'zarinpal' => [
    'enabled' => env('ZARINPAL_ENABLED', true),
    'weight' => env('ZARINPAL_WEIGHT', 1),
    // ...
],
```

- **enabled**: مشخص می‌کند آیا این درگاه در چرخه‌ی انتخاب خودکار شرکت می‌کند
  یا نه. مقدار `false` آن را کاملاً از استخر انتخاب حذف می‌کند.
- **weight**: یک عدد صحیح که سهم نسبی آن درگاه از کل درخواست‌ها را تعیین
  می‌کند. برای مثال اگر `zarinpal` وزن `10` و `pay` وزن `90` داشته باشند
  (هر دو فعال، مجموع وزن‌ها `100`)، به‌طور میانگین حدود ۱۰٪ درخواست‌ها به
  `zarinpal` و ۹۰٪ به `pay` می‌رود.

این انتخاب کاملاً **ریاضی و لحظه‌ای** است (با `random_int()` در PHP) و هیچ
دیتابیس یا وضعیت ذخیره‌شده‌ای درگیر نیست — هر بار که سرویس بدون نام درگاه
ساخته شود، یک انتخاب تصادفیِ وزن‌دار جدید انجام می‌شود. اگر هیچ درگاهی فعال
نباشد، به نام استاتیکِ `channels.ipg.default` به‌عنوان fallback بازمی‌گردد.

### تغییر enabled/weight در زمان اجرا (Runtime)

کلاس `MbpCoder\Payment\GatewayWeightRegistry` امکان override کردن
`enabled`/`weight` را در زمان اجرا، روی تنظیمات `config/channels.php`،
بدون نیاز به دیتابیس فراهم می‌کند:

```php
use MbpCoder\Payment\GatewayWeightRegistry;

GatewayWeightRegistry::disable('pay');
GatewayWeightRegistry::enable('zarinpal');
GatewayWeightRegistry::setWeight('zarinpal', 10);
GatewayWeightRegistry::setWeights(['zarinpal' => 10, 'jibit' => 90]); // به‌صورت گروهی

// از همین لحظه، هر `new PaymentChannelService()` جدید این تغییرات را می‌بیند.
```

این override‌ها فقط در حافظه‌ی پردازش (یک آرایه‌ی static) نگه‌داری می‌شوند —
هیچ دیتابیس یا نوشتن روی فایلی درکار نیست، صرفاً یک لایه‌ی runtime روی
تنظیمات است. در PHP-FPM/CLI کلاسیک این یعنی override فقط برای همان
request/process فعلی اعمال می‌شود؛ اگر می‌خواهید بین request ها هم باقی
بماند باید آن را در هر boot دوباره تنظیم کنید (مثلاً از تنظیمات ادمین یا
کش خودتان)، یا اگر روی یک worker پایدار (Octane، RoadRunner، Swoole) اجرا
می‌کنید، کافی است یک‌بار تنظیم شود. برای بازگشت به تنظیمات استاتیک از
`GatewayWeightRegistry::clear('<name>')` یا `::reset()` استفاده کنید.

این روش در Laravel، Symfony یا PHP ساده یکسان است — چون فقط متدهای static
هستند، مستقیماً از هرجایی که برنامه‌تان مقدار enabled/weight را تعیین
می‌کند صدا بزنید (یک کنترلر ادمین، یک دستور Artisan،
`AppServiceProvider::boot()` و غیره)، بدون نیاز به facade یا binding
اضافه در کانتینر.

## درگاه‌های پشتیبانی‌شده

Zarinpal، IDPay، Pay.ir، Jibit، Sep، PayPing، Bahamta، PayStar و بیش از ۲۵
درگاه دیگر (Vandar، Zibal، BehPardakht، AsanPardakht، Sadad و غیره). فهرست
کامل و نحوه‌ی تنظیم هرکدام در [README.md](README.md#supported-gateways)
آمده است.

## مستندات کامل

برای جزئیات یکپارچه‌سازی با Laravel، Symfony، PHP ساده، refund، و سایر
موارد، به نسخه‌ی انگلیسیِ اصلی مراجعه کنید: [README.md](README.md).
