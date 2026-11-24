<?php

namespace App\Services\DecisionSupport;

use App\Models\Personnel;
use App\Models\Project;
use App\Models\ProjectCost;
use App\Models\ProjectPersonnelHistory;
use App\Models\ProjectStatusHistory;
use App\Models\ProjectSubtask;
use App\Models\ProjectTask;
use App\Models\Resource;
use App\Models\StaffPendingAction;
use App\Services\LiveUpdateService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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

    public const STAFF_MANPOWER_DENIED_REPLY = 'I cannot execute manpower assignments or removals for your account. Assigning or removing project personnel is restricted to **Administrators** and **Project Managers**.';

    public const STAFF_REQUEST_APPROVAL_REDIRECT_REPLY = 'I cannot approve or reject staff requests directly from the chat. To review, examine, and approve or reject those requests, please go to the **Staff Requests** module.';

    public const USER_MANAGEMENT_RESTRICTED_REPLY = 'I do not have access to or guidance for the **User Management** module. Please let us focus on your project management modules (such as Dashboard, Projects, Costing, Materials Catalog, Manpower, Staff Requests, Approval List, Reports, or Decision Support).';

    /**
     * Free-tier protection: maximum successful Gemini API replies per 60-second window.
     * Only increments when a message is actually sent and successfully answered by Gemini
     * (never counts UI button spam, queued messages waiting in line, or local guardrail checks).
     */
    public const MAX_SUCCESSFUL_REPLIES_PER_MINUTE = 12;

    private const RATE_LIMIT_KEY = 'kuya_dex:successful_gemini_replies';

    /**
     * Check whether the currently authenticated user is authorized to assign or remove project manpower.
     * Strictly allows only Admin and Manager roles; Staff users are prohibited.
     */
    public function canCurrentUserManageManpower(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        if ($user->hasRole('Staff')) {
            return false;
        }

        return $user->hasAnyRole(['Admin', 'Manager']);
    }

    /**
     * Process an inquiry to Kuya DEX.
     *
     * @param string $message
     * @param int|null $selectedProjectId
     * @param array<string, mixed>|null $confirmAction
     * @param bool $includeProjectTasks
     * @return array{reply: string|null, mode: string, connected: bool, action_executed?: bool, action_type?: string, project_id?: int, pending_action?: array<string, mixed>}
     */
    public function ask(
        string $message,
        ?int $selectedProjectId = null,
        ?array $confirmAction = null,
        bool $includeProjectTasks = true,
    ): array {
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

        // If the user clicked [Yes] (or typed "yes") to confirm a pending manpower action, execute it now
        if (is_array($confirmAction) && ! empty($confirmAction['action_type'])) {
            return $this->executeConfirmedManpowerAction($confirmAction, $selectedProjectId);
        }

        // Strict Exclusion: Kuya DEX must NOT guide or answer regarding User Management / Account Administration
        if ($this->isUserManagementQuery($cleanMessage)) {
            return [
                'reply' => self::USER_MANAGEMENT_RESTRICTED_REPLY,
                'mode' => 'guardrail',
                'connected' => true,
            ];
        }

        // Intercept and deterministically preview (or block for Staff) manpower assignment/unassignment commands
        $actionCommandResult = $this->tryHandleManpowerActionCommand($cleanMessage, $selectedProjectId);
        if ($actionCommandResult !== null) {
            return $actionCommandResult;
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

        $sanitizedPayload = $this->buildSanitizedSystemJson($selectedProjectId, $includeProjectTasks);
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
                'maxOutputTokens' => 1800,
            ],
        ];

        // Attach Gemini Function Calling declarations ONLY for authorized Admin / Manager users
        // and only when the message is an explicit manpower assignment/removal command (to keep forecasting/analysis fast)
        if ($this->canCurrentUserManageManpower() && $this->shouldAttachManpowerTools($cleanMessage)) {
            $requestPayload['tools'] = [
                [
                    'function_declarations' => $this->getManpowerFunctionDeclarations(),
                ],
            ];
        }

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

                $attemptResponse = Http::timeout(25)
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
            // If Gemini API is temporarily congested or timed out on a forecasting / business analysis inquiry,
            // generate a rich, data-grounded Project & Business Analyst report directly from the live DEX PMS snapshot
            $fallbackForecast = $this->tryGenerateLocalForecastAndAnalystFallback($cleanMessage, $selectedProjectId, $includeProjectTasks);
            if ($fallbackForecast !== null) {
                return [
                    'reply' => $fallbackForecast,
                    'mode' => 'gemini',
                    'connected' => true,
                ];
            }

            return [
                'reply' => self::ERROR_FALLBACK_REPLY,
                'mode' => 'error',
                'connected' => true,
            ];
        }

        // Extract function call or non-thought text part from Gemini response
        $parts = (array) data_get($response->json(), 'candidates.0.content.parts', []);
        foreach ($parts as $part) {
            if (is_array($part) && isset($part['functionCall']) && is_array($part['functionCall'])) {
                try {
                    RateLimiter::hit(self::RATE_LIMIT_KEY, 60);
                } catch (\Throwable $e) {
                    // Ignore cache errors
                }

                return $this->executeGeminiFunctionCall(
                    (string) ($part['functionCall']['name'] ?? ''),
                    (array) ($part['functionCall']['args'] ?? []),
                    $cleanMessage,
                    $selectedProjectId,
                );
            }
        }

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
    public function buildSanitizedSystemJson(?int $selectedProjectId = null, bool $includeProjectTasks = true): string
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

        $formattedProjects = $projects->map(function (Project $project) use ($projects, &$portfolioOverlaps, $includeProjectTasks): array {
            $tasks = $project->tasks ?? collect();
            $today = Carbon::now()->startOfDay();

            // Major Tasks & Minor Tasks (Subtasks / Checklist) breakdown + execution velocity diagnostics
            $totalMajorTasks = $tasks->count();
            $completedMajorTasks = 0;
            $inProgressMajorTasks = 0;
            $pendingMajorTasks = 0;
            $overdueMajorTasks = 0;
            $totalMinorTasks = 0;
            $completedMinorTasks = 0;
            $overdueMinorTasks = 0;
            $unassignedPendingMinorTasks = 0;
            $overdueMajorTaskNames = [];
            $overdueMinorTaskList = [];
            $bottleneckMajorTasks = [];

            $formattedMajorTasks = $tasks->map(function (ProjectTask $task) use (
                $today,
                &$completedMajorTasks,
                &$inProgressMajorTasks,
                &$pendingMajorTasks,
                &$overdueMajorTasks,
                &$totalMinorTasks,
                &$completedMinorTasks,
                &$overdueMinorTasks,
                &$unassignedPendingMinorTasks,
                &$overdueMajorTaskNames,
                &$overdueMinorTaskList,
                &$bottleneckMajorTasks,
            ): array {
                $subtasks = $task->subtasks ?? collect();
                $minorTotal = $subtasks->count();
                $minorCompleted = $subtasks->where('is_completed', true)->count();

                $totalMinorTasks += $minorTotal;
                $completedMinorTasks += $minorCompleted;

                $computedStatus = (string) $task->computed_status;
                $isMajorComplete = $computedStatus === 'completed';
                if ($isMajorComplete) {
                    $completedMajorTasks++;
                } elseif ($computedStatus === 'in_progress') {
                    $inProgressMajorTasks++;
                } else {
                    $pendingMajorTasks++;
                }

                $minorCompletionRatePct = $minorTotal > 0
                    ? round(($minorCompleted / $minorTotal) * 100, 1)
                    : ($isMajorComplete ? 100.0 : 0.0);

                $taskEnd = $task->end_date ? Carbon::parse($task->end_date)->startOfDay() : null;
                $isMajorTaskOverdue = ! $isMajorComplete && $taskEnd && $today->gt($taskEnd);
                $majorDaysOverdue = $isMajorTaskOverdue && $taskEnd ? (int) $taskEnd->diffInDays($today) : 0;

                if ($isMajorTaskOverdue) {
                    $overdueMajorTasks++;
                    $overdueMajorTaskNames[] = sprintf(
                        '%s (ended %s, %d day(s) overdue, %s%% minor tasks done)',
                        (string) $task->task_name,
                        $taskEnd?->format('Y-m-d') ?? 'N/A',
                        $majorDaysOverdue,
                        (string) $minorCompletionRatePct,
                    );
                }

                if (! $isMajorComplete && ($isMajorTaskOverdue || ($computedStatus === 'in_progress' && $minorCompletionRatePct < 50))) {
                    $bottleneckMajorTasks[] = sprintf(
                        '%s (%d/%d minor tasks completed — %s%%)',
                        (string) $task->task_name,
                        $minorCompleted,
                        $minorTotal,
                        (string) $minorCompletionRatePct,
                    );
                }

                $formattedMinorTasks = $subtasks->map(function (ProjectSubtask $st) use (
                    $task,
                    $today,
                    &$overdueMinorTasks,
                    &$unassignedPendingMinorTasks,
                    &$overdueMinorTaskList,
                ): array {
                    $isDone = (bool) $st->is_completed;
                    $stDue = $st->due_date ? Carbon::parse($st->due_date)->startOfDay() : null;
                    $isStOverdue = ! $isDone && $stDue && $today->gt($stDue);
                    $stDaysOverdue = $isStOverdue && $stDue ? (int) $stDue->diffInDays($today) : 0;
                    $assignedName = (string) ($st->assignedTo?->name ?: 'Unassigned');

                    if (! $isDone && $st->assigned_to === null) {
                        $unassignedPendingMinorTasks++;
                    }

                    if ($isStOverdue) {
                        $overdueMinorTasks++;
                        $overdueMinorTaskList[] = sprintf(
                            '%s [under %s] — due %s (%d day(s) overdue, assigned to: %s)',
                            (string) $st->title,
                            (string) $task->task_name,
                            $stDue?->format('Y-m-d') ?? 'N/A',
                            $stDaysOverdue,
                            $assignedName,
                        );
                    }

                    return [
                        'title' => (string) $st->title,
                        'is_completed' => $isDone,
                        'status' => $isDone
                            ? 'Completed'
                            : ($isStOverdue ? "Overdue by {$stDaysOverdue} day(s)" : 'Pending / Incomplete'),
                        'due_date' => $st->due_date?->format('Y-m-d'),
                        'completed_at' => $st->completed_at?->format('Y-m-d'),
                        'assigned_to' => $assignedName,
                    ];
                })->values()->all();

                return [
                    'major_task_name' => (string) $task->task_name,
                    'description' => (string) ($task->description ?: ''),
                    'start_date' => $task->start_date?->format('Y-m-d'),
                    'end_date' => $task->end_date?->format('Y-m-d'),
                    'status' => $computedStatus,
                    'is_major_task_complete' => $isMajorComplete,
                    'is_overdue' => $isMajorTaskOverdue,
                    'days_overdue' => $majorDaysOverdue,
                    'minor_tasks_completed_count' => $minorCompleted,
                    'minor_tasks_total_count' => $minorTotal,
                    'minor_tasks_completion_rate_pct' => $minorCompletionRatePct,
                    'minor_tasks' => $formattedMinorTasks,
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

            $contractPrice = (float) $project->contract_price;
            $actualSpend = (float) $project->actual_spend;
            $remainingBudget = (float) $project->remaining_budget;
            $projectedGrossMarginPhp = round($contractPrice - $actualSpend, 2);
            $projectedGrossMarginPct = $contractPrice > 0 ? round(($projectedGrossMarginPhp / $contractPrice) * 100, 1) : 0.0;

            $now = Carbon::now()->startOfDay();
            $daysToTarget = null;
            $isOverdue = false;
            $plannedElapsedTimePct = 0.0;
            $executionVelocityStatus = 'On Pace';
            $projectedEstimatedCompletionDate = $project->target_completion_date?->format('Y-m-d') ?? 'Not set';

            if ($project->start_date && $project->target_completion_date) {
                $pStartDay = Carbon::parse($project->start_date)->startOfDay();
                $pTargetDay = Carbon::parse($project->target_completion_date)->startOfDay();
                $totalPlannedDays = max(1, (int) $pStartDay->diffInDays($pTargetDay) + 1);
                $elapsedDays = $now->lt($pStartDay) ? 0 : (int) $pStartDay->diffInDays($now) + 1;
                $plannedElapsedTimePct = min(100.0, round(($elapsedDays / $totalPlannedDays) * 100, 1));
                $daysToTarget = (int) $now->diffInDays($pTargetDay, false);
                $isOverdue = ($daysToTarget < 0 && $project->status !== 'completed');

                if ($project->status === 'completed') {
                    $executionVelocityStatus = 'Completed';
                } elseif ($isOverdue) {
                    $executionVelocityStatus = 'Critical Slippage (Past Target Completion Date)';
                } elseif ($overallChecklistProgressPct >= $plannedElapsedTimePct + 10) {
                    $executionVelocityStatus = 'Ahead of Schedule (Strong Task Execution Pace)';
                } elseif ($plannedElapsedTimePct - $overallChecklistProgressPct >= 25) {
                    $executionVelocityStatus = 'Behind Schedule (Slow Task Execution Velocity)';
                } elseif ($plannedElapsedTimePct - $overallChecklistProgressPct >= 12) {
                    $executionVelocityStatus = 'Slightly Behind Pace (Needs Task Acceleration)';
                }

                if ($project->status !== 'completed' && $elapsedDays > 0 && $overallChecklistProgressPct > 0 && $overallChecklistProgressPct < 100) {
                    $estimatedTotalDaysNeeded = (int) ceil($elapsedDays / ($overallChecklistProgressPct / 100.0));
                    $projectedEstimatedCompletionDate = $pStartDay->copy()->addDays(max($totalPlannedDays, $estimatedTotalDaysNeeded) - 1)->format('Y-m-d');
                }
            } elseif ($project->target_completion_date) {
                $target = Carbon::parse($project->target_completion_date)->startOfDay();
                $daysToTarget = (int) $now->diffInDays($target, false);
                $isOverdue = ($daysToTarget < 0 && $project->status !== 'completed');
            }

            $spendPct = (float) $project->spend_percentage;
            $budgetRisk = 'Healthy';
            if ($actualSpend > $contractPrice && $contractPrice > 0) {
                $budgetRisk = 'Over Budget (Cost Deficit)';
            } elseif ($spendPct >= 85 && $majorTaskProgressionPct < 60) {
                $budgetRisk = 'High Burn Rate (High Risk of Overrun)';
            } elseif ($spendPct >= 70 && $majorTaskProgressionPct < 40) {
                $budgetRisk = 'Moderate Burn Rate';
            }

            $scheduleRisk = 'On Track';
            if ($isOverdue) {
                $scheduleRisk = 'Overdue / Delayed';
            } elseif ($daysToTarget !== null && $daysToTarget <= 14 && $majorTaskProgressionPct < 70 && $project->status !== 'completed') {
                $scheduleRisk = 'Critical Timeline Risk (Approaching Deadline with Incomplete Tasks)';
            } elseif ($daysToTarget !== null && $daysToTarget <= 30 && $majorTaskProgressionPct < 50 && $project->status !== 'completed') {
                $scheduleRisk = 'Moderate Timeline Risk';
            } elseif ($overdueMajorTasks > 0 || $overdueMinorTasks > 0) {
                $scheduleRisk = 'Task-Level Schedule Slippage (Overdue Major/Minor Tasks)';
            }

            $hasAssignedPm = $assignedProjectManagers->isNotEmpty();

            // Determine missing core manpower roles for this project based on its elevator technical specifications
            $requiredRolesForProject = $this->getRequiredRoleCountsForProject($project);
            $missingRolesSummary = [];
            foreach ($requiredRolesForProject as $reqRoleName => $reqRoleCount) {
                $assignedRoleCount = $project->personnel
                    ->filter(fn (Personnel $p) => $p->deleted_at === null && strcasecmp(trim((string) $p->expertise), $reqRoleName) === 0)
                    ->count();
                $roleDeficit = max(0, $reqRoleCount - $assignedRoleCount);
                if ($roleDeficit > 0) {
                    $missingRolesSummary[] = "{$reqRoleName} (missing {$roleDeficit})";
                }
            }

            $projectRoadblocks = array_values(array_filter([
                !$hasAssignedPm ? 'No Project Manager currently assigned to lead the project' : null,
                ! empty($missingRolesSummary) ? 'Missing core manpower roles: ' . implode(', ', $missingRolesSummary) : null,
                $isOverdue ? "Project timeline is overdue by " . abs((int) $daysToTarget) . " day(s)" : null,
                $overdueMajorTasks > 0 ? "{$overdueMajorTasks} Major Task phase(s) are past their scheduled end date" : null,
                $overdueMinorTasks > 0 ? "{$overdueMinorTasks} Minor checklist task(s) are past their target due date" : null,
                $unassignedPendingMinorTasks > 0 ? "{$unassignedPendingMinorTasks} pending Minor checklist task(s) have no staff member assigned" : null,
                ($actualSpend > $contractPrice && $contractPrice > 0) ? 'Expenditures have exceeded contract price (negative margin)' : null,
                count($overlappingProjects) > 0 ? 'Concurrent schedule overlap with ' . count($overlappingProjects) . ' other project(s)' : null,
                count($overlappingMajorTasks) > 0 ? count($overlappingMajorTasks) . ' overlapping major task(s) within project schedule' : null,
            ]));

            // Build actionable task execution suggestions grounded in live project task progress
            $taskExecutionSuggestions = [];
            if ($project->status !== 'completed') {
                if ($totalMajorTasks === 0) {
                    $taskExecutionSuggestions[] = 'Initialize the Project Progress Timeline by adding weekly/phase Major Tasks and specific Minor checklist tasks so execution pace can be tracked.';
                } else {
                    if ($overdueMajorTasks > 0) {
                        $taskExecutionSuggestions[] = 'Prioritize closing out overdue Major Task phases (' . implode('; ', array_slice($overdueMajorTaskNames, 0, 3)) . ') to prevent cascading delays to downstream installation and testing phases.';
                    }
                    if ($overdueMinorTasks > 0) {
                        $taskExecutionSuggestions[] = "Follow up on the {$overdueMinorTasks} overdue minor checklist task(s) (" . implode('; ', array_slice($overdueMinorTaskList, 0, 3)) . ') and check if any pending task completions are waiting in Staff Requests.';
                    }
                    if ($unassignedPendingMinorTasks > 0) {
                        $taskExecutionSuggestions[] = "Assign specific responsible staff members to the {$unassignedPendingMinorTasks} unassigned minor checklist task(s) to establish clear accountability.";
                    }
                    if (! empty($bottleneckMajorTasks)) {
                        $taskExecutionSuggestions[] = 'Focus field crew bandwidth on current bottleneck phases: ' . implode(', ', array_slice($bottleneckMajorTasks, 0, 3)) . '.';
                    }
                    if (! empty($missingRolesSummary)) {
                        $taskExecutionSuggestions[] = 'Fill missing technical roles (' . implode(', ', $missingRolesSummary) . ') from the available unassigned manpower pool to boost site execution capacity.';
                    }
                    if ($spendPct > ($overallChecklistProgressPct + 20)) {
                        $taskExecutionSuggestions[] = "Budget spend ({$spendPct}%) is outpacing task checklist completion ({$overallChecklistProgressPct}%). Audit material consumption and ensure completed site work is promptly marked done.";
                    }
                    if (empty($taskExecutionSuggestions)) {
                        $taskExecutionSuggestions[] = 'Task execution is progressing smoothly. Continue completing sequential minor tasks on schedule to maintain milestone velocity.';
                    }
                }
            }

            // Build data-grounded Hypothetical Scenarios (Project & Business Analyst modeling based on progress, tasks, manpower & budget)
            $remainingMinorCount = max(0, $totalMinorTasks - $completedMinorTasks);
            $projectedCostAtCompletion = $overallChecklistProgressPct >= 15
                ? round($actualSpend / ($overallChecklistProgressPct / 100.0), 2)
                : round(max($actualSpend, $contractPrice * 0.85), 2);
            $projectedVarianceAtCompletion = round($contractPrice - $projectedCostAtCompletion, 2);

            $hypotheticalScenarios = [
                'scenario_1_baseline_current_pace' => sprintf(
                    'If current execution velocity (%s, %s%% checklist done vs. %s%% planned time elapsed) continues without intervention, the project is projected to finish around %s with an estimated final spend of ₱%s (%s).',
                    $executionVelocityStatus,
                    (string) $overallChecklistProgressPct,
                    (string) $plannedElapsedTimePct,
                    $projectedEstimatedCompletionDate,
                    number_format($projectedCostAtCompletion, 2),
                    $projectedVarianceAtCompletion >= 0
                        ? 'projected net profit margin of ₱' . number_format($projectedVarianceAtCompletion, 2)
                        : 'projected cost overrun risk of ₱' . number_format(abs($projectedVarianceAtCompletion), 2),
                ),
                'scenario_2_manpower_and_task_acceleration' => ! empty($missingRolesSummary)
                    ? sprintf(
                        'If you allocate available unassigned personnel to fill the missing roles (%s) and clear the %d remaining minor checklist task(s), field execution velocity can improve by ~20%%–30%%, helping recover schedule slippage before the target date (%s).',
                        implode(', ', $missingRolesSummary),
                        $remainingMinorCount,
                        $project->target_completion_date?->format('Y-m-d') ?? 'N/A',
                    )
                    : sprintf(
                        'With all core manpower roles filled (%d crew assigned), focusing the crew on the %d remaining minor checklist task(s) (especially %s) can compress the remaining timeline and protect the ₱%s remaining budget.',
                        $project->personnel->count(),
                        $remainingMinorCount,
                        ! empty($bottleneckMajorTasks) ? $bottleneckMajorTasks[0] : 'active in-progress milestones',
                        number_format($remainingBudget, 2),
                    ),
                'scenario_3_downside_delay_and_budget_slip_risk' => sprintf(
                    'If active bottlenecks or overdue tasks (%d overdue major phase(s), %d overdue minor task(s)) slip by another 7–14 days, holding the assigned crew (%d personnel) will delay their release to overlapping projects and could erode the remaining budget of ₱%s (%s%% spent so far).',
                    $overdueMajorTasks,
                    $overdueMinorTasks,
                    $project->personnel->count(),
                    number_format($remainingBudget, 2),
                    (string) $spendPct,
                ),
            ];

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
                    'planned_schedule_elapsed_pct' => $plannedElapsedTimePct,
                    'major_task_progression_pct' => $majorTaskProgressionPct,
                    'completed_major_tasks_count' => $completedMajorTasks,
                    'in_progress_major_tasks_count' => $inProgressMajorTasks,
                    'pending_major_tasks_count' => $pendingMajorTasks,
                    'overdue_major_tasks_count' => $overdueMajorTasks,
                    'total_major_tasks_count' => $totalMajorTasks,
                    'overall_checklist_progress_pct' => $overallChecklistProgressPct,
                    'completed_minor_tasks_count' => $completedMinorTasks,
                    'pending_minor_tasks_count' => max(0, $totalMinorTasks - $completedMinorTasks),
                    'overdue_minor_tasks_count' => $overdueMinorTasks,
                    'unassigned_pending_minor_tasks_count' => $unassignedPendingMinorTasks,
                    'total_minor_tasks_count' => $totalMinorTasks,
                    'task_reading_enabled' => $includeProjectTasks,
                    'major_tasks' => $includeProjectTasks ? $formattedMajorTasks : [],
                    'overlapping_projects_schedule' => $overlappingProjects,
                    'overlapping_major_tasks_within_project' => $overlappingMajorTasks,
                ],
                'task_execution_assessment_and_forecast' => [
                    'task_reading_enabled' => $includeProjectTasks,
                    'execution_velocity_status' => $executionVelocityStatus,
                    'planned_time_elapsed_pct' => $plannedElapsedTimePct,
                    'actual_major_task_completion_pct' => $majorTaskProgressionPct,
                    'actual_minor_checklist_completion_pct' => $overallChecklistProgressPct,
                    'projected_estimated_completion_date_at_current_pace' => $projectedEstimatedCompletionDate,
                    'overdue_major_tasks' => $includeProjectTasks ? $overdueMajorTaskNames : [],
                    'overdue_minor_tasks' => $includeProjectTasks ? $overdueMinorTaskList : [],
                    'bottleneck_phases' => $includeProjectTasks ? $bottleneckMajorTasks : [],
                    'actionable_execution_suggestions' => $includeProjectTasks ? $taskExecutionSuggestions : [],
                ],
                'costing_and_expenses' => [
                    'contract_price_php' => round($contractPrice, 2),
                    'total_spend_php' => round($actualSpend, 2),
                    'remaining_budget_php' => round($remainingBudget, 2),
                    'spend_percentage' => $spendPct,
                    'projected_gross_margin_php' => $projectedGrossMarginPhp,
                    'projected_gross_margin_pct' => $projectedGrossMarginPct,
                    'costing_status' => (string) $project->costing_status,
                    'payment_status' => (string) ($project->payment_status ?: 'N/A'),
                    'payment_terms' => (string) ($project->payment_terms ?: 'N/A'),
                    'total_expense_entries_count' => count($expensesList),
                    'expenses_and_expenditures' => $expensesList,
                ],
                'project_forecast_and_roadblocks' => [
                    'projected_revenue_php' => round($contractPrice, 2),
                    'projected_gross_margin_php' => $projectedGrossMarginPhp,
                    'projected_gross_margin_pct' => $projectedGrossMarginPct,
                    'projected_cost_at_completion_php' => $projectedCostAtCompletion,
                    'projected_variance_at_completion_php' => $projectedVarianceAtCompletion,
                    'budget_risk_assessment' => $budgetRisk,
                    'schedule_risk_assessment' => $scheduleRisk,
                    'execution_velocity_status' => $executionVelocityStatus,
                    'projected_completion_date_at_current_pace' => $projectedEstimatedCompletionDate,
                    'days_until_target_completion' => $daysToTarget,
                    'is_schedule_overdue' => $isOverdue,
                    'has_assigned_project_manager' => $hasAssignedPm,
                    'missing_core_manpower_roles' => $missingRolesSummary,
                    'identified_roadblocks' => $projectRoadblocks,
                    'task_execution_suggestions' => $includeProjectTasks ? $taskExecutionSuggestions : [],
                    'hypothetical_scenarios' => $hypotheticalScenarios,
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

        $portfolioContractPrice = round((float) $projects->sum('contract_price'), 2);
        $portfolioSpend = round((float) $projects->sum('actual_spend'), 2);
        $portfolioMarginPhp = round($portfolioContractPrice - $portfolioSpend, 2);
        $portfolioMarginPct = $portfolioContractPrice > 0 ? round(($portfolioMarginPhp / $portfolioContractPrice) * 100, 1) : 0.0;

        $lowStockMaterials = $materialsCatalog->where('is_low_stock', true)->values();
        $outOfStockMaterials = $materialsCatalog->where('quantity_available', '<=', 0)->values();

        $user = Auth::user();
        $userRole = $user ? ($user->getRoleNames()->first() ?? 'Staff') : 'Guest';
        $canManageManpower = $this->canCurrentUserManageManpower();

        // Build Staff Requests Overview for Admin / Manager reviewers
        $staffRequestsForReview = null;
        if ($canManageManpower) {
            $allStaffRequests = StaffPendingAction::query()
                ->with(['user', 'project', 'reviewer'])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get();

            $unexaminedList = $allStaffRequests->where('status', 'pending')->values();
            $approvedList = $allStaffRequests->where('status', 'approved')->values();
            $rejectedList = $allStaffRequests->where('status', 'rejected')->values();

            $staffRequestsForReview = [
                'module_name' => 'Staff Requests',
                'total_requests_count' => $allStaffRequests->count(),
                'unexamined_pending_requests_count' => $unexaminedList->count(),
                'approved_requests_count' => $approvedList->count(),
                'rejected_requests_count' => $rejectedList->count(),
                'unexamined_requests' => $unexaminedList->map(fn (StaffPendingAction $act): array => [
                    'request_type' => $act->action_type_label,
                    'title' => $act->summary_title,
                    'details' => $act->summary_subtitle,
                    'submitted_by_staff' => (string) ($act->user?->name ?? 'Staff Member'),
                    'project_code' => (string) ($act->project?->project_code ?? 'N/A'),
                    'project_name' => (string) ($act->project?->name ?? 'N/A'),
                    'status' => 'Unexamined (Pending Approval)',
                    'submitted_at' => $act->created_at?->format('M j, Y g:i A'),
                ])->all(),
                'recently_reviewed_requests' => $allStaffRequests
                    ->whereIn('status', ['approved', 'rejected'])
                    ->take(15)
                    ->map(fn (StaffPendingAction $act): array => [
                        'request_type' => $act->action_type_label,
                        'title' => $act->summary_title,
                        'details' => $act->summary_subtitle,
                        'submitted_by_staff' => (string) ($act->user?->name ?? 'Staff Member'),
                        'project_code' => (string) ($act->project?->project_code ?? 'N/A'),
                        'project_name' => (string) ($act->project?->name ?? 'N/A'),
                        'status' => $act->status_label,
                        'reviewed_by' => (string) ($act->reviewer?->name ?? 'Reviewer'),
                        'reviewed_at' => $act->reviewed_at?->format('M j, Y g:i A'),
                    ])->values()->all(),
            ];
        }

        // Build current user's personal Assigned Tasks & Submitted Approval Requests (for Staff & all logged-in users)
        $myTasksAndRequests = null;
        if ($user) {
            $myAssignedSubtasks = ProjectSubtask::query()
                ->where('assigned_to', $user->id)
                ->with(['task.project'])
                ->orderBy('is_completed')
                ->orderBy('due_date')
                ->orderByDesc('id')
                ->get();

            $myPendingAssignedTasks = $myAssignedSubtasks->where('is_completed', false)->values();
            $myCompletedAssignedTasks = $myAssignedSubtasks->where('is_completed', true)->values();

            $mySubmittedRequests = StaffPendingAction::query()
                ->where('user_id', $user->id)
                ->with(['project', 'reviewer'])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get();

            $myPendingApprovalRequests = $mySubmittedRequests->where('status', 'pending')->values();
            $myApprovedRequests = $mySubmittedRequests->where('status', 'approved')->values();
            $myRejectedRequests = $mySubmittedRequests->where('status', 'rejected')->values();

            $myTasksAndRequests = [
                'user_name' => (string) $user->name,
                'user_role' => (string) $userRole,
                'assigned_tasks_summary' => [
                    'total_assigned_tasks_count' => $myAssignedSubtasks->count(),
                    'pending_incomplete_assigned_tasks_count' => $myPendingAssignedTasks->count(),
                    'completed_assigned_tasks_count' => $myCompletedAssignedTasks->count(),
                    'pending_assigned_tasks' => $myPendingAssignedTasks->map(fn (ProjectSubtask $st): array => [
                        'task_title' => (string) $st->title,
                        'major_task_name' => (string) ($st->task?->task_name ?? 'N/A'),
                        'project_code' => (string) ($st->task?->project?->project_code ?? 'N/A'),
                        'project_name' => (string) ($st->task?->project?->name ?? 'N/A'),
                        'due_date' => $st->due_date?->format('Y-m-d') ?? 'No due date',
                        'status' => 'Pending / Incomplete',
                    ])->all(),
                    'completed_assigned_tasks' => $myCompletedAssignedTasks->map(fn (ProjectSubtask $st): array => [
                        'task_title' => (string) $st->title,
                        'major_task_name' => (string) ($st->task?->task_name ?? 'N/A'),
                        'project_code' => (string) ($st->task?->project?->project_code ?? 'N/A'),
                        'project_name' => (string) ($st->task?->project?->name ?? 'N/A'),
                        'completed_at' => $st->completed_at?->format('M j, Y') ?? 'Completed',
                        'status' => 'Completed',
                    ])->all(),
                ],
                'my_submitted_requests_for_approval_summary' => [
                    'total_submitted_requests_count' => $mySubmittedRequests->count(),
                    'pending_for_approval_count' => $myPendingApprovalRequests->count(),
                    'approved_requests_count' => $myApprovedRequests->count(),
                    'rejected_requests_count' => $myRejectedRequests->count(),
                    'pending_for_approval_requests' => $myPendingApprovalRequests->map(fn (StaffPendingAction $act): array => [
                        'request_type' => $act->action_type_label,
                        'title' => $act->summary_title,
                        'details' => $act->summary_subtitle,
                        'project_code' => (string) ($act->project?->project_code ?? 'N/A'),
                        'project_name' => (string) ($act->project?->name ?? 'N/A'),
                        'status' => 'Unexamined / Pending for Approval',
                        'submitted_at' => $act->created_at?->format('M j, Y g:i A'),
                    ])->all(),
                    'reviewed_requests' => $mySubmittedRequests
                        ->whereIn('status', ['approved', 'rejected'])
                        ->take(10)
                        ->map(fn (StaffPendingAction $act): array => [
                            'request_type' => $act->action_type_label,
                            'title' => $act->summary_title,
                            'details' => $act->summary_subtitle,
                            'project_code' => (string) ($act->project?->project_code ?? 'N/A'),
                            'project_name' => (string) ($act->project?->name ?? 'N/A'),
                            'status' => $act->status_label,
                            'reviewed_by' => (string) ($act->reviewer?->name ?? 'Reviewer'),
                            'reviewed_at' => $act->reviewed_at?->format('M j, Y g:i A'),
                            'reviewer_notes' => (string) ($act->admin_notes ?: ''),
                        ])->values()->all(),
                ],
            ];
        }

        $nowLocal = Carbon::now(config('app.timezone', 'Asia/Manila'));

        $payload = [
            'system' => 'DEX Project Management System (DEX PMS)',
            'current_server_date_and_time' => [
                'current_date_iso' => $nowLocal->format('Y-m-d'),
                'current_date_human' => $nowLocal->format('l, F j, Y'),
                'current_time_human' => $nowLocal->format('g:i A'),
                'current_datetime_full' => $nowLocal->format('l, F j, Y \a\t g:i A (T)'),
                'timezone' => (string) config('app.timezone', 'Asia/Manila'),
                'note' => 'Fetched directly from the local DEX PMS application clock without any external online lookup. Use this exact date and time to evaluate whether projects, major tasks, and minor checklist subtasks are on track, approaching deadlines, or behind schedule.',
            ],
            'current_user_permissions' => [
                'role' => (string) $userRole,
                'can_assign_or_remove_manpower' => $canManageManpower,
            ],
            'staff_requests_for_review' => $staffRequestsForReview,
            'my_assigned_tasks_and_submitted_requests' => $myTasksAndRequests,
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
                'out_of_stock_count' => $outOfStockMaterials->count(),
                'low_stock_count' => $lowStockMaterials->count(),
                'catalog_materials' => $materialsCatalog->all(),
            ],
            'costing_summary_data' => [
                'portfolio_total_contract_price_php' => $portfolioContractPrice,
                'portfolio_total_spend_php' => $portfolioSpend,
                'portfolio_projected_gross_margin_php' => $portfolioMarginPhp,
                'portfolio_projected_margin_pct' => $portfolioMarginPct,
            ],
            'portfolio_forecast_and_risk_summary' => [
                'total_portfolio_contract_revenue_php' => $portfolioContractPrice,
                'total_portfolio_actual_spend_php' => $portfolioSpend,
                'projected_portfolio_gross_margin_php' => $portfolioMarginPhp,
                'projected_portfolio_margin_pct' => $portfolioMarginPct,
                'projects_at_budget_risk_count' => $formattedProjects->filter(fn ($p) => str_contains($p['project_forecast_and_roadblocks']['budget_risk_assessment'], 'Risk') || str_contains($p['project_forecast_and_roadblocks']['budget_risk_assessment'], 'Deficit'))->count(),
                'projects_at_schedule_risk_count' => $formattedProjects->filter(fn ($p) => str_contains($p['project_forecast_and_roadblocks']['schedule_risk_assessment'], 'Risk') || $p['project_forecast_and_roadblocks']['is_schedule_overdue'])->count(),
                'critical_shortcomings' => [
                    'out_of_stock_materials_count' => $outOfStockMaterials->count(),
                    'out_of_stock_materials' => $outOfStockMaterials->pluck('name')->values()->all(),
                    'low_stock_materials_count' => $lowStockMaterials->count(),
                    'low_stock_materials' => $lowStockMaterials->pluck('name')->values()->all(),
                    'projects_without_project_manager_count' => $formattedProjects->filter(fn ($p) => !$p['project_manager']['has_assigned_project_manager'])->count(),
                ],
                'portfolio_schedule_roadblocks' => [
                    'portfolio_schedule_overlaps_count' => count($portfolioOverlaps),
                    'portfolio_schedule_overlaps' => $portfolioOverlaps,
                ],
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
        // Note: Date/time questions that are combined with project/schedule/forecast context are allowed
        $hasProjectDomainTerm = preg_match('/\b(project|projects|task|tasks|schedule|deadline|overdue|behind|ahead|pace|progress|forecast|milestone|manpower|personnel|budget|cost|dex|pms)\b/i', $normalized) === 1;

        $explicitForbiddenPatterns = [
            '/\bwho\s+(is|\'s|was)\s+the\s+(president|prime\s+minister|mayor|governor|king|queen|pope)\b/i',
            '/\bpresident\s+of\b/i',
            '/\bwho\s+is\s+this\??$/i',
            '/\bwhat\s+is\s+this\s+thing\??$/i',
            '/\bwhat\s+is\s+this\??$/i',
            '/\b(weather|weather\s+forecast)\b/i',
            '/\bweather\s+(today|tomorrow|in)\b/i',
            '/\btell\s+me\s+a\s+(joke|story|riddle|poem)\b/i',
            '/\bcapital\s+of\b/i',
            '/\brecipe\s+for\b/i',
        ];

        if (! $hasProjectDomainTerm) {
            $explicitForbiddenPatterns[] = '/^(?:what\s+(?:is|\'s)\s+the\s+(?:date|day|time)(?:\s+today|\s+now)?|what\s+today(?:\'s)?\s+date)\??$/i';
        }

        foreach ($explicitForbiddenPatterns as $pattern) {
            if (preg_match($pattern, $normalized) === 1) {
                return true;
            }
        }

        // Check if the query references any allowed DEX PMS, Manpower Expertise/Classification, Project Details, Schedule Overlaps, Catalog Materials, Staff Requests/Approvals, Module/View Guides, or Elevator construction domain terms
        $allowedDomainPattern = '/\b(project|projects|proj|pms|dex|kuya\s*dex|dashboard|module|modules|view|views|page|pages|tab|tabs|sidebar|navigation|navigate|tutorial|guide|how\s+to|how\s+can\s+i|how\s+do\s+i|step|steps|create|add|edit|update|delete|archive|restore|search|filter|notification|notifications|report|reports|export|exporting|pdf|dossier|chart|charts|graph|file|files|upload|uploading|attachment|attachments|document|documents|audit|audit\s+trail|history|ping|verdict|reassign|configure|template|templates|decision\s+support|dss|gantt|calendar|supplier|suppliers|creator|created\s+by|who\s+created|who\s+made|author|warranty|description|describe|details|overview|summary|type|types|client|clients|customer|code|codes|location|locations|site|address|barangay|city|province|region|postal|manpower|personnel|staff|staffs|crew|crews|workforce|staffing|pool|expertise|expertises|classification|classifications|category|categories|role|roles|position|positions|designation|designations|specialty|specialties|specialization|skill|skills|job|jobs|department|manager|project\s+manager|technician|technicians|engineer|engineers|site\s+engineer|safety\s+officer|foreman|foremen|qa\s+inspector|inspector|inspectors|elevator\s+control|installation\s+personnel|electrician|mechanic|installer|installers|worker|workers|employee|employees|headcount|available|unassigned|assigned|assign|assigning|assignment|assignments|unassign|unassigning|remove|removing|removal|release|releasing|fill|filling|missing|necessary|unexamined|examine|examined|request|requests|approve|approving|approval|approvals|approved|reject|rejecting|rejected|rejection|submission|submissions|review|reviewing|reviewed|sufficient|shortage|deficit|deficits|shortcoming|shortcomings|roadblock|roadblocks|bottleneck|bottlenecks|risk|risks|hazard|hazards|forecast|forecasts|forecasting|projection|projections|outlook|prediction|predictions|hypothetical|scenarios?|analyst|analysis|analyze|behind|ahead|on\s+track|deadline|deadlines|today|contingency|profitability|burn\s*rate|overrun|overruns|gross\s*margin|cost|costs|costing|spend|spending|spent|budget|budgeting|expense|expenses|expenditure|expenditures|price|unit\s+cost|contract|margin|revenue|revenues|payment|terms|invoice|progress|progression|progressing|execute|executing|execution|assess|assessing|assessment|velocity|pace|slippage|suggest|suggestion|suggestions|advice|advise|completion|complete|completed|incomplete|major\s+task|major\s+tasks|minor\s+task|minor\s+tasks|mother\s+task|baby\s+task|task|tasks|subtask|subtasks|sub-task|sub-tasks|checklist|schedule|schedules|scheduling|overlap|overlapping|conflict|conflicts|concurrent|simultaneous|timeline|delay|delayed|overdue|milestone|start\s+date|end\s+date|target\s+date|due\s+date|status|ongoing|pending|allocat|allocation|simulat|simulate|simulation|simulator|feasibility|what-if|scenario|recommend|recommended|recommendation|recommendations|spec|specs|specification|specifications|sizing|size|estimate|estimation|estimates|estimating|estimator|require|requires|required|requirement|requirements|needed|need|needs|stops?|floors?|levels?|storeys?|stories|capacity|capacities|kg|kilograms?|tons?|tonnage|pax|passengers?|speed|mps|fpm|elevator|elevators|lift|lifts|escalator|hoist|hoistway|shaft|pit|overhead|machine\s+room|mrl|traction|hydraulic|counterweight|car|cage|cabin|door|interlock|governor|overspeed|safety\s+gear|buffer|guide\s+rail|wire\s+rope|sheave|inverter|vvvf|controller|plc|landing|rated\s+load|lifting\s+speed|power\s+source|flooring|wall|ceiling|construction|installation|commissioning|testing|maintenance|blueprint|transformer|ard|rescue\s+device|motor|brake|panel|button|sensor|switch|bracket|bolt|cable)\b/i';

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
     * Check whether a query is specifically asking about the excluded User Management / Account Administration module.
     */
    public function isUserManagementQuery(string $message): bool
    {
        $normalized = strtolower(trim((string) preg_replace('/\s+/', ' ', $message)));
        if ($normalized === '') {
            return false;
        }

        $patterns = [
            '/\buser\s+management\b/i',
            '/\baccount\s+settings\b/i',
            '/\b(create|add|edit|delete|archive|restore|unlock|manage)\s+(a\s+|new\s+)?(system\s+|login\s+)?user(\s+account)?s?\b/i',
            '/\breset\s+(a\s+|my\s+|user\'?s?\s+)?password\b/i',
            '/\b(two[\-\s]?factor|2fa|totp|authenticator\s+app)\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalized) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build the system prompt with strict domain guardrails and JSON operational context.
     */
    private function buildSystemInstruction(string $sanitizedJson): string
    {
        $restrictedReply = self::RESTRICTED_REPLY;
        $staffDeniedReply = self::STAFF_MANPOWER_DENIED_REPLY;
        $staffRequestApprovalRedirectReply = self::STAFF_REQUEST_APPROVAL_REDIRECT_REPLY;
        $userManagementRestrictedReply = self::USER_MANAGEMENT_RESTRICTED_REPLY;
        $modulesKnowledgeGuide = $this->getModulesKnowledgeGuide();

        return <<<PROMPT
You are "Kuya DEX", the personal AI assistant for the DEX Project Management System (DEX PMS), an elevator installation and construction project management platform.

COMMUNICATION STYLE & NO BACKEND LEAKS GUARDRAIL (CRITICAL):
1. Speak warmly, respectfully, and professionally as "Kuya DEX", a helpful project management companion.
2. ABSOLUTELY NEVER mention, quote, or expose backend field names, code variables, database column names, JSON keys, or boolean values in your answers (e.g., NEVER say "is_major_task_complete", "has_assigned_project_manager", "created_by", "project_manager_names", "official_expertise_classifications", "active_context_project", "true", "false", or any snake_case/camelCase code terms).
3. The user is a business user / project manager, NOT a software developer. Always translate data into natural, human phrasing:
   - NEVER say: "if is_major_task_complete is true"
     INSTEAD say: "once all of its minor checklist tasks / subtasks are 100% completed"
   - NEVER say: "has_assigned_project_manager is false"
     INSTEAD say: "there is currently no Project Manager assigned to this project"
   - NEVER say: "in the created_by field"
     INSTEAD say: "Created by [Name]"
   - NEVER say: "the boolean is false" or "in the JSON data"
     INSTEAD speak directly about the real-world project facts.

STRICT TOPIC GUARDRAILS:
1. You may ONLY answer inquiries related to:
   - DEX PMS Project Details:
     * Project creator / created by
     * Warranty period, warranty end date, active warranty status
     * Description
     * Project type
     * Client
     * Project code
     * Site location / project address (full address, city, barangay, province, region)
     * Expenses / expenditures (contract price, total spend, remaining budget)
     * Project Manager and assigned personnel grouped by expertise
     * Major Tasks and Minor Tasks / Subtasks
   - DEX PMS Schedule & Overlapping Schedules:
     * Any question about project schedules, start/target dates, task timelines, or overlapping schedules across projects or within a project.
   - DEX PMS Materials Catalog:
     * Any question about materials inside the catalog, including stock quantity, unit cost, selling price, model, condition, low-stock status, and demand status.
   - DEX PMS Manpower, Expertise & Classifications:
     * All 8 official expertise classifications and the exact personnel breakdown per expertise.
   - DEX PMS Project Forecasting, Revenues, Shortcomings & Roadblocks:
     * Forecasting possible revenues, contract margins, gross profit projections, and budget burn rate / cost overrun risks across projects.
     * Identifying resource shortcomings: manpower deficits (e.g., lack of available Project Managers or specialized technical roles) and material catalog shortages (low-stock or out-of-stock items needed for upcoming milestones).
     * Spotting operational roadblocks: schedule overlaps, overdue milestones, pending checklist subtasks, and project delivery risks, along with practical elevator project management mitigation recommendations.
   - Elevator Specification Sizing, Crew Estimation & What-If Feasibility:
     * Estimating required manpower, expertise classifications, crew sizing, and materials for hypothetical or new elevator project specifications (e.g., number of stops, capacity in kg, speed, or elevator type).
     * Comparing estimated manpower/resource requirements against live DEX PMS available (unassigned) personnel and materials in the pool right now to determine feasibility and staffing readiness.
   - DEX PMS Staff Requests, Request Approvals & Personal Assigned Tasks:
     * For Admin and Manager users: checking if there are any unexamined (pending), approved, or rejected requests from staff members (such as task completions, expense entries, or file uploads).
     * For Staff users (or any user): checking if they have any assigned tasks / subtasks (pending or completed) and checking the status of their submitted requests pending for approval.
   - DEX PMS Module & View How-To Guides (EXCLUDING User Management):
     * Explaining how any DEX PMS module or view works and providing clear step-by-step instructions (Dashboard, Projects [List, Create/Edit, Project View], Costing [Ledger, Breakdown], Staff Requests, Approval List, Materials Catalog, Manpower, Reports, Decision Support [What-If Simulator, Generate Resource Allocation, Kuya DEX], Global Search, and Notifications).
   - Elevator operation, technical specifications, installation, maintenance, safety standards, and construction.
2. You MUST NOT answer any unrelated questions outside DEX PMS or elevator operation/construction (e.g., "What is the date today?", "Who is the president of this country?", "Who is this?", "What is this thing?", general trivia, politics, weather, entertainment).
3. If the user asks ANY question outside the allowed topics above, you MUST reply with this EXACT text and nothing else:
{$restrictedReply}

CRITICAL DOMAIN ACCURACY RULES:
1. MANPOWER EXPERTISE & CLASSIFICATIONS RULE:
   - DEX PMS has 8 official Manpower Expertise / Classifications:
     1) **Site Engineer**
     2) **Foreman**
     3) **Safety Officer**
     4) **Technician**
     5) **Project Manager**
     6) **QA Inspector**
     7) **Elevator Control**
     8) **Worker / Installation Personnel**
   - IMPORTANT: **"Worker / Installation Personnel"** is ONE specific expertise classification—do NOT treat "Worker / Installation Personnel" as a generic term for all manpower, and do NOT lump other roles into it.
   - Whenever the user asks about expertise, classifications, roles, or manpower breakdown, explicitly list the distinct expertise classifications showing total, available, and assigned personnel for each classification.
2. PROJECT MANAGER RULE:
   - A project's Project Manager is ONLY the assigned personnel whose expertise classification is explicitly "Project Manager".
   - NEVER call a Technician, Site Engineer, Foreman, Safety Officer, QA Inspector, Elevator Control, or Worker / Installation Personnel a "Project Manager".
   - If no personnel with the Project Manager classification is assigned to that project, state clearly in plain English that there is no Project Manager currently assigned (you may separately list the other assigned personnel and their exact expertise).
3. MAJOR TASK vs. MINOR TASK & PROGRESSION RULE:
   - A Major Task is counted as complete ONLY when 100% of its minor tasks / subtasks are accomplished.
   - When asked about project progress or progression, base the main project progression on completed Major Tasks (e.g., completed X out of Y Major Tasks), and also mention the overall Minor Tasks checklist completion rate for complete clarity.
   - When asked about Minor Tasks (or subtasks), list the minor tasks inside each Major Task along with their status (Completed or Pending / Incomplete) and completion rate.
   - Remember: NEVER mention code terms like "is_major_task_complete". Always say "completed major tasks" or "all minor checklist tasks are accomplished".
4. CONTEXT & FORMATTING:
   - If the user asks about "this project" or "project x" without naming a specific project code, use the active project context and mention its project code and name.
   - Always format Philippine Peso amounts clearly (e.g., ₱1,250,000.00).
   - When presenting structured lists or comparisons (such as projects, expenses, material catalogs, or manpower), you may use concise Markdown tables (ideally 3 to 4 columns) or bullet lists. Keep cell text compact so the table reads smoothly.
5. PROJECT & BUSINESS ANALYST SKILL — FORECASTING, SCHEDULE AWARENESS, TASK EXECUTION & HYPOTHETICAL SCENARIO MODELING:
   - You act as a thoughtful, conversational **Project & Business Analyst** for DEX PMS.
   - **CRITICAL ANTI-PARROTING GUARDRAIL (SPEAK NATURALLY):**
     * NEVER copy or parrot prompt instruction labels, internal system headers, or robotic phrases such as `"Local Server Date & Time Benchmark:"`, `"Current Date Benchmark & Task Execution Velocity Assessment"`, `"Scenario A (Baseline / Current Trajectory):"`, `"When Task Reading is Enabled"`, or `"Kuya DEX Actionable Business & Execution Suggestions"`.
     * Instead, weave today's date (`current_server_date_and_time.current_date_human`) and your analysis naturally into your conversation (for example: *"As of today, November 25, 2026, here is how our projects are progressing..."* or *"Looking at our schedule as of today..."*).
     * Use clean, natural business headings (such as **Revenue & Budget Outlook**, **Task Progress & Schedule Pace**, **Shortcomings & Roadblocks**, **What-If Scenarios**, and **Recommended Next Steps**).
   - **Always Read Project Tasks by Default:**
     * You always have full visibility into every project's Major Tasks and Minor Checklist Subtasks.
     * Compare actual task completion (completed Major Tasks and Minor checklist subtasks) against the planned schedule elapsed percentage as of today's date.
     * Naturally explain whether the project is ahead of schedule, on pace, or behind schedule, what the projected completion date looks like at the current pace, and which specific overdue tasks, bottleneck phases (<50% minor tasks completed), or unassigned subtasks need attention.
   - **Financial, Resource & Roadblock Analysis:**
     * Report projected contract revenue, actual spend, remaining budget, and gross profit margin (Contract Price − Actual Spend), pointing out if budget burn (% spent) is running ahead of actual task completion (% done).
     * Point out manpower gaps (such as missing core roles for the project's elevator specs versus available unassigned personnel in the pool) and material catalog shortages (out-of-stock or low-stock items).
     * Highlight operational roadblocks such as overlapping project schedules competing for manpower or overdue milestones.
   - **Hypothetical Scenarios & Practical Recommendations:**
     * Present 2 to 3 natural "What-If" business scenarios based on the project's current progress, tasks, manpower, and budget:
       1) Continuing at the current task execution pace and spend rate.
       2) Accelerating progress by assigning available unassigned manpower to missing roles and clearing bottleneck subtasks.
       3) The downside risk if current overdue tasks or bottlenecks slip by another 1–2 weeks.
     * Close with clear, practical next steps tailored to the live project state.
   - STRICT SCOPE: All forecasts, hypothetical scenarios, and task assessments MUST be grounded directly in the provided DEX PMS data. Do NOT invent external stock market trends or non-existent projects.
