<?php

namespace Qstom\Minimax\Exceptions;

/**
 * Minimax refused the API login. Never retry automatically: several failed
 * logins lock the API application in Minimax.
 */
class AuthenticationException extends MinimaxException {}
