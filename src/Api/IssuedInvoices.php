<?php

namespace Qstom\Minimax\Api;

use Qstom\Minimax\Documents\IssuedInvoiceDraft;
use Qstom\Minimax\Exceptions\MinimaxException;
use Qstom\Minimax\MinimaxClient;
use Qstom\Minimax\Pdf;

/**
 * Issued invoices and proforma invoices (api/orgs/{organisationId}/issuedinvoices).
 *
 * A new invoice is a draft (Status O) until the "issue" action gives it a
 * number (Status I). Actions need the record's current RowVersion.
 */
final readonly class IssuedInvoices
{
    public const INVOICE = 'R';

    public const PROFORMA = 'P';

    public const ACTION_ISSUE = 'issue';

    public const ACTION_ISSUE_AND_GENERATE_PDF = 'issueAndGeneratepdf';

    public const ACTION_GENERATE_PDF = 'generatepdf';

    public const ACTION_CANCEL = 'issueCancellation';

    public function __construct(private MinimaxClient $client) {}

    /**
     * Search issued invoices. InvoiceType is mandatory in Minimax; other
     * filters: CustomerID, CustomerName, InvoiceNumber, Year, DateIssuedFrom,
     * DateIssuedTo, DateDueFrom, DateDueTo, Status, PaymentStatus, CurrentPage,
     * PageSize, SortField, Order (A or D).
     *
     * @param  array<string, scalar|null>  $filters
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function search(string $type = self::INVOICE, array $filters = []): array
    {
        $response = $this->client->get($this->client->orgPath('issuedinvoices'), array_filter(
            ['InvoiceType' => $type, ...$filters],
            fn (mixed $value): bool => $value !== null && $value !== '',
        ));

        return [
            'rows' => array_values((array) ($response['Rows'] ?? [])),
            'total' => (int) ($response['TotalRows'] ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function find(int $invoiceId): array
    {
        return $this->client->get($this->client->orgPath("issuedinvoices/{$invoiceId}"));
    }

    /**
     * Create a draft invoice and return it as stored in Minimax.
     *
     * @return array<string, mixed>
     */
    public function create(IssuedInvoiceDraft|array $invoice): array
    {
        return $this->client->create($this->client->orgPath('issuedinvoices'), $invoice instanceof IssuedInvoiceDraft ? $invoice->toArray() : $invoice);
    }

    /**
     * Run an action on the invoice with its current RowVersion.
     *
     * @param  array<string, scalar>  $parameters  e.g. issueCancellationReason
     * @return array<mixed>
     */
    public function action(int $invoiceId, string $action, ?string $rowVersion = null, array $parameters = []): array
    {
        $rowVersion ??= (string) ($this->find($invoiceId)['RowVersion'] ?? '');

        return $this->client->put(
            $this->client->orgPath("issuedinvoices/{$invoiceId}/actions/{$action}"),
            query: ['rowVersion' => $rowVersion, ...$parameters],
        );
    }

    /**
     * Issue a draft (it gets its number) and return the issued invoice.
     *
     * @return array<string, mixed>
     */
    public function issue(int $invoiceId, ?string $rowVersion = null): array
    {
        $this->action($invoiceId, self::ACTION_ISSUE, $rowVersion);

        return $this->find($invoiceId);
    }

    /**
     * The invoice PDF: the stored attachment when Minimax has one, otherwise a
     * freshly generated PDF.
     */
    public function pdf(int $invoiceId): Pdf
    {
        $fallbackName = "invoice-{$invoiceId}.pdf";
        $attachments = $this->client->get($this->client->orgPath("issuedinvoices/{$invoiceId}/attachments"));

        foreach (array_is_list($attachments) ? $attachments : [] as $attachment) {
            $mime = strtolower((string) ($attachment['AttachmentMimeType'] ?? 'application/pdf'));

            if (is_array($attachment) && filled($attachment['AttachmentData'] ?? null) && str_contains($mime, 'pdf')) {
                return Pdf::fromAttachment($attachment, $fallbackName);
            }
        }

        $generated = $this->action($invoiceId, self::ACTION_GENERATE_PDF);

        if (! is_array($generated['Data'] ?? null)) {
            throw new MinimaxException('Minimax did not return a PDF for this document.');
        }

        return Pdf::fromAttachment($generated['Data'], $fallbackName);
    }

    /**
     * Payment methods offered on issued invoices (PaymentMethodId, Name,
     * Type: T bank, G cash, B cash register, K card, D other).
     *
     * @return list<array<string, mixed>>
     */
    public function paymentMethods(): array
    {
        $result = $this->client->get($this->client->orgPath('issuedinvoices/paymentmethods'));

        return array_values((array) ($result['Rows'] ?? (array_is_list($result) ? $result : [])));
    }
}
