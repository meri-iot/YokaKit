'use strict';

// Node 環境で window・moment グローバルをシミュレートして各クラスを読み込む
global.window = global;
global.moment = require('moment');
require('../../resources/js/indicator.js');
require('../../resources/js/production.js');

const Production = global.Production;

/**
 * テスト用の最小生産レコードオブジェクトを生成するヘルパー。
 * サーバーの DB レコード相当のオブジェクト（スネークケース）を模倣する。
 *
 * デフォルト値:
 *   production_line_id = 10
 *   at                 = '2026-04-17T12:00:00'
 *   count              = 100
 *   status_name        = 'RUNNING'
 *   in_planned_outage  = false
 *   working_time       = 30,600,000 ms
 *   loading_time       = 28,800,000 ms (8時間)
 *   operating_time     = 27,000,000 ms (7.5時間)
 *   net_time           = 25,200,000 ms (7時間)
 *   breakdown_count    = 0
 *   auto_resume_count  = 0
 *   defective_count    = 0
 *
 * コンストラクタ引数のデフォルト値:
 *   cycleTimeMs  = 30,000 ms (30秒)
 *   overTimeMs   =  5,000 ms ( 5秒)
 *   countSwitch  = false  (count = 総生産数として扱う)
 */
function makeProduction(recordOverrides = {}, cycleTimeMs = 30000, overTimeMs = 5000, countSwitch = false) {
    const record = {
        production_line_id: 10,
        at: '2026-04-17T12:00:00',
        count: 100,
        status_name: 'RUNNING',
        in_planned_outage: false,
        working_time: 30600000,
        loading_time: 28800000,
        operating_time: 27000000,
        net_time: 25200000,
        breakdown_count: 0,
        auto_resume_count: 0,
        defective_count: 0,
        ...recordOverrides,
    };
    return new Production(record, cycleTimeMs, overTimeMs, countSwitch);
}

// ─────────────────────────────────────────────
// constructor — レコードフィールドのマッピング
// ─────────────────────────────────────────────
describe('constructor — レコードフィールドのマッピング', () => {
    test('production_line_id が lineId にマッピングされる', () => {
        const p = makeProduction({ production_line_id: 99 });
        expect(p.lineId).toBe(99);
    });

    test('at が moment オブジェクトに変換される', () => {
        const p = makeProduction({ at: '2026-04-17T15:30:00' });
        expect(moment.isMoment(p.at)).toBe(true);
        expect(p.at.format('HH:mm')).toBe('15:30');
    });

    test('count が設定される', () => {
        const p = makeProduction({ count: 250 });
        expect(p.count).toBe(250);
    });

    test('status_name が statusName にマッピングされる', () => {
        const p = makeProduction({ status_name: 'BREAKDOWN' });
        expect(p.statusName).toBe('BREAKDOWN');
    });

    test('in_planned_outage が inPlannedOutage にマッピングされる', () => {
        const p = makeProduction({ in_planned_outage: true });
        expect(p.inPlannedOutage).toBe(true);
    });

    test('working_time が workingTime にマッピングされる', () => {
        const p = makeProduction({ working_time: 12345 });
        expect(p.workingTime).toBe(12345);
    });

    test('loading_time が loadingTime にマッピングされる', () => {
        const p = makeProduction({ loading_time: 28000000 });
        expect(p.loadingTime).toBe(28000000);
    });

    test('operating_time が operatingTime にマッピングされる', () => {
        const p = makeProduction({ operating_time: 26000000 });
        expect(p.operatingTime).toBe(26000000);
    });

    test('net_time が netTime にマッピングされる', () => {
        const p = makeProduction({ net_time: 24000000 });
        expect(p.netTime).toBe(24000000);
    });

    test('breakdown_count が breakdownCount にマッピングされる', () => {
        const p = makeProduction({ breakdown_count: 5 });
        expect(p.breakdownCount).toBe(5);
    });

    test('auto_resume_count が autoResumeCount にマッピングされる', () => {
        const p = makeProduction({ auto_resume_count: 3 });
        expect(p.autoResumeCount).toBe(3);
    });

    test('defective_count が defectives にマッピングされる', () => {
        const p = makeProduction({ defective_count: 12 });
        expect(p.defectives).toBe(12);
    });
});

// ─────────────────────────────────────────────
// constructor — コンストラクタ引数のマッピング
// ─────────────────────────────────────────────
describe('constructor — コンストラクタ引数のマッピング', () => {
    test('cycleTimeMs が super() 経由で設定される', () => {
        const p = makeProduction({}, 15000, 5000, false);
        expect(p.cycleTimeMs).toBe(15000);
    });

    test('overTimeMs が super() 経由で設定される', () => {
        const p = makeProduction({}, 30000, 3000, false);
        expect(p.overTimeMs).toBe(3000);
    });

    test('countSwitch が設定される', () => {
        const p = makeProduction({}, 30000, 5000, true);
        expect(p.countSwitch).toBe(true);
    });
});

