@extends('components.header', ['breadcrumbs' => $process])

@section('title', $process->process_name . '：' . __('yokakit.notification'))

@section('content')
    <div class="row">
        <div class="col-12">
            <x-adminlte-card>
                <div class="timeline" id="timeline">
                    @foreach ($process->onOffEvents as $event)
                        <div class="timeline-content">
                            <div class="timeline-item">
                                <div class="timeline-body">
                                    {{ "[{$event->at}]　{$event->event_name}：　{$event->message}" }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-adminlte-card>
        </div>
    </div>
@endsection

@push('css')
    <style>
        .timeline>.timeline-content:first-child>.timeline-item {
            color: #ffc107;
            font-size: 1.5rem;
        }

        .timeline>.timeline-content:not(:first-child)>.timeline-item {
            color: #6c757d;
            font-size: 1.5rem;
        }
    </style>
@endpush

@push('js')
    <script>
        $(() => {
            const timeline = $('#timeline');
            Echo.join('onoff')
                .listen('OnOffNotificationEvent', (data) => {
                    console.log('OnOffEvent', data);
                    const datetime = moment(data.at).format('YYYY-MM-DD HH:mm:ss');
                    const message = `[${datetime}]　${data.event_name}：　${data.message}`;
                    const body = $('<div>').addClass('timeline-body').append(message);
                    const item = $('<div>').addClass('timeline-item').append(body);
                    const div = $('<div>').addClass('timeline-content').append(item);
                    timeline.prepend(div);
                    $('#timeline>div:nth-child(11)').remove();
                });
        });
    </script>
@endpush
