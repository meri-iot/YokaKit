'use strict';

// Node 環境で window・moment グローバルをシミュレートして各クラスを読み込む
global.window = global;
global.moment = require('moment');
require('../../resources/js/indicator.js');
require('../../resources/js/payload.js');

const Payload = global.Payload;

/**
 * テスト用の最小ペイロードオブジェクトを生成するヘルパー。
 * サーバーから届く JSON 相当のオブジェクトを模倣する。
 *
 * デフォルト値:
 *   processId       = 1
 *   partNumberName  = 'PART-A'
 *   start           = '2026-04-17T08:00:00'
 *   goal            = 500
 *   lineId          = 10
 *   at              = '2026-04-17T12:00:00'
 *   count           = 100
 *   statusName      = 'RUNNING'
 *   inPlannedOutage = false
 *   countSwitch     = false  (count = 総生産数として扱う)
 *   defectiveCounts = {}
 *   cycleTimeMs     = 30,000 ms (30秒)
 *   overTimeMs      =  5,000 ms ( 5秒)
 *   workingTime     = 30,600,000 ms
 *   loadingTime     = 28,800,000 ms (8時間)
 *   operatingTime   = 27,000,000 ms (7.5時間)
 *   netTime         = 25,200,000 ms (7時間)
 *   breakdownCount  = 0
 *   autoResumeCount = 0
 */
function makePayloadData(overrides = {}) {
    return {
        processId: 1,
        partNumberName: 'PART-A',
        start: '2026-04-17T08:00:00',
        goal: 500,
        lineId: 10,
        at: '2026-04-17T12:00:00',
        count: 100,
        statusName: 'RUNNING',
        inPlannedOutage: false,
        countSwitch: false,
        defectiveCounts: {},
        cycleTimeMs: 30000,
        overTimeMs: 5000,
        workingTime: 30600000,
        loadingTime: 28800000,
        operatingTime: 27000000,
        netTime: 25200000,
        breakdownCount: 0,
        autoResumeCount: 0,
        ...overrides,
    };
}

// ─────────────────────────────────────────────
// constructor — Payload 固有プロパティ
// ─────────────────────────────────────────────
describe('constructor — Payload 固有プロパティ', () => {
    test('processId が設定される', () => {
        const p = new Payload(makePayloadData({ processId: 42 }));
        expect(p.processId).toBe(42);
    });

    test('partNumberName が設定される', () => {
        const p = new Payload(makePayloadData({ partNumberName: 'PART-XYZ' }));
        expect(p.partNumberName).toBe('PART-XYZ');
    });

    test('goal が設定される', () => {
        const p = new Payload(makePayloadData({ goal: 800 }));
        expect(p.goal).toBe(800);
    });

    test('start が moment オブジェクトに変換される', () => {
        const p = new Payload(makePayloadData({ start: '2026-04-17T08:00:00' }));
        expect(moment.isMoment(p.start)).toBe(true);
        expect(p.start.format('HH:mm')).toBe('08:00');
    });
});

// ─────────────────────────────────────────────
// constructor — Indicator 継承プロパティ
// ─────────────────────────────────────────────
describe('constructor — Indicator 継承プロパティ', () => {
    test('cycleTimeMs・overTimeMs が super() 経由で設定される', () => {
        const p = new Payload(makePayloadData({ cycleTimeMs: 15000, overTimeMs: 3000 }));
        expect(p.cycleTimeMs).toBe(15000);
        expect(p.overTimeMs).toBe(3000);
    });

    test('lineId が設定される', () => {
        const p = new Payload(makePayloadData({ lineId: 99 }));
        expect(p.lineId).toBe(99);
    });

    test('at が moment オブジェクトに変換される', () => {
        const p = new Payload(makePayloadData({ at: '2026-04-17T12:30:00' }));
        expect(moment.isMoment(p.at)).toBe(true);
        expect(p.at.format('HH:mm')).toBe('12:30');
    });

    test('count が設定される', () => {
        const p = new Payload(makePayloadData({ count: 200 }));
        expect(p.count).toBe(200);
    });

    test('statusName が設定される', () => {
        const p = new Payload(makePayloadData({ statusName: 'BREAKDOWN' }));
        expect(p.statusName).toBe('BREAKDOWN');
    });

    test('inPlannedOutage が設定される', () => {
        const p = new Payload(makePayloadData({ inPlannedOutage: true }));
        expect(p.inPlannedOutage).toBe(true);
    });

    test('countSwitch が設定される', () => {
        const p = new Payload(makePayloadData({ countSwitch: true }));
        expect(p.countSwitch).toBe(true);
    });

    test('defectiveCounts が設定される', () => {
        const counts = { lineA: 3, lineB: 7 };
        const p = new Payload(makePayloadData({ defectiveCounts: counts }));
        expect(p.defectiveCounts).toEqual(counts);
    });

    test('workingTime・loadingTime・operatingTime・netTime が設定される', () => {
        const p = new Payload(makePayloadData());
        expect(p.workingTime).toBe(30600000);
        expect(p.loadingTime).toBe(28800000);
        expect(p.operatingTime).toBe(27000000);
        expect(p.netTime).toBe(25200000);
    });

    test('breakdownCount・autoResumeCount が設定される', () => {
        const p = new Payload(makePayloadData({ breakdownCount: 3, autoResumeCount: 1 }));
        expect(p.breakdownCount).toBe(3);
        expect(p.autoResumeCount).toBe(1);
    });
});