// ─────────────────────────────────────────────
// defectiveCount() — オーバーライドメソッド
// ─────────────────────────────────────────────
describe('defectiveCount()', () => {
    test('defective_count の値をそのまま返す', () => {
        const p = makeProduction({ defective_count: 15 });
        expect(p.defectiveCount()).toBe(15);
    });

    test('defective_count が 0 のとき 0 を返す', () => {
        const p = makeProduction({ defective_count: 0 });
        expect(p.defectiveCount()).toBe(0);
    });

    test('defectiveCounts オブジェクトではなく defectives の値を使う（親クラスとの違い）', () => {
        // Indicator 基底クラスでは defectiveCounts オブジェクトを集計するが、
        // Production は defective_count（サーバー集計済み単一値）を使う。
        // defectiveCounts を設定しても defectiveCount() の結果に影響しないことを確認する。
        const p = makeProduction({ defective_count: 5 });
        p.defectiveCounts = { lineA: 999 }; // 親クラスのフィールドを書き換えても
        expect(p.defectiveCount()).toBe(5);  // 戻り値は変わらない
    });
});

// ─────────────────────────────────────────────
// Indicator 継承メソッド — Production でも正しく動作する
// ─────────────────────────────────────────────
describe('Indicator 継承メソッド', () => {
    test('isComplete() — statusName が COMPLETE のとき true を返す', () => {
        const p = makeProduction({ status_name: 'COMPLETE' });
        expect(p.isComplete()).toBe(true);
    });

    test('isChangeover() — statusName が CHANGEOVER のとき true を返す', () => {
        const p = makeProduction({ status_name: 'CHANGEOVER' });
        expect(p.isChangeover()).toBe(true);
    });

    test('isBreakdown() — statusName が BREAKDOWN のとき true を返す', () => {
        const p = makeProduction({ status_name: 'BREAKDOWN' });
        expect(p.isBreakdown()).toBe(true);
    });

    test('totalCount() — countSwitch=false のとき count をそのまま返す', () => {
        // defective_count はスカラー値なので defectiveCounts={} のまま
        // totalCount = count = 100
        const p = makeProduction({ count: 100, defective_count: 10 }, 30000, 5000, false);
        expect(p.totalCount()).toBe(100);
    });

    test('totalCount() — countSwitch=true のとき count + defectiveCount を返す', () => {
        // count=90(良品), defective_count=10 → total=100
        const p = makeProduction({ count: 90, defective_count: 10 }, 30000, 5000, true);
        expect(p.totalCount()).toBe(100);
    });

    test('goodCount() — totalCount - defectiveCount', () => {
        // countSwitch=false: total=100, defective=10 → good=90
        const p = makeProduction({ count: 100, defective_count: 10 }, 30000, 5000, false);
        expect(p.goodCount()).toBe(90);
    });

    test('goodRate() — 不良品 10/100 のとき 0.9 を返す', () => {
        const p = makeProduction({ count: 100, defective_count: 10 }, 30000, 5000, false);
        expect(p.goodRate()).toBeCloseTo(0.9);
    });

    test('goodRate() — 不良品なしのとき 1.0 を返す', () => {
        const p = makeProduction({ count: 100, defective_count: 0 });
        expect(p.goodRate()).toBe(1.0);
    });

    test('planCount() — operatingTime / cycleTimeMs を切り捨てて返す', () => {
        // 27,000,000 / 30,000 = 900
        const p = makeProduction();
        expect(p.planCount()).toBe(900);
    });

    test('achievementRate() — goodCount / planCount を返す', () => {
        // goodCount=100, planCount=900
        const p = makeProduction({ count: 100, defective_count: 0 });
        expect(p.achievementRate()).toBeCloseTo(100 / 900);
    });

    test('timeOperatingRate() — operatingTime / loadingTime を返す', () => {
        // 27,000,000 / 28,800,000 = 0.9375
        const p = makeProduction();
        expect(p.timeOperatingRate()).toBeCloseTo(0.9375);
    });

    test('performanceOperatingRate() — netTime / operatingTime を返す', () => {
        const p = makeProduction();
        expect(p.performanceOperatingRate()).toBeCloseTo(25200000 / 27000000);
    });

    test('overallEquipmentEffectiveness() — OEE = goodRate × tOR × pOR', () => {
        const p = makeProduction({ count: 100, defective_count: 0 });
        const expected = 1.0 * (27000000 / 28800000) * (25200000 / 27000000);
        expect(p.overallEquipmentEffectiveness()).toBeCloseTo(expected);
    });
});
