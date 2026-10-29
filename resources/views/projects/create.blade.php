@extends('layouts.app')

@section('title', 'Create Project')
@section('meta-description', 'Register a new Dex PMS project.')

@section('content')
    <div class="page-header">
        <div>
            <h1>Create Project</h1>
            <p class="page-header__description">A unique project code and an initial Pending status are assigned automatically.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('projects.store') }}" class="project-form-layout" novalidate>
        @csrf
        @include('projects._form')
    </form>
@endsection
