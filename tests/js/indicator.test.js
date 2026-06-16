'use strict';

// Node 環境で window グローバルをシミュレートして indicator.js を読み込む
global.window = global;
require('../../resources/js/indicator.js');

const Indicator = global.Indicator;

/**
 * テスト用 Indicator インスタンスを生成するヘルパー。
 * デフォルト値を持つ基本状態を作り、必要なフィールドだけオーバーライドできる。
 *
 * デフォルト値:
 *   cycleTimeMs   = 30,000 ms (30秒)
 *   overTimeMs    =  5,000 ms ( 5秒)
 *   count         = 100
 *   countSwitch   = false  (count = 総生産数として扱う)
 *   defectiveCounts = {}
 *   statusName    = 'RUNNING'
 *   loadingTime   = 28,800,000 ms (8時間)
 *   operatingTime = 27,000,000 ms (7.5時間)
 *   netTime       = 25,200,000 ms (7時間)
 *   breakdownCount   = 0
 *   autoResumeCount  = 0
 */
function makeIndicator(overrides = {}) {
    const params = {
        cycleTimeMs: 30000,
        overTimeMs: 5000,
        count: 100,
        countSwitch: false,
        defectiveCounts: {},
        statusName: 'RUNNING',
        inPlannedOutage: false,
        loadingTime: 28800000,
        operatingTime: 27000000,
        netTime: 25200000,
        workingTime: 30600000,
        breakdownCount: 0,
        autoResumeCount: 0,
        ...overrides,
    };

    const ind = new Indicator(params.cycleTimeMs, params.overTimeMs);
    Object.assign(ind, params);
    return ind;
}

// ─────────────────────────────────────────────
// constructor
// ─────────────────────────────────────────────
describe('constructor', () => {
    test('cycleTimeMs・overTimeMs がコンストラクタ引数で設定される', () => {
        const ind = new Indicator(15000, 3000);
        expect(ind.cycleTimeMs).toBe(15000);
        expect(ind.overTimeMs).toBe(3000);
    });

    test('defectiveCounts は空オブジェクトで初期化される', () => {
        const ind = new Indicator(10000, 1000);
        expect(ind.defectiveCounts).toEqual({});
    });
});

// ─────────────────────────────────────────────
// ステータス判定メソッド
// ─────────────────────────────────────────────
describe('isComplete()', () => {
    test('statusName が COMPLETE のとき true を返す', () => {
        expect(makeIndicator({ statusName: 'COMPLETE' }).isComplete()).toBe(true);
    });

    test.each(['RUNNING', 'CHANGEOVER', 'BREAKDOWN'])(
        'statusName が %s のとき false を返す',
        (status) => {
            expect(makeIndicator({ statusName: status }).isComplete()).toBe(false);
        }
    );
});

describe('isChangeover()', () => {
    test('statusName が CHANGEOVER のとき true を返す', () => {
        expect(makeIndicator({ statusName: 'CHANGEOVER' }).isChangeover()).toBe(true);
    });

    test.each(['RUNNING', 'BREAKDOWN', 'COMPLETE'])(
        'statusName が %s のとき false を返す',
        (status) => {
            expect(makeIndicator({ statusName: status }).isChangeover()).toBe(false);
        }
    );
});

describe('isBreakdown()', () => {
    test('statusName が BREAKDOWN のとき true を返す', () => {
        expect(makeIndicator({ statusName: 'BREAKDOWN' }).isBreakdown()).toBe(true);
    });

    test.each(['RUNNING', 'CHANGEOVER', 'COMPLETE'])(
        'statusName が %s のとき false を返す',
        (status) => {
            expect(makeIndicator({ statusName: status }).isBreakdown()).toBe(false);
        }
    );
});

// ─────────────────────────────────────────────
// defectiveCount()
// ─────────────────────────────────────────────
describe('defectiveCount()', () => {
    test('defectiveCounts が空のとき 0 を返す', () => {
        expect(makeIndicator().defectiveCount()).toBe(0);
    });

    test('defectiveCounts が null のとき 0 を返す', () => {
        expect(makeIndicator({ defectiveCounts: null }).defectiveCount()).toBe(0);
    });

    test('複数ラインの不良品数を合計して返す', () => {
        //  lineA:3, lineB:7 → 合計 10
        const ind = makeIndicator({ defectiveCounts: { lineA: 3, lineB: 7 } });
        expect(ind.defectiveCount()).toBe(10);
    });

    test('単一ラインの不良品数をそのまま返す', () => {
        const ind = makeIndicator({ defectiveCounts: { lineA: 5 } });
        expect(ind.defectiveCount()).toBe(5);
    });
});

