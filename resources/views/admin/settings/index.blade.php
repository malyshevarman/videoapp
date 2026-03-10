@extends('layouts.admin')

@section('title', 'Настройки')
@section('page_title', 'Настройки')

@section('breadcrumb')
    <li class="breadcrumb-item active">Настройки</li>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="card card-outline card-primary shadow-sm">
            <div class="card-header">
                <h3 class="card-title mb-0">Защита внешнего API</h3>
            </div>

            <div class="card-body">
                <div class="mb-4">
                    <div class="mb-2">
                        <strong>Статус токена:</strong>
                        @if($tokenConfigured)
                            <span class="badge badge-success">Настроен</span>
                        @else
                            <span class="badge badge-danger">Не настроен</span>
                        @endif
                    </div>

                    <div class="text-muted">
                        @if($tokenUpdatedAt)
                            Последнее обновление: {{ \Carbon\Carbon::parse($tokenUpdatedAt)->format('d.m.Y H:i:s') }}
                        @else
                            Токен ещё не создавался.
                        @endif
                    </div>
                </div>

                @if($plainToken)
                    <div class="alert alert-warning">
                        <div class="font-weight-bold mb-2">Bearer token</div>
                        <div class="mb-2">Текущий токен отображается ниже и остаётся доступным в этом разделе до следующей генерации.</div>
                        <code class="d-block p-3 bg-white border rounded" style="word-break: break-all;">{{ $plainToken }}</code>
                    </div>
                @endif

                <div class="alert alert-light border">
                    Для запросов к внешнему API используйте заголовок
                    <code>Authorization: Bearer &lt;token&gt;</code>.
                    Без корректного токена метод <code>POST /api/services</code> будет возвращать <code>401</code>.
                </div>

                <form action="{{ route('admin.settings.token.regenerate') }}" method="POST" onsubmit="return confirm('Вы уверены, что хотите сгенерировать новый Bearer token? Старый токен перестанет работать.');">
                    @csrf
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-key mr-1"></i>
                        {{ $tokenConfigured ? 'Обновить токен' : 'Сгенерировать токен' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection
