<?php

namespace PayTR;

class BasketItem
{
    public string $name;
    public float $price;
    public int $quantity;

    /**
     * @param string $name Ürün veya hizmet adı
     * @param float $price Birim satış fiyatı (TL cinsinden, örn: 150.50)
     * @param int $quantity Adet
     */
    public function __construct(string $name, float $price, int $quantity = 1)
    {
        $this->name = trim($name);
        $this->price = round($price, 2);
        $this->quantity = max(1, $quantity);
    }

    /**
     * PayTR sepet formatına dönüştürür: [Ürün Adı, Fiyat, Adet]
     *
     * @return array{0: string, 1: string, 2: int}
     */
    public function toArray(): array
    {
        return [
            $this->name,
            number_format($this->price, 2, '.', ''),
            $this->quantity
        ];
    }
}
