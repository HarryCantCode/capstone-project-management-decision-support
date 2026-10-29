<?php

namespace App\Services\DecisionSupport\DTOs;

/**
 * DecisionSupportResult DTO
 *
 * Data Transfer Object representing the output of the decision support engine.
 * Encapsulates suggestions for manpower, materials, and timeline.
 */
class DecisionSupportResult
{
    public function __construct(
        public readonly ?array $manpowerSuggestion = null,
        public readonly ?array $materialSuggestion = null,
        public readonly ?array $timelineSuggestion = null,
        public readonly ?string $operationalReadiness = null,
        public readonly string $status = 'not_yet_implemented',
    ) {
    }

    /**
     * Convert the DTO to an associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'manpowerSuggestion' => $this->manpowerSuggestion,
            'materialSuggestion' => $this->materialSuggestion,
            'timelineSuggestion' => $this->timelineSuggestion,
            'operationalReadiness' => $this->operationalReadiness,
            'status' => $this->status,
        ];
    }
}
