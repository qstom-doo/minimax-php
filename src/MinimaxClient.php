<?php

namespace Qstom\Minimax;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException as HttpConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Qstom\Minimax\Api\Codelists;
use Qstom\Minimax\Api\Customers;
use Qstom\Minimax\Api\IssuedInvoices;
use Qstom\Minimax\Exceptions\ApiException;
use Qstom\Minimax\Exceptions\AuthenticationException;
use Qstom\Minimax\Exceptions\ConnectionException;
use Qstom\Minimax\Exceptions\MinimaxException;
use Qstom\Minimax\Exceptions\NotFoundException;

/**
 * Client for the Minimax REST API (https://moj.minimax.si/SI/API/).
 *
 * Paths follow the official Swagger document (api/orgs/{organisationId}/…).
 * A created record is read back from the Location header, as Minimax answers
 * a POST with 201 and the record's URL. The access token is cached (in the
 * given cache, else for the lifetime of this object); a refused token request
 * is never retried, because repeated failed logins lock the API application.
 */
class MinimaxClient
{
    private const TOKEN_CACHE_PREFIX = 'minimax.access_token.';

    private ?string $memoryToken = null;

    public function __construct(
        private Config $config,
        private Http $http = new Http,
        private ?Cache $cache = null,
    ) {}

    public function config(): Config
    {
        return $this->config;
    }

    public function forOrganisation(int $organisationId): self
    {
        return new self($this->config->withOrganisation($organisationId), $this->http, $this->cache);
    }

    public function customers(): Customers
    {
        return new Customers($this);
    }

    public function issuedInvoices(): IssuedInvoices
    {
        return new IssuedInvoices($this);
    }

    public function codelists(): Codelists
    {
        return new Codelists($this);
    }

    /**
     * Organisations the API user can access.
     *
     * @return list<array{id: int, name: string}>
     */
    public function organisations(): array
    {
        $rows = $this->get('api/currentuser/orgs')['Rows'] ?? [];
        $organisations = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            $id = Ref::id($row['Organisation'] ?? null);

            if ($id !== null) {
                $organisations[] = ['id' => $id, 'name' => (string) ($row['Organisation']['Name'] ?? '')];
            }
        }

        return $organisations;
    }

    /**
     * Path inside the selected organisation, e.g. orgPath('customers').
     */
    public function orgPath(string $path): string
    {
        if ($this->config->organisationId === null) {
            throw new MinimaxException('No Minimax organisation is selected.');
        }

        return "api/orgs/{$this->config->organisationId}/".ltrim($path, '/');
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->json($this->send('get', $path, $query));
    }

    /**
     * Create a record and return it as Minimax stores it (read back from the
     * Location header, or the response body when there is no header).
     *
     * @param  array<string, mixed>  $body
     * @return array<mixed>
     */
    public function create(string $path, array $body): array
    {
        $response = $this->send('post', $path, body: $body);
        $location = $response->header('Location');

        if ($location !== '') {
            return $this->json($this->send('get', $location));
        }

        return $this->json($response);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<mixed>
     */
    public function put(string $path, array $body = [], array $query = []): array
    {
        return $this->json($this->send('put', $path, $query, $body));
    }

    public function delete(string $path, array $query = []): void
    {
        $this->json($this->send('delete', $path, $query));
    }

    public function forgetToken(): void
    {
        $this->memoryToken = null;
        $this->cache?->forget($this->tokenCacheKey());
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     */
    private function send(string $method, string $path, array $query = [], ?array $body = null, bool $retried = false): Response
    {
        $url = str_starts_with($path, 'https://') ? $path : $this->config->apiUrl().ltrim($path, '/');

        if ($query !== [] && $method !== 'get') {
            $url .= (str_contains($url, '?') ? '&' : '?').http_build_query($query);
        }

        try {
            $request = $this->request();
            $response = match ($method) {
                'get' => $request->get($url, $query === [] ? null : $query),
                'post' => $request->post($url, $body ?? []),
                'put' => $body === null || $body === [] ? $request->withBody('', 'application/json')->put($url) : $request->put($url, $body),
                'delete' => $request->delete($url),
            };
        } catch (HttpConnectionException $exception) {
            throw new ConnectionException('Minimax is not reachable: '.$exception->getMessage(), previous: $exception);
        }

        // An expired token: fetch a new one once. A refused token request
        // itself throws before this point and is never repeated.
        if ($response->status() === 401 && ! $retried) {
            $this->forgetToken();

            return $this->send($method, $path, $query, $body, true);
        }

        return $response;
    }

    private function request(): PendingRequest
    {
        return $this->http->withToken($this->accessToken())
            ->acceptJson()
            ->asJson()
            ->timeout($this->config->timeoutSeconds);
    }

    private function accessToken(): string
    {
        if (! $this->config->hasCredentials()) {
            throw new AuthenticationException('Minimax API access is not set up.');
        }

        $cached = $this->memoryToken ?? $this->cache?->get($this->tokenCacheKey());

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = $this->http->asForm()->timeout($this->config->timeoutSeconds)->post($this->config->tokenUrl(), [
                'grant_type' => 'password',
                'client_id' => $this->config->clientId,
                'client_secret' => $this->config->clientSecret,
                'username' => $this->config->username,
                'password' => $this->config->password,
                'scope' => 'minimax.si',
            ]);
        } catch (HttpConnectionException $exception) {
            throw new ConnectionException('Minimax is not reachable: '.$exception->getMessage(), previous: $exception);
        }

        $token = $response->json('access_token');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new AuthenticationException('Minimax refused the API login ('.$response->status().'). Check the client ID, secret, username and password; repeated failures lock the application in Minimax.');
        }

        $this->memoryToken = $token;
        $this->cache?->put($this->tokenCacheKey(), $token, max(60, (int) $response->json('expires_in', 3600) - 60));

        return $token;
    }

    private function tokenCacheKey(): string
    {
        return self::TOKEN_CACHE_PREFIX.md5($this->config->tokenUrl().'|'.$this->config->clientId.'|'.$this->config->username);
    }

    /**
     * @return array<mixed>
     */
    private function json(Response $response): array
    {
        if ($response->successful()) {
            $data = $response->json();

            return is_array($data) ? $data : [];
        }

        $messages = $this->validationMessages($response);
        $summary = $messages === []
            ? mb_substr(trim($response->body()), 0, 300)
            : implode('; ', array_map(fn (array $message): string => ($message['property'] ? $message['property'].': ' : '').$message['message'], $messages));

        $exception = $response->status() === 404 ? NotFoundException::class : ApiException::class;

        throw new $exception('Minimax API error '.$response->status().($summary !== '' ? ': '.$summary : ''), $response->status(), $response->body(), $messages);
    }

    /**
     * @return list<array{message: string, property: ?string}>
     */
    private function validationMessages(Response $response): array
    {
        $data = $response->json();

        if (! is_array($data)) {
            return [];
        }

        $messages = [];

        foreach ((array) ($data['ValidationMessages'] ?? []) as $message) {
            if (is_array($message) && filled($message['Message'] ?? null)) {
                $messages[] = ['message' => (string) $message['Message'], 'property' => filled($message['PropertyName'] ?? null) ? (string) $message['PropertyName'] : null];
            }
        }

        if ($messages === [] && filled($data['Message'] ?? null)) {
            $messages[] = ['message' => (string) $data['Message'], 'property' => null];
        }

        return $messages;
    }
}
