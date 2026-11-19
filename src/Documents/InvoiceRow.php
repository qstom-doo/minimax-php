<?php

namespace Qstom\Minimax\Documents;

use Qstom\Minimax\Ref;

/**
 * One row of an issued invoice (IssuedInvoiceRow). Prices are net unless
 * the invoice has PricesOnInvoice D.
 */
final readonly class InvoiceRow
{
    public function __construct(
        public int $itemId,
        public string $itemName,
        public float $quantity,
        public float $price,
        public int $vatRateId,
        public float $vatPercent,
        public string $unitOfMeasurement = '',
        public ?float $discountPercent = null,
        public ?string $description = null,
        public ?string $itemCode = null,
    ) {}

    /**
     * Row value: quantity × price, less the discount, rounded to cents.
     */
    public function value(): float
    {
        return round($this->quantity * $this->price * (1 - ($this->discountPercent ?? 0) / 100), 2);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(int $rowNumber): array
    {
        return array_filter([
            'RowNumber' => $rowNumber,
            'Item' => Ref::to($this->itemId),
            'ItemName' => $this->itemName,
            'ItemCode' => $this->itemCode,
            'Description' => $this->description,
            'Quantity' => $this->quantity,
            'UnitOfMeasurement' => $this->unitOfMeasurement,
            'Price' => $this->price,
            'DiscountPercent' => $this->discountPercent ?? 0.0,
            'Discount' => 0.0,
            'VATPercent' => $this->vatPercent,
            'VatRate' => Ref::to($this->vatRateId),
            'Value' => $this->value(),
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
