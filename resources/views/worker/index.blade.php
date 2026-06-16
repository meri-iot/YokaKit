@extends('components.header')

@section('title', __('yokakit.target_list', ['target' => __('yokakit.worker')]))

@section('content')
    @include('adminlte::partials.common.preloader')
    <div class="row">
        @php
            $heads = [
                '#',
                __('yokakit.identification_number'),
                __('yokakit.target_name', ['target' => __('yokakit.worker')]),
                __('yokakit.mac_address'),
            ];
            $columns = [['visible' => false], null, null, null];
            if (Gate::allows('admin')) {
                array_push($heads, ['label' => '', 'no-export' => true, 'width' => 5]);
                array_push($columns, ['orderable' => false, 'searchable' => false]);
            }
            $config = [
                'columns' => $columns,
                'language' => ['url' => route('datatables')],
            ];
        @endphp
        <x-datatable-index href="{{ route('worker.create') }}" add="{{ __('yokakit.target_add', ['target' => __('yokakit.worker')]) }}" :heads="$heads"
            :config="$config">
            @foreach ($workers as $worker)
                <tr>
                    <td></td>
                    <td class="align-middle">{{ $worker->identification_number }}</td>
                    <td class="align-middle">{{ $worker->worker_name }}</td>
                    <td class="align-middle">{{ $worker->mac_address }}</td>
                    @can('admin')
                        <td class="text-nowrap text-right align-middle">
                            <x-button-edit href="{{ route('worker.edit', ['worker' => $worker]) }}" />
                            <x-button-delete target="worker_{{ $worker->worker_id }}" />
                        </td>
                        {{-- 削除ダイアログ --}}
                        <x-modal-delete id="worker_{{ $worker->worker_id }}" action="{{ route('worker.destroy', ['worker' => $worker]) }}">
                            <strong>{{ __('yokakit.confirm_delete', ['target' => __('yokakit.worker')]) }}</strong>
                            <x-adminlte-card class="mt-4">
                                <strong>{{ __('yokakit.identification_number') }}</strong>
                                <p class="ml-2 mt-1">{{ $worker->identification_number }}</p>
                                <hr>
                                <strong>{{ __('yokakit.target_name', ['target' => __('yokakit.worker')]) }}</strong>
                                <p class="mb-0 ml-2 mt-1">{{ $worker->worker_name }}</p>
                            </x-adminlte-card>
                        </x-modal-delete>
                    @endcan
                </tr>
            @endforeach
        </x-datatable-index>
    </div>
@endsection

@section('js')
    @include('components.toast')
@endsection
