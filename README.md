# PayTR Güvenli Ödeme Geçidi (Payment Gateway) PHP SDK

[![PHP Version](https://img.shields.io/badge/php-%3E%3D%208.0-8892BF.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Zero Dependencies](https://img.shields.io/badge/dependencies-zero-blue.svg)](#)
[![Encryption](https://img.shields.io/badge/HMAC--SHA256-Strict-orange.svg)](#)
[![Protocol](https://img.shields.io/badge/Protocol-REST%20%2F%20POST-purple.svg)](#)

Türkiye'nin en popüler lisanslı sanal POS ve ödeme kuruluşu **PayTR** için hazırlanmış; **sıfır bağımlılığa (zero-dependency)** sahip, harici HTTP kütüphanelerine (Guzzle vb.) ihtiyaç duymadan yerleşik PHP `cURL` motoruyla çalışan, tip güvenli (type-safe) ve dayanıklı modern bir PHP SDK'sıdır.

Hem **insan yazılımcılar** hem de **AI kodlama asistanları** (Gemini, Claude, Cursor, Copilot vb.) için en ideal, temiz ve prodüksiyon kalitesinde PayTR entegrasyon yapısını sunar.

---

## 🚀 Önemli Özellikler

1. **Sıfır Dış Bağımlılık:** Herhangi bir Composer paketine veya framework'e ihtiyaç duymaz. PHP native `ext-curl` ve `ext-json` ile hafif, hızlı ve bağımsız çalışır.
2. **Kusursuz HMAC-SHA256 İmzası:** PayTR'ın beklediği katı parametre sıralamasını ve `merchant_salt` / `merchant_key` gizli anahtar türetimini arka planda hatasız yönetir.
3. **Akıllı Kuruş & Float Güvenliği:** PHP'nin kayan noktalı sayı (float) dönüşüm hatalarını (`(int) (19.99 * 100)` -> `1998` problemi) önleyen `(int) round($amount * 100)` matematiksel hassasiyet motoruna sahiptir.
4. **Prodüksiyon Düzeyinde Webhook / Callback Güvenliği:**
   - Timing-attack (zamanlama saldırısı) korumalı `hash_equals()` doğrulaması.
   - Sadece durum kodunu değil, ödenen kuruş tutarı ile sipariş tutarını karşılaştıran çifte güvenlik denetimi.
   - PayTR'ın tekrarlayan bildirimlerinde çift stok düşümünü veya mükerrer işlem yapılmasını önleyen **idempotency** koruması.
5. **iFrame Resizer Entegrasyonu:** Müşteri ödeme ekranının mobil ve masaüstü tarayıcılarda kaydırma çubuğu (scrollbar) olmadan esnek ve tam boy açılmasını sağlayan hazır HTML şablon motoru.
6. **Tam ve Kısmi İade (Refund) Desteği:** Sipariş iptallerinde veya iadelerde tek metotla PayTR iade API'sini tetikleme ve referans takibi.
7. **Banka Taksit Oranları & Komisyon Sorgulama:** Mağazanıza tanımlı kart ailelerinin (Bonus, World, Maximum, Axess, CardFinans, Paraf vb.) taksit katsayılarını listeleme.
8. **BIN Detay Sorgulama:** Kartın ilk 6 veya 8 hanesinden kart tipini (Kredi/Banka/Ön Ödemeli), bankasını ve şemasını (Troy, Visa, Mastercard) öğrenme.
9. **Gerçek İstemci IP Çözümleyici:** Cloudflare (`CF-Connecting-IP`) veya ters vekiller (Reverse Proxy / Nginx) arkasındaki gerçek kullanıcı IP adresini otomatik tespit etme.
10. **PSR-3 Loglama Desteği:** Monolog, Laravel Log veya herhangi bir PSR-3 uyumlu log motoruyla entegre hata ve güvenlik izleme.

---

## ⚠️ En Büyük Entegrasyon Tuzakları ve Sahada Çözümleri

Gerçek e-ticaret projelerinde PayTR entegrasyonu yapan yazılımcıların karşılaştığı en yaygın hatalar ve bu SDK'nın sunduğu çözümler:

### 1. Token Oluştururken HMAC-SHA256 Parametre Sıralaması
* **Tuzak:** `get-token` API'sinde hash hesaplanırken birleştirilen string'in sırası şöyledir:  
  `$merchant_id . $user_ip . $merchant_oid . $email . $payment_amount . $user_basket . $no_installment . $max_installment . $currency . $test_mode`  
  Ardından bu metnin sonuna `$merchant_salt` eklenerek `$merchant_key` ile `sha256` HMAC alınır. Bu sıralamada tek bir alanın yeri değişirse veya parametre eksik olursa PayTR `PAYTR TOKEN GECERSIZ` veya `HASH HATASI` döndürür.
* **SDK Çözümü:** SDK, `PaymentData` DTO nesnesi üzerindeki alanları bu katı sıraya göre otomatik olarak birleştirir ve güvenle şifreler.

### 2. `user_basket` JSON ve Base64 Kodlama Tuzağı
* **Tuzak:** Sepet içeriği `[[Ürün Adı, Fiyat, Adet], ...]` biçiminde iki boyutlu dizi olmalıdır. Fiyat mutlaka noktalı ondalık (`100.50`) olmalı, Türkçe karakterler bozulmamalı (`JSON_UNESCAPED_UNICODE`) ve çıktı `base64_encode` edilmelidir.
* **SDK Çözümü:** `BasketItem` sınıfı ve `PaymentData::toBasketJson()` metodu bu süreci otomatik yönetir. Sepet boş olsa bile sipariş tutarından geçerli bir varsayılan sepet satırı türeterek API'nin reddetmesini önler.

### 3. Tutar Birimi Karmaşası (Kuruş vs TL) & Float Yuvarlama
* **Tuzak:**
  - Token alırken `payment_amount` değeri **kuruş cinsinden tam sayı** (`150.50 TL` -> `15050`) olmalıdır.
  - İade (refund) yaparken ise `return_amount` **noktalı TL string'i** (`150.50`) olmalıdır!
  - Ayrıca PHP'de `(int) (19.99 * 100)` işlemi IEEE-754 kayan nokta temsili nedeniyle `1998` olarak hesaplanabilir; bu da faturada 1 kuruş eksikliğe yol açar.
* **SDK Çözümü:** SDK, `(int) round($amount * 100)` formülüyle kuruş dönüşümlerini hatasız yapar. İadelerde ise `number_format($amount, 2, '.', '')` standardını uygular.

### 4. Webhook Tutar Denetimi & İdempotency (Tekrarlayan Bildirimler)
* **Tuzak:** Birçok geliştirici webhook geldiğinde yalnızca `$post['status'] === 'success'` kontrolü yapar. Kötü niyetli biri araya girip 1000 TL'lik siparişi 10 TL ile ödemeyi denerse ve tutar doğrulanmazsa sipariş onaylanabilir. Ayrıca PayTR, `OK` cevabı alana kadar bildirimi yineler; eğer sipariş zaten ödenmişse tekrar stok düşülmesi veya mükerrer bildirim gönderilmesi yaşanır.
* **SDK Çözümü:** `verifyCallback()` metodu hem HMAC imzasını hem de ödenen kuruş tutarını beklenen sipariş tutarıyla kuruşu kuruşuna karşılaştırır. Sipariş zaten ödenmişse hemen `OK` yanıtı döndürülmesini tavsiye eden idempotent mimari sunulur.

### 5. Callback Yanıtında `OK` Dışında Çıktı Bulunması
* **Tuzak:** Webhook dosyası içinde `echo "OK";` yazarken dosya başında görünmeyen bir boşluk (BOM karakteri), satır atlama veya HTML bulunursa PayTR bildirimi başarısız sayar ve saatlerce tekrar dener.
* **SDK Çözümü:** `examples/04_callback_webhook_handler.php` dosyasında yalnızca saf `echo "OK"; exit;` çıktısı üreten kesin standart sunulmuştur.

### 6. Ters Vekil / Cloudflare Arkasında Kullanıcı IP'sinin Kaybolması
* **Tuzak:** PayTR ödeme anında kullanıcının gerçek IP'sini (`user_ip`) doğrular. Sunucuda Cloudflare veya Nginx proxy varsa ve `$_SERVER['REMOTE_ADDR']` kullanılırsa, tüm siparişler proxy IP'siyle gider ve PayTR 3D Secure veya fraud filtreleri devreye girer.
* **SDK Çözümü:** `PayTRClient::resolveClientIp()` metodu `HTTP_CF_CONNECTING_IP`, `HTTP_X_FORWARDED_FOR` ve `HTTP_X_REAL_IP` başlıklarını önceliklendirerek gerçek istemci IP'sini güvenle çözer.

---

## ⚙️ Kurulum

Kütüphaneyi projenize Composer kullanarak dahil edebilirsiniz:

```bash
composer require emirhangungormez/paytrapi
```

---

## 📖 Temel Kullanım Senaryoları

### 1. İstemciyi Başlatma & Bağlantı Testi

```php
<?php

use PayTR\PayTRClient;
use PayTR\PayTRException;

require_once 'vendor/autoload.php';

$client = new PayTRClient([
    'merchant_id'   => 'YOUR_MERCHANT_ID',
    'merchant_key'  => 'YOUR_MERCHANT_KEY',
    'merchant_salt' => 'YOUR_MERCHANT_SALT',
    'test_mode'     => true, // Test modu için true, canlı için false
    'verify_ssl'    => true,
    'timeout'       => 30
]);

try {
    $check = $client->testConnection();
    echo "Bağlantı: " . $check['message'] . " (Mod: " . $check['mode'] . ")\n";
} catch (PayTRException $e) {
    echo "Hata: " . $e->getMessage();
}
```

---

### 2. iFrame Ödeme Tokeni Alma ve Gömme (Checkout)

```php
<?php

use PayTR\PayTRClient;
use PayTR\PaymentData;
use PayTR\BasketItem;

$client = new PayTRClient([/* config */]);

// 1. Ödeme Bilgileri DTO'sunu Tanımlama
$payment = new PaymentData(
    merchantOid: 'SIPARIS-' . time(),
    paymentAmount: 450.75, // TL Cinsinden Toplam Sipariş Tutarı
    userIp: PayTRClient::resolveClientIp(), // Gerçek kullanıcı IP'si
    email: 'ahmet@example.com',
    userName: 'Ahmet Yılmaz',
    userAddress: 'Atatürk Cad. No:10 Daire:4 Kadıköy İstanbul',
    userPhone: '05551234567',
    merchantOkUrl: 'https://www.siteniz.com/odeme/basarili',
    merchantFailUrl: 'https://www.siteniz.com/odeme/basarisiz'
);

// 2. Sepet Kalemleri
$payment->addItem(new BasketItem('Kablosuz Kulaklık Siyah', 350.00, 1));
$payment->addItem(new BasketItem('Hızlı Şarj Adaptörü', 100.75, 1));

// 3. İsteğe Bağlı Ayarlar
$payment->noInstallment = false; // Taksit seçeneği açık
$payment->maxInstallment = 12;   // En fazla 12 taksit
$payment->currency = 'TL';

// 4. Token Talebi
$result = $client->createIframeToken($payment);

echo "Token: " . $result['token'] . "\n";
echo "Doğrudan iFrame URL: " . $result['iframe_url'] . "\n";

// 5. Blade / PHP View İçinde iFrame HTML Çıktısı Üretme:
echo $client->renderIframeHtml($result['token']);
```

---

### 3. Güvenli Webhook / Callback (Bildirim URL) İşleyicisi

PayTR'ın ödeme sonucunu bildirdiği endpoint'te uygulanması gereken referans kod:

```php
<?php

use PayTR\PayTRClient;
use PayTR\PayTRException;

$client = new PayTRClient([/* config */]);
$post = $_POST;

try {
    // 1. Veritabanından siparişin beklenen tutarını çekin
    $orderNumber = $post['merchant_oid'] ?? '';
    $expectedOrderAmountTl = 450.75; // Veritabanından gelen tutar

    // 2. İmza (Hash) ve Kuruş Tutarı Doğrulama
    $verification = $client->verifyCallback($post, $expectedOrderAmountTl);

    if (!$verification['valid']) {
        // Tutar uyuşmuyor veya işlem başarısız!
        // Siparişi iptal veya inceleme durumuna alın.
        exit($client->okResponse());
    }

    // 3. İdempotency: Sipariş zaten 'paid' ise tekrar işlem yapmayın
    /*
    if ($order->isAlreadyPaid()) {
        exit($client->okResponse());
    }
    */

    // 4. Ödeme Başarılı!
    // $order->markAsPaid();
    // $inventory->deduct();

    // 5. PayTR'a MUTLAKA "OK" YANITI VERİN:
    echo $client->okResponse();
    exit;

} catch (PayTRException $e) {
    // Hash uyuşmazlığı durumunda 400 Bad Request dönün
    http_response_code(400);
    exit($e->getMessage());
}
```

---

### 4. İade / Refund İşlemi (Tam veya Kısmi)

Müşterinin kredi kartına ödeme iadesi yapılması:

```php
<?php
use PayTR\PayTRClient;

$client = new PayTRClient([/* config */]);

// Sipariş referansı ve iade edilecek TL tutarı
$response = $client->refund(
    merchantOid: 'SIPARIS-10025',
    returnAmount: 150.50, // Kısmi veya tam iade tutarı
    referenceNo: 'IADE-' . time() // İsteğe bağlı takip kodu
);

echo "İade Durumu: " . $response['status'] . "\n";
echo "İade Edilen Tutar: " . $response['return_amount'] . " TL\n";
```

---

### 5. Taksit Oranları ve Komisyonları Listeleme

```php
<?php
use PayTR\PayTRClient;

$client = new PayTRClient([/* config */]);

// Banka kart ailelerinin taksit katsayılarını çekme
$installmentRates = $client->getInstallmentRates();
print_r($installmentRates);
```

---

### 6. Kart BIN Numarası Sorgulama

```php
<?php
use PayTR\PayTRClient;

$client = new PayTRClient([/* config */]);

// Kartın ilk 6 veya 8 hanesi
$binData = $client->getBinDetail('552879');

echo "Banka: " . ($binData['bank'] ?? 'N/A') . "\n";
echo "Kart Ailesi: " . ($binData['cardFamily'] ?? 'N/A') . "\n";
echo "Kart Tipi: " . ($binData['cardType'] ?? 'N/A') . " (Kredi/Banka)\n";
```

---

## 🔒 Güvenlik Notları ve En İyi Uygulamalar

1. **CSRF Korumasından Muafiyet:** PayTR bildirim URL'niz (`/paytr/callback`) harici bir sunucudan POST aldığı için framework'ünüzün CSRF koruma filtresinden hariç tutulmalıdır.
2. **Timing Attack Savunması:** Callback doğrulamalarında PHP'nin `==` operatörü yerine sabit zamanlı karşılaştırma yapan `hash_equals()` fonksiyonu kullanılır.
3. **Çifte Tutar Doğrulaması:** Yalnızca hash doğrulamak yetersizdir; gelen `total_amount` değeri ile sisteminizdeki sipariş tutarı kuruş cinsinden eşitlenmelidir.
4. **Content-Security-Policy (CSP):** Sitenizde CSP başlığı kullanılıyorsa bankaların 3D Secure doğrulama iframe'lerinin engellenmemesi için `frame-src` yönergesine `https://www.paytr.com` ve ilgili banka alan adları dahil edilmelidir.
5. **Kimlik Bilgisi İzolasyonu:** `merchant_id`, `merchant_key` ve `merchant_salt` değerlerinizi asla Git depolarına eklemeyin; `.env` dosyası üzerinden okuyun.

---

## 📜 Lisans

Bu proje **MIT Lisansı** ile lisanslanmıştır. Dilediğiniz gibi kişisel veya ticari projelerinizde kullanabilir, değiştirebilir ve dağıtabilirsiniz.
