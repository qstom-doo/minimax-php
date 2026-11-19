<?php

namespace Qstom\Minimax;

/**
 * Access to one Minimax API application.
 *
 * Minimax authenticates with the OAuth2 password grant: the client ID and
 * secret of the API application plus the username and password of the API
 * user (Minimax > Moj profil > Gesla za dostop zunanjih aplikacij). Several
 * failed logins lock the application, so the client never retries a token
 * request that Minimax refused.
 */
final readonly class Config
{
    /**
     * @param  string  $localization  Minimax country edition: SI, HR or RS.
     */
    public function __construct(
        public string $clientId,
        public string $clientSecret,
        public string $username,
        public string $password,
        public ?int $organisationId = null,
        public string $localization = 'SI',
        public int $timeoutSeconds = 20,
    ) {}

    public function withOrganisation(int $organisationId): self
    {
        return new self($this->clientId, $this->clientSecret, $this->username, $this->password, $organisationId, $this->localization, $this->timeoutSeconds);
    }

    public function tokenUrl(): string
    {
        return "https://moj.minimax.{$this->domain()}/{$this->localization}/AUT/oauth20/token";
    }

    public function apiUrl(): string
    {
        return "https://moj.minimax.{$this->domain()}/{$this->localization}/API/";
    }

    public function hasCredentials(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '' && $this->username !== '' && $this->password !== '';
    }

    private function domain(): string
    {
        return strtolower($this->localization);
    }
}
