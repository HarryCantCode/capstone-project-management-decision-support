<?php

namespace App\Services\DecisionSupport;

use App\Models\Personnel;
use App\Models\Project;
use App\Models\ProjectCost;
use App\Models\ProjectSubtask;
use App\Models\ProjectTask;
use App\Models\Resource;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * KuyaDexChatService
 *
 * Powers "Kuya DEX", the personal AI assistant for the DEX Project Management System (DEX PMS).
 *
 * Security & Privacy Guarantees:
 * - Passes ONLY sanitized operational JSON data (Materials, Projects, Costing/Expenditures, Manpower, Tasks/Schedule).
 * - NEVER accesses or transmits User credentials, passwords, OTPs, tokens, or session secrets.
 *
 * Strict Guardrail:
 * - Only answers inquiries regarding DEX PMS data (Materials Catalog, Projects, Costing/Expenses, Manpower,
 *   Major/Minor Tasks, Overlapping Schedules) and Elevator operation, specifications, installation, and construction.
 * - Any unrelated inquiry (e.g., current date, president of a country, general trivia, identity questions)
 *   is strictly refused with the exact message:
 *   "I have no idea with that, please let us focus to your project management"
 */
class KuyaDexChatService
{
    public const RESTRICTED_REPLY = 'I have no idea with that, please let us focus to your project management';

    public const ERROR_FALLBACK_REPLY = 'Sorry I encountered an issue from my end, please check your internet connection or try again at a later time.';

    /**
     * Free-tier protection: maximum successful Gemini API replies per 60-second window.
     * Only increments when a message is actually sent and successfully answered by Gemini
     * (never counts UI button spam, queued messages waiting in line, or local guardrail checks).
     */
    public const MAX_SUCCESSFUL_REPLIES_PER_MINUTE = 12;

    private const RATE_LIMIT_KEY = 'kuya_dex:successful_gemini_replies';

    /**
     * Process an inquiry to Kuya DEX.
     *
     * @param string $message
     * @param int|null $selectedProjectId
     * @return array{reply: string|null, mode: string, connected: bool}
     */
    public function ask(string $message, ?int $selectedProjectId = null): array
    {
        $cleanMessage = trim($message);
        $apiKey = trim((string) config('services.gemini.api_key', ''));
        $isConnected = $apiKey !== '';

        // If the LLM is not yet connected via GEMINI_API_KEY, keep the UI in typing-indicator mode
        if (! $isConnected) {
            return [
                'reply' => null,
                'mode' => 'typing_only',
                'connected' => false,
            ];
        }

        // Strict Pre-Check Guardrail for restricted / unrelated topics (does not consume API quota)
        if ($this->isRestrictedQuery($cleanMessage)) {
            return [
                'reply' => self::RESTRICTED_REPLY,
                'mode' => 'guardrail',
                'connected' => true,
            ];
        }

        // Check if 12 successful Gemini replies have already been completed within the last 60 seconds
        try {
            if (RateLimiter::tooManyAttempts(self::RATE_LIMIT_KEY, self::MAX_SUCCESSFUL_REPLIES_PER_MINUTE)) {
                $waitSeconds = max(1, RateLimiter::availableIn(self::RATE_LIMIT_KEY));

                return [
                    'reply' => sprintf(
                        'Kuya DEX has reached the free-tier limit of %d successful replies per minute. Please wait %d second(s) before sending your next inquiry.',
                        self::MAX_SUCCESSFUL_REPLIES_PER_MINUTE,
                        $waitSeconds,
                    ),
                    'mode' => 'rate_limited',
                    'connected' => true,
                ];
            }
        } catch (\Throwable $e) {
            // Do not block chat if cache driver is temporarily unavailable
        }

        $sanitizedPayload = $this->buildSanitizedSystemJson($selectedProjectId);
        $configuredModel = trim((string) config('services.gemini.model', 'gemini-3.5-flash-lite')) ?: 'gemini-3.5-flash-lite';

        // Automatically remap retired/congested model IDs to the fastest verified free-tier model (gemini-3.5-flash-lite)
        $legacyModelMap = [
            'gemini-1.5-flash' => 'gemini-3.5-flash-lite',
            'gemini-1.5-flash-latest' => 'gemini-3.5-flash-lite',
            'gemini-1.5-pro' => 'gemini-3.5-flash-lite',
            'gemini-1.5-pro-latest' => 'gemini-3.5-flash-lite',
            'gemini-2.5-flash' => 'gemini-3.5-flash-lite',
            'gemini-2.5-flash-lite' => 'gemini-3.5-flash-lite',
            'gemini-flash-latest' => 'gemini-3.5-flash-lite',
        ];
        $model = $legacyModelMap[strtolower($configuredModel)] ?? $configuredModel;

        $systemInstruction = $this->buildSystemInstruction($sanitizedPayload);

        $requestPayload = [
            'system_instruction' => [
                'parts' => [
                    ['text' => $systemInstruction],
                ],
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $cleanMessage],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.15,
                'maxOutputTokens' => 900,
            ],
        ];

