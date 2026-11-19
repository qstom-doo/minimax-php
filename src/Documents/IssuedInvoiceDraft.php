<?php

namespace Qstom\Minimax\Documents;

use DateTimeInterface;
use InvalidArgumentException;
use Qstom\Minimax\Api\IssuedInvoices;
use Qstom\Minimax\Ref;

/**
 * Builds the body of a new issued invoice or proforma invoice (IssuedInvoice).
 *
 *     $draft = IssuedInvoiceDraft::invoice($customerId, $currencyId)
 *         ->dates(issued: $today, due: $today->addDays(8))
 *         ->addressee('Acme d.o.o.', 'Main street 1', '1000', 'Ljubljana', $siCountryId)
 *         ->reportTemplate($templateId)
 *         ->addRow(new InvoiceRow(...))
 *         ->paidWith($paymentMethodId, 122.00);
 */
final class IssuedInvoiceDraft
{
    private ?DateTimeInterface $dateIssued = null;

    private ?DateTimeInterface $dateTransaction = null;

    private ?DateTimeInterface $dateDue = null;

    /** @var array<string, mixed> */
    private array $fields = [];

    /** @var list<InvoiceRow> */
    private array $rows = [];

    /** @var list<array<string, mixed>> */
    private array $paymentMethods = [];

    private function __construct(
        private readonly string $type,
        private readonly int $customerId,
        private readonly int $currencyId,
    ) {}

    public static function invoice(int $customerId, int $currencyId): self
    {
        return new self(IssuedInvoices::INVOICE, $customerId, $currencyId);
    }

    public static function proforma(int $customerId, int $currencyId): self
    {
        return new self(IssuedInvoices::PROFORMA, $customerId, $currencyId);
    }

    public function dates(DateTimeInterface $issued, ?DateTimeInterface $due = null, ?DateTimeInterface $transaction = null): self
    {
        $this->dateIssued = $issued;
        $this->dateDue = $due ?? $issued;
        $this->dateTransaction = $transaction ?? $issued;

        return $this;
    }

    /**
     * Who the invoice is addressed to. The country name is only sent for a
     * foreign country (Minimax refuses it for the home country).
     */
    public function addressee(string $name, ?string $address, ?string $postalCode, ?string $city, ?int $countryId, ?string $foreignCountryName = null): self
    {
        return $this->set([
            'AddresseeName' => $name,
            'AddresseeAddress' => $address,
            'AddresseePostalCode' => $postalCode,
            'AddresseeCity' => $city,
            'AddresseeCountry' => $countryId ? Ref::to($countryId) : null,
            'AddresseeCountryName' => $foreignCountryName,
        ]);
    }

    public function documentNumbering(?int $documentNumberingId): self
    {
        return $this->set(['DocumentNumbering' => $documentNumberingId ? Ref::to($documentNumberingId) : null]);
    }

    /**
     * The print template, which sets the language and texts of the invoice.
     */
    public function reportTemplate(?int $reportTemplateId): self
    {
        return $this->set(['IssuedInvoiceReportTemplate' => $reportTemplateId ? Ref::to($reportTemplateId) : null]);
    }

    public function analytic(?int $analyticId): self
    {
        return $this->set(['Analytic' => $analyticId ? Ref::to($analyticId) : null]);
    }

    public function documentReference(?string $reference): self
    {
        return $this->set(['DocumentReference' => $reference]);
    }

    public function paymentReference(?string $reference): self
    {
        return $this->set(['PaymentReference' => $reference]);
    }

    public function descriptions(?string $above = null, ?string $below = null): self
    {
        return $this->set(['DescriptionAbove' => $above, 'DescriptionBelow' => $below]);
    }

    public function notes(?string $notes): self
    {
        return $this->set(['Notes' => $notes]);
    }

    public function addRow(InvoiceRow $row): self
    {
        $this->rows[] = $row;

        return $this;
    }

    /**
     * Record that the invoice was already paid when it was issued
     * (IssuedInvoicePaymentMethods, AlreadyPaid D).
     */
    public function paidWith(int $paymentMethodId, float $amount): self
    {
        $this->paymentMethods[] = [
            'PaymentMethod' => Ref::to($paymentMethodId),
            'Amount' => round($amount, 2),
            'AlreadyPaid' => 'D',
        ];

        return $this;
    }

    /**
     * @param  array<string, mixed>  $fields  Any other IssuedInvoice fields.
     */
    public function set(array $fields): self
    {
        $this->fields = [...$this->fields, ...$fields];

        return $this;
    }

    public function netTotal(): float
    {
        return round(array_sum(array_map(fn (InvoiceRow $row): float => $row->value(), $this->rows)), 2);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if ($this->rows === []) {
            throw new InvalidArgumentException('An invoice needs at least one row.');
        }

        $issued = $this->dateIssued ?? new \DateTimeImmutable('today');

        $body = [
            'InvoiceType' => $this->type,
            'Status' => 'O',
            'Customer' => Ref::to($this->customerId),
            'Currency' => Ref::to($this->currencyId),
            'DateIssued' => $issued->format('Y-m-d'),
            'DateTransaction' => ($this->dateTransaction ?? $issued)->format('Y-m-d'),
            'DateTransactionFrom' => ($this->dateTransaction ?? $issued)->format('Y-m-d'),
            'DateDue' => ($this->dateDue ?? $issued)->format('Y-m-d'),
            'PricesOnInvoice' => 'N',
            'RecurringInvoice' => 'N',
            ...$this->fields,
            'IssuedInvoiceRows' => array_map(fn (InvoiceRow $row, int $index): array => $row->toArray($index + 1), $this->rows, array_keys($this->rows)),
        ];

        if ($this->paymentMethods !== []) {
            $body['IssuedInvoicePaymentMethods'] = $this->paymentMethods;
        }

        return array_filter($body, fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