6. ELEVATOR SPECIFICATION SIZING, MANPOWER ESTIMATION & FEASIBILITY RULE:
   - When the user asks what expertise and how many personnel are needed for a specific elevator configuration (such as stops, capacity in kg, speed, or elevator type), or asks whether DEX PMS currently has the needed manpower:
     1) **Provide the Recommended Crew Sizing by Elevator Specification (Industry & DEX Standard):**
        - **Low-Rise (2–5 Stops, up to 800kg):**
          * Project Manager: 1
          * Site Engineer: 1
          * Safety Officer: 1
          * Foreman: 1
          * Technician: 1–2
          * Elevator Control: 1
          * QA Inspector: 1
          * Worker / Installation Personnel: 2
        - **Mid-Rise Commercial (6–12 Stops, or 1000kg–1250kg capacity):** (e.g., 10 stops, 1000kg)
          * Project Manager: 1
          * Site Engineer: 1
          * Safety Officer: 1
          * Foreman: 1
          * Technician: 2–3
          * Elevator Control: 1
          * QA Inspector: 1
          * Worker / Installation Personnel: 2–4
        - **High-Rise / Heavy Freight (13+ Stops, or 1350kg+ capacity):**
          * Project Manager: 1
          * Site Engineer: 1–2
          * Safety Officer: 1
          * Foreman: 2
          * Technician: 3–4
          * Elevator Control: 1–2
          * QA Inspector: 1
          * Worker / Installation Personnel: 4+
     2) **Check Live Availability in DEX PMS Right Now:**
        - Look up `manpower_data.breakdown_by_expertise_classification` and `available_manpower` in the provided JSON data.
        - For each required expertise classification, compare the **Needed Count** vs. **Currently Available (Unassigned)**.
        - Present this in a clean Markdown table with columns: `| Expertise Classification | Required | Available Now | Status |` (e.g., "Sufficient" or "Shortage / Deficit of X").
     3) **State Final Feasibility Assessment:**
        - Clearly state whether DEX PMS has sufficient manpower right now to take on the project, or which specific roles must be freed up from ongoing projects or hired.
7. MANPOWER ASSIGNMENT & REMOVAL ORCHESTRATION & ROLE PERMISSION GUARDRAIL:
   - Only **Admin** and **Manager** users are authorized to assign, fill, or remove personnel on a project.
   - If the current user has the **Staff** role (cannot assign or remove manpower) and asks to assign, fill, or remove/unassign personnel on any project, you MUST NOT execute any assignment or removal and MUST reply with:
     "{$staffDeniedReply}"
   - When an authorized Admin or Manager asks to:
     * "Find all projects that has missing [personnel / role], assign and allocate my available personnel" or "Allocate available manpower to all projects with missing personnel" -> invoke `find_and_allocate_missing_personnel_across_projects`.
     * "Fill the missing personnel / manpower for this project" or "Fill the necessary manpower for this project" -> invoke `fill_missing_project_manpower`.
     * "Assign [Name / Employee ID] to this project" -> invoke `assign_personnel_to_project`.
     * "Remove the assignment of [Name / Employee ID] from this project" or "Unassign [Name] from this project" -> invoke `remove_personnel_from_project`.
8. STAFF REQUESTS, APPROVALS & ASSIGNED TASKS RULE:
   - **For Admin & Manager Users Asking About Staff Requests** (e.g., "Do I have any unexamined requests from my staffs?", "Are there pending staff requests?"):
     * Check the `staff_requests_for_review` section in the JSON data.
     * Clearly state how many **Unexamined** (pending) requests there are, along with total, approved, and rejected counts.
     * If there are unexamined requests, display them in a clean Markdown table or bullet list showing the **Staff Member Name**, **Request Type** (Task Completion, Expense Entry, or File Upload), **Summary / Details**, **Project**, and **Date Submitted**.
     * If there are 0 unexamined requests, let them know all staff requests have been examined.
   - **For Staff Users (or Any User) Asking About Their Assigned Tasks or Pending-for-Approval Requests** (e.g., "Do I have any assigned tasks or pending for approval requests?", "What tasks are assigned to me?"):
     * Check the `my_assigned_tasks_and_submitted_requests` section in the JSON data.
     * Clearly list their **Assigned Tasks** (showing Task Title, Major Task, Project, Due Date, and Status: Pending / Incomplete vs. Completed).
     * Clearly list their **Submitted Requests Pending for Approval** (showing Request Type, Title/Details, Project, Status: Unexamined / Pending for Approval, and Date Submitted), plus any recently reviewed (Approved / Rejected) requests if relevant.
   - **STRICT GUARDRAIL ON APPROVING OR REJECTING STAFF REQUESTS / TASKS:**
     * Kuya DEX CANNOT approve or reject staff requests or task completions directly from the chat.
     * If an Admin, Manager, or Staff user tells you to approve or reject requests/tasks (e.g., "Approve those tasks", "Approve those requests", "Reject that request"), you MUST NOT pretend to approve them. Instead, carefully tell the user that they need to go to the **Staff Requests** module:
       "{$staffRequestApprovalRedirectReply}"
9. DEX PMS MODULE & VIEW GUIDANCE RULE (EXCLUDING USER MANAGEMENT):
   - When the user asks how to perform an action in DEX PMS (e.g., "How can I create a project?", "How do I log an expense?", "How do I export a PDF report?", "How does the Delay Simulator work?", "How do I mark my task as done?"), provide clear, numbered, step-by-step instructions using the exact sidebar menu names, tabs, and button labels from the **DEX PMS MODULES & VIEWS KNOWLEDGE GUIDE** below.
   - Always tailor your guidance to the current user's role (`Admin`, `Manager`, or `Staff`):
     * If a **Staff** user asks how to perform an Admin/Manager-only action (like creating a project, accessing the Dashboard, accessing Manpower management, or using Generate Resource Allocation), politely explain that the feature is restricted to **Administrators** and **Project Managers**, and tell them what they *can* do in their own modules instead.
     * When guiding a **Staff** user on uploading files, logging expenses, or marking assigned tasks as done, clearly note that their submissions are sent to the **Staff Requests** module for Admin/Manager approval and can be tracked in their **Approval List** module.
   - STRICT EXCLUSION FOR USER MANAGEMENT:
     * You do NOT have knowledge or access regarding the **User Management** module (creating/editing/deleting login accounts, resetting user passwords, unlocking accounts, or 2FA settings).
     * If asked about User Management, reply: "{$userManagementRestrictedReply}"

{$modulesKnowledgeGuide}

