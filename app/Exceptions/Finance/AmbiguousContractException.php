<?php

namespace App\Exceptions\Finance;

use Exception;

class AmbiguousContractException extends Exception
{
    protected int $projectId;
    protected array $contractIds;

    public function __construct(int $projectId, array $contractIds, string $message = "")
    {
        $this->projectId = $projectId;
        $this->contractIds = $contractIds;

        if (empty($message)) {
            $idsList = implode(', ', $contractIds);
            $message = "Project #{$projectId} is linked to multiple active contracts [IDs: {$idsList}]. Explicit contract_id is strictly required to post construction WIP.";
        }

        parent::__construct($message);
    }

    public function getProjectId(): int
    {
        return $this->projectId;
    }

    public function getContractIds(): array
    {
        return $this->contractIds;
    }
}
