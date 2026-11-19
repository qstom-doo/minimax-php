<?php

namespace Qstom\Minimax\Exceptions;

use Throwable;

/**
 * Minimax answered with an error status. Validation messages are read from
 * the response when Minimax sends them (ValidationMessages: Message and
 * PropertyName).
 */
class ApiException extends MinimaxException
{
    /**
     * @param  list<array{message: string, property: ?string}>  $validationMessages
     */
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly string $responseBody = '',
        public readonly array $validationMessages = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }
}
