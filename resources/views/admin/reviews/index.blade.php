@extends('layouts.admin')

@section('title', 'Отзывы')
@section('page_title', 'Отзывы')

@section('breadcrumb')
    <li class="breadcrumb-item active">Отзывы</li>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="card card-outline card-warning shadow-sm">
            <div class="card-header">
                <div class="d-flex flex-wrap align-items-center justify-content-between" style="gap: 12px;">
                    <div>
                        <h3 class="card-title mb-1">Оценки клиентов по сервисам</h3>
                    </div>

                    <form method="GET" action="{{ route('admin.reviews.index') }}" class="review-search-form">
                        <div class="input-group input-group-sm">
                            <input
                                type="text"
                                name="search"
                                class="form-control"
                                placeholder="Поиск по заказу, клиенту, комментарию"
                                value="{{ $search }}"
                            >
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-warning px-3">
                                    <i class="fas fa-search"></i>
                                </button>
                                @if($search !== '')
                                    <a href="{{ route('admin.reviews.index') }}" class="btn btn-outline-secondary px-3">
                                        <i class="fas fa-times"></i>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card-body p-0">
                @if($reviews->count())
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 reviews-table">
                            <thead>
                            <tr>
                                <th>Заказ</th>
                                <th>Клиент</th>
                                <th>Сервис</th>
                                <th>Видео</th>
                                <th>Комментарий</th>
                                <th>Дата</th>
                                <th class="text-right">Действия</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($reviews as $review)
                                @php
                                    $service = $review->serviceOrder;
                                    $client = is_array($service?->client) ? $service->client : [];
                                    $firstName = trim((string) ($client['customerFirstName'] ?? $client['firstName'] ?? ''));
                                    $lastName = trim((string) ($client['customerLastName'] ?? $client['lastName'] ?? ''));
                                    $fullName = trim($firstName . ' ' . $lastName);
                                    $serviceAverage = collect([$review->info_usefulness, $review->usability])->filter(fn ($value) => $value !== null);
                                    $videoAverage = collect([$review->video_content, $review->video_image, $review->video_sound, $review->video_duration])->filter(fn ($value) => $value !== null);
                                @endphp
                                <tr>
                                    <td>
                                        <div class="font-weight-bold">{{ $service?->order_id ?? '—' }}</div>
                                        <div class="text-muted small">Сервис #{{ $service?->id ?? '—' }}</div>
                                    </td>
                                    <td>{{ $fullName !== '' ? $fullName : 'Клиент не указан' }}</td>
                                    <td>
                                        <div class="rating-chip">
                                            <span class="rating-chip__label">Средняя</span>
                                            <span class="rating-chip__value">
                                                {{ $serviceAverage->isNotEmpty() ? number_format($serviceAverage->avg(), 1, ',', ' ') : '—' }}
                                            </span>
                                        </div>
                                        <div class="text-muted small mt-1">
                                            Полезность: {{ $review->info_usefulness ?? '—' }} / Удобство: {{ $review->usability ?? '—' }}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="rating-chip rating-chip--soft">
                                            <span class="rating-chip__label">Средняя</span>
                                            <span class="rating-chip__value">
                                                {{ $videoAverage->isNotEmpty() ? number_format($videoAverage->avg(), 1, ',', ' ') : '—' }}
                                            </span>
                                        </div>
                                        <div class="text-muted small mt-1">
                                            Контент {{ $review->video_content ?? '—' }}, изображение {{ $review->video_image ?? '—' }}, звук {{ $review->video_sound ?? '—' }}, длительность {{ $review->video_duration ?? '—' }}
                                        </div>
                                    </td>
                                    <td>
                                        @if($review->comment)
                                            <div class="review-comment">{{ $review->comment }}</div>
                                        @else
                                            <span class="text-muted">Без комментария</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div>{{ $review->created_at?->format('d.m.Y') }}</div>
                                        <div class="text-muted small">{{ $review->created_at?->format('H:i') }}</div>
                                    </td>
                                    <td class="text-right text-nowrap">
                                        @if($service)
                                            <a href="{{ route('admin.services.info', $service->id) }}"
                                               class="btn btn-sm btn-outline-info review-action-btn"
                                               title="Открыть сервис">
                                                <i class="fas fa-info"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-4 text-center text-muted">
                        <div class="mb-2"><i class="fas fa-star-half-alt fa-2x"></i></div>
                        Отзывы пока не найдены.
                    </div>
                @endif
            </div>

            <div class="card-footer d-flex justify-content-between align-items-center flex-wrap" style="gap: 8px;">
                <div class="text-muted small">
                    Показано {{ $reviews->firstItem() ?? 0 }}-{{ $reviews->lastItem() ?? 0 }} из {{ $reviews->total() }}
                </div>
                <div>{{ $reviews->links() }}</div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .review-search-form {
            width: 380px;
            max-width: 100%;
        }

        .reviews-table thead th {
            background: #fffaf0;
            border-bottom: 1px solid #f3e6bf;
            font-size: 12px;
            letter-spacing: .03em;
            text-transform: uppercase;
            color: #7c5f10;
        }

        .review-comment {
            max-width: 320px;
            white-space: pre-wrap;
            word-break: break-word;
            color: #1f2937;
        }

        .rating-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 4px 10px;
            border-radius: 999px;
            background: #fff7ed;
            color: #9a3412;
            font-weight: 600;
        }

        .rating-chip--soft {
            background: #eff6ff;
            color: #1d4ed8;
        }

        .rating-chip__label {
            font-size: 12px;
            opacity: .8;
        }

        .rating-chip__value {
            font-size: 14px;
        }

        .review-action-btn {
            width: 31px;
            height: 31px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        @media (max-width: 768px) {
            .review-search-form {
                width: 100%;
            }
        }
    </style>
@endpush