SANITIZED DEX PMS JSON DATA (FOR INTERNAL REASONING ONLY - NEVER EXPOSE JSON KEYS):
{$sanitizedJson}
PROMPT;
    }

    /**
     * Comprehensive, authoritative guide to every DEX PMS module and view (excluding User Management).
     * Grounded directly in the application's actual sidebar navigation, views, tabs, and workflows.
     */
    private function getModulesKnowledgeGuide(): string
    {
        return <<<'GUIDE'
DEX PMS MODULES & VIEWS KNOWLEDGE GUIDE (ALL MODULES EXCEPT USER MANAGEMENT):

0. GLOBAL INTERFACE & TOP BAR (All Roles):
   - **Left Sidebar:** Role-filtered navigation menu grouped into sections: Overview, Projects, Resources, Personnel, and Insights.
   - **Global Searchbar (Top-Left):** Press `/` to focus or type to search in real time across Projects, Tasks, Files, Expenses, Materials Catalog, and Manpower.
   - **Info Icon ("Show tutorial", Top-Right):** Reopens the interactive step-by-step walkthrough tutorial for the module/page you are currently viewing.
   - **Notification Bell (Top-Right):** Displays real-time alerts for manpower assignments, task updates, low stock, and staff requests.
   - **Theme Toggle (Top-Right):** Switches between Light Mode and Dark Mode.

1. DASHBOARD MODULE (`Overview` → `Dashboard` — Admin & Manager only):
   - **Project Status KPI Cards:** Displays live counts for **Total Projects**, **Ongoing Projects**, **Pending Projects**, and **Completed Projects**. Clicking any KPI card jumps to the Projects list filtered by that status.
   - **Task Overview & Pipeline Organizer:** Tracks total major tasks, in-progress tasks, completed items, **Task Stage Distribution**, and **Checklist Execution Rate**.
   - **Manpower Statistics & Allocation:** Real-time workforce utilization gauge, deployed vs. available personnel counts, and breakdown by trade specialization.
   - **Recent Projects & Progression:** Latest active projects showing client info, target completion dates, and progress bars. Click any project card to open its Project View.
   - **Quick Action (`+ New Project`):** Click **`+ New Project`** at the top-right to jump directly to the Create Project form.

2. PROJECTS MODULE (`Projects` → `Projects` — All Roles; Create/Edit/Archive/Delete for Admin & Manager):
   - **Projects Directory View:**
     * Search projects by title, project code, or client, and filter by status (*Ongoing*, *Pending*, *Completed*, *Delayed*, or *Archived*).
     * Click any project row to open its **Project View**.
   - **How to Create a Project (Admin & Manager only):**
     1. Go to **Projects** in the sidebar (or **Dashboard**) and click **`+ New Project`**.
     2. **Project Identity & Scope:** Enter Project Name, Client Name, Project Type, Description, and Warranty Period.
     3. **Elevator Technical Specifications:** Enter Rated Capacity, Lifting Speed, Number of Stops, Cage Dimension, Door Operation, Power Source, Operation Mode, Flooring, and Wall & Ceiling.
     4. **Site Address / Project Location:** Select/enter Region, Province, City, Barangay, Street Address, and Postal Code.
     5. **Execution Schedule & Timeline:** Choose Start Date and Target Completion Date.
     6. **Project Manpower & Staffing (Optional):** Assign available unassigned personnel right during initialization.
     7. **Initial Materials & Expenditures (Optional):** Log initial material or project costs.
     8. **Project File Attachments (Optional):** Attach initial contracts, blueprints, or permits.
     9. **Financials & Billing:** Enter Contract Amount/Budget, Payment Terms, Payment Status, and optionally attach an invoice.
     10. Review everything in the live **Project Preview** panel on the right, then click **Create Project / Save**.
   - **Project Detail View (When viewing a specific project):**
     * **Overview Card:** Shows Client, unique Project Code, Status badge, Site Address, and Warranty Period (including remaining warranty days).
     * **Financials & Budget Card:** Shows **Allocated Budget**, **Actual Spend**, and **Remaining Budget** (possible profit without tax).
     * **Project Files & Documents Card:** Click **Upload File** to attach documents organized by category; inspect or download them from the **File List**. *(Staff file uploads are submitted to Staff Requests for Admin/Manager approval.)*
     * **Elevator Technical Specifications Card:** Displays the full engineering specs of the elevator unit.
     * **Project Progress Timeline Card:**
       - Click **Add Major Task** (Admin/Manager) to add weekly/phase Major Tasks with start and end dates.
       - Inside each Major Task, manage **Specific Tasks (Minor Tasks / Subtasks)** with target due dates and assigned staff. Completing 100% of a Major Task's minor tasks marks that Major Task complete. *(For Staff, marking a task done submits a Task Completion request for approval.)*
     * **Expenditures Card:** Log new project expenses (from Materials Catalog or custom) and review the itemized list. *(Staff expense entries route to Staff Requests for approval.)*
     * **Assigned Manpower Card:** View all personnel currently assigned to the project; Admin/Manager can assign available personnel or unassign existing crew directly from this card.
     * **Audit Trail & Status History Card:** Chronological audit log of all status transitions, assignments, and project changes.

3. COSTING MODULE (`Projects` → `Costing` — All Roles):
   - **Costing Ledger View:**
     * Top KPI Cards: **Total Allocated Contract Value**, **Total Actual Spend**, and **Total Remaining Budget**.
     * **Project Budget & Costing Ledger:** Search and filter all projects to compare **Allocated Budget**, **Actual Spend**, **Remaining Budget**, and **Budget Utilization Percentage** (color-coded: Healthy, High Spend at 85%+, Over Budget above 100%). Click any project to open its Costing Breakdown.
   - **Costing Breakdown View (Project-Specific):**
     * **Financial Overview:** Allocated Budget, Total Actual Spend, Remaining Budget, and utilization bar.
     * **Spending by Category:** Breakdown across **Materials**, **Equipment Rental**, **Labor / Overtime**, **Subcontractor**, and **Other / Misc**.
     * **Itemized Expense Ledger:** Shows Description, Category, Qty, Amount (PHP), Incurred Date, Logged By, and Actions (Admin/Manager can review pending submissions or delete erroneous entries).
     * **How to Log a New Expense:** Click **`Log New Expense / Cost`**, choose either an item from the **Materials Catalog** (auto-calculates cost and deducts catalog stock) or enter a custom expense, fill in quantity/amount/category/date, and submit. *(For Staff, submitted expenses wait for Admin/Manager approval.)*

4. STAFF REQUESTS MODULE (`Projects` → `Staff Requests` — Admin & Manager only):
   - **KPI Cards:** **Total Requests**, **Unexamined Requests** (click to filter pending items), **Approved Requests**, and **Rejected Requests**.
   - **Tab 1 — Request Approvals:** Lists all staff submissions (**Task Completion**, **Expense Entry**, **File Upload**) with search and filters (Status, Recency, Request Type).
     * **How to Approve or Reject a Request:** Click **`Verdict`** on any **Unexamined** request, inspect the submission details and proof attachments, enter your review notes, and click **Approve** or **Reject**. Click **`Review`** on processed requests to view past decisions.
   - **Tab 2 — View from Staff List:** Shows the Staff Directory with each staff member's **Unexamined**, **Approved**, and **Rejected** counts. Click **`View Requests`** on any staff member to inspect all their requests in a dedicated modal.

5. APPROVAL LIST MODULE (`Projects` → `Approval List` — Staff only):
   - **KPI Cards:** **Total Requests**, **Assigned Tasks**, **Approved Requests**, and **Rejected Requests**.
   - **Tab 1 — My Submitted Requests:** Track every Task Completion, Expense Entry, and File Upload you submitted, filterable by Status, Recency, and Request Type.
     * Click **`Ping`** on an Unexamined request to send a reminder notification to Admins/Managers (15-minute cooldown between pings).
     * Click **`Details`** to view your submission attachments and any reviewer remarks.
   - **Tab 2 — Assigned Tasks:** View all project timeline tasks assigned directly to you, filterable by **To Complete**, **In Review**, or **Completed**, with target dates and **Overdue** badges.
     * **How to Complete an Assigned Task:** Click **`Mark as done`** on an active task to submit a completion approval request with optional remarks and proof attachments. *(Note: sequential task locking applies if an earlier task in the same project is not yet completed.)*

6. MATERIALS CATALOG MODULE (`Resources` → `Materials Catalog` — All Roles):
   - Search and filter central inventory by Item Code, Item Name, Model/Specs, Stock Level, or Demand Status.
   - Columns: **Item Code**, **Item Name & Description**, **Model / Specs**, **QTY** (available stock with Low Stock / Out of Stock alerts), **Price** (DEX Selling Price), **Unit Cost** (Procurement/Supplier Price), and **Demand Status** (*Fast-moving* or *Slow-moving* based on 3-month usage).
   - **How to Add a Material:** Click **`Add Material`**, enter the code, name, model/specs, initial stock quantity, DEX selling price, and supplier unit cost, then save.
   - **How to Edit / Restock / Remove a Material:** Click **`Configure`** on any material row to update stock quantity, change prices, edit specs, or archive/remove the item.

7. MANPOWER MODULE (`Personnel` → `Manpower` — Admin & Manager only):
   - Displays the active workforce roster: **Employee ID**, **Full Name**, **Role** (one of the 8 official expertise classifications), **Assigned Project**, **Status** (*Assigned* or *Not Assigned*), and **Date Assigned**.
   - **How to Add New Personnel:** Click **`Add New Personnel`**, fill in personal details and Expertise Classification, optionally assign them to an active project, and save.
   - **How to Reassign or Unassign Personnel:** Click **`Reassign`** on any personnel row to deploy them to an active project or release them back to the unassigned pool.
   - **How to Edit a Personnel Profile:** Click **`Configure`** on any row to update their details, expertise classification, or profile status.

8. REPORTS MODULE (`Insights` → `Reports` — All Roles):
   - Top KPI Cards: **Projects Register**, **Contract Portfolio (Without Taxes)**, **Materials Catalog**, and **Field Manpower**.
   - **4 PDF Export Tabs:**
     1. **Project Data Export:** Customize included sections via checkboxes (**Project Overview**, **Elevator Technical Specs**, **Financials & Budget**, **Expenditures**, **Assigned Manpower**), select a project from the dropdown or table, and click **`Preview`** or **`Download PDF` / `Export PDF`**.
     2. **Revenue Data Export:** Toggle **`Monthly Comparison`** or **`Yearly Comparison`**, filter by year/date range/status, inspect KPI cards (Total Contract Value, Actual Spends, Remaining Budget without tax, Period Comparison) and the visual chart, then click **`Generate Revenue PDF`**.
     3. **Materials List Export:** Filter by Fast-Moving/Slow-Moving or stock level, tick checkboxes for desired items, and click **`Export Selected to PDF`**.
     4. **Manpower List Export:** Filter by expertise or deployment status, optionally include recent project history, select workers, and click **`Export Selected to PDF`**.

9. DECISION SUPPORT MODULE (`Insights` → `Decision Support` — All Roles; Tab 2 is Admin & Manager only):
   - **Tab 1 — What-If Simulator (In-Memory — does NOT alter live database records):**
     * **Project Feasibility:** Enter proposed budget, dates, required manpower roles, and catalog materials to get a color-coded feasibility verdict (Green/Yellow/Red) with readiness and stock charts.
     * **Project Delay Simulation:** Select a target project and drag the **Delay Duration Slider** (slip days) to inspect the Gantt-style **Portfolio Schedule Calendar**, **Projects Run Over by Schedule Slip**, and **Held Field Personnel**.
     * **Supplier Procurement Trade-Off:** Use the drag-and-drop canvas (**`Add Profile`**, **`Choose From Existing Profiles`**, **`Create Profile`**) to connect supplier profiles supplying the same material and compare unit costs, shipping lead days, savings, and latest order cutoff dates.
   - **Tab 2 — Generate Resource Allocation (Admin & Manager only):**
     * Choose a **Target Project** and a **Scenario Template** (Auto Spec-Matched, Low-Rise, Mid-Rise, High-Rise, or Saved Custom Template).
     * Click **`Match Project Specs`** to auto-calculate missing roles and materials, or **`+ Save as Custom Template`** to reuse your setup later.
     * Review **Live Pool Availability & Auto-Matched Crew**, **Catalog Stock Sufficiency**, and **Post-Allocation Project Budget Impact**, then click **`Allocate Resource to Project`** to commit manpower and materials atomically.
   - **Tab 3 — Kuya DEX (Personal AI Assistant):**
     * Choose an active **Project Context** from the top-right dropdown, ask operational questions, request forecasts or elevator crew sizing, check staff requests/assigned tasks, or (for Admin/Manager) assign/remove manpower with **`[Yes]` / `[No]`** confirmation. Click **`Reset Chat`** to clear the volatile session.
GUIDE;
    }

    /**
     * Determine whether Gemini function declarations for manpower assignment/removal should be attached.
     * Avoids attaching tool declarations to analytical/forecasting queries so Gemini responds faster without tool latency.
     */
    private function shouldAttachManpowerTools(string $message): bool
    {
        $normalized = strtolower(trim((string) preg_replace('/\s+/', ' ', $message)));
        if ($normalized === '') {
            return false;
        }

        // Do not attach action tools if the user is asking for a forecast, assessment, or explanation
        if (preg_match('/\b(forecast|forecasts|forecasting|roadblock|roadblocks|shortcoming|shortcomings|revenue|revenues|assess|assessing|assessment|hypothetical|scenario|scenarios|how\s+to|how\s+can\s+i|how\s+do\s+i)\b/i', $normalized) === 1
            && preg_match('/\b(assign|allocate|fill|remove|unassign)\b/i', $normalized) !== 1) {
            return false;
        }

        return preg_match('/\b(assign|allocate|fill|populate|remove|unassign|release|deallocate)\b/i', $normalized) === 1;
    }

    /**
     * Generate a comprehensive, data-grounded Project & Business Analyst report directly from live DEX PMS data
     * whenever the user asks a forecasting, revenue, shortcoming, roadblock, task assessment, or hypothetical scenario
     * inquiry and the external Gemini endpoint is temporarily congested or slow.
     */
    public function tryGenerateLocalForecastAndAnalystFallback(
        string $message,
        ?int $selectedProjectId = null,
        bool $includeProjectTasks = true,
    ): ?string {
        $normalized = strtolower(trim((string) preg_replace('/\s+/', ' ', $message)));
        $isForecastOrAnalystQuery = preg_match(
            '/\b(forecast|forecasts|forecasting|shortcoming|shortcomings|roadblock|roadblocks|bottleneck|bottlenecks|revenue|revenues|hypothetical|scenario|scenarios|assess|assessing|assessment|progressing|executing|execution|behind|ahead|velocity|pace|burn\s*rate|overrun|margin)\b/i',
            $normalized,
        ) === 1;

        if (! $isForecastOrAnalystQuery) {
            return null;
        }

        $nowLocal = Carbon::now(config('app.timezone', 'Asia/Manila'));
        $todayHuman = $nowLocal->format('l, F j, Y \a\t g:i A');
        $todayDay = $nowLocal->copy()->startOfDay();

        $projects = Project::query()
            ->with(['personnel', 'tasks.subtasks.assignedTo', 'costs.resource'])
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->get();

        if ($projects->isEmpty()) {
            return "As of **{$todayHuman}**, there are currently no active projects registered in DEX PMS to forecast.";
        }

        $activeProject = $selectedProjectId
            ? ($projects->firstWhere('id', $selectedProjectId) ?? $projects->first())
            : $projects->first();

        $allPersonnel = Personnel::query()->whereNull('deleted_at')->get();
        $unassignedPersonnel = $allPersonnel->whereNull('project_id');
        $allResources = Resource::query()->whereNull('deleted_at')->get();
        $outOfStock = $allResources->filter(fn (Resource $r) => (int) $r->quantity_available <= 0);
        $lowStock = $allResources->filter(fn (Resource $r) => (int) $r->quantity_available > 0 && $r->isLowStock());

        $portfolioRevenue = round((float) $projects->sum('contract_price'), 2);
        $portfolioSpend = round((float) $projects->sum('actual_spend'), 2);
        $portfolioMargin = round($portfolioRevenue - $portfolioSpend, 2);
        $portfolioMarginPct = $portfolioRevenue > 0 ? round(($portfolioMargin / $portfolioRevenue) * 100, 1) : 0.0;

        // Analyze active context project in detail
        $pTasks = $activeProject->tasks ?? collect();
        $majorTotal = $pTasks->count();
        $majorDone = 0;
        $minorTotal = 0;
        $minorDone = 0;
        $overdueMajorCount = 0;
        $overdueMinorCount = 0;
        $unassignedMinorCount = 0;
        $overdueTaskLabels = [];
        $bottleneckLabels = [];

        foreach ($pTasks as $t) {
            $subs = $t->subtasks ?? collect();
            $mTot = $subs->count();
            $mComp = $subs->where('is_completed', true)->count();
            $minorTotal += $mTot;
            $minorDone += $mComp;

            $isComp = $t->computed_status === 'completed';
            if ($isComp) {
                $majorDone++;
            }

            $tEnd = $t->end_date ? Carbon::parse($t->end_date)->startOfDay() : null;
            if (! $isComp && $tEnd && $todayDay->gt($tEnd)) {
                $overdueMajorCount++;
                $daysLate = (int) $tEnd->diffInDays($todayDay);
                $overdueTaskLabels[] = "**{$t->task_name}** ({$daysLate} day(s) past {$tEnd->format('Y-m-d')})";
            }

            $rate = $mTot > 0 ? round(($mComp / $mTot) * 100, 1) : ($isComp ? 100.0 : 0.0);
            if (! $isComp && $rate < 50) {
                $bottleneckLabels[] = "**{$t->task_name}** ({$mComp}/{$mTot} minor tasks done — {$rate}%)";
            }

            foreach ($subs as $st) {
                if (! $st->is_completed) {
                    if ($st->assigned_to === null) {
                        $unassignedMinorCount++;
                    }
                    $stDue = $st->due_date ? Carbon::parse($st->due_date)->startOfDay() : null;
                    if ($stDue && $todayDay->gt($stDue)) {
                        $overdueMinorCount++;
                    }
                }
            }
        }

        $majorPct = $majorTotal > 0 ? round(($majorDone / $majorTotal) * 100, 1) : ($activeProject->status === 'completed' ? 100.0 : 0.0);
        $minorPct = $minorTotal > 0 ? round(($minorDone / $minorTotal) * 100, 1) : ($activeProject->status === 'completed' ? 100.0 : 0.0);

        $elapsedPct = 0.0;
        $velocityLabel = 'On Pace';
        $projectedFinish = $activeProject->target_completion_date?->format('Y-m-d') ?? 'Not set';
        if ($activeProject->start_date && $activeProject->target_completion_date) {
            $sDay = Carbon::parse($activeProject->start_date)->startOfDay();
            $eDay = Carbon::parse($activeProject->target_completion_date)->startOfDay();
            $totDays = max(1, (int) $sDay->diffInDays($eDay) + 1);
            $elDays = $todayDay->lt($sDay) ? 0 : (int) $sDay->diffInDays($todayDay) + 1;
            $elapsedPct = min(100.0, round(($elDays / $totDays) * 100, 1));

            if ($activeProject->status === 'completed') {
                $velocityLabel = 'Completed';
            } elseif ($todayDay->gt($eDay)) {
                $velocityLabel = 'Critical Slippage (Past Target Completion Date)';
            } elseif ($minorPct >= $elapsedPct + 10) {
                $velocityLabel = 'Ahead of Schedule';
            } elseif ($elapsedPct - $minorPct >= 20) {
                $velocityLabel = 'Behind Schedule (Slow Task Execution Velocity)';
            } elseif ($elapsedPct - $minorPct >= 10) {
                $velocityLabel = 'Slightly Behind Pace';
            }

            if ($activeProject->status !== 'completed' && $elDays > 0 && $minorPct > 0 && $minorPct < 100) {
                $estDays = (int) ceil($elDays / ($minorPct / 100.0));
                $projectedFinish = $sDay->copy()->addDays(max($totDays, $estDays) - 1)->format('Y-m-d');
            }
        }

        $reqRoles = $this->getRequiredRoleCountsForProject($activeProject);
        $missingRoles = [];
        foreach ($reqRoles as $rName => $rCnt) {
            $haveCnt = $activeProject->personnel
                ->filter(fn (Personnel $p) => $p->deleted_at === null && strcasecmp(trim((string) $p->expertise), $rName) === 0)
                ->count();
            if ($rCnt > $haveCnt) {
                $missingRoles[] = "{$rName} (missing " . ($rCnt - $haveCnt) . ')';
            }
        }

        $pContract = round((float) $activeProject->contract_price, 2);
        $pSpend = round((float) $activeProject->actual_spend, 2);
        $pRem = round((float) $activeProject->remaining_budget, 2);
        $pSpendPct = round((float) $activeProject->spend_percentage, 1);
        $pMarginPct = $pContract > 0 ? round((($pContract - $pSpend) / $pContract) * 100, 1) : 0.0;

        $todayDateOnly = $nowLocal->format('F j, Y');
        $lines = [];
        $lines[] = "Here is my project and business analysis as of **{$todayDateOnly}** for **{$activeProject->project_code} — {$activeProject->name}** (" . ucfirst((string) $activeProject->status) . "):";
        $lines[] = '';
        $lines[] = "#### Revenue & Budget Outlook";
        $lines[] = sprintf(
            "- **Portfolio Overview:** Across our **%d projects**, total contract value stands at **₱%s** with **₱%s** spent so far, giving us a projected gross margin of **₱%s (%s%%)**.",
            $projects->count(),
            number_format($portfolioRevenue, 2),
            number_format($portfolioSpend, 2),
            number_format($portfolioMargin, 2),
            (string) $portfolioMarginPct,
        );
        $lines[] = sprintf(
            "- **%s Financials:** Out of the **₱%s** contract budget, **₱%s (%s%%)** has been spent, leaving **₱%s (%s%% projected margin)**.",
            $activeProject->project_code,
            number_format($pContract, 2),
            number_format($pSpend, 2),
            (string) $pSpendPct,
            number_format($pRem, 2),
            (string) $pMarginPct,
        );

        $lines[] = '';
        $lines[] = "#### Task Progress & Schedule Pace";
        $lines[] = sprintf(
            "- **Current Execution Pace:** **%s** — **%s%%** of the planned schedule has passed as of today, while **%d of %d Major Tasks (%s%%)** and **%d of %d Minor Checklist Tasks (%s%%)** are complete.",
            $velocityLabel,
            (string) $elapsedPct,
            $majorDone,
            $majorTotal,
            (string) $majorPct,
            $minorDone,
            $minorTotal,
            (string) $minorPct,
        );
        $lines[] = sprintf(
            "- **Completion Outlook:** The target completion date is **%s**. Based on our current task completion speed, the projected finish date is **%s**.",
            $activeProject->target_completion_date?->format('Y-m-d') ?? 'N/A',
            $projectedFinish,
        );
        if ($overdueMajorCount > 0 || $overdueMinorCount > 0) {
            $lines[] = "- **Overdue Items:** There are **{$overdueMajorCount} overdue Major Task phase(s)** and **{$overdueMinorCount} overdue Minor checklist task(s)**" . (! empty($overdueTaskLabels) ? ' (' . implode(', ', array_slice($overdueTaskLabels, 0, 3)) . ')' : '') . '.';
        }
        if (! empty($bottleneckLabels)) {
            $lines[] = '- **Slow-Moving Phases:** ' . implode(', ', array_slice($bottleneckLabels, 0, 3)) . '.';
        }

        $lines[] = '';
        $lines[] = "#### Shortcomings & Operational Roadblocks";
        $lines[] = ! empty($missingRoles)
            ? "- **Manpower Gaps on {$activeProject->project_code}:** Still missing **" . implode(', ', $missingRoles) . "** based on the project's elevator specs (**{$unassignedPersonnel->count()} unassigned personnel** are currently available in the pool)."
            : "- **Manpower Staffing on {$activeProject->project_code}:** All core roles are filled (**{$activeProject->personnel->count()} personnel** assigned; **{$unassignedPersonnel->count()} unassigned** available in the pool).";
        $lines[] = sprintf(
            "- **Materials Inventory:** **%d out-of-stock** item(s)%s and **%d low-stock** item(s)%s in the catalog.",
            $outOfStock->count(),
            $outOfStock->isNotEmpty() ? ' (' . $outOfStock->take(3)->pluck('name')->implode(', ') . ')' : '',
            $lowStock->count(),
            $lowStock->isNotEmpty() ? ' (' . $lowStock->take(3)->pluck('name')->implode(', ') . ')' : '',
        );

        $lines[] = '';
        $lines[] = "#### What-If Scenarios";
        $remMinor = max(0, $minorTotal - $minorDone);
        $lines[] = sprintf(
            "- **If We Continue at Current Pace:** With **%s%%** of checklist tasks done against **%s%%** elapsed time, **%s** is on track to finish around **%s** with **₱%s** in remaining budget.",
            (string) $minorPct,
            (string) $elapsedPct,
            $activeProject->project_code,
            $projectedFinish,
            number_format($pRem, 2),
        );
        $lines[] = ! empty($missingRoles)
            ? sprintf(
                "- **If We Fill Missing Manpower & Clear Bottlenecks:** Assigning available personnel to cover **%s** and focusing on the **%d remaining minor task(s)** can speed up site execution by ~20%%–30%% and help bring delivery back toward **%s**.",
                implode(', ', $missingRoles),
                $remMinor,
                $activeProject->target_completion_date?->format('Y-m-d') ?? 'the target date',
            )
            : sprintf(
                "- **If We Focus Crew on Bottleneck Tasks:** Concentrating our **%d assigned crew members** on the **%d remaining minor checklist task(s)** will help compress the remaining timeline and protect the **%s%%** gross margin.",
                $activeProject->personnel->count(),
                $remMinor,
                (string) $pMarginPct,
            );
        $lines[] = sprintf(
            "- **If Tasks Slip by Another 1–2 Weeks:** A 7–14 day delay on active milestones will keep our **%d assigned personnel** tied up longer, delaying their transfer to other projects and putting extra pressure on the **₱%s** remaining budget.",
            $activeProject->personnel->count(),
            number_format($pRem, 2),
        );

        $lines[] = '';
        $lines[] = "#### Recommended Next Steps";
        if (! empty($missingRoles)) {
            $lines[] = "- **Fill Missing Roles:** You can ask me *\"Fill the necessary manpower for this project\"* to match and assign available personnel to **" . implode(', ', $missingRoles) . "**.";
        }
        if ($overdueMajorCount > 0 || $overdueMinorCount > 0) {
            $lines[] = "- **Resolve Overdue Tasks:** Follow up on the **{$overdueMinorCount} overdue minor task(s)** and check the **Staff Requests** module for any pending task completions waiting for review.";
        }
        if ($unassignedMinorCount > 0) {
            $lines[] = "- **Assign Task Owners:** Assign responsible staff to the **{$unassignedMinorCount} unassigned minor task(s)** in the Project Progress Timeline.";
        }
        if ($outOfStock->isNotEmpty() || $lowStock->isNotEmpty()) {
            $lines[] = "- **Restock Catalog Items:** Replenish out-of-stock and low-stock elevator components in the **Materials Catalog** before upcoming installation phases.";
        }

        return implode("\n", $lines);
    }

    /**
     * Deterministically calculate the required core manpower role headcounts for a project
     * based on its elevator technical specifications (stops, rated capacity, and project type).
     *
     * @param Project $project
     * @return array<string, int>
     */
    private function getRequiredRoleCountsForProject(Project $project): array
    {
        $stops = max(2, (int) ($project->no_of_stops ?: 4));
        preg_match('/\d+/', (string) ($project->rated_capacity ?: '800'), $capMatches);
        $capacityKg = isset($capMatches[0]) ? (int) $capMatches[0] : 800;
        $projType = strtolower((string) ($project->project_type ?: 'Standard Elevator'));

        $techCount = 1;
        $foremanCount = 1;
        if ($stops >= 12 || $capacityKg >= 1350 || str_contains($projType, 'freight')) {
            $techCount = 2;
            $foremanCount = 2;
        } elseif ($stops >= 6 || $capacityKg >= 1000) {
            $techCount = 2;
            $foremanCount = 1;
        }

        return [
            Personnel::EXPERTISE_PROJECT_MANAGER => 1,
            Personnel::EXPERTISE_SITE_ENGINEER => 1,
            Personnel::EXPERTISE_FOREMAN => $foremanCount,
            Personnel::EXPERTISE_SAFETY_OFFICER => 1,
            Personnel::EXPERTISE_TECHNICIAN => $techCount,
        ];
    }

    /**
     * Resolve a user-specified personnel role or generic manpower keyword into one of the 8 official
     * DEX PMS expertise classifications, or null if the user asked for all missing personnel/manpower in general.
     *
     * @param string|null $roleQuery
     * @return string|null
     */
    public function resolveSpecificRoleFilter(?string $roleQuery): ?string
    {
        $clean = strtolower(trim((string) $roleQuery, " \t\n\r\0\x0B.!?\"'[]()"));
        $clean = preg_replace('/^(?:a\s+|an\s+|the\s+|any\s+|some\s+|our\s+|my\s+|missing\s+|needed\s+|required\s+|necessary\s+)+/i', '', $clean) ?? $clean;
        $clean = trim($clean);

        if ($clean === '' || preg_match('/^(?:personnel|manpower|staff|staffs|crew|crews|workers?|employees?|members?|people|roles?|positions?|team|workforce|personnel\s*\/\s*manpower|manpower\s*\/\s*personnel)$/i', $clean) === 1) {
            return null;
        }

        if (str_contains($clean, 'installation') || preg_match('/\bworker\s*\/\s*installation\b/i', $clean) === 1) {
            return Personnel::EXPERTISE_WORKER;
        }
        if (str_contains($clean, 'project manager') || $clean === 'pm' || $clean === 'pms' || $clean === 'manager' || $clean === 'managers') {
            return Personnel::EXPERTISE_PROJECT_MANAGER;
        }
        if (str_contains($clean, 'site engineer') || $clean === 'engineer' || $clean === 'engineers') {
            return Personnel::EXPERTISE_SITE_ENGINEER;
        }
        if (str_contains($clean, 'safety officer') || str_contains($clean, 'safety')) {
            return Personnel::EXPERTISE_SAFETY_OFFICER;
        }
        if (str_contains($clean, 'foreman') || str_contains($clean, 'foremen')) {
            return Personnel::EXPERTISE_FOREMAN;
        }
        if (str_contains($clean, 'technician') || $clean === 'tech' || $clean === 'techs') {
            return Personnel::EXPERTISE_TECHNICIAN;
        }
        if (str_contains($clean, 'qa') || str_contains($clean, 'inspector') || str_contains($clean, 'quality')) {
            return Personnel::EXPERTISE_QA_INSPECTOR;
        }
        if (str_contains($clean, 'elevator control') || str_contains($clean, 'control')) {
            return Personnel::EXPERTISE_ELEVATOR_CONTROL;
        }

        foreach (Personnel::EXPERTISE_OPTIONS as $option) {
            if (strcasecmp($clean, $option) === 0 || strcasecmp(rtrim($clean, 's'), $option) === 0) {
                return $option;
            }
        }

        return null;
    }

    /**
     * Gemini function declarations for manpower assignment & removal orchestration.
     *
     * @return list<array<string, mixed>>
     */
    private function getManpowerFunctionDeclarations(): array
    {
        return [
            [
                'name' => 'find_and_allocate_missing_personnel_across_projects',
                'description' => 'Find all active projects that have missing personnel (or a specific missing expertise role such as Project Manager, Site Engineer, Technician, Foreman, Safety Officer, QA Inspector, Elevator Control, or Worker / Installation Personnel) and automatically assign/allocate available unassigned personnel across those projects.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'role_filter' => [
                            'type' => 'STRING',
                            'description' => 'Optional specific expertise classification to check and allocate (e.g. "Project Manager", "Technician", "Site Engineer", "Foreman", "Safety Officer", "QA Inspector", "Elevator Control", "Worker / Installation Personnel"), or "personnel" / "manpower" for all missing core roles.',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'fill_missing_project_manpower',
                'description' => 'Automatically fill and assign the missing or necessary manpower roles for a target project using available unassigned personnel.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'project_identifier' => [
                            'type' => 'STRING',
                            'description' => 'The project code (e.g. PRJ-2026-001) or project name, or "this project" to use the active selected project.',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'assign_personnel_to_project',
                'description' => 'Assign a specific personnel member (by full name or employee ID) or expertise role to a target project.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'personnel_identifier' => [
                            'type' => 'STRING',
                            'description' => 'The full name (e.g. John Doe), employee ID (e.g. EMP-2026001), or expertise role of the personnel to assign.',
                        ],
                        'project_identifier' => [
                            'type' => 'STRING',
                            'description' => 'The project code or project name, or "this project" to use the active selected project.',
                        ],
                    ],
                    'required' => ['personnel_identifier'],
                ],
            ],
            [
                'name' => 'remove_personnel_from_project',
                'description' => 'Remove or unassign a specific personnel member (by full name or employee ID) from a target project.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'personnel_identifier' => [
                            'type' => 'STRING',
                            'description' => 'The full name (e.g. John Doe) or employee ID (e.g. EMP-2026001) of the personnel to remove/unassign.',
                        ],
                        'project_identifier' => [
                            'type' => 'STRING',
                            'description' => 'The project code or project name, or "this project" to use the active selected project.',
                        ],
                    ],
                    'required' => ['personnel_identifier'],
                ],
            ],
        ];
    }

    /**
     * Execute a Gemini tool/function call for manpower management.
     *
     * @param string $functionName
     * @param array<string, mixed> $args
     * @param string $rawMessage
     * @param int|null $selectedProjectId
     * @return array{reply: string, mode: string, connected: bool, action_executed: bool, action_type?: string, project_id?: int}
     */
    private function executeGeminiFunctionCall(
        string $functionName,
        array $args,
        string $rawMessage,
        ?int $selectedProjectId,
    ): array {
        if (! $this->canCurrentUserManageManpower()) {
            return [
                'reply' => self::STAFF_MANPOWER_DENIED_REPLY,
                'mode' => 'guardrail',
                'connected' => true,
                'action_executed' => false,
            ];
        }

        $projectIdentifier = isset($args['project_identifier']) ? (string) $args['project_identifier'] : null;
        $personnelIdentifier = isset($args['personnel_identifier']) ? (string) $args['personnel_identifier'] : '';
        $roleFilter = isset($args['role_filter']) ? (string) $args['role_filter'] : null;

        return match ($functionName) {
            'find_and_allocate_missing_personnel_across_projects' => $this->executeFindAndAllocateMissingPersonnelAcrossProjects($roleFilter),
            'fill_missing_project_manpower' => $this->executeFillMissingManpower($projectIdentifier, $rawMessage, $selectedProjectId),
            'assign_personnel_to_project' => $this->executeAssignPersonnel($personnelIdentifier, $projectIdentifier, $rawMessage, $selectedProjectId),
            'remove_personnel_from_project' => $this->executeRemovePersonnel($personnelIdentifier, $projectIdentifier, $rawMessage, $selectedProjectId),
            default => [
                'reply' => self::RESTRICTED_REPLY,
                'mode' => 'guardrail',
                'connected' => true,
                'action_executed' => false,
            ],
        };
    }

    /**
     * Deterministically parse and handle manpower assignment, missing-role filling, and unassignment commands.
     * Enforces the Staff role guardrail before touching the database.
     *
     * @param string $message
     * @param int|null $selectedProjectId
     * @return array{reply: string, mode: string, connected: bool, action_executed: bool, action_type?: string, project_id?: int}|null
     */
    public function tryHandleManpowerActionCommand(string $message, ?int $selectedProjectId = null): ?array
    {
        $normalized = trim((string) preg_replace('/\s+/', ' ', $message));
        if ($normalized === '') {
            return null;
        }

        // 0. Guardrail: If an Admin/Manager/Staff commands Kuya DEX to approve or reject staff requests/tasks (e.g., "Approve those tasks", "Approve those requests"), direct them to the Staff Requests module
        if (preg_match('/^(?:please\s+|can\s+you\s+|could\s+you\s+|would\s+you\s+|kuya(?:\s+dex)?[,:\s]+)*(?:approve|reject|decline|deny)\s+(?:those|these|the|all|my|our|pending|unexamined|staff|assigned|submitted)?\s*(?:staff\s+)?(?:tasks?|subtasks?|requests?|approvals?|submissions?|actions?|expenses?|files?)\b/i', $normalized) === 1) {
            return [
                'reply' => self::STAFF_REQUEST_APPROVAL_REDIRECT_REPLY,
                'mode' => 'guardrail',
                'connected' => true,
                'action_executed' => false,
            ];
        }

        // Do not intercept informational questions (e.g. "Who is assigned...", "What is the missing manpower...", "How many...")
        // unless the user explicitly phrases an action request ("Can you assign...", "Could you fill...", "Can you remove...", "Find all projects... assign and allocate...")
        $isPureQuestion = preg_match('/^(who|what|which|where|when|why|how|is|are|do|does|did|list|show|check|tell)\b/i', $normalized) === 1
            && preg_match('/\b(assign|allocate|fill|populate|auto[\-\s]?assign)\b/i', $normalized) !== 1;
        if ($isPureQuestion) {
            return null;
        }

        $clean = preg_replace('/^(?:please\s+|can\s+you\s+|could\s+you\s+|would\s+you\s+|kuya(?:\s+dex)?[,:\s]+)+/i', '', $normalized) ?? $normalized;
        $clean = trim($clean, " \t\n\r\0\x0B.!?\"'");

        // 0.5. Check for multi-project missing manpower search & allocation:
        // e.g. "Find all projects that has missing [personnel], assign and allocate my available personnel"
        // e.g. "Find all projects that have missing Project Manager, assign and allocate my available personnel"
        if (preg_match('/^(?:find|search|check|identify|locate|look\s+for|get|list|show)\s+(?:all\s+|any\s+|the\s+|our\s+|my\s+)?projects?\s+(?:that\s+|which\s+)?(?:has|have|with|lacking|needing|missing|without)\s+(?:a\s+|an\s+|any\s+|the\s+)?(?:missing\s+|needed\s+|required\s+|necessary\s+|unfilled\s+)?(.+?)(?:\s*(?:,|\band\b|\bthen\b)\s*(?:please\s+)?(?:assign|allocate|fill|deploy|distribute|populate).*)$/i', $clean, $matches) === 1) {
            if (! $this->canCurrentUserManageManpower()) {
                return [
                    'reply' => self::STAFF_MANPOWER_DENIED_REPLY,
                    'mode' => 'guardrail',
                    'connected' => true,
                    'action_executed' => false,
                ];
            }

            $roleTarget = trim($matches[1], " \t\n\r\0\x0B.!?\"'");

            return $this->executeFindAndAllocateMissingPersonnelAcrossProjects($roleTarget);
        }

        // Also match "Assign and allocate my available personnel to all projects that has missing [personnel]"
        // or "Fill / allocate missing [personnel] for all projects"
        if (preg_match('/^(?:assign(?:\s+and\s+allocate)?|allocate(?:\s+and\s+assign)?|fill(?:\s+in|\s+up)?|populate|auto[\-\s]?assign)\s+(?:my\s+|our\s+|the\s+|all\s+)?(?:available\s+|unassigned\s+)?(?:missing\s+|necessary\s+|needed\s+|required\s+)?(.+?)\s+(?:to|for|across|on|in)\s+all\s+(?:active\s+|ongoing\s+|our\s+|my\s+)?projects?(?:\s+(?:that\s+|which\s+)?(?:has|have|with|lacking|missing)\s+(?:missing\s+|needed\s+|required\s+)?(.+))?$/i', $clean, $matches) === 1) {
            if (! $this->canCurrentUserManageManpower()) {
                return [
                    'reply' => self::STAFF_MANPOWER_DENIED_REPLY,
                    'mode' => 'guardrail',
                    'connected' => true,
                    'action_executed' => false,
                ];
            }

            $roleCandidate = ! empty($matches[2]) ? trim($matches[2], " \t\n\r\0\x0B.!?\"'") : trim($matches[1], " \t\n\r\0\x0B.!?\"'");

            return $this->executeFindAndAllocateMissingPersonnelAcrossProjects($roleCandidate);
        }

        // 1. Check for "Remove the assignment of [Name] from [Project]" / "Unassign [Name] from [Project]"
        if (preg_match('/^(?:remove\s+(?:the\s+)?assignment\s+of|unassign|release|deallocate)\s+(.+?)(?:\s+(?:from|in|on|of|at)\s+(.+))?$/i', $clean, $matches) === 1) {
            if (! $this->canCurrentUserManageManpower()) {
                return [
                    'reply' => self::STAFF_MANPOWER_DENIED_REPLY,
                    'mode' => 'guardrail',
                    'connected' => true,
                    'action_executed' => false,
                ];
            }

            $personnelTarget = trim($matches[1], " \t\n\r\0\x0B.!?\"'");
            $projectTarget = isset($matches[2]) ? trim($matches[2], " \t\n\r\0\x0B.!?\"'") : null;

            return $this->executeRemovePersonnel($personnelTarget, $projectTarget, $normalized, $selectedProjectId);
        }

        if (preg_match('/^remove\s+(.+?)\s+(?:from|in|on|of|at)\s+(.+)$/i', $clean, $matches) === 1) {
            $personnelTarget = trim($matches[1], " \t\n\r\0\x0B.!?\"'");
            $projectTarget = trim($matches[2], " \t\n\r\0\x0B.!?\"'");
            // Ensure this is a project/manpower removal command
            if (preg_match('/\b(project|prj[\-\s]?\d+|manpower|personnel|crew|assignment)\b/i', $normalized) === 1
                || $this->findMatchingPersonnel($personnelTarget)->isNotEmpty()) {
                if (! $this->canCurrentUserManageManpower()) {
                    return [
                        'reply' => self::STAFF_MANPOWER_DENIED_REPLY,
                        'mode' => 'guardrail',
                        'connected' => true,
                        'action_executed' => false,
                    ];
                }

                return $this->executeRemovePersonnel($personnelTarget, $projectTarget, $normalized, $selectedProjectId);
            }
        }

        // 2. Check for "Fill the missing personnel / manpower for this project" / "Fill the necessary manpower for this project" / "Assign manpower to this project"
        if (preg_match('/^(?:fill(?:\s+in|\s+up)?|populate|complete|auto[\-\s]?assign|allocate)\s+(?:the\s+|all\s+|our\s+)?(?:missing|necessary|needed|required|available|remaining)?\s*(?:personnel\s*\/\s*manpower|manpower\s*\/\s*personnel|personnel\s+and\s+manpower|manpower\s+and\s+personnel|personnel|manpower|staff|crew|roles?|workers?)(?:\s+(?:to|for|on|in|into|of)\s+(.+))?$/i', $clean, $matches) === 1) {
            if (! $this->canCurrentUserManageManpower()) {
                return [
                    'reply' => self::STAFF_MANPOWER_DENIED_REPLY,
                    'mode' => 'guardrail',
                    'connected' => true,
                    'action_executed' => false,
                ];
            }

            $projectTarget = isset($matches[1]) ? trim($matches[1], " \t\n\r\0\x0B.!?\"'") : null;

            return $this->executeFillMissingManpower($projectTarget, $normalized, $selectedProjectId);
        }

        if (preg_match('/^assign\s+(?:the\s+|all\s+|our\s+)?(?:missing|necessary|needed|required|available)?\s*(?:personnel\s*\/\s*manpower|manpower\s*\/\s*personnel|personnel\s+and\s+manpower|manpower\s+and\s+personnel|personnel|manpower|staff|crew|roles?|workers?)(?:\s+(?:to|for|on|in|into|of)\s+(.+))?$/i', $clean, $matches) === 1) {
            if (! $this->canCurrentUserManageManpower()) {
                return [
                    'reply' => self::STAFF_MANPOWER_DENIED_REPLY,
                    'mode' => 'guardrail',
                    'connected' => true,
                    'action_executed' => false,
                ];
            }

            $projectTarget = isset($matches[1]) ? trim($matches[1], " \t\n\r\0\x0B.!?\"'") : null;

            return $this->executeFillMissingManpower($projectTarget, $normalized, $selectedProjectId);
        }

        // 3. Check for "Assign [John Doe / EMP-2026XXX / Role] to [this project / PRJ-XXX]"
        if (preg_match('/^(?:assign|allocate)\s+(.+?)\s+(?:to|for|on|in|into)\s+(.+)$/i', $clean, $matches) === 1) {
            if (! $this->canCurrentUserManageManpower()) {
                return [
                    'reply' => self::STAFF_MANPOWER_DENIED_REPLY,
                    'mode' => 'guardrail',
                    'connected' => true,
                    'action_executed' => false,
                ];
            }

            $personnelTarget = trim($matches[1], " \t\n\r\0\x0B.!?\"'");
            $projectTarget = trim($matches[2], " \t\n\r\0\x0B.!?\"'");

            return $this->executeAssignPersonnel($personnelTarget, $projectTarget, $normalized, $selectedProjectId);
        }

        return null;
    }

    /**
     * Execute a user-confirmed manpower assignment or removal action after the user clicks [Yes].
     *
     * @param array<string, mixed> $confirmAction
     * @param int|null $selectedProjectId
     * @return array{reply: string, mode: string, connected: bool, action_executed: bool, action_type?: string, project_id?: int}
     */
    public function executeConfirmedManpowerAction(array $confirmAction, ?int $selectedProjectId = null): array
    {
        if (! $this->canCurrentUserManageManpower()) {
            return [
                'reply' => self::STAFF_MANPOWER_DENIED_REPLY,
                'mode' => 'guardrail',
                'connected' => true,
                'action_executed' => false,
            ];
        }

        $actionType = (string) ($confirmAction['action_type'] ?? '');
        $targetProjectId = isset($confirmAction['project_id']) ? (int) $confirmAction['project_id'] : $selectedProjectId;
        $personnelQuery = (string) ($confirmAction['personnel_query'] ?? '');
        $roleFilter = isset($confirmAction['role_filter']) ? (string) $confirmAction['role_filter'] : null;
        $explicitIds = isset($confirmAction['personnel_ids']) && is_array($confirmAction['personnel_ids'])
            ? array_values(array_unique(array_filter(array_map('intval', $confirmAction['personnel_ids']))))
            : null;
        $explicitAllocations = isset($confirmAction['allocations']) && is_array($confirmAction['allocations'])
            ? $confirmAction['allocations']
            : null;

        return match ($actionType) {
            'allocate_missing_across_projects' => $this->executeFindAndAllocateMissingPersonnelAcrossProjects($roleFilter, true, $explicitAllocations),
            'fill_missing_manpower' => $this->executeFillMissingManpower(null, '', $targetProjectId, true, $explicitIds),
            'assign_personnel' => $this->executeAssignPersonnel($personnelQuery, null, '', $targetProjectId, true, $explicitIds),
            'remove_personnel' => $this->executeRemovePersonnel($personnelQuery, null, '', $targetProjectId, true, $explicitIds),
            default => [
                'reply' => 'Invalid confirmation action.',
                'mode' => 'gemini',
                'connected' => true,
                'action_executed' => false,
            ],
        };
    }

    /**
     * Find all active projects that have missing personnel (either all core roles or a specific role like Project Manager,
     * Technician, Site Engineer, etc.) and preview or execute allocating available unassigned personnel across those projects.
     *
     * @param string|null $roleQuery
     * @param bool $confirmed
     * @param list<array<string, mixed>>|null $explicitAllocations
     * @return array{reply: string, mode: string, connected: bool, action_executed: bool, action_type?: string, pending_action?: array<string, mixed>}
     */
    public function executeFindAndAllocateMissingPersonnelAcrossProjects(
        ?string $roleQuery = null,
        bool $confirmed = false,
        ?array $explicitAllocations = null,
    ): array {
        if (! $this->canCurrentUserManageManpower()) {
            return [
                'reply' => self::STAFF_MANPOWER_DENIED_REPLY,
                'mode' => 'guardrail',
                'connected' => true,
                'action_executed' => false,
            ];
        }

        $user = Auth::user();
        if (! $user) {
            return [
                'reply' => self::STAFF_MANPOWER_DENIED_REPLY,
                'mode' => 'guardrail',
                'connected' => true,
                'action_executed' => false,
            ];
        }

        $specificRole = $this->resolveSpecificRoleFilter($roleQuery);
        $roleLabel = $specificRole ?: 'core manpower roles';

        // Load all active (non-completed, non-deleted) projects in priority order (delayed -> ongoing -> pending)
        $activeProjects = Project::query()
            ->with('personnel')
            ->whereNull('deleted_at')
            ->where('status', '!=', 'completed')
            ->orderByRaw("CASE status
                WHEN 'delayed' THEN 1
                WHEN 'ongoing' THEN 2
                WHEN 'pending' THEN 3
                ELSE 4 END")
            ->orderBy('created_at')
            ->get()
            ->filter(fn (Project $p) => Gate::forUser($user)->allows('update', $p))
            ->values();

        if ($activeProjects->isEmpty()) {
            return [
                'reply' => 'There are currently no active (ongoing, delayed, or pending) projects in DEX PMS that require manpower allocation.',
                'mode' => 'gemini',
                'connected' => true,
                'action_executed' => false,
            ];
        }

        // Scan each active project for missing roles
        /** @var list<array{project: Project, missing_roles: array<string, int>}> $projectsWithDeficits */
        $projectsWithDeficits = [];

        foreach ($activeProjects as $project) {
            $currentAssigned = $project->personnel
                ->filter(fn (Personnel $p) => $p->deleted_at === null);

            if ($specificRole !== null) {
                $requiredMap = $this->getRequiredRoleCountsForProject($project);
                $reqCount = $requiredMap[$specificRole] ?? 1;
                $existingCount = $currentAssigned
                    ->filter(fn (Personnel $p) => strcasecmp(trim((string) $p->expertise), $specificRole) === 0)
                    ->count();
                $deficit = max(0, $reqCount - $existingCount);
                if ($deficit > 0) {
                    $projectsWithDeficits[] = [
                        'project' => $project,
                        'missing_roles' => [$specificRole => $deficit],
                    ];
                }
            } else {
                $requiredMap = $this->getRequiredRoleCountsForProject($project);
                $missingMap = [];
                foreach ($requiredMap as $rName => $reqCount) {
                    $existingCount = $currentAssigned
                        ->filter(fn (Personnel $p) => strcasecmp(trim((string) $p->expertise), $rName) === 0)
                        ->count();
                    $deficit = max(0, $reqCount - $existingCount);
                    if ($deficit > 0) {
                        $missingMap[$rName] = $deficit;
                    }
                }
                if (! empty($missingMap)) {
                    $projectsWithDeficits[] = [
                        'project' => $project,
                        'missing_roles' => $missingMap,
                    ];
                }
            }
        }

        if (empty($projectsWithDeficits)) {
            $targetText = $specificRole ? "missing **{$specificRole}**" : 'missing core manpower roles';

            return [
                'reply' => "I scanned all active projects in DEX PMS, and every active project currently has its {$targetText} filled! No new personnel allocations are needed right now.",
                'mode' => 'gemini',
                'connected' => true,
                'action_executed' => false,
            ];
        }

        // Phase 1: Preview all projects with missing personnel and proposed allocations, then ask [Yes] / [No]
        if (! $confirmed) {
            $availablePool = Personnel::query()
                ->whereNull('project_id')
                ->whereNull('deleted_at')
                ->withCount('assignmentHistory')
                ->get();

            $usedPersonnelIds = [];
            /** @var list<array{project_id: int, project_code: string, project_name: string, personnel_id: int, employee_id: string, full_name: string, expertise: string}> $proposedAllocations */
            $proposedAllocations = [];
            /** @var list<string> $projectSummaryLines */
            $projectSummaryLines = [];
            /** @var list<string> $unfilledSummaryLines */
            $unfilledSummaryLines = [];

            foreach ($projectsWithDeficits as $entry) {
                /** @var Project $proj */
                $proj = $entry['project'];
                $missingRoles = $entry['missing_roles'];

                $missingDesc = collect($missingRoles)
                    ->map(fn (int $cnt, string $role) => "{$role} (×{$cnt})")
                    ->implode(', ');

                $projectSummaryLines[] = sprintf(
                    '- **%s — %s** (%s): Missing %s',
                    $proj->project_code,
                    $proj->name,
                    ucfirst((string) $proj->status),
                    $missingDesc,
                );

                foreach ($missingRoles as $roleName => $neededCount) {
                    $candidates = $availablePool
                        ->filter(fn (Personnel $p) => ! in_array((int) $p->id, $usedPersonnelIds, true)
                            && strcasecmp(trim((string) $p->expertise), $roleName) === 0)
                        ->sort(function (Personnel $a, Personnel $b): int {
                            $loadCmp = ($a->assignment_history_count ?? 0) <=> ($b->assignment_history_count ?? 0);
                            if ($loadCmp !== 0) {
                                return $loadCmp;
                            }

                            return strcmp((string) $a->employee_id, (string) $b->employee_id);
                        })
                        ->values();

                    $chosenList = $candidates->take($neededCount);
                    foreach ($chosenList as $chosen) {
                        $usedPersonnelIds[] = (int) $chosen->id;
                        $proposedAllocations[] = [
                            'project_id' => (int) $proj->id,
                            'project_code' => (string) $proj->project_code,
                            'project_name' => (string) $proj->name,
                            'personnel_id' => (int) $chosen->id,
                            'employee_id' => (string) $chosen->employee_id,
                            'full_name' => (string) $chosen->full_name,
                            'expertise' => (string) $chosen->expertise,
                        ];
                    }

                    $shortfall = $neededCount - $chosenList->count();
                    if ($shortfall > 0) {
                        $unfilledSummaryLines[] = sprintf(
                            '- **%s — %s:** %s (%d still needed — 0 available in unassigned pool)',
                            $proj->project_code,
                            $proj->name,
                            $roleName,
                            $shortfall,
                        );
                    }
                }
            }

            if (empty($proposedAllocations)) {
                return [
                    'reply' => sprintf(
                        "I found **%d active project(s)** with missing **%s**:\n\n%s\n\nHowever, there are currently **0 unassigned personnel** in the available manpower pool matching those missing roles. Please release personnel from other projects or add new manpower records first.",
                        count($projectsWithDeficits),
                        $roleLabel,
                        implode("\n", $projectSummaryLines),
                    ),
                    'mode' => 'gemini',
                    'connected' => true,
                    'action_executed' => false,
                ];
            }

            $previewTableRows = collect($proposedAllocations)->map(fn (array $item) => sprintf(
                '| %s | **%s** | %s | Assign to **%s — %s** |',
                $item['employee_id'],
                $item['full_name'],
                $item['expertise'],
                $item['project_code'],
                $item['project_name'],
            ))->implode("\n");

            $affectedProjectsCount = count(array_unique(array_column($proposedAllocations, 'project_id')));

            $previewReply = sprintf(
                "I found **%d active project(s)** with missing **%s**:\n\n%s\n\nI can allocate **%d available personnel** from your unassigned pool across **%d project(s)**:\n\n| Employee ID | Full Name | Expertise Classification | Proposed Allocation |\n| :--- | :--- | :--- | :--- |\n%s",
                count($projectsWithDeficits),
                $roleLabel,
                implode("\n", $projectSummaryLines),
                count($proposedAllocations),
                $affectedProjectsCount,
                $previewTableRows,
            );

            if (! empty($unfilledSummaryLines)) {
                $previewReply .= "\n\n**Remaining Unfilled Roles (Insufficient Unassigned Pool):**\n" . implode("\n", $unfilledSummaryLines);
            }

            $previewReply .= sprintf(
                "\n\nWould you like me to proceed with assigning and allocating these **%d personnel** across the affected projects?",
                count($proposedAllocations),
            );

            return [
                'reply' => $previewReply,
                'mode' => 'confirmation_required',
                'connected' => true,
                'action_executed' => false,
                'pending_action' => [
                    'action_type' => 'allocate_missing_across_projects',
                    'project_code' => "{$affectedProjectsCount} Active Project(s)",
                    'project_name' => 'Portfolio Allocation',
                    'role_filter' => $specificRole,
                    'allocations' => array_map(fn (array $item) => [
                        'project_id' => $item['project_id'],
                        'personnel_id' => $item['personnel_id'],
                    ], $proposedAllocations),
                ],
            ];
        }

        // Phase 2: Confirmed by the user -> execute atomic DB transaction across all target projects
        $userId = $user->id;
        $nowDate = now()->toDateString();
        /** @var list<array{employee_id: string, full_name: string, expertise: string, project_code: string, project_name: string}> $committedAllocations */
        $committedAllocations = [];

        DB::transaction(function () use ($user, $userId, $nowDate, $explicitAllocations, $projectsWithDeficits, &$committedAllocations) {
            $availablePool = Personnel::query()
                ->whereNull('project_id')
                ->whereNull('deleted_at')
                ->withCount('assignmentHistory')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var array<int, Collection<int, Personnel>> $assignedByProject */
            $assignedByProject = [];
            /** @var array<int, Project> $lockedProjectsById */
            $lockedProjectsById = [];

            if (! empty($explicitAllocations)) {
                foreach ($explicitAllocations as $pair) {
                    $projId = isset($pair['project_id']) ? (int) $pair['project_id'] : 0;
                    $persId = isset($pair['personnel_id']) ? (int) $pair['personnel_id'] : 0;
                    if ($projId <= 0 || $persId <= 0 || ! $availablePool->has($persId)) {
                        continue;
                    }

                    if (! isset($lockedProjectsById[$projId])) {
                        $lockedProj = Project::query()->lockForUpdate()->find($projId);
                        if (! $lockedProj || $lockedProj->trashed() || $lockedProj->status === 'completed' || ! Gate::forUser($user)->allows('update', $lockedProj)) {
                            continue;
                        }
                        $lockedProjectsById[$projId] = $lockedProj;
                        $assignedByProject[$projId] = collect();
                    }

                    $lockedProject = $lockedProjectsById[$projId];
                    /** @var Personnel $chosen */
                    $chosen = $availablePool->pull($persId);
                    if ($chosen->project_id !== null) {
                        continue;
                    }

                    $chosen->update([
                        'project_id' => $lockedProject->id,
                        'date_assigned' => $nowDate,
                        'updated_by' => $userId,
                    ]);

                    ProjectPersonnelHistory::create([
                        'project_id' => $lockedProject->id,
                        'personnel_id' => $chosen->id,
                        'assigned_at' => $nowDate,
                        'assigned_by' => $userId,
                    ]);

                    $assignedByProject[$projId]->push($chosen);
                    $committedAllocations[] = [
                        'employee_id' => (string) $chosen->employee_id,
                        'full_name' => (string) $chosen->full_name,
                        'expertise' => (string) $chosen->expertise,
                        'project_code' => (string) $lockedProject->project_code,
                        'project_name' => (string) $lockedProject->name,
                    ];
                }
            } else {
                foreach ($projectsWithDeficits as $entry) {
                    /** @var Project $proj */
                    $proj = $entry['project'];
                    $lockedProject = Project::query()->lockForUpdate()->find($proj->id);
                    if (! $lockedProject || $lockedProject->trashed() || $lockedProject->status === 'completed') {
                        continue;
                    }

                    $lockedProjectsById[$lockedProject->id] = $lockedProject;
                    $assignedByProject[$lockedProject->id] = collect();

                    foreach ($entry['missing_roles'] as $roleName => $neededCount) {
                        $candidates = $availablePool
                            ->filter(fn (Personnel $p) => strcasecmp(trim((string) $p->expertise), $roleName) === 0)
                            ->sort(function (Personnel $a, Personnel $b): int {
                                $loadCmp = ($a->assignment_history_count ?? 0) <=> ($b->assignment_history_count ?? 0);
                                if ($loadCmp !== 0) {
                                    return $loadCmp;
                                }

                                return strcmp((string) $a->employee_id, (string) $b->employee_id);
                            })
                            ->values();

                        $selected = $candidates->take($neededCount);
                        foreach ($selected as $chosen) {
                            $availablePool->forget($chosen->id);

                            $chosen->update([
                                'project_id' => $lockedProject->id,
                                'date_assigned' => $nowDate,
                                'updated_by' => $userId,
                            ]);

                            ProjectPersonnelHistory::create([
                                'project_id' => $lockedProject->id,
                                'personnel_id' => $chosen->id,
                                'assigned_at' => $nowDate,
                                'assigned_by' => $userId,
                            ]);

                            $assignedByProject[$lockedProject->id]->push($chosen);
                            $committedAllocations[] = [
                                'employee_id' => (string) $chosen->employee_id,
                                'full_name' => (string) $chosen->full_name,
                                'expertise' => (string) $chosen->expertise,
                                'project_code' => (string) $lockedProject->project_code,
                                'project_name' => (string) $lockedProject->name,
                            ];
                        }
                    }
                }
            }

            $anyCommitted = false;
            foreach ($assignedByProject as $projId => $assignedCollection) {
                if ($assignedCollection->isEmpty() || ! isset($lockedProjectsById[$projId])) {
                    continue;
                }
                $anyCommitted = true;
                $lockedProject = $lockedProjectsById[$projId];
                $count = $assignedCollection->count();
                $manpowerSummary = $assignedCollection
                    ->map(fn (Personnel $p) => "{$p->employee_id} ({$p->full_name} - {$p->expertise})")
                    ->implode(', ');

                ProjectStatusHistory::create([
                    'project_id' => $lockedProject->id,
                    'from_status' => $lockedProject->status,
                    'to_status' => $lockedProject->status,
                    'notes' => $count === 1
                        ? "Assigned manpower: {$manpowerSummary}"
                        : "Assigned {$count} manpower: {$manpowerSummary}",
                    'changed_by' => $userId,
                    'changed_at' => now(),
                ]);

                app(NotificationService::class)->notifyManpowerAssigned($lockedProject, $assignedCollection);
            }

            if ($anyCommitted) {
                app(LiveUpdateService::class)->bump('resources');
            }
        });

        if (empty($committedAllocations)) {
            return [
                'reply' => 'No personnel could be allocated because the selected personnel are no longer available in the unassigned pool.',
                'mode' => 'gemini',
                'connected' => true,
                'action_executed' => false,
            ];
        }

        $tableRows = collect($committedAllocations)->map(fn (array $item) => sprintf(
            '| %s | **%s** | %s | Assigned to **%s — %s** |',
            $item['employee_id'],
            $item['full_name'],
            $item['expertise'],
            $item['project_code'],
            $item['project_name'],
        ))->implode("\n");

        $affectedProjectsCount = count(array_unique(array_column($committedAllocations, 'project_code')));

        return [
            'reply' => sprintf(
                "Confirmed! I have assigned and allocated **%d available personnel** across **%d project(s)** with missing **%s**:\n\n| Employee ID | Full Name | Expertise Classification | Status |\n| :--- | :--- | :--- | :--- |\n%s",
                count($committedAllocations),
                $affectedProjectsCount,
                $roleLabel,
                $tableRows,
            ),
            'mode' => 'gemini',
            'connected' => true,
            'action_executed' => true,
            'action_type' => 'allocate_missing_across_projects',
        ];
    }

    /**
     * Execute (or preview for confirmation) filling missing/necessary manpower roles for a target project.
     * Strictly uses the same transaction, history, status log, live update, and notification flow as the backend controllers.
     *
     * @param string|null $projectQuery
     * @param string $fullMessage
     * @param int|null $selectedProjectId
     * @param bool $confirmed
     * @param list<int>|null $explicitPersonnelIds
     * @return array{reply: string, mode: string, connected: bool, action_executed: bool, action_type?: string, project_id?: int, pending_action?: array<string, mixed>}
     */
    public function executeFillMissingManpower(
        ?string $projectQuery,
        string $fullMessage,
        ?int $selectedProjectId,
        bool $confirmed = false,
        ?array $explicitPersonnelIds = null,
    ): array {
        if (! $this->canCurrentUserManageManpower()) {
            return [
                'reply' => self::STAFF_MANPOWER_DENIED_REPLY,
                'mode' => 'guardrail',
                'connected' => true,
                'action_executed' => false,
            ];
        }

        $resolved = $this->resolveTargetProject($projectQuery, $fullMessage, $selectedProjectId);
        if ($resolved['error'] !== null || ! $resolved['project']) {
            return [
                'reply' => (string) ($resolved['error'] ?? 'Could not determine the target project.'),
                'mode' => 'gemini',
                'connected' => true,
                'action_executed' => false,
            ];
        }

        /** @var Project $project */
        $project = $resolved['project'];
        $user = Auth::user();

        if (! $user || ! Gate::forUser($user)->allows('update', $project)) {
            return [
                'reply' => self::STAFF_MANPOWER_DENIED_REPLY,
                'mode' => 'guardrail',
                'connected' => true,
                'action_executed' => false,
            ];
        }

        if ($project->trashed() || $project->status === 'completed') {
            return [
                'reply' => sprintf(
                    'I cannot assign manpower to **%s — %s** because this project is already marked as **Completed**.',
                    $project->project_code,
                    $project->name,
                ),
                'mode' => 'gemini',
                'connected' => true,
                'action_executed' => false,
            ];
        }

        // Determine required roles based on core project roles and project elevator specifications
        $stops = max(2, (int) ($project->no_of_stops ?: 4));
        preg_match('/\d+/', (string) ($project->rated_capacity ?: '800'), $capMatches);
        $capacityKg = isset($capMatches[0]) ? (int) $capMatches[0] : 800;
        $projType = strtolower((string) ($project->project_type ?: 'Standard Elevator'));

        $techCount = 1;
        $foremanCount = 1;
        if ($stops >= 12 || $capacityKg >= 1350 || str_contains($projType, 'freight')) {
            $techCount = 2;
            $foremanCount = 2;
        } elseif ($stops >= 6 || $capacityKg >= 1000) {
            $techCount = 2;
            $foremanCount = 1;
        }

        $requiredRoleCounts = [
            Personnel::EXPERTISE_PROJECT_MANAGER => 1,
            Personnel::EXPERTISE_SITE_ENGINEER => 1,
            Personnel::EXPERTISE_FOREMAN => $foremanCount,
            Personnel::EXPERTISE_SAFETY_OFFICER => 1,
            Personnel::EXPERTISE_TECHNICIAN => $techCount,
        ];

        $currentAssigned = Personnel::query()
            ->where('project_id', $project->id)
            ->whereNull('deleted_at')
            ->get();

        $missingRoleCounts = [];
        foreach ($requiredRoleCounts as $roleName => $reqCount) {
            $existingCount = $currentAssigned
                ->filter(fn (Personnel $p) => strcasecmp(trim((string) $p->expertise), $roleName) === 0)
                ->count();
            $deficit = max(0, $reqCount - $existingCount);
            if ($deficit > 0) {
                $missingRoleCounts[$roleName] = $deficit;
            }
        }

        if (empty($missingRoleCounts)) {
            $crewRows = $currentAssigned->map(fn (Personnel $p) => sprintf(
                '| %s | **%s** | %s |',
                $p->employee_id,
                $p->full_name,
                $p->expertise,
            ))->implode("\n");

            return [
                'reply' => sprintf(
                    "All necessary core manpower roles for **%s — %s** are already filled! No additional assignments were needed.\n\n| Employee ID | Full Name | Expertise Classification |\n| :--- | :--- | :--- |\n%s",
                    $project->project_code,
                    $project->name,
                    $crewRows,
                ),
                'mode' => 'gemini',
                'connected' => true,
                'action_executed' => false,
            ];
        }

        // Phase 1: If not yet confirmed by the user, preview the candidate selection and ask [Yes] / [No]
        if (! $confirmed) {
            $availablePool = Personnel::query()
                ->whereNull('project_id')
                ->whereNull('deleted_at')
                ->withCount('assignmentHistory')
                ->get();

            /** @var Collection<int, Personnel> $previewSelected */
            $previewSelected = collect();
            $previewShortfalls = [];

            foreach ($missingRoleCounts as $roleName => $neededCount) {
                $candidates = $availablePool
                    ->filter(fn (Personnel $p) => strcasecmp(trim((string) $p->expertise), $roleName) === 0)
                    ->sort(function (Personnel $a, Personnel $b): int {
                        $loadCmp = ($a->assignment_history_count ?? 0) <=> ($b->assignment_history_count ?? 0);
                        if ($loadCmp !== 0) {
                            return $loadCmp;
                        }

                        return strcmp((string) $a->employee_id, (string) $b->employee_id);
                    })
                    ->values();

                $chosenList = $candidates->take($neededCount);
                foreach ($chosenList as $chosen) {
                    $previewSelected->push($chosen);
                }

                $shortfall = $neededCount - $chosenList->count();
                if ($shortfall > 0) {
                    $previewShortfalls[$roleName] = $shortfall;
                }
            }

            if ($previewSelected->isEmpty()) {
                $missingList = collect($previewShortfalls)
                    ->map(fn (int $cnt, string $role) => "- **{$role}:** {$cnt} needed (0 available in unassigned pool)")
                    ->implode("\n");

                return [
                    'reply' => sprintf(
                        "I checked the available manpower pool for **%s — %s**, but there are currently **0 unassigned personnel** matching the project's missing core roles:\n\n%s\n\nPlease release personnel from completed/other projects or add new manpower records first.",
                        $project->project_code,
                        $project->name,
                        $missingList,
                    ),
                    'mode' => 'gemini',
                    'connected' => true,
                    'action_executed' => false,
                ];
            }

            $previewTableRows = $previewSelected->map(fn (Personnel $p) => sprintf(
                '| %s | **%s** | %s | Assign to %s |',
                $p->employee_id,
                $p->full_name,
                $p->expertise,
                $project->project_code,
            ))->implode("\n");

            $previewReply = sprintf(
                "I found **%d available manpower** to fill the necessary roles for **%s — %s**:\n\n| Employee ID | Full Name | Expertise Classification | Proposed Action |\n| :--- | :--- | :--- | :--- |\n%s",
                $previewSelected->count(),
                $project->project_code,
                $project->name,
                $previewTableRows,
            );

            if (! empty($previewShortfalls)) {
                $shortfallLines = collect($previewShortfalls)
                    ->map(fn (int $cnt, string $role) => "- **{$role}:** {$cnt} still needed (no unassigned personnel currently available)")
                    ->implode("\n");
                $previewReply .= "\n\n**Note on Remaining Role Shortages:**\n" . $shortfallLines;
            }

            $previewReply .= sprintf(
                "\n\nWould you like me to proceed with assigning these personnel to **%s — %s**?",
                $project->project_code,
                $project->name,
            );

            return [
                'reply' => $previewReply,
                'mode' => 'confirmation_required',
                'connected' => true,
                'action_executed' => false,
                'pending_action' => [
                    'action_type' => 'fill_missing_manpower',
                    'project_id' => $project->id,
                    'project_code' => $project->project_code,
                    'project_name' => $project->name,
                    'personnel_ids' => $previewSelected->pluck('id')->values()->all(),
                    'personnel_names' => $previewSelected->pluck('full_name')->values()->all(),
                ],
            ];
        }

        // Phase 2: Confirmed by the user -> execute atomic DB transaction
        $userId = $user->id;
        $nowDate = now()->toDateString();
        /** @var Collection<int, Personnel> $newlyAssigned */
        $newlyAssigned = collect();
        $unfilledRoles = [];

        DB::transaction(function () use ($project, $missingRoleCounts, $explicitPersonnelIds, $userId, $nowDate, &$newlyAssigned, &$unfilledRoles) {
            /** @var Project $lockedProject */
            $lockedProject = Project::query()->lockForUpdate()->findOrFail($project->id);
            if ($lockedProject->status === 'completed') {
                return;
            }

            $availablePool = Personnel::query()
                ->whereNull('project_id')
                ->whereNull('deleted_at')
                ->withCount('assignmentHistory')
                ->lockForUpdate()
                ->get();

            if (! empty($explicitPersonnelIds)) {
                $selectedFromExplicit = $availablePool->whereIn('id', $explicitPersonnelIds)->values();
                foreach ($selectedFromExplicit as $chosen) {
                    $chosen->update([
                        'project_id' => $lockedProject->id,
                        'date_assigned' => $nowDate,
                        'updated_by' => $userId,
                    ]);

                    ProjectPersonnelHistory::create([
                        'project_id' => $lockedProject->id,
                        'personnel_id' => $chosen->id,
                        'assigned_at' => $nowDate,
                        'assigned_by' => $userId,
                    ]);

                    $newlyAssigned->push($chosen);
                }

                foreach ($missingRoleCounts as $roleName => $neededCount) {
                    $filledForRole = $newlyAssigned
                        ->filter(fn (Personnel $p) => strcasecmp(trim((string) $p->expertise), $roleName) === 0)
                        ->count();
                    $shortfall = max(0, $neededCount - $filledForRole);
                    if ($shortfall > 0) {
                        $unfilledRoles[$roleName] = $shortfall;
                    }
                }
            } else {
                foreach ($missingRoleCounts as $roleName => $neededCount) {
                    $candidates = $availablePool
                        ->filter(fn (Personnel $p) => strcasecmp(trim((string) $p->expertise), $roleName) === 0)
                        ->sort(function (Personnel $a, Personnel $b): int {
                            $loadCmp = ($a->assignment_history_count ?? 0) <=> ($b->assignment_history_count ?? 0);
                            if ($loadCmp !== 0) {
                                return $loadCmp;
                            }

                            return strcmp((string) $a->employee_id, (string) $b->employee_id);
                        })
                        ->values();

                    $selected = $candidates->take($neededCount);
                    foreach ($selected as $chosen) {
                        $chosen->update([
                            'project_id' => $lockedProject->id,
                            'date_assigned' => $nowDate,
                            'updated_by' => $userId,
                        ]);

                        ProjectPersonnelHistory::create([
                            'project_id' => $lockedProject->id,
                            'personnel_id' => $chosen->id,
                            'assigned_at' => $nowDate,
                            'assigned_by' => $userId,
                        ]);

                        $newlyAssigned->push($chosen);
                    }

                    $shortfall = $neededCount - $selected->count();
                    if ($shortfall > 0) {
                        $unfilledRoles[$roleName] = $shortfall;
                    }
                }
            }

            if ($newlyAssigned->isNotEmpty()) {
                $count = $newlyAssigned->count();
                $manpowerSummary = $newlyAssigned
                    ->map(fn (Personnel $p) => "{$p->employee_id} ({$p->full_name} - {$p->expertise})")
                    ->implode(', ');

                ProjectStatusHistory::create([
                    'project_id' => $lockedProject->id,
                    'from_status' => $lockedProject->status,
                    'to_status' => $lockedProject->status,
                    'notes' => $count === 1
                        ? "Assigned manpower: {$manpowerSummary}"
                        : "Assigned {$count} manpower: {$manpowerSummary}",
                    'changed_by' => $userId,
                    'changed_at' => now(),
                ]);

                app(LiveUpdateService::class)->bump('resources');
                app(NotificationService::class)->notifyManpowerAssigned($lockedProject, $newlyAssigned);
            }
        });

        if ($newlyAssigned->isEmpty()) {
            $missingList = collect($unfilledRoles)
                ->map(fn (int $cnt, string $role) => "- **{$role}:** {$cnt} needed (0 available in unassigned pool)")
                ->implode("\n");

            return [
                'reply' => sprintf(
                    "I checked the available manpower pool for **%s — %s**, but there are currently **0 unassigned personnel** matching the project's missing core roles:\n\n%s\n\nPlease release personnel from completed/other projects or add new manpower records first.",
                    $project->project_code,
                    $project->name,
                    $missingList,
                ),
                'mode' => 'gemini',
                'connected' => true,
                'action_executed' => false,
            ];
        }

        $tableRows = $newlyAssigned->map(fn (Personnel $p) => sprintf(
            '| %s | **%s** | %s | Assigned to %s |',
            $p->employee_id,
            $p->full_name,
            $p->expertise,
            $project->project_code,
        ))->implode("\n");

        $reply = sprintf(
            "Confirmed! I have filled and assigned **%d necessary manpower** to **%s — %s**:\n\n| Employee ID | Full Name | Expertise Classification | Status |\n| :--- | :--- | :--- | :--- |\n%s",
            $newlyAssigned->count(),
            $project->project_code,
            $project->name,
            $tableRows,
        );

        if (! empty($unfilledRoles)) {
            $shortfallLines = collect($unfilledRoles)
                ->map(fn (int $cnt, string $role) => "- **{$role}:** {$cnt} still needed (no unassigned personnel currently available)")
                ->implode("\n");
            $reply .= "\n\n**Remaining Role Shortages:**\n" . $shortfallLines;
        }

        return [
            'reply' => $reply,
            'mode' => 'gemini',
            'connected' => true,
            'action_executed' => true,
            'action_type' => 'fill_missing_manpower',
            'project_id' => $project->id,
        ];
    }

    /**
     * Execute (or preview for confirmation) assigning a specific personnel (or expertise role) to a target project.
     * Prevents conflicts by refusing to steal personnel already assigned to another active project.
     *
     * @param string $personnelQuery
     * @param string|null $projectQuery
     * @param string $fullMessage
     * @param int|null $selectedProjectId
     * @param bool $confirmed
     * @param list<int>|null $explicitPersonnelIds
     * @return array{reply: string, mode: string, connected: bool, action_executed: bool, action_type?: string, project_id?: int, pending_action?: array<string, mixed>}
     */
    public function executeAssignPersonnel(
        string $personnelQuery,
        ?string $projectQuery,
        string $fullMessage,
        ?int $selectedProjectId,
        bool $confirmed = false,
        ?array $explicitPersonnelIds = null,
    ): array {
        if (! $this->canCurrentUserManageManpower()) {
            return [
                'reply' => self::STAFF_MANPOWER_DENIED_REPLY,
                'mode' => 'guardrail',
                'connected' => true,
                'action_executed' => false,
            ];
        }

        $resolved = $this->resolveTargetProject($projectQuery, $fullMessage, $selectedProjectId);
        if ($resolved['error'] !== null || ! $resolved['project']) {
            return [
                'reply' => (string) ($resolved['error'] ?? 'Could not determine the target project.'),
                'mode' => 'gemini',
                'connected' => true,
                'action_executed' => false,
            ];
        }

        /** @var Project $project */
        $project = $resolved['project'];
        $user = Auth::user();

        if (! $user || ! Gate::forUser($user)->allows('update', $project)) {
            return [
                'reply' => self::STAFF_MANPOWER_DENIED_REPLY,
                'mode' => 'guardrail',
                'connected' => true,
                'action_executed' => false,
            ];
        }

        if ($project->trashed() || $project->status === 'completed') {
            return [
                'reply' => sprintf(
                    'I cannot assign personnel to **%s — %s** because this project is already marked as **Completed**.',
                    $project->project_code,
                    $project->name,
                ),
                'mode' => 'gemini',
                'connected' => true,
                'action_executed' => false,
            ];
        }

        $cleanPersonnelQuery = trim($personnelQuery, " \t\n\r\0\x0B.!?\"'");
        $matchedPersonnel = ! empty($explicitPersonnelIds)
            ? Personnel::query()->with('project')->whereIn('id', $explicitPersonnelIds)->whereNull('deleted_at')->get()
            : $this->findMatchingPersonnel($cleanPersonnelQuery);

        if ($matchedPersonnel->isEmpty()) {
            return [
                'reply' => sprintf(
                    'I could not find any personnel matching **"%s"** in the DEX PMS manpower records. Please verify their full name or Employee ID (e.g., `EMP-2026XXX`), or ask me **"Who are my available manpower?"** to see all unassigned staff.',
                    $cleanPersonnelQuery,
                ),
                'mode' => 'gemini',
                'connected' => true,
                'action_executed' => false,
            ];
        }

        // Check for conflicts before opening transaction
        $alreadyOnTarget = collect();
        $conflictingOnOther = collect();
        $readyToAssign = collect();

        foreach ($matchedPersonnel as $person) {
            if ($person->project_id !== null && (int) $person->project_id === (int) $project->id) {
                $alreadyOnTarget->push($person);
            } elseif ($person->project_id !== null && (int) $person->project_id !== (int) $project->id) {
                $conflictingOnOther->push($person);
            } else {
                $readyToAssign->push($person);
            }
        }

        if ($readyToAssign->isEmpty()) {
            if ($conflictingOnOther->isNotEmpty()) {
                /** @var Personnel $conflictPerson */
                $conflictPerson = $conflictingOnOther->first();
                $otherProjLabel = $conflictPerson->project
                    ? "{$conflictPerson->project->project_code} — {$conflictPerson->project->name}"
                    : "Project ID #{$conflictPerson->project_id}";

                return [
                    'reply' => sprintf(
                        'I cannot assign **%s** (`%s` — %s) to **%s — %s** because they are currently assigned to **%s**. To prevent assignment conflicts, please remove their assignment from that project first.',
                        $conflictPerson->full_name,
                        $conflictPerson->employee_id,
                        $conflictPerson->expertise,
                        $project->project_code,
                        $project->name,
                        $otherProjLabel,
                    ),
                    'mode' => 'gemini',
                    'connected' => true,
                    'action_executed' => false,
                ];
            }

            /** @var Personnel $existingPerson */
            $existingPerson = $alreadyOnTarget->first();

            return [
                'reply' => sprintf(
                    '**%s** (`%s` — %s) is already assigned to **%s — %s**.',
                    $existingPerson->full_name,
                    $existingPerson->employee_id,
                    $existingPerson->expertise,
                    $project->project_code,
                    $project->name,
                ),
                'mode' => 'gemini',
                'connected' => true,
                'action_executed' => false,
            ];
        }

        // Phase 1: If not yet confirmed by the user, show preview table and ask [Yes] / [No]
        if (! $confirmed) {
            $previewRows = $readyToAssign->map(fn (Personnel $p) => sprintf(
                '| %s | **%s** | %s | Assign to %s |',
                $p->employee_id,
                $p->full_name,
                $p->expertise,
                $project->project_code,
            ))->implode("\n");

            return [
                'reply' => sprintf(
                    "I am ready to assign **%s** to **%s — %s**:\n\n| Employee ID | Full Name | Expertise Classification | Proposed Action |\n| :--- | :--- | :--- | :--- |\n%s\n\nWould you like me to proceed with assigning this personnel to **%s — %s**?",
                    $readyToAssign->pluck('full_name')->implode(', '),
                    $project->project_code,
                    $project->name,
                    $previewRows,
                    $project->project_code,
                    $project->name,
                ),
                'mode' => 'confirmation_required',
                'connected' => true,
                'action_executed' => false,
                'pending_action' => [
                    'action_type' => 'assign_personnel',
                    'project_id' => $project->id,
                    'project_code' => $project->project_code,
                    'project_name' => $project->name,
                    'personnel_ids' => $readyToAssign->pluck('id')->values()->all(),
                    'personnel_query' => $cleanPersonnelQuery,
                    'personnel_names' => $readyToAssign->pluck('full_name')->values()->all(),
                ],
            ];
        }

        // Phase 2: Confirmed by the user -> execute atomic DB transaction
        $userId = $user->id;
        $nowDate = now()->toDateString();
        /** @var Collection<int, Personnel> $assignedRecords */
        $assignedRecords = collect();

        DB::transaction(function () use ($project, $readyToAssign, $userId, $nowDate, &$assignedRecords) {
            /** @var Project $lockedProject */
            $lockedProject = Project::query()->lockForUpdate()->findOrFail($project->id);
            if ($lockedProject->status === 'completed') {
                return;
            }

            $lockedPersonnel = Personnel::query()
                ->whereIn('id', $readyToAssign->pluck('id')->all())
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->get();

            foreach ($lockedPersonnel as $person) {
                // Re-verify unassigned status inside lock to prevent race conditions
                if ($person->project_id !== null) {
                    continue;
                }

                $person->update([
                    'project_id' => $lockedProject->id,
                    'date_assigned' => $nowDate,
                    'updated_by' => $userId,
                ]);

                ProjectPersonnelHistory::create([
                    'project_id' => $lockedProject->id,
                    'personnel_id' => $person->id,
                    'assigned_at' => $nowDate,
                    'assigned_by' => $userId,
                ]);

                $assignedRecords->push($person);
            }

            if ($assignedRecords->isNotEmpty()) {
                $count = $assignedRecords->count();
                $manpowerSummary = $assignedRecords
                    ->map(fn (Personnel $p) => "{$p->employee_id} ({$p->full_name} - {$p->expertise})")
                    ->implode(', ');

                ProjectStatusHistory::create([
                    'project_id' => $lockedProject->id,
                    'from_status' => $lockedProject->status,
                    'to_status' => $lockedProject->status,
                    'notes' => $count === 1
                        ? "Assigned manpower: {$manpowerSummary}"
                        : "Assigned {$count} manpower: {$manpowerSummary}",
                    'changed_by' => $userId,
                    'changed_at' => now(),
                ]);

                app(LiveUpdateService::class)->bump('resources');
                app(NotificationService::class)->notifyManpowerAssigned($lockedProject, $assignedRecords);
            }
        });

        $tableRows = $assignedRecords->map(fn (Personnel $p) => sprintf(
            '| %s | **%s** | %s | Assigned to %s |',
            $p->employee_id,
            $p->full_name,
            $p->expertise,
            $project->project_code,
        ))->implode("\n");

        return [
            'reply' => sprintf(
                "Confirmed! Successfully assigned **%s** to **%s — %s**:\n\n| Employee ID | Full Name | Expertise Classification | Status |\n| :--- | :--- | :--- | :--- |\n%s",
                $assignedRecords->pluck('full_name')->implode(', '),
                $project->project_code,
                $project->name,
                $tableRows,
            ),
            'mode' => 'gemini',
            'connected' => true,
            'action_executed' => true,
            'action_type' => 'assign_personnel',
            'project_id' => $project->id,
        ];
    }

    /**
     * Execute (or preview for confirmation) removing/unassigning a specific personnel from a target project.
     * Matches `ProjectController::unassignPersonnel()` transaction, history release, and status history logging.
     *
     * @param string $personnelQuery
     * @param string|null $projectQuery
     * @param string $fullMessage
     * @param int|null $selectedProjectId
     * @param bool $confirmed
     * @param list<int>|null $explicitPersonnelIds
     * @return array{reply: string, mode: string, connected: bool, action_executed: bool, action_type?: string, project_id?: int, pending_action?: array<string, mixed>}
     */
    public function executeRemovePersonnel(
        string $personnelQuery,
        ?string $projectQuery,
        string $fullMessage,
        ?int $selectedProjectId,
        bool $confirmed = false,
        ?array $explicitPersonnelIds = null,
    ): array {
        if (! $this->canCurrentUserManageManpower()) {
            return [
                'reply' => self::STAFF_MANPOWER_DENIED_REPLY,
                'mode' => 'guardrail',
                'connected' => true,
                'action_executed' => false,
            ];
        }

        $resolved = $this->resolveTargetProject($projectQuery, $fullMessage, $selectedProjectId);
        if ($resolved['error'] !== null || ! $resolved['project']) {
            return [
                'reply' => (string) ($resolved['error'] ?? 'Could not determine the target project.'),
                'mode' => 'gemini',
                'connected' => true,
                'action_executed' => false,
            ];
        }

        /** @var Project $project */
        $project = $resolved['project'];
        $user = Auth::user();

        if (! $user || ! Gate::forUser($user)->allows('update', $project)) {
            return [
                'reply' => self::STAFF_MANPOWER_DENIED_REPLY,
                'mode' => 'guardrail',
                'connected' => true,
                'action_executed' => false,
            ];
        }

        $cleanPersonnelQuery = trim($personnelQuery, " \t\n\r\0\x0B.!?\"'");
        $matchedPersonnel = ! empty($explicitPersonnelIds)
            ? Personnel::query()->with('project')->whereIn('id', $explicitPersonnelIds)->whereNull('deleted_at')->get()
            : $this->findMatchingPersonnel($cleanPersonnelQuery);

        if ($matchedPersonnel->isEmpty()) {
            return [
                'reply' => sprintf(
                    'I could not find any personnel matching **"%s"** in the DEX PMS manpower records. Please verify their full name or Employee ID (e.g., `EMP-2026XXX`).',
                    $cleanPersonnelQuery,
                ),
                'mode' => 'gemini',
                'connected' => true,
                'action_executed' => false,
            ];
        }

        $assignedOnTarget = $matchedPersonnel->filter(
            fn (Personnel $p) => $p->project_id !== null && (int) $p->project_id === (int) $project->id,
        )->values();

        if ($assignedOnTarget->isEmpty()) {
            /** @var Personnel $firstPerson */
            $firstPerson = $matchedPersonnel->first();
            if ($firstPerson->project_id === null) {
                return [
                    'reply' => sprintf(
                        '**%s** (`%s` — %s) is currently **Unassigned** and is not assigned to **%s — %s**.',
                        $firstPerson->full_name,
                        $firstPerson->employee_id,
                        $firstPerson->expertise,
                        $project->project_code,
                        $project->name,
                    ),
                    'mode' => 'gemini',
                    'connected' => true,
                    'action_executed' => false,
                ];
            }

            $otherLabel = $firstPerson->project
                ? "{$firstPerson->project->project_code} — {$firstPerson->project->name}"
                : "Project ID #{$firstPerson->project_id}";

            return [
                'reply' => sprintf(
                    '**%s** (`%s` — %s) is not assigned to **%s — %s** (they are currently assigned to **%s**).',
                    $firstPerson->full_name,
                    $firstPerson->employee_id,
                    $firstPerson->expertise,
                    $project->project_code,
                    $project->name,
                    $otherLabel,
                ),
                'mode' => 'gemini',
                'connected' => true,
                'action_executed' => false,
            ];
        }

        // Phase 1: If not yet confirmed by the user, show preview table and ask [Yes] / [No]
        if (! $confirmed) {
            $previewRows = $assignedOnTarget->map(fn (Personnel $p) => sprintf(
                '| %s | **%s** | %s | Remove from %s |',
                $p->employee_id,
                $p->full_name,
                $p->expertise,
                $project->project_code,
            ))->implode("\n");

            return [
                'reply' => sprintf(
                    "I am ready to remove the assignment of **%s** from **%s — %s**:\n\n| Employee ID | Full Name | Expertise Classification | Proposed Action |\n| :--- | :--- | :--- | :--- |\n%s\n\nWould you like me to proceed with unassigning this personnel from **%s — %s**?",
                    $assignedOnTarget->pluck('full_name')->implode(', '),
                    $project->project_code,
                    $project->name,
                    $previewRows,
                    $project->project_code,
                    $project->name,
                ),
                'mode' => 'confirmation_required',
                'connected' => true,
                'action_executed' => false,
                'pending_action' => [
                    'action_type' => 'remove_personnel',
                    'project_id' => $project->id,
                    'project_code' => $project->project_code,
                    'project_name' => $project->name,
                    'personnel_ids' => $assignedOnTarget->pluck('id')->values()->all(),
                    'personnel_query' => $cleanPersonnelQuery,
                    'personnel_names' => $assignedOnTarget->pluck('full_name')->values()->all(),
                ],
            ];
        }

        // Phase 2: Confirmed by the user -> execute atomic DB transaction
        $userId = $user->id;
        $nowDate = now()->toDateString();
        /** @var Collection<int, Personnel> $removedRecords */
        $removedRecords = collect();

        DB::transaction(function () use ($project, $assignedOnTarget, $userId, $nowDate, &$removedRecords) {
            /** @var Project $lockedProject */
            $lockedProject = Project::query()->lockForUpdate()->findOrFail($project->id);

            $lockedPersonnel = Personnel::query()
                ->whereIn('id', $assignedOnTarget->pluck('id')->all())
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->get();

            foreach ($lockedPersonnel as $person) {
                if ((int) $person->project_id !== (int) $lockedProject->id) {
                    continue;
                }

                $person->update([
                    'project_id' => null,
                    'date_assigned' => null,
                    'updated_by' => $userId,
                ]);

                ProjectPersonnelHistory::where('project_id', $lockedProject->id)
                    ->where('personnel_id', $person->id)
                    ->whereNull('released_at')
                    ->update([
                        'released_at' => $nowDate,
                        'release_reason' => 'unassigned',
                        'released_by' => $userId,
                    ]);

                ProjectStatusHistory::create([
                    'project_id' => $lockedProject->id,
                    'from_status' => $lockedProject->status,
                    'to_status' => $lockedProject->status,
                    'notes' => "Unassigned manpower: {$person->employee_id} ({$person->full_name} - {$person->expertise})",
                    'changed_by' => $userId,
                    'changed_at' => now(),
                ]);

                $removedRecords->push($person);
            }

            if ($removedRecords->isNotEmpty()) {
                app(LiveUpdateService::class)->bump('resources');
            }
        });

        $tableRows = $removedRecords->map(fn (Personnel $p) => sprintf(
            '| %s | **%s** | %s | Available (Unassigned) |',
            $p->employee_id,
            $p->full_name,
            $p->expertise,
        ))->implode("\n");

        return [
            'reply' => sprintf(
                "Confirmed! Successfully removed the assignment of **%s** from **%s — %s**:\n\n| Employee ID | Full Name | Expertise Classification | New Status |\n| :--- | :--- | :--- | :--- |\n%s",
                $removedRecords->pluck('full_name')->implode(', '),
                $project->project_code,
                $project->name,
                $tableRows,
            ),
            'mode' => 'gemini',
            'connected' => true,
            'action_executed' => true,
            'action_type' => 'remove_personnel',
            'project_id' => $project->id,
        ];
    }

    /**
     * Resolve the target Project from an optional project query string, full message, or active selectedProjectId.
     *
     * @param string|null $projectQuery
     * @param string $fullMessage
     * @param int|null $selectedProjectId
     * @return array{project: Project|null, error: string|null}
     */
    private function resolveTargetProject(?string $projectQuery, string $fullMessage, ?int $selectedProjectId): array
    {
        $projects = Project::query()
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->get();

        if ($projects->isEmpty()) {
            return [
                'project' => null,
                'error' => 'There are currently no active projects in DEX PMS.',
            ];
        }

        $cleanQuery = strtolower(trim((string) $projectQuery, " \t\n\r\0\x0B.!?\"'"));
        $isContextPlaceholder = $cleanQuery === ''
            || in_array($cleanQuery, [
                'this project',
                'the project',
                'current project',
                'selected project',
                'active project',
                'our project',
                'my project',
                'project x',
                'this project x',
                'my project x',
                'here',
            ], true);

        // 1. Check if a specific project code or name was explicitly written in $projectQuery or $fullMessage
        $searchTarget = ! $isContextPlaceholder ? $cleanQuery : strtolower($fullMessage);

        // Sort by project_code length descending to avoid partial prefix ambiguity
        foreach ($projects->sortByDesc(fn (Project $p) => strlen((string) $p->project_code)) as $proj) {
            $code = strtolower(trim((string) $proj->project_code));
            if ($code !== '' && str_contains($searchTarget, $code)) {
                return ['project' => $proj, 'error' => null];
            }
        }

        // Check project name match if not a generic placeholder
        if (! $isContextPlaceholder) {
            foreach ($projects->sortByDesc(fn (Project $p) => strlen((string) $p->name)) as $proj) {
                $name = strtolower(trim((string) $proj->name));
                if ($name !== '' && (str_contains($cleanQuery, $name) || str_contains($name, preg_replace('/^project\s+/i', '', $cleanQuery)))) {
                    return ['project' => $proj, 'error' => null];
                }
            }

            return [
                'project' => null,
                'error' => sprintf(
                    'I could not find any project matching **"%s"** in DEX PMS. Please check the project code or select a project from the **Project Context** dropdown.',
                    $projectQuery,
                ),
            ];
        }

        // 2. Use active selectedProjectId from the Kuya DEX Project Context dropdown
        $activeProject = $selectedProjectId
            ? ($projects->firstWhere('id', $selectedProjectId) ?? $projects->first())
            : $projects->first();

        return [
            'project' => $activeProject,
            'error' => null,
        ];
    }

    /**
     * Find matching non-deleted Personnel records by full name, first+last name, or employee ID.
     *
     * @param string $query
     * @return Collection<int, Personnel>
     */
    private function findMatchingPersonnel(string $query): Collection
    {
        $clean = trim($query, " \t\n\r\0\x0B.!?\"'");
        $clean = preg_replace('/^(?:the\s+)?(?:assignment\s+of\s+|personnel\s+|manpower\s+|employee\s+|worker\s+|staff\s+)/i', '', $clean) ?? $clean;
        $clean = trim($clean);
        if ($clean === '') {
            return collect();
        }

        $allPersonnel = Personnel::query()
            ->with('project')
            ->whereNull('deleted_at')
            ->orderBy('employee_id')
            ->get();

        // Split by "and" or comma if multiple names were provided
        $tokens = preg_split('/\s*(?:,|\band\b)\s*/i', $clean) ?: [$clean];
        $matched = collect();

        foreach ($tokens as $token) {
            $needle = strtolower(trim((string) preg_replace('/\s+/', ' ', $token)));
            if ($needle === '') {
                continue;
            }

            // 1. Exact Employee ID match
            $exactId = $allPersonnel->first(
                fn (Personnel $p) => strtolower(trim((string) $p->employee_id)) === $needle,
            );
            if ($exactId) {
                $matched->push($exactId);
                continue;
            }

            // 2. Exact Full Name or First+Last Name match
            $exactName = $allPersonnel->first(function (Personnel $p) use ($needle): bool {
                $full = strtolower(trim((string) preg_replace('/\s+/', ' ', $p->full_name)));
                $firstLast = strtolower(trim((string) preg_replace('/\s+/', ' ', "{$p->first_name} {$p->last_name}")));

                return $full === $needle || $firstLast === $needle;
            });
            if ($exactName) {
                $matched->push($exactName);
                continue;
            }

            // 3. Partial / Substring match on Employee ID or Full Name (if unambiguous or clear match)
            $partial = $allPersonnel->filter(function (Personnel $p) use ($needle): bool {
                $empId = strtolower(trim((string) $p->employee_id));
                $full = strtolower(trim((string) preg_replace('/\s+/', ' ', $p->full_name)));
                $firstLast = strtolower(trim((string) preg_replace('/\s+/', ' ', "{$p->first_name} {$p->last_name}")));

                return ($empId !== '' && str_contains($needle, $empId))
                    || ($full !== '' && (str_contains($full, $needle) || str_contains($needle, $full)))
                    || ($firstLast !== '' && (str_contains($firstLast, $needle) || str_contains($needle, $firstLast)));
            });

            if ($partial->count() === 1) {
                $matched->push($partial->first());
            }
        }

        return $matched->unique('id')->values();
    }
}
