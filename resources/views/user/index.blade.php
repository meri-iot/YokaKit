@extends('components.header')

@section('title', __('yokakit.target_list', ['target' => __('yokakit.user')]))

@section('content')
    @include('adminlte::partials.common.preloader')
    <div class="row">
        @php
            $heads = ['#', __('yokakit.name'), __('yokakit.email'), __('yokakit.role'), ['label' => '', 'no-export' => true, 'width' => 5]];
            $columns = [['visible' => false], null, null, null, ['orderable' => false, 'searchable' => false]];
            $config = [
                'columns' => $columns,
                'language' => ['url' => route('datatables')],
            ];
        @endphp
        <x-datatable-index href="{{ route('user.create') }}" add="{{ __('yokakit.target_add', ['target' => __('yokakit.user')]) }}" :heads="$heads"
            :config="$config">
            @foreach ($users as $user)
                <tr>
                    <td></td>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->role->description }}</td>
                    <td class="text-nowrap text-right align-middle">
                        <x-button-delete target="user_{{ $user->id }}" />
                    </td>
                    {{-- 削除ダイアログ --}}
                    <x-modal-delete id="user_{{ $user->id }}" action="{{ route('user.destroy', ['user' => $user]) }}">
                        <strong>{{ __('yokakit.confirm_delete', ['target' => __('yokakit.user')]) }}</strong>
                        <x-adminlte-card class="mt-4">
                            <strong>{{ __('yokakit.name') }}</strong>
                            <p class="ml-2 mt-1">{{ $user->name }}</p>
                            <hr>
                            <strong>{{ __('yokakit.email') }}</strong>
                            <p class="mb-0 ml-2 mt-1">{{ $user->email }}</p>
                            <hr>
                            <strong>{{ __('yokakit.role') }}</strong>
                            <p class="mb-0 ml-2 mt-1">{{ $user->role->description }}</p>
                        </x-adminlte-card>
                    </x-modal-delete>
                </tr>
            @endforeach
        </x-datatable-index>
    </div>
@endsection

@section('js')
    @include('components.toast')
@endsection
