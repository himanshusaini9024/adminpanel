<?php

namespace App\Exceptions;

/**
 * Extends InvalidArgumentException so existing API handlers return 422 with the message.
 */
class InsufficientStockException extends \InvalidArgumentException
{
    public function __construct(string $message, public array $shortages = [])
    {
        parent::__construct($message);
    }
}
