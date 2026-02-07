@extends('layouts.services')

@section('content')
    <div id="service-client"
         data-service='@json($service)'
         data-items='@json($items)'>
    </div>

@endsection
