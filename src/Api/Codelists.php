<?php

namespace Qstom\Minimax\Api;

use Qstom\Minimax\Exceptions\NotFoundException;
use Qstom\Minimax\MinimaxClient;

/**
 * Code lists needed to build documents: countries, currencies, VAT rates,
 * items, document numbering and report templates.
 */
final readonly class Codelists
{
    public function __construct(private MinimaxClient $client) {}

    /**
     * @return array<string, mixed> CountryId, Code, Name, Currency
     */
    public function country(string $code): array
    {
        return $this->client->get($this->client->orgPath('countries/code('.rawurlencode(strtoupper($code)).')'));
    }

    /**
     * @return array<string, mixed> CurrencyId, Code, Name
     */
    public function currency(string $code): array
    {
        return $this->client->get($this->client->orgPath('currencies/code('.rawurlencode(strtoupper($code)).')'));
    }

    /**
     * VAT rate valid on a date. Codes: S standard, Z reduced, P flat rate,
     * 0 lower, O exempt, N non-taxable.
     *
     * @return array<string, mixed> VatRateId, Code, Percent
     */
    public function vatRate(string $code, ?\DateTimeInterface $date = null): array
    {
        return $this->client->get(
            $this->client->orgPath('vatrates/code('.rawurlencode($code).')'),
            array_filter(['date' => $date?->format('Y-m-d')]),
        );
    }

    /**
     * @return array<string, mixed> ItemId, Name, Code, UnitOfMeasurement, VatRate, Price …
     */
    public function item(int $itemId): array
    {
        return $this->client->get($this->client->orgPath("items/{$itemId}"));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function itemByCode(string $code): ?array
    {
        try {
            return $this->client->get($this->client->orgPath('items/code('.rawurlencode($code).')'));
        } catch (NotFoundException) {
            return null;
        }
    }

    /**
     * Document numberings (Document: IR issued invoice, PD proforma).
     *
     * @return list<array<string, mixed>>
     */
    public function documentNumberings(): array
    {
        return $this->rows($this->client->get($this->client->orgPath('document-numbering'), ['PageSize' => 100]));
    }

    /**
     * Report templates (DisplayType: IR issued invoice, UP invoice with
     * payment order, PUPN proforma with payment order, …). The template
     * decides the language and texts of the printed invoice.
     *
     * @return list<array<string, mixed>>
     */
    public function reportTemplates(?string $search = null): array
    {
        return $this->rows($this->client->get($this->client->orgPath('report-templates'), array_filter(['SearchString' => $search, 'PageSize' => 100])));
    }

    /**
     * @param  array<mixed>  $result
     * @return list<array<string, mixed>>
     */
    private function rows(array $result): array
    {
        return array_values((array) ($result['Rows'] ?? (array_is_list($result) ? $result : [])));
    }
}
