@extends('layouts.admin')

@section('title', 'Редактирование диллера')
@section('page_title', 'Редактирование диллера')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dealers.index') }}">Диллеры</a></li>
    <li class="breadcrumb-item active">#{{ $dealer->id }}</li>
@endsection

@section('content')
    @php
        $logoUrl = $dealer->getFirstMediaUrl('logo', 'logo_500') ?: $dealer->getFirstMediaUrl('logo');

        $dealerFormInitial = [
            'external_id' => old('external_id', $dealer->external_id),
            'name' => old('name', $dealer->name),
            'remove_logo' => (bool) old('remove_logo', false),
        ];

        $dealerFormErrors = $errors->toArray();
    @endphp

    <div class="container-fluid">
        <div class="card card-outline card-primary shadow-sm">
            <form action="{{ route('admin.dealers.update', $dealer) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div
                    id="admin-dealer-form"
                    data-initial='@json($dealerFormInitial)'
                    data-errors='@json($dealerFormErrors)'
                    data-submit-label="Сохранить"
                    data-is-edit="1"
                    data-current-logo-url="{{ $logoUrl }}"
                ></div>
            </form>
        </div>
    </div>
@endsection