// ─────────────────────────────────────────────
// Indicator 継承メソッド — Payload でも正しく動作する
// ─────────────────────────────────────────────
describe('Indicator 継承メソッド', () => {
    test('isComplete() — statusName が COMPLETE のとき true を返す', () => {
        const p = new Payload(makePayloadData({ statusName: 'COMPLETE' }));
        expect(p.isComplete()).toBe(true);
    });

    test('isChangeover() — statusName が CHANGEOVER のとき true を返す', () => {
        const p = new Payload(makePayloadData({ statusName: 'CHANGEOVER' }));
        expect(p.isChangeover()).toBe(true);
    });

    test('isBreakdown() — statusName が BREAKDOWN のとき true を返す', () => {
        const p = new Payload(makePayloadData({ statusName: 'BREAKDOWN' }));
        expect(p.isBreakdown()).toBe(true);
    });

    test('defectiveCount() — 複数ラインの不良品数を合計して返す', () => {
        const p = new Payload(makePayloadData({ defectiveCounts: { lineA: 4, lineB: 6 } }));
        expect(p.defectiveCount()).toBe(10);
    });

    test('defectiveCount() — defectiveCounts が空のとき 0 を返す', () => {
        const p = new Payload(makePayloadData({ defectiveCounts: {} }));
        expect(p.defectiveCount()).toBe(0);
    });

    test('goodCount() — 良品数 = totalCount - defectiveCount', () => {
        // countSwitch=false: totalCount=count=100, defective=10 → good=90
        const p = new Payload(makePayloadData({
            count: 100,
            countSwitch: false,
            defectiveCounts: { lineA: 10 },
        }));
        expect(p.goodCount()).toBe(90);
    });

    test('goodRate() — 不良品なしのとき 1.0 を返す', () => {
        const p = new Payload(makePayloadData({ count: 100, defectiveCounts: {} }));
        expect(p.goodRate()).toBe(1.0);
    });

    test('goodRate() — 不良品 10/100 のとき 0.9 を返す', () => {
        const p = new Payload(makePayloadData({
            count: 100,
            countSwitch: false,
            defectiveCounts: { lineA: 10 },
        }));
        expect(p.goodRate()).toBeCloseTo(0.9);
    });

    test('planCount() — operatingTime / cycleTimeMs を切り捨てて返す', () => {
        // 27,000,000 / 30,000 = 900
        const p = new Payload(makePayloadData());
        expect(p.planCount()).toBe(900);
    });

    test('achievementRate() — goodCount / planCount を返す', () => {
        // goodCount=100, planCount=900
        const p = new Payload(makePayloadData({ count: 100 }));
        expect(p.achievementRate()).toBeCloseTo(100 / 900);
    });

    test('timeOperatingRate() — operatingTime / loadingTime を返す', () => {
        // 27,000,000 / 28,800,000 = 0.9375
        const p = new Payload(makePayloadData());
        expect(p.timeOperatingRate()).toBeCloseTo(0.9375);
    });

    test('performanceOperatingRate() — netTime / operatingTime を返す', () => {
        const p = new Payload(makePayloadData());
        expect(p.performanceOperatingRate()).toBeCloseTo(25200000 / 27000000);
    });

    test('overallEquipmentEffectiveness() — OEE = goodRate × tOR × pOR', () => {
        const p = new Payload(makePayloadData());
        const expected = 1.0 * (27000000 / 28800000) * (25200000 / 27000000);
        expect(p.overallEquipmentEffectiveness()).toBeCloseTo(expected);
    });
});
