<?php

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Qstom\Minimax\Config;
use Qstom\Minimax\Exceptions\ApiException;
use Qstom\Minimax\Exceptions\AuthenticationException;
use Qstom\Minimax\Exceptions\MinimaxException;
use Qstom\Minimax\MinimaxClient;

it('logs in with the password grant and sends the bearer token', function () {
    [$client, $http] = fakeMinimax([
        'moj.minimax.si/SI/API/api/currentuser/orgs' => (new Factory)->response(['Rows' => [['Organisation' => ['ID' => 77, 'Name' => 'qStom d.o.o.']]]]),
    ]);

    expect($client->organisations())->toBe([['id' => 77, 'name' => 'qStom d.o.o.']]);

    $http->assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'oauth20/token')
        && $request['grant_type'] === 'password' && $request['username'] === 'user' && $request['scope'] === 'minimax.si');
    $http->assertSent(fn (Request $request): bool => str_contains($request->url(), 'currentuser/orgs') && $request->hasHeader('Authorization', 'Bearer token-1'));
});

it('never retries a refused login', function () {
    $http = new Factory;
    $http->fake(['moj.minimax.si/SI/AUT/oauth20/token' => $http->response(['error' => 'invalid_grant'], 400)]);
    $client = new MinimaxClient(new Config('client', 'secret', 'user', 'wrong', 77), $http);

    expect(fn () => $client->organisations())->toThrow(AuthenticationException::class);
    expect(fn () => $client->organisations())->toThrow(AuthenticationException::class);

    // A new object asks again, but each call asks only once
    expect($http->recorded())->toHaveCount(2);
});

it('reuses the token for later calls', function () {
    [$client, $http] = fakeMinimax(['moj.minimax.si/SI/API/*' => (new Factory)->response(['Rows' => []])]);

    $client->organisations();
    $client->organisations();

    expect(collect($http->recorded())->filter(fn (array $pair): bool => str_contains($pair[0]->url(), 'oauth20'))->count())->toBe(1);
});

it('reads validation messages from an error', function () {
    [$client] = fakeMinimax([
        'moj.minimax.si/SI/API/api/orgs/77/customers' => (new Factory)->response(['ValidationMessages' => [['Message' => 'Required', 'PropertyName' => 'Name']]], 400),
    ]);

    try {
        $client->customers()->create(['Code' => 'X']);
        $this->fail('Expected an exception');
    } catch (ApiException $exception) {
        expect($exception->status)->toBe(400)
            ->and($exception->validationMessages)->toBe([['message' => 'Required', 'property' => 'Name']])
            ->and($exception->getMessage())->toContain('Name: Required');
    }
});

it('needs an organisation for organisation paths', function () {
    $client = new MinimaxClient(new Config('a', 'b', 'c', 'd'), new Factory);

    expect(fn () => $client->orgPath('customers'))->toThrow(MinimaxException::class, 'No Minimax organisation');
});
