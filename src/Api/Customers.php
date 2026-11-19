<?php

namespace Qstom\Minimax\Api;

use Qstom\Minimax\Exceptions\NotFoundException;
use Qstom\Minimax\MinimaxClient;

/**
 * Customers (api/orgs/{organisationId}/customers) and their contacts.
 *
 * Fields follow the Customer model: Code (unique in the organisation), Name,
 * Address, PostalCode, City, Country {ID}, CountryName (foreign countries
 * only), TaxNumber, VATIdentificationNumber, RegistrationNumber,
 * SubjectToVAT (D, M or N), Currency {ID}, EInvoiceIssuing, RowVersion.
 */
final readonly class Customers
{
    public function __construct(private MinimaxClient $client) {}

    /**
     * @return array<string, mixed>
     */
    public function find(int $customerId): array
    {
        return $this->client->get($this->client->orgPath("customers/{$customerId}"));
    }

    /**
     * The customer with this code, or null when there is none.
     *
     * @return array<string, mixed>|null
     */
    public function findByCode(string $code): ?array
    {
        try {
            $customer = $this->client->get($this->client->orgPath('customers/code('.rawurlencode($code).')'));
        } catch (NotFoundException) {
            return null;
        }

        return ($customer['CustomerId'] ?? null) ? $customer : null;
    }

    /**
     * @param  array<string, mixed>  $customer
     * @return array<string, mixed>
     */
    public function create(array $customer): array
    {
        return $this->client->create($this->client->orgPath('customers'), $customer);
    }

    /**
     * Update the given fields. The current record is read first, so the
     * update carries every other field and the current RowVersion.
     *
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    public function update(int $customerId, array $changes): array
    {
        $current = $this->find($customerId);

        return $this->client->put($this->client->orgPath("customers/{$customerId}"), [
            ...$current,
            ...$changes,
            'CustomerId' => $customerId,
            'RowVersion' => $current['RowVersion'] ?? null,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function contacts(int $customerId): array
    {
        $result = $this->client->get($this->client->orgPath("customers/{$customerId}/contacts"));

        return array_values((array) ($result['Rows'] ?? (array_is_list($result) ? $result : [])));
    }

    /**
     * Contact fields: FullName, Email, PhoneNumber, Default (D/N), Notes.
     *
     * @param  array<string, mixed>  $contact
     * @return array<string, mixed>
     */
    public function addContact(int $customerId, array $contact): array
    {
        return $this->client->create($this->client->orgPath("customers/{$customerId}/contacts"), $contact);
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    public function updateContact(int $customerId, int $contactId, array $changes): array
    {
        $path = $this->client->orgPath("customers/{$customerId}/contacts/{$contactId}");
        $current = $this->client->get($path);

        return $this->client->put($path, [...$current, ...$changes, 'ContactId' => $contactId, 'RowVersion' => $current['RowVersion'] ?? null]);
    }
}
