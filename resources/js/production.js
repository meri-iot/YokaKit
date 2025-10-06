/**
 * moment.js
 * @typedef {import('moment').Moment} Moment
 */

'use strict';

window.Production = class Production extends Indicator {

    /**
     * コンストラクタ
     *
     * @param {*} production
     * @param {number} cycleTimeMs サイクルタイム[ms]
     * @param {number} overTimeMs サイクルタイム[ms]
     * @param {boolean} countSwitch カウントスイッチ
     */
    constructor(production, cycleTimeMs, overTimeMs, countSwitch) {

        super(cycleTimeMs, overTimeMs);

        /** @type {number} ラインID */
        super.lineId = production.production_line_id;
        /** @type {Moment} 時刻 */
        super.at = moment(production.at);
        /** @type {number} 生産数 */
        super.count = production.count;
        /** @type {'RUNNING'|'CHANGEOVER'|'BREAKDOWN'|'COMPLETE'} ステータス */
        super.statusName = production.status_name;
        /** @type {boolean} 計画停止時間中かどうか */
        super.inPlannedOutage = production.in_planned_outage;
        /** @type {number} 操業時間[ms] */
        super.workingTime = production.working_time;
        /** @type {number} 負荷時間[ms] */
        super.loadingTime = production.loading_time;
        /** @type {number} 稼働時間[ms] */
        super.operatingTime = production.operating_time;
        /** @type {number} 正味稼働時間[ms] */
        super.netTime = production.net_time;
        /** @type {number} チョコ停回数 */
        super.breakdownCount = production.breakdown_count;
        /** @type {number} 段取り替え自動復帰回数 */
        super.autoResumeCount = production.auto_resume_count;
        /** @type {boolean} カウント切替 */
        super.countSwitch = countSwitch;

        /** @type {number} 不良品数 */
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
