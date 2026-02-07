@extends('layouts.admin')

@section('title', 'Сервисы')
@section('page_title', 'Сервисы')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.services.index') }}">Сервисы</a></li>
@endsection

@section('content')


    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Сервисы</h3>

                        <div class="card-tools">
                            <form method="GET" action="{{ route('admin.services.index') }}">
                            <div class="input-group input-group-sm" style="width: 150px;">
                                <input type="text" name="table_search" class="form-control float-right" placeholder="Поиск по ID"
                                       value="{{ request('table_search') }}">
                                <div class="input-group-append">
                                    <button type="submit" class="btn btn-default">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            </form>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body table-responsive p-0">
                        <table class="table table-hover text-nowrap">
                            <thead>
                            <tr>
                                <th>ID внешний </th>
                                <th>Статус</th>
                                <th>Клиент</th>
                                <th>Дата</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($orders as $order)
                                <tr>
                                    <td>{{ $order->order_id }}</td>
                                    <td>
                                        {{ $order->processStatus ?? '' }}
                                    </td>
                                    <td>
                                        {{ $order->client['customerFirstName'] ?? '' }}
                                        {{ $order->client['customerLastName'] ?? '' }}
                                    </td>
                                    <td>{{ $order->created_at->format('d-m-Y H:i') }}</td>
                                    <td class="text-nowrap">
                                        <a href="{{ route('admin.services.edit', $order->id) }}"
                                           class="btn btn-sm btn-primary mr-1"
                                           title="Редактировать">
                                            <i class="fas fa-edit"></i>
                                        </a>

                                    </td>

                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Пагинация -->
                    <div class="mt-3">
                        {{ $orders->links() }}
                    </div>

                    <!-- /.card-body -->
                </div>
                <!-- /.card -->
            </div>
        </div>
    </div><!-- /.container-fluid -->
@endsection

@push('scripts')

@endpush
