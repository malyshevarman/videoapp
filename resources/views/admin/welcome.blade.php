@extends('layouts.admin')

@section('title', 'Дашборд')
@section('page_title', 'Дашборд')

@section('content')
    @php
        $statusLabels = [
            'surveycompleted' => 'Осмотр завершён',
            'quotescreated' => 'Сметы созданы',
            'approvallinksent' => 'Ссылка согласования отправлена',
            'approvallinkopened' => 'Ссылка согласования открыта',
            'customerdecisionrecorded' => 'Решение клиента зафиксировано',
        ];
    @endphp

    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex flex-wrap align-items-center justify-content-between" style="gap: 8px;">
                    <h5 class="mb-0">Статистика сервисов</h5>
                    <div class="text-muted small">
                        @if(!empty($serviceStats['latest_created_at']))
                            Последнее создание: {{ \Carbon\Carbon::parse($serviceStats['latest_created_at'])->format('d.m.Y H:i') }}
                        @else
                            Нет данных
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3>{{ $serviceStats['total'] ?? 0 }}</h3>
                        <p>Всего сервисов</p>
                    </div>
                    <div class="icon"><i class="fas fa-layer-group"></i></div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="small-box bg-primary">
                    <div class="inner">
                        <h3>{{ $serviceStats['with_history'] ?? 0 }}</h3>
                        <p>С историей статусов</p>
                    </div>
                    <div class="icon"><i class="fas fa-stream"></i></div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3>{{ $serviceStats['without_history'] ?? 0 }}</h3>
                        <p>Без истории статусов</p>
                    </div>
                    <div class="icon"><i class="fas fa-exclamation-circle"></i></div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="small-box bg-secondary">
                    <div class="inner">
                        <h3>{{ $serviceStats['last_24h'] ?? 0 }}</h3>
                        <p>Создано за 24 часа</p>
                    </div>
                    <div class="icon"><i class="fas fa-clock"></i></div>
                </div>
            </div>

            @foreach(($serviceStats['status_counts'] ?? []) as $statusKey => $count)
                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="small-box bg-light border">
                        <div class="inner">
                            <h3>{{ $count }}</h3>
                            <p>{{ $statusLabels[$statusKey] ?? $statusKey }}</p>
                        </div>
                        <div class="icon"><i class="fas fa-chart-bar text-muted"></i></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div><!-- /.container-fluid -->
@endsection

@push('scripts')

@endpush
