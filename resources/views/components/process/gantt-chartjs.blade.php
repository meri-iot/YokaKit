<script defer src="{{ asset('js/chartjs-adapter-moment/chartjs-adapter-moment.js') }}"></script>
<script>
    /** ガントチャート種別: 基準信号 */
    const CHART_TYPE_BASE = 2;
    /** ガントチャート種別: 稼働信号 */
    const CHART_TYPE_WORK = 3;

    /**
     * ガントチャートのX軸表示範囲（開始・終了モーメント）を返す。
     *
     * hasFixedStart が true の場合は指定された start から range 分間を返す。
     * range が1440分未満（1日未満）の場合は現在時刻を中心に整時で切った範囲を返す。
     * それ以外（1日以上）の場合は当日の開始から range 分間を返す。
     *
     * @param {moment.Moment} now          現在時刻（サーバーオフセット補正済み）
     * @param {moment.Moment|string} start 固定開始時刻
     * @param {number} range               表示範囲（分）
     * @param {boolean} hasFixedStart      固定開始時刻を使用するかどうか
     * @returns {[moment.Moment, moment.Moment]} [min, max]
     */
    function getChartRange(now, start, range, hasFixedStart) {
        if (hasFixedStart) {
            return [moment(start), moment(start).add(range, 'minute')];
        }

        if (range < 1440) {
            const min = moment(now).add(30 - range / 2, 'minute').startOf('hour');
            return [min, moment(min).add(range, 'minute')];
        }

        const dayStart = moment(now).startOf('day');
        return [dayStart, moment(dayStart).add(range, 'minute')];
    }

    /**
     * baseRange と各範囲との重複時間の合計（ミリ秒）を返す。
     *
     * @param {[moment.Moment, moment.Moment]} baseRange 基準範囲 [start, end]
     * @param {[moment.Moment, moment.Moment][]} ranges   比較対象の範囲の配列
     * @returns {number} 重複時間の合計（ミリ秒）
     */
    function getOverlaps(baseRange, ranges) {
        return ranges.sum(r => {
            const start = moment.max(baseRange[0], r[0]);
            const end = moment.min(baseRange[1], r[1]);
            return start.isBefore(end) ? end.diff(start) : 0;
        });
    }

    /**
     * 稼働率表示を更新する。
     *
     * 基準信号（BASE）の最後の未確定区間と稼働信号（WORK）の重複時間から
     * 稼働率を計算し、対応するDOM要素のテキストを更新する。
     *
     * @param {Object} baseChart  基準信号のdataset（data: Array<{x: [moment.Moment, moment.Moment], notConfirmed?: boolean}>）
     * @param {Object} ganttChart 稼働信号のdataset（data: Array<{x: [moment.Moment, moment.Moment]}>, ganttChartId: number）
     * @returns {void}
     */
    function updateOperatingRate(baseChart, ganttChart) {
        const lastData = baseChart.data.last();
        if (lastData && lastData.notConfirmed) {
            const [start, end] = lastData.x;
            const total = end.diff(start);
            const work = getOverlaps([start, end], ganttChart.data.map(d => d.x));
            $(`#operating-rate-${ganttChart.ganttChartId}`).text(`${Math.trunc(work * 100 / total)}%`);
        }
    }

    /**
     * ガントチャートイベント配列を ON→OFF ペアが成立する形に正規化する。
     *
     * 最後のイベントが ON の場合は現在時刻までの未確定区間を追加し、
     * 先頭のイベントが OFF の場合はそれを除去する。
     *
     * @param {Array} events     ガントチャートイベントの配列
     * @param {number} processId 工程ID
     * @param {number} chartId   ガントチャートID
     * @param {moment.Moment} now 現在時刻
     * @returns {Array} 正規化済みイベント配列
     */
    function normalizeEvents(events, processId, chartId, now) {
        if (events.length === 0) {
            return events;
        }
        if (events.last().signal) {
            // 最後がONの場合、現在時刻までの未確定区間を追加
            events.push({
                process_id: processId,
                gantt_chart_id: chartId,
                at: moment(now),
                notConfirmed: true,
            });
        }
        if (!events.first().signal) {
            // 最初がOFFの場合は除去
            events.shift();
        }
        return events;
    }

    /**
     * ガントチャート1件分のChart.jsデータセットを構築する。
     *
     * @param {Object} g   ガントチャートオブジェクト
     * @param {moment.Moment} now 現在時刻
     * @returns {Object} Chart.jsデータセット
     */
    function buildDataset(g, now) {
        const events = normalizeEvents(g.gantt_chart_events || [], g.process_id, g.gantt_chart_id, now);
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
    }

    /**
     * Chart.jsの設定オプションを構築する。
     *
     * @param {Object} process ガントチャートを持つ工程オブジェクト
     * @param {moment.Moment} min X軸の最小値
     * @param {moment.Moment} max X軸の最大値
     * @returns {Object} Chart.jsオプション
     */
    function buildChartOptions(process, min, max) {
        const timeUnit = process.range <= 240 ? 'minute' : process.range <= 4320 ? 'hour' : 'day';
        return {
            animation: true,
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
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
            },
            barPercentage: 1, // 棒の幅を最大に設定
            categoryPercentage: 1, // カテゴリ内の棒の間隔をなくす
            scales: {
                x: {
                    type: 'time',
                    stacked: true,
                    min,
                    max,
                    ticks: {
                        color: 'lightgray',
                        maxRotation: 0, // 回転を無効化
                        minRotation: 0, // 回転を無効化
                    },
                    grid: {
                        color: '#555555',
                        drawTicks: false,
                    },
                    time: {
                        unit: timeUnit,
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
                        padding: 10, // ラベルとバーの間隔を設定
                        color: 'lightgray',
                        autoSkip: false, // 全ラベルを表示
                    },
                    grid: {
                        color: '#555555',
                        drawTicks: false,
                    },
                }
            },
            range: process.range,
        };
    }

    $(async () => {

        const processes = @json($processes);

        // サーバー時刻とのズレを取得
        const offset = await Util.getServerDateOffsetAsync(@json(route('date')));

        const startDate = @json($start);
        const hasFixedStart = Boolean(startDate);
        const now = moment().add(offset, 'ms');
        const start = startDate ? moment(startDate) : moment(now).startOf('day');
        const ganttChartDatasets = [];
        const charts = {};

        for (const process of processes.filter(p => p.gantt_charts.length)) {
            const canvas = document.getElementById(`gantt-chart-${process.process_id}`);
            const ctx = canvas.getContext('2d');
            canvas.height = 30 * process.gantt_charts.length + 38;

            const [min, max] = getChartRange(now, start, process.range, hasFixedStart);
            const datasets = process.gantt_charts.map(g => buildDataset(g, now));
            const chart = new Chart(ctx, {
                type: 'bar',
                options: buildChartOptions(process, min, max),
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
                if (!chart || !process || !ganttChart) {
                    return;
                }
                if (data.signal) {
                    ganttChart.data.push({
                        x: [moment(data.at), moment().add(offset, 'ms')],
                        y: ganttChart.ganttChartName,
                        notConfirmed: true,
                    });
                    if (ganttChart.ganttChartType === CHART_TYPE_BASE) {
                        process.gantt_charts
                            .filter(gc => gc.chart_type === CHART_TYPE_WORK)
                            .forEach(gc => $(`#operating-rate-${gc.gantt_chart_id}`).text('0%'));
                    } else if (ganttChart.ganttChartType === CHART_TYPE_WORK) {
                        const baseChart = ganttChartDatasets.find(g => g.processId === data.process_id && g.ganttChartType ===
                            CHART_TYPE_BASE);
                        if (baseChart) {
                            updateOperatingRate(baseChart, ganttChart);
                        }
                    }
                } else {
                    const last = ganttChart.data.last();
                    if (last && last.notConfirmed) {
                        delete last.notConfirmed;
                        last.x[1] = moment(data.at);
                        if (ganttChart.ganttChartType === CHART_TYPE_BASE) {
                            process.gantt_charts
                                .filter(gc => gc.chart_type === CHART_TYPE_WORK)
                                .forEach(gc => $(`#operating-rate-${gc.gantt_chart_id}`).text('--'));
                        } else if (ganttChart.ganttChartType === CHART_TYPE_WORK) {
                            const baseChart = ganttChartDatasets.find(g => g.processId === data.process_id && g.ganttChartType ===
                                CHART_TYPE_BASE);
                            if (baseChart) {
                                updateOperatingRate(baseChart, ganttChart);
                            }
                        }
                    }
                }
                chart.update();
            });

        /**
         * 全稼働信号の稼働率表示を初期化する。
         *
         * ganttChartDatasets の中から稼働信号（WORK）を抽出し、
         * 対応する基準信号（BASE）が存在する場合に稼働率を更新する。
         *
         * @returns {void}
         */
        function initOperatingRate() {
            for (const gcd of ganttChartDatasets.filter(g => g.ganttChartType === CHART_TYPE_WORK)) {
                const baseChart = ganttChartDatasets.find(g => g.processId === gcd.processId && g.ganttChartType === CHART_TYPE_BASE);
                if (baseChart) {
                    updateOperatingRate(baseChart, gcd);
                }
            }
        }

        initOperatingRate();
        if (!hasFixedStart) {
            setInterval(() => {
                const currentNow = moment().add(offset, 'ms');
                for (const chart of Object.values(charts)) {
                    const [min, max] = getChartRange(currentNow, start, chart.options.range, false);
                    chart.options.scales.x.min = min;
                    chart.options.scales.x.max = max;
                    chart.data.datasets.forEach(dataset => {
                        const last = dataset.data.last();
                        if (last && last.notConfirmed) {
                            last.x[1] = moment(currentNow);
                        }
                    });
                    chart.update();
                }
                initOperatingRate();
            }, 10000);
        }
    });
</script>
