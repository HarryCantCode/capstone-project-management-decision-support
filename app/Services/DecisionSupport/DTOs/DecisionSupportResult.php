<?php

namespace App\Services\DecisionSupport\DTOs;

/**
 * DecisionSupportResult DTO
 *
 * Data Transfer Object representing the deterministic output of the
 * rule-based Decision Support Engine for a project.
 * Encapsulates explainable metrics, formulas, risk flags (`ok` / `warning` / `critical`),
 * and actionable recommendations across Manpower, Materials/Inventory, Timeline, and Budget.
 */
class DecisionSupportResult
{
    /**
     * @param array<string, mixed>|null $manpowerSuggestion
     * @param array<string, mixed>|null $materialSuggestion
     * @param array<string, mixed>|null $timelineSuggestion
     * @param string|null $operationalReadiness
     * @param string $status
     * @param array<string, mixed>|null $budgetSuggestion
     * @param array<string, array<string, mixed>> $badges
     * @param list<array<string, mixed>> $recommendations
     */
    public function __construct(
        public readonly ?array $manpowerSuggestion = null,
        public readonly ?array $materialSuggestion = null,
        public readonly ?array $timelineSuggestion = null,
        public readonly ?string $operationalReadiness = null,
        public readonly string $status = 'evaluated',
        public readonly ?array $budgetSuggestion = null,
        public readonly array $badges = [],
        public readonly array $recommendations = [],
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
            'budgetSuggestion' => $this->budgetSuggestion,
            'operationalReadiness' => $this->operationalReadiness,
            'status' => $this->status,
            'badges' => $this->badges,
            'recommendations' => $this->recommendations,
        ];
    }
}
