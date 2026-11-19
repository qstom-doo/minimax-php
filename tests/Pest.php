<?php

use Illuminate\Http\Client\Factory;
use Qstom\Minimax\Config;
use Qstom\Minimax\MinimaxClient;

/**
 * @param  array<string, mixed>  $responses
 * @return array{0: MinimaxClient, 1: Factory}
 */
function fakeMinimax(array $responses = []): array
{
    $http = new Factory;
    $http->fake([
        'moj.minimax.si/SI/AUT/oauth20/token' => $http->response(['access_token' => 'token-1', 'expires_in' => 3600]),
        ...$responses,
    ]);
    $http->preventStrayRequests();

    return [new MinimaxClient(new Config('client', 'secret', 'user', 'pass', 77), $http), $http];
}
