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

    <div>
        <h1>Grilla de videos</h1>
    </div>
</section>

@endsection