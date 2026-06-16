/**
 * moment.js
 * @typedef {import('moment').Moment} Moment
 */

'use strict';

window.Payload = class Payload extends Indicator {

    /**
     * コンストラクタ
     *
     * @param {Object} payload サーバーから受信した生産ペイロードオブジェクト
     */
    constructor(payload) {

        super(payload.cycleTimeMs, payload.overTimeMs);

        // Payload 固有のプロパティ
        /** @type {number} 工程ID */
        this.processId = payload.processId;
        /** @type {string} 品番名 */
        this.partNumberName = payload.partNumberName;
        /** @type {Moment} 開始時刻 */
        this.start = moment(payload.start);
        /** @type {number} 目標値 */
        this.goal = payload.goal;

        // Indicator 継承プロパティ（super()で未設定のもの）
        /** @type {number} ラインID */
        this.lineId = payload.lineId;
        /** @type {Moment} 時刻 */
        this.at = moment(payload.at);
        /** @type {number} 生産数 */
        this.count = payload.count;
        /** @type {'RUNNING'|'CHANGEOVER'|'BREAKDOWN'|'COMPLETE'} ステータス */
        this.statusName = payload.statusName;
        /** @type {boolean} 計画停止時間中かどうか */
        this.inPlannedOutage = payload.inPlannedOutage;
        /** @type {boolean} カウント切替 */
        this.countSwitch = payload.countSwitch;
        /** @type {Object<string, number>} 不良品数（ライン別） */
        this.defectiveCounts = payload.defectiveCounts;
        /** @type {number} 操業時間[ms] */
        this.workingTime = payload.workingTime;
        /** @type {number} 負荷時間[ms] */
        this.loadingTime = payload.loadingTime;
        /** @type {number} 稼働時間[ms] */
        this.operatingTime = payload.operatingTime;
        /** @type {number} 正味稼働時間[ms] */
        this.netTime = payload.netTime;
        /** @type {number} チョコ停回数 */
        this.breakdownCount = payload.breakdownCount;
        /** @type {number} 段取り替え自動復帰回数 */
        this.autoResumeCount = payload.autoResumeCount;
    }
};