// ─────────────────────────────────────────────
// totalCount()
// ─────────────────────────────────────────────
describe('totalCount()', () => {
    test('countSwitch=false のとき count をそのまま返す（count = 総生産数）', () => {
        // count=100, defectiveCounts={A:10} でも加算しない
        const ind = makeIndicator({ count: 100, countSwitch: false, defectiveCounts: { A: 10 } });
        expect(ind.totalCount()).toBe(100);
    });

    test('countSwitch=true のとき count + defectiveCount を返す（count = 良品数）', () => {
        // count=90 (良品), defectives=10 → total=100
        const ind = makeIndicator({ count: 90, countSwitch: true, defectiveCounts: { A: 5, B: 5 } });
        expect(ind.totalCount()).toBe(100);
    });

    test('countSwitch=true かつ不良品なしのとき count のみを返す', () => {
        const ind = makeIndicator({ count: 50, countSwitch: true, defectiveCounts: {} });
        expect(ind.totalCount()).toBe(50);
    });
});

// ─────────────────────────────────────────────
// goodCount()
// ─────────────────────────────────────────────
describe('goodCount()', () => {
    test('不良品なしのとき totalCount と等しい', () => {
        const ind = makeIndicator({ count: 100, countSwitch: false });
        expect(ind.goodCount()).toBe(100);
    });

    test('countSwitch=false: goodCount = count - defectiveCount', () => {
        // count=100 (総), defectives=10 → good=90
        const ind = makeIndicator({ count: 100, countSwitch: false, defectiveCounts: { A: 10 } });
        expect(ind.goodCount()).toBe(90);
    });

    test('countSwitch=true: goodCount = count のまま変わらない', () => {
        // count=90 (良品), defectives=10 → total=100, good=100-10=90
        const ind = makeIndicator({ count: 90, countSwitch: true, defectiveCounts: { A: 10 } });
        expect(ind.goodCount()).toBe(90);
    });
});

// ─────────────────────────────────────────────
// goodRate()
// ─────────────────────────────────────────────
describe('goodRate()', () => {
    test('不良品なしのとき 1.0 を返す', () => {
        expect(makeIndicator({ count: 100 }).goodRate()).toBe(1.0);
    });

    test('不良品 10/100 のとき 0.9 を返す', () => {
        const ind = makeIndicator({ count: 100, countSwitch: false, defectiveCounts: { A: 10 } });
        expect(ind.goodRate()).toBeCloseTo(0.9);
    });

    test('totalCount が 0 のとき 0 を返す（ゼロ除算ガード）', () => {
        const ind = makeIndicator({ count: 0, defectiveCounts: {} });
        expect(ind.goodRate()).toBe(0);
    });
});

// ─────────────────────────────────────────────
// defectiveRate()
// ─────────────────────────────────────────────
describe('defectiveRate()', () => {
    test('不良品なしのとき 0 を返す', () => {
        expect(makeIndicator({ count: 100 }).defectiveRate()).toBe(0);
    });

    test('不良品 10/100 のとき 0.1 を返す', () => {
        const ind = makeIndicator({ count: 100, countSwitch: false, defectiveCounts: { A: 10 } });
        expect(ind.defectiveRate()).toBeCloseTo(0.1);
    });

    test('goodRate + defectiveRate = 1.0 を満たす', () => {
        const ind = makeIndicator({ count: 100, countSwitch: false, defectiveCounts: { A: 30 } });
        expect(ind.goodRate() + ind.defectiveRate()).toBeCloseTo(1.0);
    });

    test('totalCount が 0 のとき 0 を返す（ゼロ除算ガード）', () => {
        const ind = makeIndicator({ count: 0, defectiveCounts: {} });
        expect(ind.defectiveRate()).toBe(0);
    });
});

// ─────────────────────────────────────────────
// planCount()
// ─────────────────────────────────────────────
describe('planCount()', () => {
    test('operatingTime / cycleTimeMs を切り捨てて返す', () => {
        // 27,000,000 / 30,000 = 900
        expect(makeIndicator().planCount()).toBe(900);
    });

    test('割り切れない場合は切り捨てる', () => {
        // 27,100,000 / 30,000 = 903.333... → 903
        const ind = makeIndicator({ operatingTime: 27100000, cycleTimeMs: 30000 });
        expect(ind.planCount()).toBe(903);
    });
});

