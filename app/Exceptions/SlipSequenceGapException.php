<?php

namespace App\Exceptions;

class SlipSequenceGapException extends SlipSequenceException
{
    protected int $storeId;
    protected string $slipType;
    protected int $expectedNumber;
    protected int $givenNumber;
    protected array $missingNumbers = [];
    protected array $missingFormatted = [];

    public function __construct(
        string $message,
        int $storeId,
        string $slipType,
        int $expectedNumber,
        int $givenNumber,
        array $missingNumbers = [],
        array $missingFormatted = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->storeId = $storeId;
        $this->slipType = $slipType;
        $this->expectedNumber = $expectedNumber;
        $this->givenNumber = $givenNumber;
        $this->missingNumbers = $missingNumbers;
        $this->missingFormatted = $missingFormatted;
    }

    public function getStoreId(): int { return $this->storeId; }
    public function getSlipType(): string { return $this->slipType; }
    public function getExpectedNumber(): int { return $this->expectedNumber; }
    public function getGivenNumber(): int { return $this->givenNumber; }
    public function getMissingNumbers(): array { return $this->missingNumbers; }
    public function getMissingFormatted(): array { return $this->missingFormatted; }
}
