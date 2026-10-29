<tr id="project-row-{{ $project->id }}">
    <td>
        <a href="{{ route('projects.show', $project) }}" class="table-link">
            <span class="font-mono">{{ $project->project_code }}</span>
            <span class="project-name">{{ $project->name }}</span>
        </a>
        @if ($project->project_type || $project->city)
            <div style="font-size: 0.75rem; color: var(--color-muted); margin-top: 2px;">
                {{ $project->project_type }}{{ $project->project_type && $project->city ? ' · ' : '' }}{{ $project->city }}
            </div>
        @endif
    </td>
    <td>{{ $project->client_name }}</td>
    <td>
        @if ($project->target_completion_date)
            <span class="font-mono" style="font-size: 0.8125rem;">{{ $project->target_completion_date->format('M j, Y') }}</span>
        @else
            <span class="text-muted">—</span>
        @endif
    </td>
    <td class="cell-numeric text-success">
        <div>₱{{ number_format($project->contract_price, 2) }}</div>
        @if($project->payment_status)
            <div style="margin-top: 2px;">
                <span class="status-pill status-pill--{{ strtolower($project->payment_status) }}" style="font-size: 0.6875rem; padding: 1px 6px;">
                    {{ $project->payment_status }}
                </span>
            </div>
        @endif
    </td>
    <td><x-status-pill :status="$project->status" /></td>
    <td>{{ $project->creator?->name ?? 'System' }}</td>
    <td class="text-right"><a href="{{ route('projects.show', $project) }}" class="btn btn-secondary btn-sm">View</a></td>
</tr>
