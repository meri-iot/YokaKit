'use strict';

window.Indicator = class Indicator {

    constructor(cycleTimeMs, overTimeMs) {
        /** @type {number} ラインID */
        this.lineId;
        /** @type {Moment} 時刻 */
        this.at;
        /** @type {number} 生産数 */
        this.count;
        /** @type {boolean} カウント切替 */
        this.countSwitch;
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
     * ステータスが完了であるかどうかを取得する
     *
     * @returns {boolean} trueの場合ステータスは完了
     */
    isComplete() {
        return this.statusName === 'COMPLETE';
    }

    /**
     * ステータスが段取り替えであるかどうかを取得する
     *
     * @returns {boolean} trueの場合ステータスは完了
     */
    isChangeover() {
        return this.statusName === 'CHANGEOVER';
    }

    /**
     * ステータスがチョコ停であるかどうかを取得する
     *
     * @returns {boolean} trueの場合ステータスはチョコ停
     */
    isBreakdown() {
        return this.statusName === 'BREAKDOWN';
    }

    /**
     * 生産数を取得する
     *
     * @returns {number} 生産数
     */
    totalCount() {
        if (this.countSwitch) {
            return this.count + this.defectiveCount();
        } else {
            return this.count;
        }
    }

    /**
     * 良品数を取得する
     *
     * @returns {number} 良品数
     */
    goodCount() {
        return this.totalCount() - this.defectiveCount();
    }

    /**
     * 良品率を取得する
     *
     * @returns {number} 良品率(0~1)
     */
    goodRate() {
        const totalCount = this.totalCount();
        if (totalCount <= 0) {
            return 0;
        } else {
            return (totalCount - this.defectiveCount()) / totalCount;
        }
    }

    /**
     * 不良品率を取得する
     *
     * @returns {number} 不良品率(0~1)
     */
    defectiveRate() {
        const totalCount = this.totalCount();
        if (totalCount <= 0) {
            return 0;
        } else {
            return this.defectiveCount() / totalCount;
        }
    }

    /**
     * 不良品数を取得する
     *
     * @returns {number} 不良品数
     */
    defectiveCount() {
        return 0;
    }

    /**
     * 計画値を取得する
     *
     * @returns {number} 計画値
     */
    planCount() {
        return Math.trunc(this.operatingTime / this.cycleTimeMs);
    }

    /**
     * 達成率を取得する
     *
     * @returns {number} 達成率(0~1)
     */
    achievementRate() {
        const planCount = this.planCount();
        if (planCount <= 0) {
            return 0;
        } else {
            return this.goodCount() / this.planCount();
        }
    }

    /**
     * サイクルタイムを取得する
     *
     * @returns {number} サイクルタイム[s]
     */
    cycleTime() {
        const totalCount = this.totalCount();
        const productionCount = totalCount - this.autoResumeCount - this.breakdownCount + (this.isBreakdown() ? 1 : 0);
        if (productionCount <= 0) {
            return 0;
        } else {
            return Math.max(0, (this.netTime - this.overTimeMs * this.breakdownCount) / (productionCount * 1000));
        }
    }

    /**
     * 時間稼働率を取得する
     *
     * @returns {number} 時間稼働率(0~1)
     */
    timeOperatingRate() {
        if (this.loadingTime <= 0) {
            return 0;
        } else {
            return this.operatingTime / this.loadingTime;
        }
    }

    /**
     * 性能稼働率を取得する
     *
     * @returns {number} 性能稼働率(0~1)
     */
    performanceOperatingRate() {
        if (this.operatingTime <= 0) {
            return 0;
        } else {
            return this.netTime / this.operatingTime;
        }
    }

    /**
     * 設備総合効率を取得する
     *
     * @returns {number} 設備総合効率(0~1)
     */
    overallEquipmentEffectiveness() {
        return this.goodRate() * this.timeOperatingRate() * this.performanceOperatingRate();
    }
}