// ─────────────────────────────────────────────
// achievementRate()
// ─────────────────────────────────────────────
describe('achievementRate()', () => {
    test('goodCount / planCount を返す', () => {
        // goodCount=100, planCount=900 → 100/900 ≈ 0.1111
        const ind = makeIndicator({ count: 100 });
        expect(ind.achievementRate()).toBeCloseTo(100 / 900);
    });

    test('planCount が 0 のとき 0 を返す（ゼロ除算ガード）', () => {
        // operatingTime=0 → planCount=0
        const ind = makeIndicator({ operatingTime: 0 });
        expect(ind.achievementRate()).toBe(0);
    });

    test('goodCount が planCount を超えるとき 1.0 を超える', () => {
        // goodCount=1000, planCount=900 → 約1.111
        const ind = makeIndicator({ count: 1000 });
        expect(ind.achievementRate()).toBeGreaterThan(1.0);
    });
});

// ─────────────────────────────────────────────
// cycleTime()
// ─────────────────────────────────────────────
describe('cycleTime()', () => {
    test('通常稼働時: (netTime) / (totalCount * 1000) を返す', () => {
        // totalCount=100, breakdownCount=0, autoResumeCount=0, isBreakdown=false
        // productionCount = 100 - 0 - 0 + 0 = 100
        // cycleTime = 25,200,000 / (100 * 1000) = 252 秒
        expect(makeIndicator().cycleTime()).toBeCloseTo(252);
    });

    test('チョコ停中: breakdownCount を考慮した計算になる', () => {
        const ind = makeIndicator({
            statusName: 'BREAKDOWN',
            count: 100,
            breakdownCount: 5,
            autoResumeCount: 2,
            overTimeMs: 5000,
            netTime: 25200000,
        });
        // productionCount = 100 - 2 - 5 + 1(isBreakdown) = 94
        // cycleTime = (25,200,000 - 5,000*5) / (94 * 1000) = 25,175,000 / 94,000 ≈ 267.82
        expect(ind.cycleTime()).toBeCloseTo(25175000 / 94000);
    });

    test('productionCount が 0 のとき 0 を返す（ゼロ除算ガード）', () => {
        // count=0, breakdownCount=0, autoResumeCount=0, isBreakdown=false → productionCount=0
        const ind = makeIndicator({ count: 0 });
        expect(ind.cycleTime()).toBe(0);
    });

    test('計算結果が負になる場合でも 0 を返す（Math.max ガード）', () => {
        // overTimeMs が大きいと (netTime - overTimeMs*breakdownCount) が負になりうる
        const ind = makeIndicator({
            count: 10,
            breakdownCount: 3,
            overTimeMs: 10000000, // 意図的に過大
            netTime: 5000000,
        });
        expect(ind.cycleTime()).toBe(0);
    });
});

// ─────────────────────────────────────────────
// timeOperatingRate()
// ─────────────────────────────────────────────
describe('timeOperatingRate()', () => {
    test('operatingTime / loadingTime を返す', () => {
        // 27,000,000 / 28,800,000 = 0.9375
        expect(makeIndicator().timeOperatingRate()).toBeCloseTo(0.9375);
    });

    test('loadingTime が 0 のとき 0 を返す（ゼロ除算ガード）', () => {
        const ind = makeIndicator({ loadingTime: 0 });
        expect(ind.timeOperatingRate()).toBe(0);
    });
});

// ─────────────────────────────────────────────
// performanceOperatingRate()
// ─────────────────────────────────────────────
describe('performanceOperatingRate()', () => {
    test('netTime / operatingTime を返す', () => {
        // 25,200,000 / 27,000,000 ≈ 0.9333
        expect(makeIndicator().performanceOperatingRate()).toBeCloseTo(25200000 / 27000000);
    });

    test('operatingTime が 0 のとき 0 を返す（ゼロ除算ガード）', () => {
        const ind = makeIndicator({ operatingTime: 0 });
        expect(ind.performanceOperatingRate()).toBe(0);
    });
});

// ─────────────────────────────────────────────
// overallEquipmentEffectiveness() (OEE)
// ─────────────────────────────────────────────
describe('overallEquipmentEffectiveness()', () => {
    test('goodRate × timeOperatingRate × performanceOperatingRate を返す', () => {
        const ind = makeIndicator();
        // goodRate=1.0, tOR=0.9375, pOR=25200000/27000000
        const expected = 1.0 * (27000000 / 28800000) * (25200000 / 27000000);
        expect(ind.overallEquipmentEffectiveness()).toBeCloseTo(expected);
    });

    test('不良品がある場合は OEE が低下する', () => {
        const perfect = makeIndicator();
        const withDefects = makeIndicator({ count: 100, countSwitch: false, defectiveCounts: { A: 20 } });
        expect(withDefects.overallEquipmentEffectiveness())
            .toBeLessThan(perfect.overallEquipmentEffectiveness());
    });

    test('いずれかの構成要素が 0 のとき 0 を返す', () => {
        const ind = makeIndicator({ loadingTime: 0 }); // timeOperatingRate = 0
        expect(ind.overallEquipmentEffectiveness()).toBe(0);
    });
});
