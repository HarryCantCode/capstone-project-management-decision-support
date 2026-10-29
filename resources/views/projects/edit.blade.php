@extends('layouts.app')

@section('title', 'Edit Project')
@section('meta-description', 'Update project information.')

@section('content')
    <div class="page-header">
        <div>
            <h1>Edit Project</h1>
            <p class="page-header__description"><span class="font-mono">{{ $project->project_code }}</span> · Status changes are managed separately.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('projects.update', $project) }}" class="project-form-layout" novalidate>
        @csrf
        @method('PUT')
        @include('projects._form')
    </form>
@endsection
