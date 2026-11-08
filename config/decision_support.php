<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Decision Support System (DSS) — Risk Thresholds
    |--------------------------------------------------------------------------
    |
    | Deterministic, rule-based thresholds used by RuleEngineService and
    | ScenarioSimulatorService. Kept in configuration so business rules remain
    | transparent, auditable, and adjustable without code changes.
    |
    */

    'budget' => [
        // Burn ratio (actual_spend / contract_price) may exceed elapsed_time_ratio
        // by up to this percentage-point buffer before triggering a burn warning.
        'burn_buffer_percent' => 15.0,

        // Utilization percentage that marks a project budget as "At Risk" (warning).
        'at_risk_utilization_percent' => 85.0,

        // Utilization percentage that marks a project budget as "Over Budget" (critical).
        'critical_utilization_percent' => 100.0,

        // If materials alone consume more than this % of the total contract budget,
        // flag a material cost concentration warning.
        'material_share_warning_percent' => 70.0,
    ],

    'inventory' => [
        // Stock quantity at or below which a catalog material is flagged as low stock (<= 8).
        // Matches catalog low-stock notification threshold.
        'low_stock_threshold' => 8,

        // Target baseline reserve for fast-moving materials when computing reorder deficits.
        'fast_moving_target_stock' => 12,

        // Target baseline reserve for slow-moving materials when computing reorder deficits.
        'slow_moving_target_stock' => 8,

        // Safety buffer ratio added to a stock deficit when calculating reorder quantity (10%).
        'safety_buffer_ratio' => 0.10,
    ],

    'manpower' => [
        // Core engineering & safety roles recommended for an active elevator installation project.
        'core_project_roles' => [
            \App\Models\Personnel::EXPERTISE_PROJECT_MANAGER,
            \App\Models\Personnel::EXPERTISE_SITE_ENGINEER,
            \App\Models\Personnel::EXPERTISE_FOREMAN,
            \App\Models\Personnel::EXPERTISE_SAFETY_OFFICER,
            \App\Models\Personnel::EXPERTISE_TECHNICIAN,
        ],

        // Maximum number of suggested available personnel returned per project analysis.
        'max_suggested_crew' => 8,
    ],

    'schedule' => [
        // Days overdue threshold that escalates a schedule warning into a critical flag.
        'critical_overdue_days' => 7,

        // Days remaining before target_completion_date to flag an approaching deadline
        // when task completion progress is still low (< 70%).
        'approaching_deadline_days' => 14,
    ],

];
