'use strict';

/**
 * 生産指標計算クラス
 *
 * 生産ラインの各種KPI（生産数、良品率、達成率など）を計算します。
 * PayloadDataから派生したデータを受け取り、リアルタイム指標を提供します。
 */
window.Indicator = class Indicator {

    /**
     * コンストラクタ
     *
     * @param {number} cycleTimeMs サイクルタイム[ms]
     * @param {number} overTimeMs オーバータイム[ms]
     */
    constructor(cycleTimeMs, overTimeMs) {
        /** @type {number} ラインID */
        this.lineId;
        /** @type {Moment} 時刻 */
        this.at;
        /** @type {number} 生産数 */
        this.count;
        /** @type {boolean} カウント切替 */
        this.countSwitch;
        /** @type {Object<string, number>} 不良品ライン別カウント */
        this.defectiveCounts = {};
        /** @type {'RUNNING'|'CHANGEOVER'|'BREAKDOWN'|'COMPLETE'} ステータス */
        this.statusName;
        /** @type {boolean} 計画停止時間中かどうか */
        this.inPlannedOutage;
        /** @type {number} 操業時間[ms] */
        this.workingTime;
        /** @type {number} 負荷時間[ms] */
        this.loadingTime;
        /** @type {number} 稼働時間[ms] */
        this.operatingTime;
        /** @type {number} 正味稼働時間[ms] */
        this.netTime;
        /** @type {number} チョコ停回数 */
        this.breakdownCount;
        /** @type {number} 段取り替え自動復帰回数 */
        this.autoResumeCount;
        /** @type {number} サイクルタイム[ms] */
        this.cycleTimeMs = cycleTimeMs;
        /** @type {number} オーバータイム[ms] */
        this.overTimeMs = overTimeMs;
    }

    /**
     * ステータスが完了であるかどうか
     *
     * @returns {boolean} trueの場合ステータスは完了
     */
    isComplete() {
        return this.statusName === 'COMPLETE';
    }

    /**
     * ステータスが段取り替えであるかどうか
     *
     * @returns {boolean} trueの場合ステータスは段取り替え
     */
    isChangeover() {
        return this.statusName === 'CHANGEOVER';
    }

    /**
     * ステータスがチョコ停であるかどうか
     *
     * @returns {boolean} trueの場合ステータスはチョコ停
     */
    isBreakdown() {
        return this.statusName === 'BREAKDOWN';
    }

    /**
     * 不良品数を計算する（全ラインの不良品合計）
     *
     * @returns {number} 不良品数
     */
    defectiveCount() {
        if (!this.defectiveCounts || Object.keys(this.defectiveCounts).length === 0) {
            return 0;
        }
        return Object.values(this.defectiveCounts).reduce((sum, count) => sum + count, 0);
    }

    /**
     * 総生産数を計算する（良品+不良品）
     *
     * @returns {number} 総生産数
     */
    totalCount() {
        if (this.countSwitch) {
            return this.count + this.defectiveCount();
        } else {
            return this.count;
        }
    }

    /**
     * 良品数を計算する
     *
     * @returns {number} 良品数
     */
    goodCount() {
        return this.totalCount() - this.defectiveCount();
    }

    /**
     * 良品率を計算する（0～1）
     *
     * @returns {number} 良品率
     */
    goodRate() {
        const totalCount = this.totalCount();
        if (totalCount <= 0) {
            return 0;
        }
        return this.goodCount() / totalCount;
    }

    /**
     * 不良品率を計算する（0～1）
     *
     * @returns {number} 不良品率
     */
    defectiveRate() {
        const totalCount = this.totalCount();
        if (totalCount <= 0) {
            return 0;
        }
        return this.defectiveCount() / totalCount;
    }

    /**
     * 計画生産数を計算する
     *
     * @returns {number} 計画値
     */
    planCount() {
        return Math.trunc(this.operatingTime / this.cycleTimeMs);
    }

    /**
     * 達成率を計算する（実績/計画）（0～1）
     *
     * @returns {number} 達成率
     */
    achievementRate() {
        const planCount = this.planCount();
        if (planCount <= 0) {
            return 0;
        }
        return this.goodCount() / this.planCount();
    }

    /**
     * 平均サイクルタイムを計算する
     *
     * @returns {number} サイクルタイム[s]
     */
    cycleTime() {
        const totalCount = this.totalCount();
        const productionCount = totalCount - this.autoResumeCount - this.breakdownCount + (this.isBreakdown() ? 1 : 0);
        if (productionCount <= 0) {
            return 0;
        }
        return Math.max(0, (this.netTime - this.overTimeMs * this.breakdownCount) / (productionCount * 1000));
    }

    /**
     * 時間稼働率を計算する（操業時間/負荷時間）（0～1）
     *
     * @returns {number} 時間稼働率
     */
    timeOperatingRate() {
        if (this.loadingTime <= 0) {
            return 0;
        }
        return this.operatingTime / this.loadingTime;
    }

    /**
     * 性能稼働率を計算する（正味稼働時間/操業時間）（0～1）
     *
     * @returns {number} 性能稼働率
     */
    performanceOperatingRate() {
        if (this.operatingTime <= 0) {
            return 0;
        }
        return this.netTime / this.operatingTime;
    }

    /**
     * 設備総合効率（OEE）を計算する（良品率×時間稼働率×性能稼働率）
     *
     * OEE = (良品数/計画値) × (操業時間/負荷時間) × (正味稼働時間/操業時間)
     *
     * @returns {number} OEE (0～1)
     */
    overallEquipmentEffectiveness() {
        return this.goodRate() * this.timeOperatingRate() * this.performanceOperatingRate();
    }
}
