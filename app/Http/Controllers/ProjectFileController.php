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
     * Upload a file or multiple files directly to the project.
     */
    public function store(Request $request, Project $project): RedirectResponse|JsonResponse
    {
        abort_unless(Auth::user()->hasAnyRole(['Admin', 'Manager', 'Staff']), 403);

        $request->validate([
            'file' => ['nullable', 'file', 'mimes:pdf,docx,jpg,jpeg,png,webp', 'max:20480'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'mimes:pdf,docx,jpg,jpeg,png,webp', 'max:20480'],
            'file_type' => ['nullable', 'string', 'in:invoice,project_invoice,general,project_document,project_transaction,project_notes,project_drawing'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['nullable', 'string', 'in:invoice,project_invoice,general,project_document,project_transaction,project_notes,project_drawing'],
            'description' => ['nullable', 'string', 'max:255'],
            'descriptions' => ['nullable', 'array'],
            'descriptions.*' => ['nullable', 'string', 'max:255'],
        ], [
            'file.mimes' => 'Only PDF, DOCX, and Image (JPG, JPEG, PNG, WEBP) files are allowed.',
            'file.max' => 'The file size cannot exceed 20MB.',
            'files.*.mimes' => 'Only PDF, DOCX, and Image (JPG, JPEG, PNG, WEBP) files are allowed.',
            'files.*.max' => 'Each file size cannot exceed 20MB.',
        ]);

        $hasMultiple = $request->hasFile('files');
        $hasSingle = $request->hasFile('file');

        if (!$hasMultiple && !$hasSingle) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select at least one file to upload.',
                ], 422);
            }

            return back()->withErrors(['file' => 'Please select at least one file to upload.']);
        }

        $items = [];
        $defaultCategory = $request->input('file_type', 'project_document');
        if ($defaultCategory === 'general') {
            $defaultCategory = 'project_document';
        }
        if ($defaultCategory === 'project_invoice') {
            $defaultCategory = 'invoice';
        }

        if ($hasMultiple) {
            $filesList = $request->file('files');
            $cats = (array) $request->input('categories', []);
            $descs = (array) $request->input('descriptions', []);
            foreach ($filesList as $idx => $uploadedFile) {
                if ($uploadedFile instanceof \Illuminate\Http\UploadedFile) {
                    $cat = $cats[$idx] ?? $defaultCategory;
                    if ($cat === 'general') {
                        $cat = 'project_document';
                    }
                    if ($cat === 'project_invoice') {
                        $cat = 'invoice';
                    }
                    $desc = $descs[$idx] ?? $request->input('description');
                    $items[] = [
                        'file' => $uploadedFile,
                        'category' => $cat,
                        'description' => $desc,
                    ];
                }
            }
        } elseif ($hasSingle) {
            $uploadedFile = $request->file('file');
            $items[] = [
                'file' => $uploadedFile,
                'category' => $defaultCategory,
                'description' => $request->input('description'),
            ];
        }

        if (empty($items)) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'No valid files provided.'], 422);
            }

            return back()->withErrors(['file' => 'No valid files provided.']);
        }

        if (Auth::user()->hasRole('Staff')) {
            $pendingActions = [];
            foreach ($items as $item) {
                $uploadedFile = $item['file'];
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
                        'file_type' => $item['category'],
                        'description' => $item['description'],
                        'subtask_id' => null,
                        'subtask_title' => null,
                    ],
                    'status' => 'pending',
                ]);

                app(\App\Services\NotificationService::class)->notifyStaffActionPending($pendingAction);
                $pendingActions[] = $pendingAction;
            }

            $count = count($pendingActions);
            $msg = $count > 1
                ? "{$count} file upload requests have been submitted successfully and will be reviewed by your manager or admin."
                : 'Your file upload request has been submitted successfully and will be reviewed by your manager or admin.';

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

        $storedFiles = [];
        foreach ($items as $item) {
            $storedFiles[] = $this->projectService->storeProjectFile(
                $project,
                $item['file'],
                $item['category'],
                Auth::id(),
                $item['description'],
            );
        }

        $count = count($storedFiles);
        $msg = $count > 1 ? "{$count} files uploaded successfully." : 'File uploaded successfully.';

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'files' => $storedFiles,
            ]);
        }

        return redirect()
            ->route('projects.show', $project)
            ->with('status', $msg);
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

        if (request()->has('preview')) {
            return Storage::disk('public')->response($file->file_path, $file->file_name);
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
