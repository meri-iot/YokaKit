@extends('components.header')

@section('title', __('yokakit.profile'))

@section('content')
    <div class="row">
        <div class="col-md-12">
            <x-adminlte-card>
                <strong>{{ __('yokakit.name') }}</strong>
                <p class="text-muted ml-2 mt-1">{{ $user->name }}</p>
                <hr>
                <strong>{{ __('yokakit.email') }}</strong>
                <p class="text-muted ml-2 mt-1">{{ $user->email }}</p>

                <a class="btn btn-primary" href="{{ route('user.edit') }}" role="button">
                    {{ __('yokakit.target_edit', ['target' => __('yokakit.profile')]) }}
                </a>
                <a class="btn btn-primary ml-2" href="{{ route('user.password') }}" role="button">
                    {{ __('yokakit.change_password') }}
                </a>
            </x-adminlte-card>
            @can('admin')
                <x-adminlte-card>
                    <form action="{{ route('user.token') }}" method="POST" autocomplete="off">
                        @csrf
                        <x-input name="token" value="{{ session('token') }}" label="{{ __('yokakit.webapi_token') }}" />
                        <x-adminlte-button class="btn-primary" type="submit" label="{{ __('yokakit.generate_token') }}" />
                    </form>
                </x-adminlte-card>
            @endcan
        </div>
    </div>
@endsection

@section('js')
    @include('components.toast')
@endsection
