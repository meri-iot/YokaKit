<div class="position-relative float-right pr-2" style="top:-2.75rem; height: 0;">
    @can('admin')
        @if ($process->isStopped())
            <a class="btn btn-tool pb-0 pt-0" href="{{ route('onoff.index', ['process' => $process]) }}" role="button">
                <i class="fa-solid fa-lg fa-message"></i>
            </a>
            <x-button-add class="pb-0 pt-0" href="{{ route('onoff.create', ['process' => $process]) }}" />
        @endif
    @endcan
</div>
<table class="mt-2 table">
    <thead>
        <tr>
            <th class="border-top-0 border-bottom-0">{{ __('yokakit.target_name', ['target' => __('yokakit.event')]) }}</th>
            <th class="border-top-0 border-bottom-0">{{ __('yokakit.target_message', ['target' => 'ON']) }}</th>
            <th class="border-top-0 border-bottom-0">{{ __('yokakit.target_message', ['target' => 'OFF']) }}</th>
            <th class="border-top-0 border-bottom-0">{{ __('yokakit.raspberry_pi') }}</th>
            <th class="border-top-0 border-bottom-0">{{ __('yokakit.pin_number') }}</th>
            @can('admin')
                @if ($process->isStopped())
                    <th class="border-top-0 border-bottom-0 w-1"></th>
                @endif
            @endcan
        </tr>
    </thead>
    <tbody>
        @foreach ($process->onOffs as $onOff)
            <tr class="text-muted">
                <td class="align-middle">{{ $onOff->event_name }}</td>
                <td class="align-middle">{{ $onOff->on_message }}</td>
                <td class="align-middle">{{ $onOff->off_message }}</td>
                <td class="align-middle">{{ $onOff->raspberryPi->raspberry_pi_name }}</td>
                <td class="align-middle">{{ $onOff->pinNumber() }}</td>
                @can('admin')
                    @if ($process->isStopped())
                        <td class="text-nowrap text-right align-middle">
                            {{-- ON-OFF編集ボタン --}}
                            <x-button-edit href="{{ route('onoff.edit', ['process' => $process, 'onOff' => $onOff]) }}" />
                            {{-- ON-OFF削除ボタン --}}
                            <x-button-delete target="onoff_{{ $onOff->on_off_id }}" />
                        </td>
                        {{-- ON-OFF削除ダイアログ --}}
                        <x-modal-delete id="onoff_{{ $onOff->on_off_id }}"
                            action="{{ route('onoff.destroy', ['process' => $process, 'onOff' => $onOff]) }}">
                            <strong>{{ __('yokakit.confirm_delete', ['target' => __('yokakit.notification')]) }}</strong>
                            <x-adminlte-card class="mt-4">
                                <strong>{{ __('yokakit.target_name', ['target' => __('yokakit.event')]) }}</strong>
                                <p class="ml-2 mt-1">{{ $onOff->event_name }}</p>
                                <hr>
                                <strong>{{ __('yokakit.target_message', ['target' => 'ON']) }}</strong>
                                <p class="ml-2 mt-1">{{ $onOff->on_message }}</p>
                                <hr>
                                <strong>{{ __('yokakit.target_message', ['target' => 'OFF']) }}</strong>
                                <p class="ml-2 mt-1">{{ $onOff->off_message }}</p>
                                <hr>
                                <strong>{{ __('yokakit.raspberry_pi') }}</strong>
                                <p class="ml-2 mt-1">{{ $onOff->raspberryPi->raspberry_pi_name }}</p>
                                <hr>
                                <strong>{{ __('yokakit.pin_number') }}</strong>
                                <p class="ml-2 mt-1">{{ $onOff->pinNumber() }}</p>
                            </x-adminlte-card>
                        </x-modal-delete>
                    @endif
                @endcan
            </tr>
        @endforeach
    </tbody>
</table>
