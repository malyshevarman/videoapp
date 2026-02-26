@extends('layouts.admin')

@section('title', 'Информация о сервисе')
@section('page_title', 'Информация о сервисе')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.services.index') }}">Сервисы</a></li>
    <li class="breadcrumb-item active">Информация</li>
@endsection

@section('content')
    @php
        $reference = is_array($payload['referenceObject'] ?? null) ? $payload['referenceObject'] : [];
        $tasks = is_array($payload['tasks'] ?? null) ? $payload['tasks'] : [];
        $details = is_array($payload['details'] ?? null) ? $payload['details'] : [];
        $records = is_array($payload['processStatusRecords'] ?? null) ? $payload['processStatusRecords'] : [];
        $client = is_array($payload['client'] ?? null) ? $payload['client'] : [];
        $driver = is_array($payload['carDriver'] ?? null) ? $payload['carDriver'] : [];
        $owner = is_array($payload['carOwner'] ?? null) ? $payload['carOwner'] : [];
        $survey = is_array($payload['surveyObject'] ?? null) ? $payload['surveyObject'] : [];
        $requester = is_array($payload['requester'] ?? null) ? $payload['requester'] : [];
        $responsible = is_array($payload['responsibleEmployee'] ?? null) ? $payload['responsibleEmployee'] : [];
        $defects = is_array($payload['defects'] ?? null) ? $payload['defects'] : [];

        $personName = function (array $data): string {
            $keys = ['customerLastName', 'customerFirstName', 'customerMidName', 'specialistLastName', 'specialistFirstName', 'specialistMidName', 'specialistMiddleName'];
            $parts = [];
            foreach ($keys as $key) {
                $val = trim((string) ($data[$key] ?? ''));
                if ($val !== '') {
                    $parts[] = $val;
                }
            }
            return trim(implode(' ', $parts));
        };

        $fmt = function ($value): string {
            if (!$value) {
                return '—';
            }
            try {
                return \Illuminate\Support\Carbon::parse($value)->format('d.m.Y H:i:s');
            } catch (\Throwable $e) {
                return (string) $value;
            }
        };

        $boolText = function ($value): string {
            if (is_bool($value)) {
                return $value ? 'true' : 'false';
            }
            return ((string) $value !== '') ? (string) $value : '—';
        };

        $taskNameById = [];
        foreach ($tasks as $task) {
            $taskId = (string) ($task['taskId'] ?? '');
            if ($taskId !== '') {
                $taskNameById[$taskId] = $task['taskName'] ?? null;
            }
        }
    @endphp

    <div class="container-fluid">
        <div class="card card-outline card-info shadow-sm">
            <div class="card-header">
                <div class="d-flex flex-wrap align-items-center justify-content-between" style="gap: 12px;">
                    <div>
                      <div class="text-muted small">
                            Внутренний ID: {{ $service->id }}
                            @if($payload['processStatus'] ?? null) | Статус: {{ $payload['processStatus'] }} @endif
                        </div>
                    </div>
                    <div class="d-flex" style="gap: 8px;">
                        <a href="{{ route('admin.services.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left mr-1"></i>К списку</a>
                        <a href="{{ route('admin.services.edit', $service->id) }}" class="btn btn-sm btn-primary"><i class="fas fa-pen mr-1"></i>Открыть сервис</a>
                    </div>
                </div>
            </div>

            <div class="card-body service-info-page">
                <div class="row">
                    <div class="col-12 col-xl-6">
                        <div class="info-panel">
                            <h5 class="info-panel__title">Основное</h5>
                            <table class="table table-sm table-borderless info-table mb-0">
                                <tbody>
                                <tr><th>ID</th><td>{{ $service->id }}</td></tr>
                                <tr><th>orderId</th><td>{{ $reference['orderId'] ?? $service->order_id ?? '—' }}</td></tr>
                                <tr><th>orderBarcode</th><td>{{ $reference['orderBarcode'] ?? '—' }}</td></tr>
                                <tr><th>Тип заказа</th><td>{{ $reference['orderType'] ?? '—' }}</td></tr>
                                <tr><th>Вид работ</th><td>{{ $reference['orderWorkType'] ?? '—' }}</td></tr>
                                <tr><th>Категория заказа</th><td>{{ $reference['orderCategory'] ?? '—' }}</td></tr>
                                <tr><th>siteId</th><td>{{ $payload['siteId'] ?? '—' }}</td></tr>
                                <tr><th>locationCode</th><td>{{ $payload['locationCode'] ?? '—' }}</td></tr>
                                <tr><th>dealerCode</th><td>{{ $payload['dealerCode'] ?? '—' }}</td></tr>
                                <tr><th>reviewCategory</th><td>{{ $payload['reviewCategory'] ?? '—' }}</td></tr>
                                <tr><th>reviewType</th><td>{{ $payload['reviewType'] ?? '—' }}</td></tr>
                                <tr><th>reviewName</th><td>{{ $payload['reviewName'] ?? '—' }}</td></tr>
                                <tr><th>processStatus</th><td>{{ $payload['processStatus'] ?? '—' }}</td></tr>
                                <tr><th>changeTimeStamp</th><td>{{ $fmt($payload['changeTimeStamp'] ?? null) }}</td></tr>
                                <tr><th>completionTimeStamp</th><td>{{ $fmt($payload['completionTimeStamp'] ?? null) }}</td></tr>
                                <tr><th>creationTimestamp</th><td>{{ $fmt($payload['creationTimestamp'] ?? null) }}</td></tr>
                                <tr><th>visitStartTime</th><td>{{ $fmt($payload['visitStartTime'] ?? null) }}</td></tr>
                                <tr><th>closed</th><td>{{ $boolText($payload['closed'] ?? null) }}</td></tr>
                                <tr><th>completed</th><td>{{ $boolText($payload['completed'] ?? null) }}</td></tr>
                                <tr><th>hasSurveyRefs</th><td>{{ $boolText($payload['hasSurveyRefs'] ?? null) }}</td></tr>
                                <tr><th>systemId</th><td>{{ $payload['systemId'] ?? '—' }}</td></tr>
                                <tr><th>reviewTemplateId</th><td>{{ $payload['reviewTemplateId'] ?? '—' }}</td></tr>
                                <tr><th>timeSpent</th><td>{{ $payload['timeSpent'] ?? 0 }}</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="info-panel">
                            <h5 class="info-panel__title">Суммы по заказу</h5>
                            <table class="table table-sm table-borderless info-table mb-0">
                                <tbody>
                                <tr><th>Сумма без НДС</th><td>{{ $reference['orderAmountExVat'] ?? '—' }}</td></tr>
                                <tr><th>Сумма с НДС</th><td>{{ $reference['orderAmountIncVat'] ?? '—' }}</td></tr>
                                <tr><th>Валюта</th><td>{{ $reference['currencyCode'] ?? '—' }}</td></tr>
                                <tr><th>orderClosed</th><td>{{ array_key_exists('orderClosed', $reference) ? ($reference['orderClosed'] ? 'true' : 'false') : '—' }}</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="info-panel">
                            <h5 class="info-panel__title">Автомобиль (surveyObject)</h5>
                            <table class="table table-sm table-borderless info-table mb-0">
                                <tbody>
                                <tr><th>Марка</th><td>{{ $survey['carBrand'] ?? '—' }}</td></tr>
                                <tr><th>Модель</th><td>{{ $survey['carModel'] ?? '—' }}</td></tr>
                                <tr><th>Код модели</th><td>{{ $survey['carModelCode'] ?? '—' }}</td></tr>
                                <tr><th>VIN</th><td>{{ $survey['carVin'] ?? '—' }}</td></tr>
                                <tr><th>Номер</th><td>{{ $survey['carLicensePlate'] ?? '—' }}</td></tr>
                                <tr><th>Страна номера</th><td>{{ $survey['carLicensePlateCountry'] ?? '—' }}</td></tr>
                                <tr><th>Топливо</th><td>{{ $survey['carFuel'] ?? '—' }}</td></tr>
                                <tr><th>carId</th><td>{{ $survey['carId'] ?? '—' }}</td></tr>

                                </tbody>
                            </table>
                        </div>

                        <div class="info-panel">
                            <h5 class="info-panel__title">Клиент</h5>
                            <table class="table table-sm table-borderless info-table mb-0">
                                <tbody>
                                <tr><th>ФИО</th><td>{{ $personName($client) ?: '—' }}</td></tr>
                                <tr><th>ID</th><td>{{ $client['customerId'] ?? '—' }}</td></tr>
                                <tr><th>Телефон</th><td>{{ $client['customerPhone'] ?? '—' }}</td></tr>
                                <tr><th>Email</th><td>{{ $client['customerEmail'] ?? '—' }}</td></tr>
                                <tr><th>Адрес</th><td>{{ $client['customerAddress'] ?? '—' }}</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="info-panel">
                            <h5 class="info-panel__title">Механик</h5>
                            <table class="table table-sm table-borderless info-table mb-0">
                                <tbody>
                                <tr><th>ФИО</th><td>{{ $personName($requester) ?: '—' }}</td></tr>
                                <tr><th>Ид специалиста</th><td>{{ $requester['specialistId'] ?? '—' }}</td></tr>
                                <tr><th>User ID системный</th><td>{{ $requester['systemsUserId'] ?? '—' }}</td></tr>
                                <tr><th>Категория</th><td>{{ $requester['category'] ?? '—' }}</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="info-panel">
                            <h5 class="info-panel__title">Сотрудник</h5>
                            <table class="table table-sm table-borderless info-table mb-0">
                                <tbody>
                                <tr><th>ФИО</th><td>{{ $personName($responsible) ?: '—' }}</td></tr>
                                <tr><th>Ид специалиста</th><td>{{ $responsible['specialistId'] ?? '—' }}</td></tr>
                                <tr><th>Внешний ид</th><td>{{ $responsible['internalId'] ?? '—' }}</td></tr>
                                <tr><th>Ид категории</th><td>{{ $responsible['idCategory'] ?? '—' }}</td></tr>
                                <tr><th>Тип специалиста</th><td>{{ $responsible['specialistType'] ?? '—' }}</td></tr>
                               </tbody>
                            </table>
                        </div>

                        <div class="info-panel">
                            <h5 class="info-panel__title">Машина</h5>
                            <table class="table table-sm table-borderless info-table mb-0">
                                <tbody>
                                <tr><th>ФИО</th><td>{{ $personName($driver) ?: '—' }}</td></tr>
                                <tr><th>ID</th><td>{{ $driver['customerId'] ?? '—' }}</td></tr>
                                <tr><th>Телефон</th><td>{{ $driver['customerPhone'] ?? '—' }}</td></tr>
                                <tr><th>Email</th><td>{{ $driver['customerEmail'] ?? '—' }}</td></tr>
                                <tr><th>Адрес</th><td>{{ $driver['customerAddress'] ?? '—' }}</td></tr>

                               </tbody>
                            </table>
                        </div>

                        <div class="info-panel">
                            <h5 class="info-panel__title">Владелец</h5>
                            <table class="table table-sm table-borderless info-table mb-0">
                                <tbody>
                                <tr><th>ФИО</th><td>{{ $personName($owner) ?: '—' }}</td></tr>
                                <tr><th>ID</th><td>{{ $owner['customerId'] ?? '—' }}</td></tr>
                                <tr><th>Телефон</th><td>{{ $owner['customerPhone'] ?? '—' }}</td></tr>
                                <tr><th>Email</th><td>{{ $owner['customerEmail'] ?? '—' }}</td></tr>
                                <tr><th>Адрес</th><td>{{ $owner['customerAddress'] ?? '—' }}</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="col-12 col-xl-6">
                        <div class="info-panel">
                            <h5 class="info-panel__title">Список задач (tasks)</h5>
                            @if(count($tasks) > 0)
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered table-hover mb-0">
                                        <thead>
                                        <tr>
                                            <th style="width: 90px;">taskId</th>
                                            <th>Название</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($tasks as $task)
                                            <tr>
                                                <td>{{ $task['taskId'] ?? '—' }}</td>
                                                <td>{{ $task['taskName'] ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="text-muted">Задачи отсутствуют.</div>
                            @endif
                        </div>

                        <div class="info-panel">
                            <h5 class="info-panel__title">Содержание заказа (details)</h5>
                            @if(count($details) > 0)
                                <div class="details-list">
                                    @foreach($details as $index => $detail)
                                        @php
                                            $taskId = (string) ($detail['taskId'] ?? '');
                                            $taskTitle = $taskNameById[$taskId] ?? null;
                                            $title = $detail['text'] ?? $detail['positionName'] ?? $detail['description'] ?? 'Элемент details';
                                        @endphp
                                        <details class="detail-item" @if($index === 0) open @endif>
                                            <summary>
                                                <span class="badge badge-secondary mr-2">#{{ $index + 1 }}</span>
                                                <span class="mr-2"><strong>taskId:</strong> {{ $taskId !== '' ? $taskId : '—' }}</span>
                                                <span class="mr-2"><strong>category:</strong> {{ $detail['category'] ?? '—' }}</span>
                                                @if($taskTitle)
                                                    <span class="text-muted mr-2">{{ $taskTitle }}</span>
                                                @endif
                                                <span class="text-muted">{{ \Illuminate\Support\Str::limit((string) $title, 90) }}</span>
                                            </summary>

                                            <div class="detail-item__body">
                                                <div class="table-responsive mb-2">
                                                    <table class="table table-sm table-borderless info-table mb-0">
                                                        <tbody>
                                                        @foreach($detail as $key => $value)
                                                            @continue(is_array($value))
                                                            <tr>
                                                                <th>{{ $key }}</th>
                                                                <td>
                                                                    @if(is_bool($value))
                                                                        {{ $value ? 'true' : 'false' }}
                                                                    @elseif($value === null)
                                                                        null
                                                                    @else
                                                                        {{ $value }}
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>

                                                @foreach($detail as $key => $value)
                                                    @continue(!is_array($value))
                                                    <div class="mini-json mb-2">
                                                        <div class="mini-json__head">{{ $key }}</div>
                                                        <pre class="mini-json__body mb-0">{{ json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </details>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-muted">details отсутствует.</div>
                            @endif
                        </div>

                        <div class="info-panel">
                            <h5 class="info-panel__title">История статусов (processStatusRecords)</h5>
                            @if(count($records) > 0)
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>status</th>
                                            <th>timestamp</th>
                                            <th>createdBy</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($records as $record)
                                            <tr>
                                                <td>{{ $record['id'] ?? '—' }}</td>
                                                <td>{{ $record['status'] ?? '—' }}</td>
                                                <td>{{ $fmt($record['timestamp'] ?? null) }}</td>
                                                <td>{{ $record['createdBy'] ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="text-muted">История статусов отсутствует.</div>
                            @endif
                        </div>


                    </div>
                </div>

                <div class="info-panel mb-0">
                    <details class="json-collapsible">
                        <summary>Полный JSON (клик для раскрытия)</summary>
                        <pre class="service-json-view mt-2 mb-0">{{ $serviceJson }}</pre>
                    </details>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .service-info-page {
            background: #f8fafc;
        }

        .info-panel {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 12px;
        }

        .info-panel__title {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #1f2937;
        }

        .info-table th {
            width: 220px;
            color: #475569;
            font-weight: 600;
            white-space: nowrap;
            padding: .22rem .35rem;
        }

        .info-table td {
            color: #111827;
            padding: .22rem .35rem;
            word-break: break-word;
        }

        .details-list {
            display: grid;
            gap: 8px;
        }

        .detail-item {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #fff;
            overflow: hidden;
        }

        .detail-item > summary {
            cursor: pointer;
            list-style: none;
            padding: 10px 12px;
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
            font-size: 13px;
        }

        .detail-item > summary::-webkit-details-marker {
            display: none;
        }

        .detail-item__body {
            padding: 10px;
        }

        .mini-json {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
        }

        .mini-json__head {
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
            color: #475569;
            font-size: 12px;
            font-weight: 600;
            padding: 6px 10px;
        }

        .mini-json__body,
        .service-json-view {
            background: #0f172a;
            color: #e2e8f0;
            border-radius: 6px;
            padding: 10px;
            font-size: 12px;
            line-height: 1.4;
            white-space: pre-wrap;
            word-break: break-word;
            overflow: auto;
            max-height: 320px;
        }

        .service-json-view {
            border: 1px solid #1e293b;
            border-radius: 10px;
            padding: 14px;
            max-height: 75vh;
            font-size: 13px;
        }

        .json-collapsible > summary {
            cursor: pointer;
            font-weight: 700;
            color: #1f2937;
            outline: none;
        }

        @media (max-width: 991.98px) {
            .info-table th {
                width: 170px;
            }
        }
    </style>
@endpush
