@extends('layouts.app')
@section('content')
<section>
    @if(session('success'))
    <div class="alert alert-success">
        <small>{{session('success')}}</small>
    </div>
    @endif

    @if(session('message'))
    <div class="alert alert-warning">
        <small>{{session('message')}}</small>
    </div>
    @endif

    <h1>{{ $user->name }}</h1>
</section>
@endsection