@extends('layouts.admin')

@section('title', 'Диллеры')
@section('page_title', 'Диллеры')

@section('breadcrumb')
    <li class="breadcrumb-item active">Диллеры</li>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="card card-outline card-primary shadow-sm">
            <div class="card-header">
                <div class="d-flex flex-wrap align-items-center justify-content-between" style="gap: 12px;">
                    <div>
                        <h3 class="card-title mb-1">Список диллеров</h3>
                    </div>

                    <div class="d-flex flex-wrap" style="gap: 8px;">
                        <form method="GET" action="{{ route('admin.dealers.index') }}" class="dealers-search-form">
                            <div class="input-group input-group-sm">
                                <input
                                    type="text"
                                    name="search"
                                    class="form-control"
                                    placeholder="Поиск по ID, внешнему ID, названию"
                                    value="{{ $search }}"
                                >
                                <div class="input-group-append">
                                    <button type="submit" class="btn btn-primary px-3" title="Найти">
                                        <i class="fas fa-search"></i>
                                    </button>
                                    @if($search !== '')
                                        <a href="{{ route('admin.dealers.index') }}" class="btn btn-outline-secondary px-3" title="Сбросить">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </form>

                        <a href="{{ route('admin.dealers.create') }}" class="btn btn-sm btn-success">
                            <i class="fas fa-plus mr-1"></i> Добавить диллера
                        </a>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                @if($dealers->count())
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 dealers-table">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Внешний ID</th>
                                <th>Название</th>
                                <th>Логотип</th>
                                <th>Создан</th>
                                <th class="text-right">Действия</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($dealers as $dealer)
                                @php
                                    $logoUrl = $dealer->getFirstMediaUrl('logo', 'logo_500') ?: $dealer->getFirstMediaUrl('logo');
                                @endphp
                                <tr>
                                    <td class="font-weight-bold">{{ $dealer->id }}</td>
                                    <td>{{ $dealer->external_id ?: '—' }}</td>
                                    <td>{{ $dealer->name }}</td>
                                    <td>
                                        @if($logoUrl)
                                            <img src="{{ $logoUrl }}" alt="Логотип" class="dealer-logo-table">
                                        @else
                                            <span class="text-muted small">Нет логотипа</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div>{{ $dealer->created_at?->format('d.m.Y') }}</div>
                                        <div class="text-muted small">{{ $dealer->created_at?->format('H:i') }}</div>
                                    </td>
                                    <td class="text-right text-nowrap">
                                        <a href="{{ route('admin.dealers.edit', $dealer) }}" class="btn btn-sm btn-outline-primary mr-1" title="Редактировать">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        <form action="{{ route('admin.dealers.destroy', $dealer) }}" method="POST" class="d-inline" onsubmit="return confirm('Удалить диллера?');">
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
                        <div class="mb-2"><i class="fas fa-building fa-2x"></i></div>
                        Диллеры не найдены.
                    </div>
                @endif
            </div>

            <div class="card-footer d-flex justify-content-between align-items-center flex-wrap" style="gap: 8px;">
                <div class="text-muted small">
                    Показано {{ $dealers->firstItem() ?? 0 }}-{{ $dealers->lastItem() ?? 0 }} из {{ $dealers->total() }}
                </div>
                <div>
                    {{ $dealers->links('pagination::bootstrap-4') }}
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .dealers-search-form {
            width: 420px;
            max-width: 100%;
        }

        .dealers-table thead th {
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
            font-size: 12px;
            letter-spacing: .03em;
            text-transform: uppercase;
            color: #64748b;
        }

        .dealer-logo-table {
            width: 56px;
            height: 56px;
            object-fit: contain;
            border: 1px solid #dee2e6;
            border-radius: .25rem;
            background: #fff;
            padding: 4px;
        }

        @media (max-width: 768px) {
            .dealers-search-form {
                width: 100%;
            }
        }
    </style>
@endpush
