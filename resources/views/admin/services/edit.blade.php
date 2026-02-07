@extends('layouts.admin')

@section('title', 'Сервисы')
@section('page_title', 'Сервисы')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.services.index') }}">Сервисы</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.services.edit', $service->id) }}">{{$service->id}}</a></li>
@endsection
@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">

                <div id="service-edit"
                     data-service='@json($service)'>
                </div>

            </div>
        </div>
    </div><!-- /.container-fluid -->
@endsection

@push('scripts')

@endpush
