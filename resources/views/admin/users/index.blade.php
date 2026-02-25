@extends('layouts.admin')

@section('title', 'Пользователи')
@section('page_title', 'Пользователи')

@section('breadcrumb')
    <li class="breadcrumb-item active">Пользователи</li>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="card card-outline card-primary shadow-sm">
            <div class="card-header">
                <div class="d-flex flex-wrap align-items-center justify-content-between" style="gap: 12px;">
                    <div>
                        <h3 class="card-title mb-1">Список пользователей</h3>
                    </div>

                    <div class="d-flex flex-wrap" style="gap: 8px;">
                        <form method="GET" action="{{ route('admin.users.index') }}" class="users-search-form">
                            <div class="input-group input-group-sm">
                                <input
                                    type="text"
                                    name="search"
                                    class="form-control"
                                    placeholder="Поиск по ID, имени, email"
                                    value="{{ $search }}"
                                >
                                <div class="input-group-append">
                                    <button type="submit" class="btn btn-primary px-3" title="Найти">
                                        <i class="fas fa-search"></i>
                                    </button>
                                    @if($search !== '')
                                        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary px-3" title="Сбросить">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </form>

                        <a href="{{ route('admin.users.create') }}" class="btn btn-sm btn-success">
                            <i class="fas fa-plus mr-1"></i> Добавить пользователя
                        </a>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                @if($users->count())
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 users-table">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Имя</th>
                                <th>Email</th>
                                <th>Роль</th>
                                <th>Диллеры</th>
                                <th>Создан</th>
                                <th class="text-right">Действия</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($users as $user)
                                <tr>
                                    <td class="font-weight-bold">{{ $user->id }}</td>
                                    <td>{{ $user->name ?: '—' }}</td>
                                    <td><a href="mailto:{{ $user->email }}">{{ $user->email }}</a></td>
                                    <td>
                                        @if(($user->role ?? null) === 'admin' || $user->is_admin)
                                            <span class="badge badge-success">Админ</span>
                                        @else
                                            <span class="badge badge-info">Менеджер</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($user->dealers->count())
                                            <div class="dealer-badges">
                                                @foreach($user->dealers as $dealer)
                                                    <span class="badge badge-light border">{{ $dealer->name }}</span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-muted small">Не назначены</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div>{{ $user->created_at?->format('d.m.Y') }}</div>
                                        <div class="text-muted small">{{ $user->created_at?->format('H:i') }}</div>
                                    </td>
                                    <td class="text-right text-nowrap">
                                        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-primary mr-1" title="Редактировать">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="d-inline" onsubmit="return confirm('Удалить пользователя?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Удалить">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-4 text-center text-muted">
                        <div class="mb-2"><i class="fas fa-users-slash fa-2x"></i></div>
                        Пользователи не найдены.
                    </div>
                @endif
            </div>

            <div class="card-footer d-flex justify-content-between align-items-center flex-wrap" style="gap: 8px;">
                <div class="text-muted small">
                    Показано {{ $users->firstItem() ?? 0 }}-{{ $users->lastItem() ?? 0 }} из {{ $users->total() }}
                </div>
                <div>
                    {{ $users->links('pagination::bootstrap-4') }}
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .users-search-form {
            width: 360px;
            max-width: 100%;
        }

        .users-table thead th {
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
            font-size: 12px;
            letter-spacing: .03em;
            text-transform: uppercase;
            color: #64748b;
        }

        .dealer-badges {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }

        @media (max-width: 768px) {
            .users-search-form {
                width: 100%;
            }
        }
    </style>
@endpush
