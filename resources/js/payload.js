/**
 * moment.js
 * @typedef {import('moment').Moment} Moment
 */

'use strict';

window.Payload = class Payload extends Indicator {

    /**
     * コンストラクタ
     *
     * @param {Payload} payload
     */
    constructor(payload) {

        super(payload.cycleTimeMs, payload.overTimeMs)

        /** @type {number} 工程ID */
        this.processId = payload.processId;
        /** @type {string} 品番名 */
        this.partNumberName = payload.partNumberName;
        /** @type {Moment} 開始時刻 */
        this.start = moment(payload.start);
        /** @type {{string: Number}} 不良品数 */
        this.defectiveCounts = payload.defectiveCounts;
        /** @type {number} 目標値 */
        this.goal = payload.goal;

        /** @type {number} ラインID */
        super.lineId = payload.lineId;
        /** @type {Moment} 時刻 */
        super.at = moment(payload.at);
        /** @type {number} 生産数 */
        super.count = payload.count;
        /** @type {'RUNNING'|'CHANGEOVER'|'BREAKDOWN'|'COMPLETE'} ステータス */
        super.statusName = payload.statusName;
        /** @type {boolean} 計画停止時間中かどうか */
        super.inPlannedOutage = payload.inPlannedOutage;
        /** @type {boolean} カウント切替 */
        super.countSwitch = payload.countSwitch;
        /** @type {number} サイクルタイム[ms] */
        super.cycleTimeMs = payload.cycleTimeMs;
        /** @type {number} 操業時間[ms] */
        super.workingTime = payload.workingTime;
        /** @type {number} 負荷時間[ms] */
        super.loadingTime = payload.loadingTime;
        /** @type {number} 稼働時間[ms] */
        super.operatingTime = payload.operatingTime;
        /** @type {number} 正味稼働時間[ms] */
        super.netTime = payload.netTime;
        /** @type {number} チョコ停回数 */
        super.breakdownCount = payload.breakdownCount;
        /** @type {number} 段取り替え自動復帰回数 */
        super.autoResumeCount = payload.autoResumeCount;
    }

    /**
     * 不良品数を取得する
     *
     * @returns {number} 不良品数
     */
    defectiveCount() {
        return Object.values(this.defectiveCounts).sum(x => x) || 0;
    }
};
