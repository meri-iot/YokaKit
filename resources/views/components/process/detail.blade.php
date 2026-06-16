<div class="position-relative float-right pr-2" style="top:-2.75rem; height: 0;">
    <a class="btn btn-tool" href="{{ route('production.index', ['process' => $process]) }}" role="button">
        <i class="fa-solid fa-lg fa-history"></i>
    </a>
    @can('admin')
        @if ($process->isStopped())
            <a class="btn btn-tool" href="{{ route('process.edit', ['process' => $process]) }}" role="button">
                <i class="fa-solid fa-lg fa-edit"></i>
            </a>
            <x-adminlte-button data-toggle="modal" data-target="#delete-modal" theme="tool" icon="fa-solid fa-lg fa-trash" />
            <x-modal-delete id="delete-modal" action="{{ route('process.destroy', ['process' => $process]) }}">
                <strong>{{ __('yokakit.confirm_delete', ['target' => __('yokakit.process')]) }}</strong>
                <x-adminlte-card class="mt-4">
                    <strong>{{ __('yokakit.target_name', ['target' => __('yokakit.process')]) }}</strong>
                    <p class="mb-0 ml-2 mt-1">{{ $process->process_name }}</p>
                </x-adminlte-card>
            </x-modal-delete>
        @endif
    @endcan
</div>
<div class="mt-2 p-4">
    <strong>{{ __('yokakit.target_name', ['target' => __('yokakit.process')]) }}</strong>
    <p class="text-muted ml-2 mt-1">{{ $process->process_name }}</p>
    <hr>
    <strong>{{ __('yokakit.plan_color') }}</strong>
    <p class="text-muted ml-2 mt-1">
        <i class="fa-solid fa-fw fa-square-full" style="padding-top:1px; color: {{ $process->plan_color }}"></i>
    </p>
    <hr>
    <strong>{{ __('yokakit.count_switch') }}</strong>
    <p class="text-muted ml-2 mt-1">{{ $process->count_switch ? __('yokakit.good_count') : __('yokakit.number_of_production') }}</p>
    <hr>
    <strong>{{ __('yokakit.gantt_chart') }}{{ __('yokakit.range') }}</strong>
    <p class="text-muted ml-2 mt-1">{{ __('yokakit.target_hour', ['target' => $process->range / 60]) }}</p>
    <hr>
    <div class="float-right mt-4">
        @if ($process->raspberryPis->count() !== 0)
            {{-- 品番切り替えボタン --}}
            <a class="btn btn-sm btn-default mr-2" href="{{ route('production.create', ['process' => $process]) }}" role="button">
                <i class="fa-solid fa-fw fa-rotate"></i>
                {{ __('yokakit.switch_part_number') }}
            </a>
        @endif
        @if (!$process->isStopped())
            @if ($process->isChangeover())
                {{-- 生産開始ボタン --}}
                <x-adminlte-button class="btn-sm mr-2" data-toggle="modal" data-target="#stop-changeover"
                    label="{{ __('yokakit.start_production') }}" icon="fa-solid fa-fw fa-play" />
            @else
                {{-- 段取り替えボタン --}}
                <x-adminlte-button class="btn-sm mr-2" data-toggle="modal" data-target="#start-changeover" label="{{ __('yokakit.changeover') }}"
                    icon="fa-solid fa-fw fa-pause" />
            @endif
            {{-- 停止ボタン --}}
            <x-adminlte-button class="btn-sm" data-toggle="modal" data-target="#stop" label="{{ __('yokakit.stop') }}"
                icon="fa-solid fa-fw fa-stop" />
        @endif
    </div>
    {{-- 工程ステータス --}}
    <strong>{{ __('yokakit.status') }}</strong>
    <p class="text-muted ml-2 mt-1">
        @if ($process->isStopped())
            {{ __('yokakit.stop') }}
        @else
            {{ $process->productionHistory->status->description }} :
            {{ $process->productionHistory->part_number_name }}
        @endif
    </p>
    <hr>
    <strong>{{ __('yokakit.remark') }}</strong>
    <p class="text-muted text-area ml-2 mt-1">{{ $process->remark }}</p>
</div>
