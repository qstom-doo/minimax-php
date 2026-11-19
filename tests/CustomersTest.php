<?php

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;

it('creates a customer and reads it back from the Location header', function () {
    $f = new Factory;
    [$client, $http] = fakeMinimax([
        'moj.minimax.si/SI/API/api/orgs/77/customers/501' => $f->response(['CustomerId' => 501, 'Code' => 'Q-1', 'RowVersion' => 'r1']),
        'moj.minimax.si/SI/API/api/orgs/77/customers' => $f->response('', 201, ['Location' => 'https://moj.minimax.si/SI/API/api/orgs/77/customers/501']),
    ]);

    $customer = $client->customers()->create(['Code' => 'Q-1', 'Name' => 'Acme', 'Country' => ['ID' => 192]]);

    expect($customer['CustomerId'])->toBe(501);
    $http->assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'customers') && $request['Name'] === 'Acme' && $request['Country'] === ['ID' => 192]);
});

it('returns null for an unknown customer code', function () {
    [$client] = fakeMinimax(['moj.minimax.si/SI/API/api/orgs/77/customers/code(Q-9)' => (new Factory)->response('', 404)]);

    expect($client->customers()->findByCode('Q-9'))->toBeNull();
});

it('updates a customer with its current row version', function () {
    $f = new Factory;
    [$client, $http] = fakeMinimax([
        'moj.minimax.si/SI/API/api/orgs/77/customers/501' => $f->sequence()
            ->push(['CustomerId' => 501, 'Name' => 'Old', 'City' => 'Kranj', 'RowVersion' => 'r1'])
            ->push(['CustomerId' => 501, 'Name' => 'New', 'City' => 'Kranj', 'RowVersion' => 'r2']),
    ]);

    $client->customers()->update(501, ['Name' => 'New']);

    $http->assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && $request['Name'] === 'New' && $request['City'] === 'Kranj' && $request['RowVersion'] === 'r1');
});
