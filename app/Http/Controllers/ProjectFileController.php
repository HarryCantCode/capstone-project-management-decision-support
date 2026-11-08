<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectSubtask;
use App\Models\ProjectTask;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectFileController extends Controller
{
    public function __construct(
        private readonly ProjectService $projectService,
    ) {
    }

    /**
     * Upload a file directly to the project (invoice or general file).
     */
    public function store(Request $request, Project $project): RedirectResponse|JsonResponse
    {
        abort_unless(Auth::user()->hasAnyRole(['Admin', 'Manager', 'Staff']), 403);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,docx,jpg,jpeg,png,webp', 'max:20480'],
            'file_type' => ['nullable', 'string', 'in:invoice,general'],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'file.required' => 'Please select a file to upload.',
            'file.mimes' => 'Only PDF, DOCX, and Image (JPG, JPEG, PNG, WEBP) files are allowed.',
            'file.max' => 'The file size cannot exceed 20MB.',
        ]);

        if (Auth::user()->hasRole('Staff')) {
            $uploadedFile = $validated['file'];
            $storedPath = $uploadedFile->store('project-files/' . $project->id, 'public');

            $pendingAction = \App\Models\StaffPendingAction::create([
                'user_id' => Auth::id(),
                'project_id' => $project->id,
                'action_type' => 'add_file',
                'payload' => [
                    'file_name' => $uploadedFile->getClientOriginalName(),
                    'file_path' => $storedPath,
                    'file_size' => $uploadedFile->getSize(),
                    'mime_type' => $uploadedFile->getClientMimeType() ?: $uploadedFile->getMimeType(),
                    'file_type' => $validated['file_type'] ?? 'general',
                    'description' => $validated['description'] ?? null,
                    'subtask_id' => null,
                    'subtask_title' => null,
                ],
                'status' => 'pending',
            ]);

            app(\App\Services\NotificationService::class)->notifyStaffActionPending($pendingAction);

            $msg = 'Your action has been submitted successfully and will be reviewed by your manager or admin to take effect.';
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'pending_approval' => true,
                    'message' => $msg,
                ]);
            }

            return redirect()
                ->route('projects.show', $project)
                ->with('staff_submitted', $msg);
        }

        $projectFile = $this->projectService->storeProjectFile(
            $project,
            $validated['file'],
            $validated['file_type'] ?? 'general',
            Auth::id(),
            $validated['description'] ?? null,
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully.',
                'file' => $projectFile->load('uploader'),
            ]);
        }

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'File uploaded successfully.');
    }

    /**
     * Upload an attachment specifically for a subtask in the timeline.
     */
    public function storeSubtaskFile(
        Request $request,
        Project $project,
        ProjectTask $task,
        ProjectSubtask $subtask,
    ): RedirectResponse|JsonResponse {
        abort_unless(Auth::user()->hasAnyRole(['Admin', 'Manager', 'Staff']), 403);
        abort_if($task->project_id !== $project->id || $subtask->project_task_id !== $task->id, 404);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,docx,jpg,jpeg,png,webp', 'max:20480'],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'file.required' => 'Please select a file to upload.',
            'file.mimes' => 'Only PDF, DOCX, and Image (JPG, JPEG, PNG, WEBP) files are allowed.',
            'file.max' => 'The file size cannot exceed 20MB.',
        ]);

        if (Auth::user()->hasRole('Staff')) {
            $uploadedFile = $validated['file'];
            $storedPath = $uploadedFile->store('project-files/' . $project->id, 'public');

            $pendingAction = \App\Models\StaffPendingAction::create([
                'user_id' => Auth::id(),
                'project_id' => $project->id,
                'action_type' => 'add_file',
                'payload' => [
                    'file_name' => $uploadedFile->getClientOriginalName(),
                    'file_path' => $storedPath,
                    'file_size' => $uploadedFile->getSize(),
                    'mime_type' => $uploadedFile->getClientMimeType() ?: $uploadedFile->getMimeType(),
                    'file_type' => 'task_attachment',
                    'description' => $validated['description'] ?? "Attachment for subtask: {$subtask->title}",
                    'subtask_id' => $subtask->id,
                    'subtask_title' => $subtask->title,
                    'task_id' => $task->id,
                ],
                'status' => 'pending',
            ]);

            app(\App\Services\NotificationService::class)->notifyStaffActionPending($pendingAction);

            $msg = 'Your action has been submitted successfully and will be reviewed by your manager or admin to take effect.';
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'pending_approval' => true,
                    'message' => $msg,
                ]);
            }

            return redirect()
                ->route('projects.show', $project)
                ->with('staff_submitted', $msg);
        }

        $projectFile = $this->projectService->storeProjectFile(
            $project,
            $validated['file'],
            'task_attachment',
            Auth::id(),
            $validated['description'] ?? "Attachment for subtask: {$subtask->title}",
            $subtask,
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Attachment uploaded to subtask.',
                'file' => $projectFile->load('uploader'),
            ]);
        }

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'Attachment uploaded to subtask successfully.');
    }

    /**
     * Download or view an uploaded file.
     */
    public function download(Project $project, ProjectFile $file): StreamedResponse
    {
        $this->authorize('view', $project);
        abort_if($file->project_id !== $project->id, 404);

        if (!Storage::disk('public')->exists($file->file_path)) {
            abort(404, 'File not found on server storage.');
        }

        return Storage::disk('public')->download($file->file_path, $file->file_name);
    }

    /**
     * Update an uploaded file's description / note.
     */
    public function update(Request $request, Project $project, ProjectFile $file): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $project);
        abort_if($file->project_id !== $project->id, 404);

        $validated = $request->validate([
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $file->update([
            'description' => $validated['description'] ?? null,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'File description updated successfully.',
                'file' => $file->fresh()->load('uploader'),
            ]);
        }

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'File description updated successfully.');
    }

    /**
     * Delete an uploaded file.
     */
    public function destroy(Request $request, Project $project, ProjectFile $file): RedirectResponse|JsonResponse
    {
        abort_if(Auth::user()->hasRole('Staff'), 403, 'Staff cannot delete files.');
        $this->authorize('update', $project);
        abort_if($file->project_id !== $project->id, 404);

        if (Storage::disk('public')->exists($file->file_path)) {
            Storage::disk('public')->delete($file->file_path);
        }

        $file->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'File deleted successfully.',
            ]);
        }

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'File deleted successfully.');
    }
}
