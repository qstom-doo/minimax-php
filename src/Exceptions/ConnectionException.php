<?php

namespace Qstom\Minimax\Exceptions;

/**
 * Minimax could not be reached (network error or time-out). Safe to retry
 * later, except after a request that creates something: check first whether
 * it was created.
 */
class ConnectionException extends MinimaxException {}
