@extends('layouts.admin')

@section('title', 'Новый дилер')
@section('page_title', 'Новый дилер')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dealers.index') }}">Дилеры</a></li>
    <li class="breadcrumb-item active">Создание</li>
@endsection

@section('content')
    @php
        $dealerFormInitial = [
            'external_id' => old('external_id', ''),
            'name' => old('name', ''),
            'remove_logo' => false,
        ];

        $dealerFormErrors = $errors->toArray();
    @endphp

    <div class="container-fluid">
        <div class="card card-outline card-primary shadow-sm">
            <form action="{{ route('admin.dealers.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div
                    id="admin-dealer-form"
                    data-initial='@json($dealerFormInitial)'
                    data-errors='@json($dealerFormErrors)'
                    data-submit-label="Создать"
                    data-is-edit="0"
                    data-current-logo-url=""
                ></div>
            </form>
        </div>
    </div>
@endsection
