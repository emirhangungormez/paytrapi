<?php

namespace PayTR;

class PaymentData
{
    public string $merchantOid;
    public string $userIp;
    public string $email;
    public float $paymentAmount; // TL cinsinden toplam tutar (Örn: 150.75)
    public string $userName;
    public string $userAddress;
    public string $userPhone;
    public string $merchantOkUrl;
    public string $merchantFailUrl;

    public string $currency = 'TL';
    public bool $noInstallment = false;
    public int $maxInstallment = 0; // 0: Sınırsız / Tümü
    public int $timeoutLimit = 30;  // Dakika
    public string $lang = 'tr';     // 'tr' veya 'en'
    public ?bool $testMode = null;  // null ise istemcinin test modu geçerli olur

    /** @var array<BasketItem> */
    public array $basketItems = [];

    public function __construct(
        string $merchantOid,
        float $paymentAmount,
        string $userIp,
        string $email,
        string $userName,
        string $userAddress,
        string $userPhone,
        string $merchantOkUrl,
        string $merchantFailUrl
    ) {
        $this->merchantOid = trim($merchantOid);
        $this->paymentAmount = round($paymentAmount, 2);
        $this->userIp = trim($userIp);
        $this->email = trim($email);
        $this->userName = trim($userName);
        $this->userAddress = trim($userAddress);
        $this->userPhone = trim($userPhone);
        $this->merchantOkUrl = trim($merchantOkUrl);
        $this->merchantFailUrl = trim($merchantFailUrl);
    }

    public function addItem(BasketItem $item): self
    {
        $this->basketItems[] = $item;
        return $this;
    }

    /**
     * Tutarın PayTR tarafından beklenen tam sayı kuruş karşılığını döndürür.
     * PHP float yuvarlama hatalarına karşı (int) round($amount * 100) kullanılır.
     */
    public function getAmountInKurus(): int
    {
        return (int) round($this->paymentAmount * 100);
    }

    /**
     * Sepeti PayTR'ın beklediği Base64 kodlanmış JSON dizisine dönüştürür.
     */
    public function toBasketJson(): string
    {
        $items = [];
        foreach ($this->basketItems as $item) {
            $items[] = $item->toArray();
        }

        // Eğer sepet boşsa ödeme tutarından tek bir varsayılan kalem üret
        if (empty($items)) {
            $items[] = ['Siparis', number_format($this->paymentAmount, 2, '.', ''), 1];
        }

        return base64_encode(json_encode($items, JSON_UNESCAPED_UNICODE));
    }
}
