<script defer src="{{ asset('js/chartjs-adapter-moment/chartjs-adapter-moment.js') }}"></script>
<script>
    function getChartRange(now, today, start, range) {
        const isShortRange = range < 1440;
        const isSameAsStart = now.isSame(start);

        if (isSameAsStart || isShortRange) {
            const min = moment(now).add(30 - range / 2, 'minute').startOf('hour');
            const max = moment(min).add(range, 'minute');

            const minDay = moment(min).startOf('day');
            const maxDay = moment(max).startOf('day');

            if (minDay.isBefore(today)) {
                return [moment(today), moment(today).add(range, 'minute')];
            }
            if (today.isBefore(maxDay)) {
                return [moment(max).subtract(range, 'minute'), maxDay];
            }
            return [min, max];
        }

        return [moment(start), moment(start).add(range, 'minute')];
    }

    function getOverlaps(baseRange, ranges) {
        return ranges.sum(r => {
            const start = moment.max(baseRange[0], r[0]);
            const end = moment.min(baseRange[1], r[1]);
            if (start.isBefore(end)) {
                return end.diff(start);
            } else {
                return 0;
            }
        });
    }

    function updateOperatingRate(baseChart, ganttChart) {
        const lastData = baseChart.data.last();
        if (lastData && lastData.notConfirmed) {
            const [start, end] = lastData.x;
            const total = end.diff(start);
            const work = getOverlaps([start, end], ganttChart.data.map(d => d.x));
            $(`#operating-rate-${ganttChart.ganttChartId}`).text(`${Math.trunc(work*100/total)}%`);
        }
    }

    $(async () => {

        const processes = @json($processes);
        console.log('processes', processes);

        // サーバー時刻とのズレを取得
        const offset = await Util.getServerDateOffsetAsync(@json(route('date')));

        const startDate = @json($start);
        const now = moment().add(offset, 'ms');
        const today = moment(now).startOf('day');
        const start = startDate ? moment(startDate) : moment(today);
        const ganttChartDatasets = [];
        const charts = {};

        for (const process of processes.filter(p => p.gantt_charts.length)) {

            const canvas = document.getElementById(`gantt-chart-${process.process_id}`);
            const ctx = canvas.getContext('2d');
            canvas.height = 30 * process.gantt_charts.length + 38;

            const [min, max] = getChartRange(now, today, start, process.range);
            const datasets = process.gantt_charts
                .map(g => {
                    const events = g.gantt_chart_events || [];
                    if (events.length !== 0) {
                        if (events.last().signal) {
                            // 最後がONの場合
                            events.push({
                                process_id: process.process_id,
                                gantt_chart_id: g.gantt_chart_id,
                                at: moment(now),
                                notConfirmed: true,
                            });
                        }
                        if (!events.first().signal) {
                            // 最初がOFFの場合
                            events.shift();
                        }
                    }
                    return {
                        data: [...Array(events.length / 2)]
                            .map((_, i) => ({
                                x: [moment(events[i * 2].at), moment(events[i * 2 + 1].at)],
                                y: g.chart_name,
                                notConfirmed: events[i * 2 + 1].notConfirmed,
                            })),
                        backgroundColor: g.chart_color,
                        ganttChartId: g.gantt_chart_id,
                        ganttChartName: g.chart_name,
                        ganttChartType: g.chart_type,
                        processId: g.process_id,
                    };
                });
            const chart = new Chart(ctx, {
                type: 'bar',
                options: {
                    animation: true,
                    indexAxis: 'y',
                    // grouped: true,
                    responsive: true,
                    maintainAspectRatio: false,
                    // aspectRatio: 2.65,
                    plugins: {
                        legend: {
                            display: false,
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const data = context.dataset.data[context.dataIndex];
                                    const start = moment(data.x[0]);
                                    const end = moment(data.x[1]);
                                    return `${start.format('YYYY-MM-DD HH:mm:ss')} ~ ${end.format('YYYY-MM-DD HH:mm:ss')}`;
                                }
                            }
                        },
                        // title: {
                        //     display: true,
                        //     color: '#fff',
                        //     text: 'Chart.js Horizontal Bar Chart'
                        // }
                    },
                    barPercentage: 1, // 棒の幅を最大に設定
                    categoryPercentage: 1, // カテゴリ内の棒の間隔をなくす
                    scales: {
                        x: {
                            // offset: false,
                            // position: "top",
                            type: 'time',
                            stacked: true,
                            // grid: {
                            //     offset: false
                            // }
                            min,
                            max,
                            ticks: {
                                color: 'lightgray',
                                maxRotation: 0, // 回転を無効化
                                minRotation: 0, // 回転を無効化
                            },
                            grid: {
                                // drawBorder: false,
                                color: '#555555',
                                drawTicks: false,
                                // drawTicks: false,
                            },
                            time: {
                                unit: (() => {
                                    if (process.range <= 240) {
                                        return 'minute';
                                    } else if (process.range <= 4320) {
                                        return 'hour';
                                    } else {
                                        return 'day';
                                    }
                                })(),
                                displayFormats: { // X軸の表示フォーマット
                                    day: 'YYYY-MM-DD',
                                    hour: process.range < 1440 ? 'H:mm' : 'YYYY-MM-DD HH:mm',
                                    minute: 'H:mm',
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            stacked: true,
                            ticks: {
                                // callback: function(value, index, ticks) {
                                //     // ラベルを切り詰める
                                //     const name = process.gantt_charts[index].chart_name;
                                //     // const maxLength = 15; // 最大文字数を指定（日本語、英数字、記号を含む場合）
                                //     // if (name.length > maxLength) {
                                //     //     return name.slice(0, maxLength) + '...'; // 長いラベルを切り詰める
                                //     // }
                                //     // return name;

                                //     // const nameLength = getStringByteCount(name);
                                //     // console.log(value, index, ticks);
                                //     const maxLength = 15; // 最大文字数
                                //     // if (nameLength > maxLength) {
                                //     //     // return (new Blob([name])).toString();
                                //     //     return unicodeSubstring(name, 0, maxLength) + ' …'; // 長い場合に切り詰め
                                //     // } else {
                                //     //     return name.padEnd(maxLength, '_'); // 短い場合にスペースで埋める
                                //     // }
                                //     const aa = truncateOrPadString(name, maxLength);
                                //     console.log('aa', aa);
                                //     return aa;
                                // },
                                // align: 'start', // ラベルを左揃え
                                padding: 10, // ラベルとバーの間隔を設定
                                color: 'lightgray',
                                autoSkip: false, // 全ラベルを表示
                            },
                            grid: {
                                // drawBorder: false,
                                color: '#555555',
                                drawTicks: false,
                            },
                        }
                    },
                    range: process.range,
                },
                data: {
                    labels: process.gantt_charts.map(g => g.chart_name),
                    datasets,
                },
            });
            ganttChartDatasets.push(...datasets);
            charts[process.process_id] = chart;
        }

        Echo.join('gantt-chart')
            .listen('GanttChartNotification', (data) => {
                const chart = charts[data.process_id];
                const process = processes.find(p => p.process_id === data.process_id);
                const ganttChart = ganttChartDatasets.find(g => g.ganttChartId === data.gantt_chart_id);
                if (chart && process && ganttChart) {
                    // console.log('GanttChartNotification', data, ganttChart);
                    if (data.signal) {
                        ganttChart.data.push({
                            x: [moment(data.at), moment().add(offset, 'ms')],
                            y: ganttChart.ganttChartName,
                            notConfirmed: true,
                        });
                        if (ganttChart.ganttChartType === 2) {
                            process.gantt_charts
                                .filter(gc => gc.chart_type === 3)
                                .forEach(gc => $(`#operating-rate-${gc.gantt_chart_id}`).text('0%'));
                        } else if (ganttChart.ganttChartType === 3) {
                            const baseChart = ganttChartDatasets.find(g => g.processId === data.process_id && g.ganttChartType === 2);
                            if (baseChart) {
                                updateOperatingRate(baseChart, ganttChart);
                            }
                        }
                    } else {
                        const last = ganttChart.data.last();
                        if (last && last.notConfirmed && !data.signal) {
                            delete last.notConfirmed;
                            last.x[1] = moment(data.at);
                            if (ganttChart.ganttChartType === 2) {
                                process.gantt_charts
                                    .filter(gc => gc.chart_type === 3)
                                    .forEach(gc => $(`#operating-rate-${gc.gantt_chart_id}`).text('--'));
                            } else if (ganttChart.ganttChartType === 3) {
                                const baseChart = ganttChartDatasets.find(g => g.processId === data.process_id && g.ganttChartType ===
                                    2);
                                if (baseChart) {
                                    updateOperatingRate(baseChart, ganttChart);
                                }
                            }
                        }
                    }
                    chart.update();
                }
            });

        function initOperateingRate() {
            for (const gcd of ganttChartDatasets) {
                // 稼働信号
                if (gcd.ganttChartType === 3) {
                    const baseChart = ganttChartDatasets.find(g => g.processId === gcd.processId && g.ganttChartType === 2);
                    if (baseChart) {
                        updateOperatingRate(baseChart, gcd);
                    }
                }
            }
        }

        initOperateingRate();
        if (today.isSame(start)) {
            setInterval(() => {
                const _now = moment().add(offset, 'ms');
                const _today = moment(_now).startOf('day');
                for (const chart of Object.values(charts)) {
                    const [min, max] = getChartRange(_now, _today, start, chart.options.range);
                    chart.options.scales.x.min = min;
                    chart.options.scales.x.max = max;
                    chart.data.datasets.forEach(dataset => {
                        const last = dataset.data.last();
                        if (last && last.notConfirmed) {
                            last.x[1] = moment(_now);
                            // console.log('Section', last.x[1].diff(last.x[0]));
                        }
                    });
                    chart.update();
                }
                initOperateingRate();
            }, 10000);
        }
    });
</script>