        // Verified fast HTTP 200 free-tier models in priority order (~1.9s–2.3s response time)
        $candidateModels = array_values(array_unique([
            $model,
            'gemini-3.5-flash-lite',
            'gemini-flash-lite-latest',
            'gemini-3.1-flash-lite',
        ]));

        $response = null;

        foreach ($candidateModels as $candidateModel) {
            try {
                $endpoint = sprintf(
                    'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
                    urlencode($candidateModel),
                    urlencode($apiKey),
                );

                $attemptResponse = Http::timeout(15)
                    ->acceptJson()
                    ->post($endpoint, $requestPayload);

                if ($attemptResponse->successful()) {
                    $response = $attemptResponse;
                    break;
                }

                Log::warning('Kuya DEX Gemini API non-success response on candidate model', [
                    'model' => $candidateModel,
                    'status' => $attemptResponse->status(),
                    'body' => $attemptResponse->json() ?? $attemptResponse->body(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('Kuya DEX Gemini API candidate model timeout/exception', [
                    'model' => $candidateModel,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if (! $response || ! $response->successful()) {
            return [
                'reply' => self::ERROR_FALLBACK_REPLY,
                'mode' => 'error',
                'connected' => true,
            ];
        }

        // Extract non-thought text part from Gemini response
        $parts = (array) data_get($response->json(), 'candidates.0.content.parts', []);
        $replyText = '';
        foreach ($parts as $part) {
            if (is_array($part) && empty($part['thought']) && isset($part['text'])) {
                $replyText .= (string) $part['text'];
            }
        }
        $replyText = trim($replyText);

        if ($replyText === '') {
            $replyText = trim((string) data_get($response->json(), 'candidates.0.content.parts.0.text', ''));
        }

        if ($replyText === '') {
            return [
                'reply' => self::RESTRICTED_REPLY,
                'mode' => 'guardrail',
                'connected' => true,
            ];
        }

        // Count ONLY messages that were successfully sent to and answered by Gemini (60-second decay)
        try {
            RateLimiter::hit(self::RATE_LIMIT_KEY, 60);
        } catch (\Throwable $e) {
            // Ignore cache errors
        }

        // Normalize if the LLM triggered the guardrail phrase with minor punctuation variance
        if (stripos($replyText, 'I have no idea with that') !== false) {
            $replyText = self::RESTRICTED_REPLY;
        }

        return [
            'reply' => $replyText,
            'mode' => 'gemini',
            'connected' => true,
        ];
    }

    /**
     * Build a strictly sanitized JSON string of operational DEX PMS data.
     * Excludes all sensitive user credentials, passwords, OTPs, tokens, or session records.
     */
    public function buildSanitizedSystemJson(?int $selectedProjectId = null): string
    {
        $projects = Project::query()
            ->with([
                'creator',
                'personnel',
                'tasks.subtasks.assignedTo',
                'costs.resource',
            ])
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->get();

        $allPersonnel = Personnel::query()
            ->with('project')
            ->whereNull('deleted_at')
            ->orderBy('employee_id')
            ->get();

        $allResources = Resource::query()
            ->whereNull('deleted_at')
            ->orderBy('resource_code')
            ->get();

        $portfolioOverlaps = [];

        $formattedProjects = $projects->map(function (Project $project) use ($projects, &$portfolioOverlaps): array {
            $tasks = $project->tasks ?? collect();

            // Major Tasks & Minor Tasks (Subtasks / Checklist) breakdown
            $totalMajorTasks = $tasks->count();
            $completedMajorTasks = 0;
            $totalMinorTasks = 0;
            $completedMinorTasks = 0;

            $formattedMajorTasks = $tasks->map(function (ProjectTask $task) use (&$completedMajorTasks, &$totalMinorTasks, &$completedMinorTasks): array {
                $subtasks = $task->subtasks ?? collect();
                $minorTotal = $subtasks->count();
                $minorCompleted = $subtasks->where('is_completed', true)->count();

                $totalMinorTasks += $minorTotal;
                $completedMinorTasks += $minorCompleted;

                $isMajorComplete = $task->computed_status === 'completed';
                if ($isMajorComplete) {
                    $completedMajorTasks++;
                }

                $minorCompletionRatePct = $minorTotal > 0
                    ? round(($minorCompleted / $minorTotal) * 100, 1)
                    : ($isMajorComplete ? 100.0 : 0.0);

                return [
                    'major_task_name' => (string) $task->task_name,
                    'description' => (string) ($task->description ?: ''),
                    'start_date' => $task->start_date?->format('Y-m-d'),
                    'end_date' => $task->end_date?->format('Y-m-d'),
                    'status' => (string) $task->computed_status,
                    'is_major_task_complete' => $isMajorComplete,
                    'minor_tasks_completed_count' => $minorCompleted,
                    'minor_tasks_total_count' => $minorTotal,
                    'minor_tasks_completion_rate_pct' => $minorCompletionRatePct,
                    'minor_tasks' => $subtasks->map(fn (ProjectSubtask $st): array => [
                        'title' => (string) $st->title,
                        'is_completed' => (bool) $st->is_completed,
                        'status' => $st->is_completed ? 'Completed' : 'Pending / Incomplete',
                        'due_date' => $st->due_date?->format('Y-m-d'),
                        'assigned_to' => (string) ($st->assignedTo?->name ?: 'Unassigned'),
                    ])->values()->all(),
                ];
            })->values()->all();

            // Overall Checklist Progress (based on Minor Tasks / Subtasks checklist)
            $overallChecklistProgressPct = $totalMinorTasks > 0
                ? round(($completedMinorTasks / $totalMinorTasks) * 100, 1)
                : ($project->status === 'completed' ? 100.0 : 0.0);

            // Major Task Progression (based on completed Major Tasks out of total Major Tasks)
            $majorTaskProgressionPct = $totalMajorTasks > 0
                ? round(($completedMajorTasks / $totalMajorTasks) * 100, 1)
                : ($project->status === 'completed' ? 100.0 : 0.0);

            // Strictly separate Assigned Project Manager from other assigned expertise classifications
            $assignedProjectManagers = $project->personnel
                ->filter(fn (Personnel $p): bool => strcasecmp(trim((string) $p->expertise), 'Project Manager') === 0)
                ->map(fn (Personnel $p): array => [
                    'employee_id' => (string) $p->employee_id,
                    'full_name' => (string) $p->full_name,
                    'expertise_classification' => 'Project Manager',
                ])
                ->values();

            $otherAssignedPersonnel = $project->personnel
                ->filter(fn (Personnel $p): bool => strcasecmp(trim((string) $p->expertise), 'Project Manager') !== 0)
                ->map(fn (Personnel $p): array => [
                    'employee_id' => (string) $p->employee_id,
                    'full_name' => (string) $p->full_name,
                    'expertise_classification' => (string) $p->expertise,
                ])
                ->values();

            $projectExpertiseBreakdown = $project->personnel
                ->groupBy(fn (Personnel $p): string => (string) ($p->expertise ?: 'Unspecified'))
                ->map(fn ($group, $expertiseName): array => [
                    'expertise_classification' => (string) $expertiseName,
                    'count' => $group->count(),
                    'personnel_names' => $group->map(fn (Personnel $p) => "{$p->full_name} ({$p->employee_id})")->values()->all(),
                ])
                ->values()
                ->all();

            // Compute overlapping project schedules against other projects
            $overlappingProjects = [];
            $pStart = $project->start_date ? Carbon::parse($project->start_date)->startOfDay() : null;
            $pEnd = $project->target_completion_date ? Carbon::parse($project->target_completion_date)->startOfDay() : null;

            if ($pStart && $pEnd && $pEnd->gte($pStart)) {
                foreach ($projects as $other) {
                    if ((int) $other->id === (int) $project->id) {
                        continue;
                    }
                    $oStart = $other->start_date ? Carbon::parse($other->start_date)->startOfDay() : null;
                    $oEnd = $other->target_completion_date ? Carbon::parse($other->target_completion_date)->startOfDay() : null;
                    if (! $oStart || ! $oEnd || $oEnd->lt($oStart)) {
                        continue;
                    }

                    if ($pStart->lte($oEnd) && $pEnd->gte($oStart)) {
                        $overlapStart = $pStart->gt($oStart) ? $pStart : $oStart;
                        $overlapEnd = $pEnd->lt($oEnd) ? $pEnd : $oEnd;
                        $overlapDays = (int) ($overlapStart->diffInDays($overlapEnd) + 1);

                        $overlappingProjects[] = [
                            'project_code' => (string) $other->project_code,
                            'project_name' => (string) $other->name,
                            'status' => (string) $other->status,
                            'other_start_date' => $oStart->format('Y-m-d'),
                            'other_target_completion_date' => $oEnd->format('Y-m-d'),
                            'overlap_start_date' => $overlapStart->format('Y-m-d'),
                            'overlap_end_date' => $overlapEnd->format('Y-m-d'),
                            'overlap_days' => $overlapDays,
                        ];

                        if ((int) $project->id < (int) $other->id) {
                            $portfolioOverlaps[] = [
                                'project_a' => $project->project_code . ' — ' . $project->name,
                                'project_b' => $other->project_code . ' — ' . $other->name,
                                'overlap_window' => $overlapStart->format('Y-m-d') . ' to ' . $overlapEnd->format('Y-m-d'),
                                'overlap_days' => $overlapDays,
                            ];
                        }
                    }
                }
            }

            // Compute overlapping Major Tasks within this project
            $overlappingMajorTasks = [];
            $taskList = $tasks->values();
            for ($i = 0; $i < $taskList->count(); $i++) {
                for ($j = $i + 1; $j < $taskList->count(); $j++) {
                    $tA = $taskList[$i];
                    $tB = $taskList[$j];
                    if (! $tA->start_date || ! $tA->end_date || ! $tB->start_date || ! $tB->end_date) {
                        continue;
                    }
                    $aStart = Carbon::parse($tA->start_date)->startOfDay();
                    $aEnd = Carbon::parse($tA->end_date)->startOfDay();
                    $bStart = Carbon::parse($tB->start_date)->startOfDay();
                    $bEnd = Carbon::parse($tB->end_date)->startOfDay();

                    if ($aStart->lte($bEnd) && $aEnd->gte($bStart)) {
                        $ovStart = $aStart->gt($bStart) ? $aStart : $bStart;
                        $ovEnd = $aEnd->lt($bEnd) ? $aEnd : $bEnd;
                        $overlappingMajorTasks[] = [
                            'major_task_1' => (string) $tA->task_name,
                            'major_task_2' => (string) $tB->task_name,
                            'overlap_start_date' => $ovStart->format('Y-m-d'),
                            'overlap_end_date' => $ovEnd->format('Y-m-d'),
                            'overlap_days' => (int) ($ovStart->diffInDays($ovEnd) + 1),
                        ];
                    }
                }
            }

            // Itemized project expenses / expenditures
            $costs = $project->costs ?? collect();
            $expensesList = $costs->map(fn (ProjectCost $c): array => [
                'cost_type' => (string) ($c->cost_type ?: 'General'),
                'description' => (string) ($c->description ?: ''),
                'quantity' => (int) ($c->quantity ?? 1),
                'amount_php' => round((float) $c->amount, 2),
                'incurred_date' => $c->incurred_date?->format('Y-m-d'),
                'material_resource_code' => (string) ($c->resource?->resource_code ?? ''),
                'material_name' => (string) ($c->resource?->name ?? ''),
            ])->values()->all();

            return [
                'id' => (int) $project->id,
                'project_code' => (string) $project->project_code,
                'name' => (string) $project->name,
                'description' => (string) ($project->description ?: 'No description provided'),
                'project_type' => (string) ($project->project_type ?: 'Standard Elevator'),
                'client' => (string) ($project->client_name ?: 'N/A'),
                'created_by' => (string) ($project->creator?->name ?: 'System'),
                'status' => (string) $project->status,
                'site_location' => [
                    'full_address' => (string) ($project->full_address ?: 'N/A'),
                    'address' => (string) ($project->address ?: ''),
                    'barangay' => (string) ($project->barangay ?: ''),
                    'city' => (string) ($project->city ?: ''),
                    'province' => (string) ($project->province ?: ''),
                    'region' => (string) ($project->region ?: ''),
                    'postal_code' => (string) ($project->postal_code ?: ''),
                ],
                'warranty' => [
                    'warranty_period' => (string) ($project->warranty_period ?: 'Not specified'),
                    'warranty_end_date' => $project->warranty_end_date?->format('Y-m-d'),
                    'has_active_warranty' => (bool) $project->has_active_warranty,
                    'warranty_days_remaining' => (int) $project->warranty_days_remaining,
                ],
                'elevator_specifications' => [
                    'project_type' => (string) ($project->project_type ?: 'Standard Elevator'),
                    'rated_capacity_kg' => (string) ($project->rated_capacity ?: 'N/A'),
                    'lifting_speed_mps' => (string) ($project->lifting_speed ?: 'N/A'),
                    'no_of_stops' => (int) ($project->no_of_stops ?? 0),
                    'cage_dimension' => (string) ($project->cage_dimension ?: 'Standard'),
                    'wall_and_ceiling' => (string) ($project->wall_and_ceiling ?: 'N/A'),
                    'flooring' => (string) ($project->flooring ?: 'N/A'),
                    'door_operation' => (string) ($project->door_operation ?: 'N/A'),
                    'power_source' => (string) ($project->power_source ?: 'N/A'),
                    'operation_mode' => (string) ($project->operation ?: 'N/A'),
                ],
                'project_manager' => [
                    'has_assigned_project_manager' => $assignedProjectManagers->isNotEmpty(),
                    'project_manager_names' => $assignedProjectManagers->isNotEmpty()
                        ? $assignedProjectManagers->pluck('full_name')->implode(', ')
                        : 'No Project Manager currently assigned',
                    'assigned_project_managers' => $assignedProjectManagers->all(),
                ],
                'assigned_manpower_by_expertise_classification' => $projectExpertiseBreakdown,
                'other_assigned_manpower_non_pm' => $otherAssignedPersonnel->all(),
                'schedule_and_progress' => [
                    'start_date' => $project->start_date?->format('Y-m-d'),
                    'target_completion_date' => $project->target_completion_date?->format('Y-m-d'),
                    'major_task_progression_pct' => $majorTaskProgressionPct,
                    'completed_major_tasks_count' => $completedMajorTasks,
                    'total_major_tasks_count' => $totalMajorTasks,
                    'overall_checklist_progress_pct' => $overallChecklistProgressPct,
                    'completed_minor_tasks_count' => $completedMinorTasks,
                    'total_minor_tasks_count' => $totalMinorTasks,
                    'major_tasks' => $formattedMajorTasks,
                    'overlapping_projects_schedule' => $overlappingProjects,
                    'overlapping_major_tasks_within_project' => $overlappingMajorTasks,
                ],
                'costing_and_expenses' => [
                    'contract_price_php' => round((float) $project->contract_price, 2),
                    'total_spend_php' => round((float) $project->actual_spend, 2),
                    'remaining_budget_php' => round((float) $project->remaining_budget, 2),
                    'spend_percentage' => (float) $project->spend_percentage,
                    'costing_status' => (string) $project->costing_status,
                    'payment_status' => (string) ($project->payment_status ?: 'N/A'),
                    'payment_terms' => (string) ($project->payment_terms ?: 'N/A'),
                    'total_expense_entries_count' => count($expensesList),
                    'expenses_and_expenditures' => $expensesList,
                ],
            ];
        })->values();

        $activeSelectedProject = $selectedProjectId
            ? ($formattedProjects->firstWhere('id', $selectedProjectId) ?? $formattedProjects->first())
            : $formattedProjects->first();

        $availableManpower = $allPersonnel
            ->filter(fn (Personnel $p) => $p->project_id === null)
            ->map(fn (Personnel $p) => [
                'employee_id' => (string) $p->employee_id,
                'full_name' => (string) $p->full_name,
                'expertise_classification' => (string) $p->expertise,
                'is_project_manager_role' => strcasecmp(trim((string) $p->expertise), 'Project Manager') === 0,
                'availability' => 'Available (Unassigned)',
            ])
            ->values();

        $assignedManpower = $allPersonnel
            ->filter(fn (Personnel $p) => $p->project_id !== null)
            ->map(fn (Personnel $p) => [
                'employee_id' => (string) $p->employee_id,
                'full_name' => (string) $p->full_name,
                'expertise_classification' => (string) $p->expertise,
                'is_project_manager_role' => strcasecmp(trim((string) $p->expertise), 'Project Manager') === 0,
                'assigned_project_code' => (string) ($p->project?->project_code ?? 'N/A'),
                'assigned_project_name' => (string) ($p->project?->name ?? 'N/A'),
            ])
            ->values();

        // Build complete breakdown across all 8 official expertise classifications
        $allExpertiseNames = collect(Personnel::EXPERTISE_OPTIONS)
            ->merge($allPersonnel->pluck('expertise')->filter())
            ->unique()
            ->values();

        $breakdownByExpertise = $allExpertiseNames->map(function (string $expName) use ($allPersonnel): array {
            $matching = $allPersonnel->filter(
                fn (Personnel $p): bool => strcasecmp(trim((string) $p->expertise), trim($expName)) === 0,
            );
            $availGroup = $matching->filter(fn (Personnel $p): bool => $p->project_id === null);
            $assignedGroup = $matching->filter(fn (Personnel $p): bool => $p->project_id !== null);

            return [
                'expertise_classification' => $expName,
                'total_personnel_count' => $matching->count(),
                'available_unassigned_count' => $availGroup->count(),
                'assigned_count' => $assignedGroup->count(),
                'available_personnel' => $availGroup->map(fn (Personnel $p): array => [
                    'employee_id' => (string) $p->employee_id,
                    'full_name' => (string) $p->full_name,
                ])->values()->all(),
                'assigned_personnel' => $assignedGroup->map(fn (Personnel $p): array => [
                    'employee_id' => (string) $p->employee_id,
                    'full_name' => (string) $p->full_name,
                    'assigned_project' => ($p->project?->project_code ?? 'N/A') . ' — ' . ($p->project?->name ?? 'N/A'),
                ])->values()->all(),
            ];
        })->values()->all();

        $materialsCatalog = $allResources->map(fn (Resource $r) => [
            'resource_code' => (string) $r->resource_code,
            'name' => (string) $r->name,
            'type' => (string) ($r->type ?: 'Material'),
            'model' => (string) ($r->model ?: 'Standard'),
            'description' => (string) ($r->description ?: ''),
            'condition' => (string) ($r->condition ?: 'Good'),
            'quantity_available' => (int) $r->quantity_available,
            'is_low_stock' => $r->isLowStock(),
            'unit_cost_php' => round((float) $r->unit_cost, 2),
            'selling_price_php' => round((float) $r->price, 2),
            'demand_status' => (string) ($r->demand_status ?: 'fast-moving'),
        ])->values();

        $payload = [
            'system' => 'DEX Project Management System (DEX PMS)',
            'active_context_project' => $activeSelectedProject,
            'projects_summary' => [
                'total_projects' => $formattedProjects->count(),
                'ongoing_count' => $formattedProjects->where('status', 'ongoing')->count(),
                'delayed_count' => $formattedProjects->where('status', 'delayed')->count(),
                'pending_count' => $formattedProjects->where('status', 'pending')->count(),
                'completed_count' => $formattedProjects->where('status', 'completed')->count(),
                'portfolio_overlapping_project_schedules' => $portfolioOverlaps,
                'projects' => $formattedProjects->all(),
            ],
            'manpower_data' => [
                'official_expertise_classifications' => Personnel::EXPERTISE_OPTIONS,
                'note_on_classifications' => 'Every personnel member in DEX PMS belongs to one specific expertise classification from official_expertise_classifications. Note that "Worker / Installation Personnel" is ONE specific expertise classification, not a generic label for all manpower.',
                'total_manpower_count' => $allPersonnel->count(),
                'available_manpower_count' => $availableManpower->count(),
                'assigned_manpower_count' => $assignedManpower->count(),
                'breakdown_by_expertise_classification' => $breakdownByExpertise,
                'available_manpower' => $availableManpower->all(),
                'assigned_manpower' => $assignedManpower->all(),
            ],
            'materials_catalog_data' => [
                'total_catalog_materials' => $materialsCatalog->count(),
                'out_of_stock_count' => $materialsCatalog->where('quantity_available', '<=', 0)->count(),
                'low_stock_count' => $materialsCatalog->where('is_low_stock', true)->count(),
                'catalog_materials' => $materialsCatalog->all(),
            ],
            'costing_summary_data' => [
                'portfolio_total_contract_price_php' => round((float) $projects->sum('contract_price'), 2),
                'portfolio_total_spend_php' => round((float) $projects->sum('actual_spend'), 2),
            ],
        ];

        return (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Deterministic pre-check for explicitly restricted/unrelated questions.
     */
    public function isRestrictedQuery(string $message): bool
    {
        $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $message) ?? ''));
        if ($normalized === '') {
            return true;
        }

        // Explicitly forbidden patterns requested by the specification
        $explicitForbiddenPatterns = [
            '/\bwhat\s+(is|\'s)\s+the\s+(date|day|time)\b/i',
            '/\b(date|day)\s+today\b/i',
            '/\bwhat\s+today(\'s)?\s+date\b/i',
            '/\bwho\s+(is|\'s|was)\s+the\s+(president|prime\s+minister|mayor|governor|king|queen|pope)\b/i',
            '/\bpresident\s+of\b/i',
            '/\bwho\s+is\s+this\??$/i',
            '/\bwhat\s+is\s+this\s+thing\??$/i',
            '/\bwhat\s+is\s+this\??$/i',
            '/\b(weather|forecast)\s+(today|tomorrow|in)\b/i',
            '/\btell\s+me\s+a\s+(joke|story|riddle|poem)\b/i',
            '/\bcapital\s+of\b/i',
            '/\brecipe\s+for\b/i',
        ];

        foreach ($explicitForbiddenPatterns as $pattern) {
            if (preg_match($pattern, $normalized) === 1) {
                return true;
            }
        }

        // Check if the query references any allowed DEX PMS, Manpower Expertise/Classification, Project Details, Schedule Overlaps, Catalog Materials, or Elevator construction domain terms
        $allowedDomainPattern = '/\b(project|projects|proj|pms|dex|kuya\s*dex|creator|created\s+by|who\s+created|who\s+made|author|warranty|description|describe|details|overview|summary|type|types|client|clients|customer|code|codes|location|locations|site|address|barangay|city|province|region|postal|manpower|personnel|staff|crew|expertise|expertises|classification|classifications|category|categories|role|roles|position|positions|designation|designations|specialty|specialties|specialization|skill|skills|job|jobs|department|manager|project\s+manager|technician|technicians|engineer|engineers|site\s+engineer|safety\s+officer|foreman|foremen|qa\s+inspector|inspector|inspectors|elevator\s+control|installation\s+personnel|electrician|mechanic|installer|installers|worker|workers|employee|employees|headcount|available|unassigned|assigned|material|materials|resource|resources|inventory|stock|stocks|catalog|item|items|model|condition|demand|fast-moving|slow-moving|supplier|procurement|reorder|shortage|deficit|cost|costs|costing|spend|spending|spent|budget|expense|expenses|expenditure|expenditures|price|unit\s+cost|contract|margin|revenue|payment|terms|invoice|progress|progression|completion|complete|completed|incomplete|major\s+task|major\s+tasks|minor\s+task|minor\s+tasks|mother\s+task|baby\s+task|task|tasks|subtask|subtasks|sub-task|sub-tasks|checklist|schedule|schedules|scheduling|overlap|overlapping|conflict|conflicts|concurrent|simultaneous|timeline|delay|delayed|overdue|milestone|start\s+date|end\s+date|target\s+date|due\s+date|status|ongoing|pending|allocat|simulat|feasibility|elevator|elevators|lift|lifts|escalator|hoist|hoistway|shaft|pit|overhead|machine\s+room|mrl|traction|hydraulic|counterweight|car|cage|cabin|door|interlock|governor|overspeed|safety\s+gear|buffer|guide\s+rail|wire\s+rope|sheave|inverter|vvvf|controller|plc|stops|landing|capacity|rated\s+load|lifting\s+speed|power\s+source|flooring|wall|ceiling|construction|installation|commissioning|testing|maintenance|blueprint|transformer|ard|rescue\s+device|motor|brake|panel|button|sensor|switch|bracket|bolt|cable)\b/i';

        // Allow brief polite greetings about Kuya DEX's PMS capabilities
        $greetingPattern = '/^(hi|hello|hey|good\s+morning|good\s+afternoon|good\s+evening|help|what\s+can\s+you\s+do)(\s+kuya\s*dex)?[\?\!\.]*$/i';

        if (preg_match($allowedDomainPattern, $normalized) === 1 || preg_match($greetingPattern, $normalized) === 1) {
            return false;
        }

        // Dynamic check against official expertise classifications, live Catalog Materials, Projects, and Personnel
        foreach (Personnel::EXPERTISE_OPTIONS as $expOption) {
            $term = strtolower(trim($expOption));
            if ($term !== '' && str_contains($normalized, $term)) {
                return false;
            }
        }

        try {
            $resourceTerms = Resource::query()
                ->whereNull('deleted_at')
                ->get(['name', 'resource_code', 'model', 'type']);

            foreach ($resourceTerms as $res) {
                foreach ([$res->resource_code, $res->name, $res->model, $res->type] as $val) {
                    $term = strtolower(trim((string) $val));
                    if (strlen($term) >= 3 && str_contains($normalized, $term)) {
                        return false;
                    }
                }
            }

            $projectTerms = Project::query()
                ->whereNull('deleted_at')
                ->get(['name', 'project_code', 'client_name', 'city']);

            foreach ($projectTerms as $proj) {
                foreach ([$proj->project_code, $proj->name, $proj->client_name, $proj->city] as $val) {
                    $term = strtolower(trim((string) $val));
                    if (strlen($term) >= 3 && str_contains($normalized, $term)) {
                        return false;
                    }
                }
            }

            $personnelTerms = Personnel::query()
                ->whereNull('deleted_at')
                ->get(['employee_id', 'first_name', 'last_name', 'expertise']);

            foreach ($personnelTerms as $person) {
                foreach ([$person->employee_id, $person->first_name, $person->last_name, $person->expertise] as $val) {
                    $term = strtolower(trim((string) $val));
                    if (strlen($term) >= 3 && str_contains($normalized, $term)) {
                        return false;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Ignore DB lookup errors during pre-check
        }

        return true;
    }

    /**
     * Build the system prompt with strict domain guardrails and JSON operational context.
     */
    private function buildSystemInstruction(string $sanitizedJson): string
    {
        $restrictedReply = self::RESTRICTED_REPLY;

        return <<<PROMPT
You are "Kuya DEX", the personal AI assistant for the DEX Project Management System (DEX PMS), an elevator installation and construction project management platform.

STRICT TOPIC GUARDRAILS (CRITICAL):
1. You may ONLY answer inquiries related to:
   - DEX PMS Project Details:
     * Project creator / created by (`created_by`)
     * Warranty period, warranty end date, active warranty status (`warranty`)
     * Description (`description`)
     * Project type (`project_type`)
     * Client (`client`)
     * Project code (`project_code`)
     * Site location / project address (`site_location.full_address`, city, barangay, province, region)
     * Expenses / expenditures (`costing_and_expenses.expenses_and_expenditures`, contract price, total spend, remaining budget)
     * Project Manager (`project_manager`) and assigned personnel grouped by expertise (`assigned_manpower_by_expertise_classification`)
     * Major Tasks and Minor Tasks / Subtasks (`schedule_and_progress.major_tasks`)
   - DEX PMS Schedule & Overlapping Schedules:
     * Any question about project schedules, start/target dates, task timelines, or overlapping schedules across projects (`overlapping_projects_schedule`, `portfolio_overlapping_project_schedules`) or within a project (`overlapping_major_tasks_within_project`).
   - DEX PMS Materials Catalog:
     * Any question about materials inside the catalog (`materials_catalog_data.catalog_materials`), including stock quantity, unit cost, selling price, model, condition, low-stock status, and demand status.
   - DEX PMS Manpower, Expertise & Classifications (`manpower_data`):
     * All 8 official expertise classifications (`official_expertise_classifications`) and the exact personnel breakdown per expertise (`breakdown_by_expertise_classification`).
   - Elevator operation, technical specifications, installation, maintenance, safety standards, and construction.
2. You MUST NOT answer any unrelated questions outside DEX PMS or elevator operation/construction (e.g., "What is the date today?", "Who is the president of this country?", "Who is this?", "What is this thing?", general trivia, politics, weather, entertainment).
3. If the user asks ANY question outside the allowed topics above, you MUST reply with this EXACT text and nothing else:
{$restrictedReply}

CRITICAL DOMAIN ACCURACY RULES:
1. MANPOWER EXPERTISE & CLASSIFICATIONS RULE:
   - DEX PMS has 8 official Manpower Expertise / Classifications (`manpower_data.official_expertise_classifications`):
     1) **Site Engineer**
     2) **Foreman**
     3) **Safety Officer**
     4) **Technician**
     5) **Project Manager**
     6) **QA Inspector**
     7) **Elevator Control**
     8) **Worker / Installation Personnel**
   - IMPORTANT: **"Worker / Installation Personnel"** is ONE specific expertise classification—do NOT treat "Worker / Installation Personnel" as a generic term for all manpower, and do NOT lump other roles into it.
   - Whenever the user asks about expertise, classifications, roles, or manpower breakdown, explicitly list the distinct expertise classifications using `manpower_data.breakdown_by_expertise_classification` (showing total, available, and assigned personnel for each classification).
2. PROJECT MANAGER RULE:
   - A project's Project Manager is ONLY the assigned personnel whose expertise classification is explicitly "Project Manager" (listed under `project_manager.assigned_project_managers` / `project_manager.project_manager_names`).
   - NEVER call a Technician, Site Engineer, Foreman, Safety Officer, QA Inspector, Elevator Control, or Worker / Installation Personnel a "Project Manager".
   - If `project_manager.has_assigned_project_manager` is false, state clearly that there is no Project Manager currently assigned to that project (you may separately list the other assigned personnel and their exact `expertise_classification`).
3. MAJOR TASK vs. MINOR TASK & PROGRESSION RULE:
   - A Major Task (`major_task_name`) is counted as complete ONLY if `is_major_task_complete` is true (100% of its minor tasks are complete).
   - When asked about project progress or progression, base the main project progression on completed Major Tasks (`schedule_and_progress.major_task_progression_pct`, i.e., `completed_major_tasks_count` out of `total_major_tasks_count` Major Tasks completed), and also mention the Minor Tasks checklist completion rate (`overall_checklist_progress_pct`, `completed_minor_tasks_count` out of `total_minor_tasks_count` minor tasks) for complete clarity.
   - When asked about Minor Tasks (or subtasks), list the `minor_tasks` inside each Major Task along with their status (`Completed` or `Pending / Incomplete`) and the `minor_tasks_completion_rate_pct`.
4. CONTEXT & FORMATTING:
   - If the user asks about "this project" or "project x" without naming a specific project code, use `active_context_project` from the JSON data and mention its `project_code` and `name`.
   - Always format Philippine Peso amounts clearly (e.g., ₱1,250,000.00).

SANITIZED DEX PMS JSON DATA:
{$sanitizedJson}
PROMPT;
    }
}
