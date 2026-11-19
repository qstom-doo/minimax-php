<?php

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Qstom\Minimax\Documents\InvoiceRow;
use Qstom\Minimax\Documents\IssuedInvoiceDraft;

it('builds an invoice body from the documented fields', function () {
    $body = IssuedInvoiceDraft::invoice(501, 7)
        ->dates(new DateTimeImmutable('2026-11-19'), new DateTimeImmutable('2026-11-27'))
        ->addressee('Acme GmbH', 'Hauptstr. 1', '10115', 'Berlin', 81, 'Germany')
        ->reportTemplate(12)
        ->documentReference('Q-2026-00012')
        ->addRow(new InvoiceRow(itemId: 33, itemName: 'q-tokens', quantity: 1000, price: 0.1, vatRateId: 4, vatPercent: 22, unitOfMeasurement: 'q', discountPercent: 10))
        ->paidWith(9, 109.8)
        ->toArray();

    expect($body)
        ->InvoiceType->toBe('R')
        ->Status->toBe('O')
        ->Customer->toBe(['ID' => 501])
        ->DateDue->toBe('2026-11-27')
        ->AddresseeCountryName->toBe('Germany')
        ->IssuedInvoiceReportTemplate->toBe(['ID' => 12])
        ->PricesOnInvoice->toBe('N')
        ->and($body['IssuedInvoiceRows'][0])->toMatchArray([
            'RowNumber' => 1, 'Item' => ['ID' => 33], 'Quantity' => 1000.0, 'Price' => 0.1,
            'DiscountPercent' => 10.0, 'VATPercent' => 22.0, 'VatRate' => ['ID' => 4], 'Value' => 90.0, 'UnitOfMeasurement' => 'q',
        ])
        ->and($body['IssuedInvoicePaymentMethods'])->toBe([['PaymentMethod' => ['ID' => 9], 'Amount' => 109.8, 'AlreadyPaid' => 'D']]);
});

it('creates a draft and issues it with the row version', function () {
    $f = new Factory;
    [$client, $http] = fakeMinimax([
        'moj.minimax.si/SI/API/api/orgs/77/issuedinvoices/900/actions/issue*' => $f->response(['Data' => null]),
        'moj.minimax.si/SI/API/api/orgs/77/issuedinvoices/900' => $f->sequence()
            ->push(['IssuedInvoiceId' => 900, 'Status' => 'O', 'RowVersion' => 'v1'])
            ->push(['IssuedInvoiceId' => 900, 'Status' => 'I', 'InvoiceNumber' => 42, 'RowVersion' => 'v2']),
        'moj.minimax.si/SI/API/api/orgs/77/issuedinvoices' => $f->response('', 201, ['Location' => 'https://moj.minimax.si/SI/API/api/orgs/77/issuedinvoices/900']),
    ]);

    $draft = $client->issuedInvoices()->create(IssuedInvoiceDraft::invoice(501, 7)->addRow(new InvoiceRow(33, 'q', 1, 1, 4, 22)));
    $issued = $client->issuedInvoices()->issue($draft['IssuedInvoiceId'], $draft['RowVersion']);

    expect($issued)->Status->toBe('I')->InvoiceNumber->toBe(42);
    $http->assertSent(fn (Request $request): bool => $request->method() === 'PUT' && str_contains($request->url(), 'actions/issue?rowVersion=v1'));
});

it('returns the stored pdf, or generates one', function () {
    $f = new Factory;
    [$client] = fakeMinimax([
        'moj.minimax.si/SI/API/api/orgs/77/issuedinvoices/1/attachments' => $f->response([['AttachmentData' => base64_encode('%PDF stored'), 'AttachmentFileName' => 'R-1.pdf', 'AttachmentMimeType' => 'application/pdf']]),
        'moj.minimax.si/SI/API/api/orgs/77/issuedinvoices/2/attachments' => $f->response([]),
        'moj.minimax.si/SI/API/api/orgs/77/issuedinvoices/2/actions/generatepdf*' => $f->response(['Data' => ['AttachmentData' => base64_encode('%PDF new'), 'AttachmentFileName' => 'R-2.pdf']]),
        'moj.minimax.si/SI/API/api/orgs/77/issuedinvoices/2' => $f->response(['IssuedInvoiceId' => 2, 'RowVersion' => 'x']),
    ]);

    expect($client->issuedInvoices()->pdf(1))->content->toBe('%PDF stored')->filename->toBe('R-1.pdf')
        ->and($client->issuedInvoices()->pdf(2))->content->toBe('%PDF new');
});

it('searches by type and customer', function () {
    [$client, $http] = fakeMinimax(['moj.minimax.si/SI/API/api/orgs/77/issuedinvoices?*' => (new Factory)->response(['Rows' => [['IssuedInvoiceId' => 1]], 'TotalRows' => 1])]);

    expect($client->issuedInvoices()->search(filters: ['CustomerID' => 501, 'Year' => null]))->total->toBe(1);
    $http->assertSent(fn (Request $request): bool => str_contains($request->url(), 'issuedinvoices') && $request['InvoiceType'] === 'R' && $request['CustomerID'] === 501 && ! isset($request['Year']));
});
