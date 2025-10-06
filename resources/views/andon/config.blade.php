@extends('components.header')

@section('title', __('yokakit.target_config', ['target' => __('yokakit.andon')]))

@section('content')
    @include('adminlte::partials.common.preloader')
    <x-form-edit action="{{ route('andon.update') }}" back="{{ route('home') }}">
        <x-input name="row_count" value="{!! $config->row_count !!}" label="{{ __('yokakit.row_count') }}" icon="grip-lines" required />
        <x-select name="column_count" label="{{ __('yokakit.column_count') }}" :options="$columns" icon="grip-lines-vertical"
            selected="{{ $config->column_count }}" required />
        <div class="form-group required">
            <label class="control-label" for="A">{{ __('yokakit.layout') }}</label>
            <div class="row mx-auto" id="sortable">
                @foreach ($processes as $process)
                    <div class="col-{{ 12 / $config->column_count }} sortable-process" id="process-{{ $process->process_id }}"
                        style="opacity: @if (!$process->andonLayout->is_display) 0.25 @endif">
                        <div class="form-control bg-secondary mb-2">
                            <div class="float-left pr-2">
                                @if ($process->andonLayout->is_display)
                                    <input class="checkbox" name="layouts[{{ $process->process_id }}][display]" type="checkbox"
                                        value="{{ $process->process_id }}" checked>
                                @else
                                    <input class="checkbox" name="layouts[{{ $process->process_id }}][display]" type="checkbox"
                                        value="{{ $process->process_id }}">
                                @endif
                            </div>
                            <div class="text-truncate">{{ $process->process_name }}</div>
                            <input name="layouts[{{ $process->process_id }}][process_id]" type="hidden" value="{{ $process->process_id }}">
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <x-input name="auto_play_speed" value="{!! $config->auto_play_speed !!}" label="{{ __('yokakit.auto_play_speed') }}{{ __('yokakit.unit_millisecond') }}"
            icon="play" required />
        <x-input name="slide_speed" value="{!! $config->slide_speed !!}" label="{{ __('yokakit.slide_speed') }}{{ __('yokakit.unit_millisecond') }}"
            icon="forward" required />
        <x-select name="easing" label="{{ __('yokakit.easing') }}" :options="$easing" icon="ellipsis" selected="{{ $config->easing }}" required />
        <x-input name="font_ratio" value="{{ $config->font_ratio }}" label="{{ __('yokakit.font_ratio') }}" icon="font" required />
        <x-select name="item_column_count" label="{{ __('yokakit.item_column_count') }}" :options="$columns" icon="grip-lines-vertical"
            selected="{{ $config->item_column_count }}" required />
        <div class="row">
            <div class="col-{{ 12 / $config->item_column_count }} d-flex justify-content display-item">
                <x-input-switch name="is_show_part_number" label="{{ __('yokakit.part_number') }}" checked="{{ $config->is_show_part_number }}" />
            </div>
            <div class="col-{{ 12 / $config->item_column_count }} d-flex justify-content display-item">
                <x-input-switch name="is_show_start" label="{{ __('yokakit.start_time') }}" checked="{{ $config->is_show_start }}" />
            </div>
            <div class="col-{{ 12 / $config->item_column_count }} d-flex justify-content display-item">
                <x-input-switch name="is_show_good_count" label="{{ __('yokakit.is_show_good_count') }}" checked="{{ $config->is_show_good_count }}" />
            </div>
            <div class="col-{{ 12 / $config->item_column_count }} d-flex justify-content display-item">
                <x-input-switch name="is_show_defective_count" label="{{ __('yokakit.is_show_defective_count') }}"
                    checked="{{ $config->is_show_defective_count }}" />
            </div>
            <div class="col-{{ 12 / $config->item_column_count }} d-flex justify-content display-item">
                <x-input-switch name="is_show_good_rate" label="{{ __('yokakit.is_show_good_rate') }}" checked="{{ $config->is_show_good_rate }}" />
            </div>
            <div class="col-{{ 12 / $config->item_column_count }} d-flex justify-content display-item">
                <x-input-switch name="is_show_defective_rate" label="{{ __('yokakit.is_show_defective_rate') }}"
                    checked="{{ $config->is_show_defective_rate }}" />
            </div>
            <div class="col-{{ 12 / $config->item_column_count }} d-flex justify-content display-item">
                <x-input-switch name="is_show_plan_count" label="{{ __('yokakit.is_show_plan_count') }}" checked="{{ $config->is_show_plan_count }}" />
            </div>
            <div class="col-{{ 12 / $config->item_column_count }} d-flex justify-content display-item">
                <x-input-switch name="is_show_achievement_rate" label="{{ __('yokakit.is_show_achievement_rate') }}"
                    checked="{{ $config->is_show_achievement_rate }}" />
            </div>
            <div class="col-{{ 12 / $config->item_column_count }} d-flex justify-content display-item">
                <x-input-switch name="is_show_cycle_time" label="{{ __('yokakit.is_show_cycle_time') }}" checked="{{ $config->is_show_cycle_time }}" />
            </div>
            <div class="col-{{ 12 / $config->item_column_count }} d-flex justify-content display-item">
                <x-input-switch name="is_show_time_operating_rate" label="{{ __('yokakit.is_show_time_operating_rate') }}"
                    checked="{{ $config->is_show_time_operating_rate }}" />
            </div>
            <div class="col-{{ 12 / $config->item_column_count }} d-flex justify-content display-item">
                <x-input-switch name="is_show_performance_operating_rate" label="{{ __('yokakit.is_show_performance_operating_rate') }}"
                    checked="{{ $config->is_show_performance_operating_rate }}" />
            </div>
            <div class="col-{{ 12 / $config->item_column_count }} d-flex justify-content display-item">
                <x-input-switch name="is_show_overall_equipment_effectiveness" label="{{ __('yokakit.is_show_overall_equipment_effectiveness') }}"
                    checked="{{ $config->is_show_overall_equipment_effectiveness }}" />
            </div>
            <div class="col-{{ 12 / $config->item_column_count }} d-flex justify-content display-item">
                <x-input-switch name="is_show_goal" label="{{ __('yokakit.is_show_goal') }}" checked="{{ $config->is_show_goal }}" />
            </div>
        </div>
    </x-form-edit>
@endsection

@push('js')
    <script>
        $(() => {
            $('#sortable').sortable({
                cursor: 'move',
                placeholder: 'placeholder border border-secondary',
                start: (event, ui) => {
                    $('.placeholder').addClass(`col-${12/currentColumn} mb-2`);
                },
                stop: (event, ui) => {
                    ui.item.css({
                        position: '',
                        left: '',
                        top: ''
                    });
                }
            });

            let currentColumn = @json($config).column_count;
            $('#column_count').on('change', (event) => {
                const nextColumn = $(event.target).val();
                $('.sortable-process')
                    .removeClass(`col-${12/currentColumn}`)
                    .addClass(`col-${12/nextColumn}`);
                currentColumn = nextColumn;
            });

            let currentItemColumn = @json($config).item_column_count;
            $('#item_column_count').on('change', (event) => {
                const nextColumn = $(event.target).val();
                $('.display-item')
                    .removeClass(`col-${12/currentItemColumn}`)
                    .addClass(`col-${12/nextColumn}`);
                currentItemColumn = nextColumn;
            });

            $('.checkbox').on('change', (event) => {
                const val = $(event.target).val();
                const checked = $(event.target).prop('checked');
                if (checked) {
                    $(`#process-${val}`).css('opacity', '');
                } else {
                    $(`#process-${val}`).css('opacity', 0.25);
                }
            });
        });
    </script>
@endpush
