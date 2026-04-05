@extends('layouts.admin')

@section('title', 'Новая тема')
@section('page_title', 'Новая тема')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.themes.index') }}">Темы</a></li>
    <li class="breadcrumb-item active">Создание</li>
@endsection

@section('content')
    @php
        $themeFormInitial = [
            'name' => old('name', ''),
        ];

        $themeFormErrors = $errors->toArray();
    @endphp

    <div class="container-fluid">
        <div class="card card-outline card-warning shadow-sm">
            <form action="{{ route('admin.themes.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div
                    id="admin-theme-form"
                    data-initial='@json($themeFormInitial)'
                    data-errors='@json($themeFormErrors)'
                    data-submit-label="Создать"
                    data-is-edit="0"
                    data-current-logo-url=""
                ></div>
            </form>
        </div>
    </div>
@endsection
