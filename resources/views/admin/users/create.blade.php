@extends('layouts.admin')

@section('title', 'Новый пользователь')
@section('page_title', 'Новый пользователь')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}">Пользователи</a></li>
    <li class="breadcrumb-item active">Создание</li>
@endsection

@section('content')
    @php
        $userFormInitial = [
            'name' => old('name', ''),
            'email' => old('email', ''),
            'role' => old('role', 'manager'),
            'dealer_ids' => old('dealer_ids', []),
        ];

        $dealerOptions = $dealers->map(function ($dealer) {
            return [
                'id' => $dealer->id,
                'name' => $dealer->name,
            ];
        })->values();

        $userFormErrors = $errors->toArray();
    @endphp

    <div class="container-fluid">
        <div class="card card-outline card-primary shadow-sm">
            <form action="{{ route('admin.users.store') }}" method="POST">
                @csrf
                <div
                    id="admin-user-form"
                    data-initial='@json($userFormInitial)'
                    data-dealers='@json($dealerOptions)'
                    data-errors='@json($userFormErrors)'
                    data-submit-label="Создать"
                    data-is-edit="0"
                ></div>
            </form>
        </div>
    </div>
@endsection
