@extends('layouts.admin')

@section('title', 'Редактирование пользователя')
@section('page_title', 'Редактирование пользователя')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}">Пользователи</a></li>
    <li class="breadcrumb-item active">#{{ $user->id }}</li>
@endsection

@section('content')
    @php
        $userFormInitial = [
            'name' => old('name', $user->name),
            'email' => old('email', $user->email),
            'role' => old('role', $user->role ?: ($user->is_admin ? 'admin' : 'manager')),
            'dealer_ids' => old('dealer_ids', $user->dealers->pluck('id')->all()),
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
            <form action="{{ route('admin.users.update', $user) }}" method="POST">
                @csrf
                @method('PUT')
                <div
                    id="admin-user-form"
                    data-initial='@json($userFormInitial)'
                    data-dealers='@json($dealerOptions)'
                    data-errors='@json($userFormErrors)'
                    data-submit-label="Сохранить"
                    data-is-edit="1"
                ></div>
            </form>
        </div>
    </div>
@endsection
