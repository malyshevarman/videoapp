@extends('layouts.admin')

@section('title', 'Редактирование темы')
@section('page_title', 'Редактирование темы')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.themes.index') }}">Темы</a></li>
    <li class="breadcrumb-item active">#{{ $theme->id }}</li>
@endsection

@section('content')
    @php
        $themeFormInitial = [
            'name' => old('name', $theme->name),
        ];

        $themeFormErrors = $errors->toArray();
    @endphp

    <div class="container-fluid">
        <div class="card card-outline card-warning shadow-sm">
            <form action="{{ route('admin.themes.update', $theme) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div
                    id="admin-theme-form"
                    data-initial='@json($themeFormInitial)'
                    data-errors='@json($themeFormErrors)'
                    data-submit-label="Сохранить"
                    data-is-edit="1"
                    data-current-logo-url="{{ $theme->logo_url }}"
                ></div>
            </form>
        </div>
    </div>
@endsection
