@extends('layouts.admin')

@section('title', 'Темы')
@section('page_title', 'Темы')

@section('breadcrumb')
    <li class="breadcrumb-item active">Темы</li>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="card card-outline card-warning shadow-sm">
            <div class="card-header">
                <div class="d-flex flex-wrap align-items-center justify-content-between" style="gap: 12px;">
                    <div>
                        <h3 class="card-title mb-1">Список тем</h3>
                    </div>

                    <div class="d-flex flex-wrap" style="gap: 8px;">
                        <form method="GET" action="{{ route('admin.themes.index') }}" class="themes-search-form">
                            <div class="input-group input-group-sm">
                                <input
                                    type="text"
                                    name="search"
                                    class="form-control"
                                    placeholder="Поиск по ID и названию темы"
                                    value="{{ $search }}"
                                >
                                <div class="input-group-append">
                                    <button type="submit" class="btn btn-warning px-3" title="Найти">
                                        <i class="fas fa-search"></i>
                                    </button>
                                    @if($search !== '')
                                        <a href="{{ route('admin.themes.index') }}" class="btn btn-outline-secondary px-3" title="Сбросить">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </form>

                        <a href="{{ route('admin.themes.create') }}" class="btn btn-sm btn-warning">
                            <i class="fas fa-plus mr-1"></i> Добавить тему
                        </a>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                @if($themes->count())
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 themes-table">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Название</th>
                                <th>Логотип</th>
                                <th>Дилеры</th>
                                <th>Создана</th>
                                <th class="text-right">Действия</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($themes as $theme)
                                <tr>
                                    <td class="font-weight-bold">{{ $theme->id }}</td>
                                    <td>{{ $theme->name }}</td>
                                    <td>
                                        @if($theme->logo_url)
                                            <img src="{{ $theme->logo_url }}" alt="Логотип темы" class="theme-logo-table">
                                        @else
                                            <span class="text-muted small">Нет логотипа</span>
                                        @endif
                                    </td>
                                    <td>{{ $theme->dealers_count }}</td>
                                    <td>
                                        <div>{{ $theme->created_at?->format('d.m.Y') }}</div>
                                        <div class="text-muted small">{{ $theme->created_at?->format('H:i') }}</div>
                                    </td>
                                    <td class="text-right text-nowrap">
                                        <a href="{{ route('admin.themes.edit', $theme) }}" class="btn btn-sm btn-outline-primary mr-1" title="Редактировать">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        <form action="{{ route('admin.themes.destroy', $theme) }}" method="POST" class="d-inline" onsubmit="return confirm('Удалить тему?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Удалить" {{ $theme->dealers_count > 0 ? 'disabled' : '' }}>
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
                        <div class="mb-2"><i class="fas fa-palette fa-2x"></i></div>
                        Темы не найдены.
                    </div>
                @endif
            </div>

            <div class="card-footer d-flex justify-content-between align-items-center flex-wrap" style="gap: 8px;">
                <div class="text-muted small">
                    Показано {{ $themes->firstItem() ?? 0 }}-{{ $themes->lastItem() ?? 0 }} из {{ $themes->total() }}
                </div>
                <div>
                    {{ $themes->links('pagination::bootstrap-4') }}
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .themes-search-form {
            width: 420px;
            max-width: 100%;
        }

        .themes-table thead th {
            background: #fff7ed;
            border-bottom: 1px solid #fed7aa;
            font-size: 12px;
            letter-spacing: .03em;
            text-transform: uppercase;
            color: #9a3412;
        }

        .theme-logo-table {
            width: 56px;
            height: 56px;
            object-fit: contain;
            border: 1px solid #fed7aa;
            border-radius: .25rem;
            background: #fff;
            padding: 4px;
        }

        @media (max-width: 768px) {
            .themes-search-form {
                width: 100%;
            }
        }
    </style>
@endpush
