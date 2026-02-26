@extends('layouts.admin')

@section('title', 'Сервисы')
@section('page_title', 'Сервисы')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.services.index') }}">Сервисы</a></li>
@endsection

@section('content')
    @php
        $statusLabels = [
            'new' => ['label' => 'Новый', 'class' => 'badge-info'],
            'inprogress' => ['label' => 'В работе', 'class' => 'badge-warning'],
            'completed' => ['label' => 'Завершён', 'class' => 'badge-success'],
            'cancelled' => ['label' => 'Отменён', 'class' => 'badge-danger'],
        ];
    @endphp

    <div class="container-fluid">
        <div class="card card-outline card-primary shadow-sm">
            <div class="card-header">
                <div class="d-flex flex-wrap align-items-center justify-content-between" style="gap: 12px;">
                    <div>
                        <h3 class="card-title mb-1">Список сервисов</h3>
                    </div>

                    <form method="GET" action="{{ route('admin.services.index') }}" class="service-search-form">
                        <div class="input-group input-group-sm">
                            <input
                                type="text"
                                name="table_search"
                                class="form-control"
                                placeholder="Поиск по ID, имени, фамилии"
                                value="{{ request('table_search') }}"
                            >
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-primary px-3" title="Найти">
                                    <i class="fas fa-search"></i>
                                </button>
                                @if(request('table_search'))
                                    <a href="{{ route('admin.services.index') }}" class="btn btn-outline-secondary px-3" title="Сбросить">
                                        <i class="fas fa-times"></i>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card-body p-0">
                @if($orders->count())
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 services-table">
                            <thead>
                            <tr>
                                <th>ID заказа</th>
                                <th>Клиент</th>
                                <th>Статус</th>
                                <th>Public URL</th>
                                <th>Создан</th>
                                <th class="text-right">Действия</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($orders as $order)
                                @php
                                    $statusKey = strtolower((string) $order->processStatus);
                                    $status = $statusLabels[$statusKey] ?? ['label' => $order->processStatus ?: 'Не указан', 'class' => 'badge-secondary'];
                                    $firstName = trim((string) ($order->client['customerFirstName'] ?? $order->client['firstName'] ?? ''));
                                    $lastName = trim((string) ($order->client['customerLastName'] ?? $order->client['lastName'] ?? ''));
                                    $fullName = trim($firstName . ' ' . $lastName);
                                @endphp
                                <tr>
                                    <td>
                                        <div class="font-weight-bold">{{ $order->order_id }}</div>
                                        <div class="text-muted small">Внутренний ID: {{ $order->id }}</div>
                                    </td>
                                    <td>
                                        <div class="font-weight-semibold">{{ $fullName !== '' ? $fullName : 'Клиент не указан' }}</div>
                                    </td>
                                    <td>
                                        <span class="badge {{ $status['class'] }} px-2 py-1">{{ $status['label'] }}</span>
                                    </td>
                                    <td>
                                        @if($order->public_url)
                                            <span class="text-monospace small d-inline-block service-url" title="{{ $order->public_url }}">
                                                {{ \Illuminate\Support\Str::limit($order->public_url, 30) }}
                                            </span>
                                        @else
                                            <span class="text-muted small">Не задан</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div>{{ $order->created_at->format('d.m.Y') }}</div>
                                        <div class="text-muted small">{{ $order->created_at->format('H:i') }}</div>
                                    </td>
                                    <td class="text-right text-nowrap">
                                        <a href="{{ route('admin.services.info', $order->id) }}"
                                           class="btn btn-sm btn-outline-info mr-1 service-action-btn"
                                           title="Информация о сервисе">
                                            <i class="fas fa-info"></i>
                                        </a>
                                        <a href="{{ route('admin.services.edit', $order->id) }}"
                                           class="btn btn-sm btn-outline-primary mr-1 service-action-btn"
                                           title="Открыть и редактировать">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-4 text-center text-muted">
                        <div class="mb-2"><i class="fas fa-inbox fa-2x"></i></div>
                        По вашему запросу сервисы не найдены.
                    </div>
                @endif
            </div>

            <div class="card-footer d-flex justify-content-between align-items-center flex-wrap" style="gap: 8px;">
                <div class="text-muted small">
                    Показано {{ $orders->firstItem() ?? 0 }}-{{ $orders->lastItem() ?? 0 }} из {{ $orders->total() }}
                </div>
                <div>
                    {{ $orders->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .service-search-form {
            width: 360px;
            max-width: 100%;
        }

        .services-table thead th {
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
            font-size: 12px;
            letter-spacing: .03em;
            text-transform: uppercase;
            color: #64748b;
        }

        .services-table tbody td {
            vertical-align: middle;
        }

        .service-url {
            max-width: 260px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .font-weight-semibold {
            font-weight: 600;
        }

        .service-action-btn {
            width: 31px;
            height: 31px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        @media (max-width: 768px) {
            .service-search-form {
                width: 100%;
            }

            .card-header .card-title {
                margin-bottom: 0;
            }
        }
    </style>
@endpush

@push('scripts')
@endpush
