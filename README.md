# minimax-php

PHP client for the [Minimax](https://www.minimax.si) accounting API: customers, issued invoices and proforma invoices, invoice PDFs and the code lists needed to build them. Built on the Laravel HTTP client, so `Http::fake()` works in tests.

Sources: [API navodila za razvijalce](https://help.minimax.si/help/api-navodila-za-razvijalce), [Swagger](https://moj.minimax.si/SI/API/swagger/ui/index), [official PHP sample](https://github.com/minimaxapi/MinimaxAPISamplePHP).

## Install

```bash
composer require qstom-doo/minimax-php
```

## Use

```php
use Qstom\Minimax\Config;
use Qstom\Minimax\MinimaxClient;
use Qstom\Minimax\Documents\InvoiceRow;
use Qstom\Minimax\Documents\IssuedInvoiceDraft;

$minimax = new MinimaxClient(
    new Config($clientId, $clientSecret, $username, $password, organisationId: 12345),
    app(\Illuminate\Http\Client\Factory::class), // optional
    cache()->store(),                            // optional: keeps the access token
);

$si = $minimax->codelists()->country('SI');
$eur = $minimax->codelists()->currency('EUR');
$vat = $minimax->codelists()->vatRate('S', now());

$customer = $minimax->customers()->findByCode('C-1')
    ?? $minimax->customers()->create([
        'Code' => 'C-1', 'Name' => 'Acme d.o.o.', 'Address' => 'Main 1', 'PostalCode' => '1000', 'City' => 'Ljubljana',
        'Country' => ['ID' => $si['CountryId']], 'Currency' => ['ID' => $eur['CurrencyId']],
        'TaxNumber' => '12345678', 'SubjectToVAT' => 'D', 'EInvoiceIssuing' => 'SeNePripravlja',
    ]);

$draft = $minimax->issuedInvoices()->create(
    IssuedInvoiceDraft::invoice($customer['CustomerId'], $eur['CurrencyId'])
        ->dates(issued: now(), due: now()->addDays(8))
        ->addressee('Acme d.o.o.', 'Main 1', '1000', 'Ljubljana', $si['CountryId'])
        ->documentReference('ORDER-12')
        ->addRow(new InvoiceRow(itemId: 33, itemName: 'Service', quantity: 1, price: 100, vatRateId: $vat['VatRateId'], vatPercent: $vat['Percent'], unitOfMeasurement: 'kos'))
);

$invoice = $minimax->issuedInvoices()->issue($draft['IssuedInvoiceId'], $draft['RowVersion']);
$pdf = $minimax->issuedInvoices()->pdf($invoice['IssuedInvoiceId']); // ->filename, ->content
```

## Things to know

- **Login**: OAuth2 password grant. A refused login throws `AuthenticationException` and is never retried: several failed logins lock the API application in Minimax. An expired token (401) is renewed once.
- **References** are `{"ID": 123}` (`Ref::to()`); `Name` and `ResourceUrl` are read-only.
- **Create** answers 201 with a `Location` header; the client reads the record back.
- **Updates and actions** need the current `RowVersion`; `Customers::update()` and `IssuedInvoices::action()` read it first when you don't pass one.
- **Invoices** start as drafts (`Status` O) and get their number with the `issue` action (`Status` I). Other actions: `issueAndGeneratepdf`, `generatepdf`, `issueCancellation`, `copytocreditnote`, `copyToReverse`, `sendEInvoice`.
- **Payments**: the API has no endpoint to record a payment on an issued invoice. Mark it paid when issuing (`paidWith()`, `AlreadyPaid` D) or read `PaymentStatus` / `PaidValue` (`PaymentStatus` enum).
- **Language** of the printed invoice comes from the report template (`reportTemplate()`); there is no language field.
- **E-mail**: the API cannot e-mail an invoice; fetch the PDF and send it yourself.
- **Limits**: 1,000 calls per 24 h per organisation and provider, 20,000 per month.

## Tests

```bash
composer install
vendor/bin/pest
```
