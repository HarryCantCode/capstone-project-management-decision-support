<?php

namespace App\Services\DecisionSupport;

use App\Models\Personnel;
use App\Models\Project;
use App\Models\ProjectCost;
use App\Models\Resource;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * KuyaDexChatService
 *
 * Powers "Kuya DEX", the personal AI assistant for the DEX Project Management System (DEX PMS).
 *
 * Security & Privacy Guarantees:
 * - Passes ONLY sanitized operational JSON data (Materials, Projects, Costing, Manpower).
 * - NEVER accesses or transmits User accounts, credentials, emails, passwords, OTPs, tokens, or session data.
 *
 * Strict Guardrail:
 * - Only answers inquiries regarding DEX PMS data (Materials, Projects, Costing, Manpower, Schedule)
 *   and Elevator operation, technical specifications, installation, maintenance, and construction.
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
                'maxOutputTokens' => 700,
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
     * Excludes all sensitive user, authentication, session, or credential records.
     */
    public function buildSanitizedSystemJson(?int $selectedProjectId = null): string
    {
        $projects = Project::query()
            ->with(['personnel', 'tasks.subtasks', 'costs'])
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

        $recentCosts = ProjectCost::query()
            ->with('project')
            ->latest('id')
            ->limit(30)
            ->get();

        $formattedProjects = $projects->map(function (Project $project): array {
            $tasks = $project->tasks ?? collect();
            $totalTasks = $tasks->count();
            $completedTasks = $tasks->where('status', 'completed')->count();

            $progressPct = 0.0;
            if ($totalTasks > 0) {
                $progressPct = round(($completedTasks / $totalTasks) * 100, 1);
            } elseif ($project->status === 'completed') {
                $progressPct = 100.0;
            }

            return [
                'id' => (int) $project->id,
                'project_code' => (string) $project->project_code,
                'name' => (string) $project->name,
                'client_name' => (string) ($project->client_name ?? 'N/A'),
                'status' => (string) $project->status,
                'location' => (string) ($project->city ?: $project->province ?: 'N/A'),
                'elevator_specifications' => [
                    'project_type' => (string) ($project->project_type ?? 'Standard Elevator'),
                    'rated_capacity_kg' => (string) ($project->rated_capacity ?? 'N/A'),
                    'lifting_speed_mps' => (string) ($project->lifting_speed ?? 'N/A'),
                    'no_of_stops' => (int) ($project->no_of_stops ?? 0),
                    'door_operation' => (string) ($project->door_operation ?? 'N/A'),
                    'power_source' => (string) ($project->power_source ?? 'N/A'),
                    'operation_mode' => (string) ($project->operation ?? 'N/A'),
                ],
                'schedule_and_progress' => [
                    'start_date' => $project->start_date?->format('Y-m-d'),
                    'target_completion_date' => $project->target_completion_date?->format('Y-m-d'),
                    'progress_percentage' => $progressPct,
                    'completed_tasks' => $completedTasks,
                    'total_tasks' => $totalTasks,
                ],
                'costing' => [
                    'contract_price_php' => round((float) $project->contract_price, 2),
                    'total_spend_php' => round((float) $project->actual_spend, 2),
                    'remaining_budget_php' => round((float) $project->remaining_budget, 2),
                    'spend_percentage' => (float) $project->spend_percentage,
                    'costing_status' => (string) $project->costing_status,
                    'payment_status' => (string) ($project->payment_status ?? 'N/A'),
                ],
                'assigned_manpower' => $project->personnel->map(fn (Personnel $p) => [
                    'employee_id' => $p->employee_id,
                    'full_name' => $p->full_name,
                    'expertise' => $p->expertise,
                ])->values()->all(),
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
                'expertise' => (string) $p->expertise,
                'availability' => 'Available (Unassigned)',
            ])
            ->values();

        $assignedManpower = $allPersonnel
            ->filter(fn (Personnel $p) => $p->project_id !== null)
            ->map(fn (Personnel $p) => [
                'employee_id' => (string) $p->employee_id,
                'full_name' => (string) $p->full_name,
                'expertise' => (string) $p->expertise,
                'assigned_project_code' => (string) ($p->project?->project_code ?? 'N/A'),
                'assigned_project_name' => (string) ($p->project?->name ?? 'N/A'),
            ])
            ->values();

        $materialsCatalog = $allResources->map(fn (Resource $r) => [
            'resource_code' => (string) $r->resource_code,
            'name' => (string) $r->name,
            'model' => (string) ($r->model ?: 'Standard'),
            'quantity_available' => (int) $r->quantity_available,
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
                'projects' => $formattedProjects->all(),
            ],
            'manpower_data' => [
                'total_manpower_count' => $allPersonnel->count(),
                'available_manpower_count' => $availableManpower->count(),
                'assigned_manpower_count' => $assignedManpower->count(),
                'available_manpower' => $availableManpower->all(),
                'assigned_manpower' => $assignedManpower->all(),
            ],
            'materials_data' => [
                'total_catalog_materials' => $materialsCatalog->count(),
                'out_of_stock_count' => $materialsCatalog->where('quantity_available', '<=', 0)->count(),
                'materials' => $materialsCatalog->all(),
            ],
            'costing_data' => [
                'portfolio_total_contract_price_php' => round((float) $projects->sum('contract_price'), 2),
                'portfolio_total_spend_php' => round((float) $projects->sum('actual_spend'), 2),
                'recent_cost_logs' => $recentCosts->map(fn (ProjectCost $c) => [
                    'project_code' => (string) ($c->project?->project_code ?? 'N/A'),
                    'project_name' => (string) ($c->project?->name ?? 'N/A'),
                    'category' => (string) ($c->category ?? 'General'),
                    'description' => (string) ($c->description ?? ''),
                    'amount_php' => round((float) $c->amount, 2),
                ])->values()->all(),
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

        // Check if the query references any allowed DEX PMS or Elevator construction domain terms
        $allowedDomainPattern = '/\b(project|projects|pms|dex|kuya\s*dex|manpower|personnel|staff|crew|technician|engineer|safety\s+officer|foreman|electrician|mechanic|installer|worker|employee|available|unassigned|assigned|material|materials|resource|resources|inventory|stock|catalog|supplier|procurement|reorder|shortage|deficit|cost|costing|spend|spending|spent|budget|expense|expenses|price|contract|margin|revenue|payment|invoice|progress|task|tasks|subtask|subtasks|schedule|timeline|delay|overdue|milestone|target\s+date|completion|status|ongoing|pending|completed|delayed|allocat|simulat|feasibility|elevator|elevators|lift|lifts|escalator|hoist|hoistway|shaft|pit|overhead|machine\s+room|mrl|traction|hydraulic|counterweight|car|cage|cabin|door|interlock|governor|overspeed|safety\s+gear|buffer|guide\s+rail|wire\s+rope|sheave|inverter|vvvf|controller|plc|stops|landing|capacity|rated\s+load|lifting\s+speed|construction|installation|commissioning|testing|maintenance|warranty|blueprint)\b/i';

        // Allow brief polite greetings about Kuya DEX's PMS capabilities
        $greetingPattern = '/^(hi|hello|hey|good\s+morning|good\s+afternoon|good\s+evening|help|what\s+can\s+you\s+do)(\s+kuya\s*dex)?[\?\!\.]*$/i';

        if (preg_match($allowedDomainPattern, $normalized) === 1 || preg_match($greetingPattern, $normalized) === 1) {
            return false;
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
   - DEX PMS Materials & Inventory data (stock levels, unit costs, prices, demand status, shortages)
   - DEX PMS Projects data (progress, tasks, status, schedule, client, elevator specs)
   - DEX PMS Costing data (contract price, total spend, remaining budget, cost logs)
   - DEX PMS Manpower data (available/unassigned personnel, assigned crew, expertise/roles)
   - Elevator operation, technical specifications, installation, maintenance, safety standards, and construction.
2. You MUST NOT answer any unrelated questions outside DEX PMS or elevator operation/construction.
   - You CANNOT answer: "What is the date today?", "Who is the president of this country?", "Who is this?", "What is this thing?", general trivia, politics, history, pop culture, weather, math homework, or general coding.
3. If the user asks ANY question outside the allowed topics above, you MUST reply with this EXACT text and nothing else:
{$restrictedReply}

HOW TO USE THE SYSTEM DATA:
- Below is the live, sanitized JSON data from DEX PMS.
- If the user asks about "this project" or "project x" without naming a specific project code, use `active_context_project` from the JSON data and mention its `project_code` and `name`.
- Always format Philippine Peso amounts clearly (e.g., ₱1,250,000.00).
- Keep your responses concise, professional, accurate to the JSON numbers, and easy to read.

SANITIZED DEX PMS JSON DATA:
{$sanitizedJson}
PROMPT;
    }
}
