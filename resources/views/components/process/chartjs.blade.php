<script defer src="{{ asset('js/chartjs-adapter-moment/chartjs-adapter-moment.js') }}"></script>
<script>
    $(async () => {

        // 工程
        const process = @json($process);

        // 異常イベント
        const abnormalEvents = @json($process->sensorEvents);
        const abnormals = new Set([]);

        // 生産ラインのカウント
        const lines = @json($lines);

        // 履歴
        const history = @json($history);

        // サイクルタイム[ms]
        const cycleTimeMs = history.cycle_time * 1000;

        // オーバータイム[ms]
        const overTimeMs = history.over_time * 1000;

        // ステータス
        let currentStatus = history.status_name;

        // 指標となる生産ライン
        const indicatorLine = lines.find(x => x.indicator === true);

        // 指標アイコンカラー
        if (indicatorLine) {
            $('#indicator-icon').css('color', indicatorLine.chart_color);
        }

        // 生産ラインのカウント描画チャートのデータセット
        const datasets = lines
            .map((x, i) => ({
                label: x.line_name,
                borderColor: x.chart_color,
                backgroundColor: x.chart_color,
                pointRadius: 0,
                lineId: x.production_line_id,
                data: createInitialCountChart(x),
            }));

        // 生産数の表示
        datasets.forEach(x => updateCount(x.lineId, lastCount(x.data)));
        // 指標ラインや初回実績が未生成でも、画面自体は表示を継続する。
        const lastProduction = indicatorLine && indicatorLine.productions ? indicatorLine.productions.last() : null;
        if (lastProduction) {
            lastProduction.defective_count = lines
                .filter(x => x.defective && x.parent_id === indicatorLine.production_line_id)
                .sum(x => {
                    const lastDefectiveProduction = x.defective_productions.last();
                    return lastDefectiveProduction ? lastDefectiveProduction.count : 0;
                });
            const firstPayload = new Production(lastProduction, cycleTimeMs, overTimeMs, history.count_switch);
            updateIndicator(firstPayload);
            updateStatus(firstPayload);
        }
        addAbnormalEvents(abnormalEvents);

        // 計画値の描画データセット
        const planDatasets = {
            label: @json(__('yokakit.plan_count')),
            borderColor: process.plan_color,
            backgroundColor: process.plan_color,
            pointRadius: 0,
            lineId: 0,
            data: createInitialPlanChart(indicatorLine),
        };

        // 計画値の更新
        updatePlanCount(lastCount(planDatasets.data));

        // チャートを作成
        const ctx = document.getElementById('production').getContext('2d');
        const chart = new Chart(ctx, {
            // 線グラフ
            type: 'line',
            // グラフ設定
            options: {
                animation: true,
                aspectRatio: 2.65,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        // onClick: (e, item, legend) => {
                        //     const meta = e.chart.getDatasetMeta(item.datasetIndex);
                        //     meta.hidden = meta.hidden === null ? !meta.hidden : null;
                        //     e.chart.update();
                        // }
                        labels: {
                            color: 'white'
                        }
                    },
                    tooltip: {
                        intersect: false,
                        // titleColor: 'black',
                        // titleFont: {
                        //     size: 16,
                        // },
                        // bodyColor: 'black',
                        // bodyFont: {
                        //     size: 16,
                        // },
                        padding: 10,
                        // backgroundColor: 'white',
                        cornerRadius: 2,
                        // displayColors: false
                        callbacks: {
                            title: (title) => {
                                return title[0].raw.x.format('YYYY年MM月DD日(ddd) HH:mm:ss');
                            },
                            // label: (data) => {
                            //     return `${data.dataset.name}: ${data.formattedValue}`;
                            // },
                        },
                    },
                },
                responsive: true,
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            minUnit: 'minute',
                            maxUnit: 'hour',
                            displayFormats: {
                                minute: 'HH:mm',
                                hour: 'HH:mm',
                            },
                            // stepSize: 0.25,
                        },
                        ticks: {
                            color: 'lightgray',
                            font: {
                                size: 14,
                            },
                            padding: 8,
                            maxRotation: 0,
                        },
                        grid: {
                            display: false,
                            // drawBorder: false,
                            // color: '#555555',
                            // drawTicks: false,
                        },
                    },
                    y: {
                        min: 0,
                        ticks: {
                            color: 'lightgray',
                            precision: 0,
                            font: {
                                size: 14,
                            },
                            padding: 8,
                        },
                        grid: {
                            drawBorder: false,
                            color: '#555555',
                            drawTicks: false,
                        },
                    },
                },
            },
            data: {
                datasets: [...datasets, planDatasets]
            }
        });

        if (history.status_name != 'COMPLETE') {
            // 指標通知イベントの登録
            Echo.join('summary')
                .listen('ProductionSummaryNotification', (data) => {
                    if (process.process_id === data.processId) {
                        const payload = new Payload(data);
                        updateChart(payload);
                        if (data.indicator) {
                            updateIndicator(payload);
                            updateStatus(payload);
                        }
                    }
                });
            // アラーム通知イベントの登録
            Echo.join('alarm')
                .listen('SensorAlarmNotification', (data) => {
                    if (process.process_id !== data.process_id) {
                        return;
                    }
                    if (data.is_start) {
                        addAbnormalEvents([data]);
                    } else {
                        removeAbnormalEvent(data);
                    }
                });
        }

        function createInitialCountChart(line) {
            return (line.defective ? line.defective_productions : line.productions)
                .reduce((acc, x) => {
                    const last = acc.last();
                    if (last) {
                        const at = moment(x.at);
                        if (last.x < at && last.y < x.count) {
                            acc.push({
                                x: at,
                                y: x.count,
                            });
                        }
                    } else {
                        acc.push({
                            x: moment(x.at),
                            y: x.count,
                        });
                    }
                    return acc;
                }, []);
        }

        function createInitialPlanChart(indicatorLine) {
            if (!indicatorLine) {
                return [];
            }

            return indicatorLine.productions
                .reduce((acc, x) => {
                    const last = acc.last();
                    const planCount = Math.trunc(x.operating_time / cycleTimeMs);
                    if (last) {
                        if (last.inPlannedOutage === true && x.in_planned_outage === false) {
                            acc.pop();
                            acc.push({
                                x: moment(x.at).subtract(cycleTimeMs, 'ms'),
                                y: last.y,
                                inPlannedOutage: true,
                            });
                        }
                        if (x.in_planned_outage === true || x.status_name === 'CHANGEOVER' || last.y < planCount) {
                            acc.push({
                                x: moment(x.at),
                                y: planCount,
                                inPlannedOutage: x.in_planned_outage,
                            });
                        }
                    } else {
                        acc.push({
                            x: moment(x.at),
                            y: planCount,
                            inPlannedOutage: x.in_planned_outage,
                        });
                    }
                    return acc;
                }, []);
        }

        function lastCount(points) {
            const last = points.last();
            return last ? last.y : 0;
        }

        /**
         * 指標を更新する
         *
         * @param {Payload} payload 指標
         */
        function updateIndicator(payload) {
            $('#good-rate').text(payload.goodRate().rate());
            $('#achievement-rate').text(payload.achievementRate().rate());
            $('#cycle-time').text(Math.round(payload.cycleTime()));
            $('#time-operating-rate').text(payload.timeOperatingRate().rate());
            $('#performance-operating-rate').text(payload.performanceOperatingRate().rate());
            $('#overall-equipment-effectiveness').text(payload.overallEquipmentEffectiveness().rate());
        }

        /**
         * 工程のステータスを更新する
         *
         * @param {Payload} payload 生産データ
         */
        function updateStatus(payload) {

            const productionStatus = $('#production-status');
            const runningText = @json(__('yokakit.running'));
            const breakdownText = @json(__('yokakit.breakdown'));
            const changeoverText = @json(__('yokakit.changeover'));
            const plannedOutageText = @json(__('yokakit.planned_outage'));

            if (payload.isBreakdown()) {
                if (payload.inPlannedOutage) {
                    productionStatus
                        .removeClass()
                        .addClass('badge badge-info')
                        .text(plannedOutageText);
                } else {
                    productionStatus
                        .removeClass()
                        .addClass('badge badge-danger')
                        .text(breakdownText);
                }
            } else if (payload.isChangeover()) {
                productionStatus
                    .removeClass()
                    .addClass('badge badge-warning')
                    .text(changeoverText);
            } else {
                productionStatus
                    .removeClass()
                    .addClass('badge badge-light')
                    .text(runningText);
            }
        }

        /**
         * グラフを更新する
         *
         * @param {Payload} payload 生産データ
         */
        function updateChart(payload) {

            const series = datasets.find(x => x.lineId === payload.lineId);
            if (series == null) {
                return;
            }

            if (lastCount(series.data) < payload.count) {
                updateCount(payload.lineId, payload.count);
                series.data.push({
                    x: payload.at,
                    y: payload.count,
                });
            }
            for (const [id, count] of Object.entries(payload.defectiveCounts)) {
                const defectiveSeries = datasets.find(x => x.lineId == id);
                if (defectiveSeries == null) {
                    continue;
                }
                if (lastCount(defectiveSeries.data) < count) {
                    updateCount(id, count);
                    defectiveSeries.data.push({
                        x: payload.at,
                        y: count,
                    });
                }
            }

            const planCount = payload.planCount();
            const lastPlanCount = lastCount(planDatasets.data);
            if (lastPlanCount !== planCount) {
                updatePlanCount(planCount);
            }
            if (payload.inPlannedOutage === true || payload.isChangeover()) {
                if (planDatasets.data.length === 0 || planDatasets.data.last().x.isBefore(payload.at)) {
                    planDatasets.data.push({
                        x: payload.at,
                        y: planCount,
                    });
                }
            } else if (lastPlanCount < planCount) {
                if (planDatasets.data.length === 0 || planDatasets.data.last().x.isBefore(payload.at)) {
                    planDatasets.data.push({
                        x: payload.at,
                        y: planCount,
                    });
                }
            }
            chart.update();
        }

        /**
         * 生産カウント数を表示する
         *
         * @param {number} lineId 生産ラインID
         * @param {number} count カウント
         */
        function updateCount(lineId, count) {
            $(`#production-line-${lineId}`).text(count).blink(100, 2);
        }

        /**
         * 計画値を更新する
         *
         * @param {number} planCount 計画値
         */
        function updatePlanCount(planCount) {
            $('#production-line-plan').text(planCount).blink(100, 2);
        }

        function addAbnormalEvents(events) {
            const statusArea = $('#status-area');
            for (const event of events) {
                if (abnormals.has(event.sensor_id)) {
                    continue;
                }
                statusArea.append($('<span>', {
                    id: `sensor-id-${event.sensor_id}`,
                    class: 'badge ml-1',
                    style: 'font-size: 100%; background-color: var(--orange); color: black;',
                    text: event.alarm_text,
                }));
                abnormals.add(event.sensor_id);
            }
        }

        function removeAbnormalEvent(event) {
            $(`#sensor-id-${event.sensor_id}`).remove();
            abnormals.delete(event.sensor_id);
        }
    });
</script>
