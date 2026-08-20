<?php

namespace App\Exceptions;

use RuntimeException;

class BudgetExceededException extends RuntimeException
{
    public $utilized;
    public $allocated;
    public $consumed;

    public function __construct(
        $utilized = 0.0,
        $allocated = 0.0,
        $consumed = 0.0,
        string $message = ''
    ) {
        $this->utilized = (float) $utilized;
        $this->allocated = (float) $allocated;
        $this->consumed = (float) $consumed;

        parent::__construct($message ?: sprintf(
            'Budget limit reached (%.1f%% utilized). The GM must allocate additional budget before new expenses can be processed.',
            (float) $utilized
        ));
    }
}
