/**
 * moment.js
 * @typedef {import('moment').Moment} Moment
 */

'use strict';

window.Production = class Production extends Indicator {

    /**
     * コンストラクタ
     *
     * @param {Object} production サーバーから取得した生産レコードオブジェクト
     * @param {number} cycleTimeMs サイクルタイム[ms]
     * @param {number} overTimeMs オーバータイム[ms]
     * @param {boolean} countSwitch カウント切替
     */
    constructor(production, cycleTimeMs, overTimeMs, countSwitch) {

        super(cycleTimeMs, overTimeMs);

        /** @type {number} ラインID */
        this.lineId = production.production_line_id;
        /** @type {Moment} 時刻 */
        this.at = moment(production.at);
        /** @type {number} 生産数 */
        this.count = production.count;
        /** @type {'RUNNING'|'CHANGEOVER'|'BREAKDOWN'|'COMPLETE'} ステータス */
        this.statusName = production.status_name;
        /** @type {boolean} 計画停止時間中かどうか */
        this.inPlannedOutage = production.in_planned_outage;
        /** @type {boolean} カウント切替 */
        this.countSwitch = countSwitch;
        /** @type {number} 操業時間[ms] */
        this.workingTime = production.working_time;
        /** @type {number} 負荷時間[ms] */
        this.loadingTime = production.loading_time;
        /** @type {number} 稼働時間[ms] */
        this.operatingTime = production.operating_time;
        /** @type {number} 正味稼働時間[ms] */
        this.netTime = production.net_time;
        /** @type {number} チョコ停回数 */
        this.breakdownCount = production.breakdown_count;
        /** @type {number} 段取り替え自動復帰回数 */
        this.autoResumeCount = production.auto_resume_count;

        /** @type {number} 不良品数（サーバー集計済み合計値） */
        this.defectives = production.defective_count;
    }

    /**
     * 不良品数を取得する
     *
     * @returns {number} 不良品数
     */
    defectiveCount() {
        return this.defectives;
    }
}
