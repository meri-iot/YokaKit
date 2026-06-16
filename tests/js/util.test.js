'use strict';

const fs = require('fs');
const path = require('path');

// ─────────────────────────────────────────────
// グローバルのセットアップ
//
// util.js は ESM の `export` 構文を含むため require() で直接読み込めない。
// ファイルを文字列として取得し、`export async function xxx` を
// `global.xxx = async function xxx` に置換してから eval で評価する。
// ─────────────────────────────────────────────

// blink メソッドが $.prototype に追加されるため eval より前に $ を定義する
class MockJQuery {
    constructor() {
        // blink 内で this.fadeOut(...).fadeIn(...) と連鎖するため自身を返す
        this.fadeOut = jest.fn().mockReturnThis();
        this.fadeIn = jest.fn().mockReturnThis();
    }
}
global.$ = MockJQuery;

global.moment = require('moment');

const utilSource = fs.readFileSync(
    path.resolve(__dirname, '../../resources/js/util.js'),
    'utf-8'
).replace(
    'export async function getServerDateOffsetAsync',
    'global.getServerDateOffsetAsync = async function getServerDateOffsetAsync'
);

// eslint-disable-next-line no-eval
eval(utilSource);

const getServerDateOffsetAsync = global.getServerDateOffsetAsync;

// ─────────────────────────────────────────────
// getServerDateOffsetAsync()
// ─────────────────────────────────────────────
describe('getServerDateOffsetAsync()', () => {
    const realMoment = global.moment;

    afterEach(() => {
        // テスト後に moment を復元する（他テストへの影響を防ぐ）
        global.moment = realMoment;
    });

    /**
     * 指定ミリ秒のタイムスタンプを持つ moment モックオブジェクトを生成する。
     * moment 同士の引き算（valueOf 経由）と .add() をサポートする。
     */
    function makeFakeMoment(ms) {
        return {
            valueOf: () => ms,
            add: (amount) => makeFakeMoment(ms + amount),
        };
    }

    /**
     * moment のモックをセットアップするヘルパー。
     *
     * @param {number} t0       beforeRequest のタイムスタンプ[ms]
     * @param {number} t1       afterRequest のタイムスタンプ[ms]
     * @param {number} tServer  サーバー時刻のタイムスタンプ[ms]
     */
    function setupMomentMock(t0, t1, tServer) {
        let callCount = 0;
        global.moment = jest.fn((arg) => {
            if (arg === undefined) {
                // 引数なし: 1回目→beforeRequest、2回目→afterRequest
                return makeFakeMoment(callCount++ === 0 ? t0 : t1);
            }
            // 引数あり: moment(response.data) → サーバー時刻
            return makeFakeMoment(tServer);
        });
    }

    test('axios.get を指定した route に対して呼び出す', async () => {
        setupMomentMock(1000000, 1000100, 1000200);
        global.axios = { get: jest.fn().mockResolvedValue({ data: '2026-04-17T12:00:00' }) };

        await getServerDateOffsetAsync('/api/time');

        expect(global.axios.get).toHaveBeenCalledWith('/api/time');
    });

    test('サーバー時刻がローカルより進んでいる場合に正のオフセットを返す', async () => {
        // beforeRequest = t0 = 1,000,000 ms
        // afterRequest  = t1 = 1,000,100 ms (100ms 後)
        // サーバー時刻  = 1,000,200 ms (ローカルより 200ms 進んでいる)
        // serverDate    = 1,000,200 + (100 / 2) = 1,000,250 ms
        // offset        = 1,000,250 - 1,000,100 = +150 ms
        setupMomentMock(1000000, 1000100, 1000200);
        global.axios = { get: jest.fn().mockResolvedValue({ data: '2026-04-17T12:00:00' }) };

        const offset = await getServerDateOffsetAsync('/api/time');

        expect(offset).toBe(150);
    });

    test('サーバー時刻がローカルより遅れている場合に負のオフセットを返す', async () => {
        // beforeRequest = 1,000,000, afterRequest = 1,000,100
        // サーバー時刻  = 999,900 (ローカルより 100ms 遅れている)
        // serverDate    = 999,900 + 50 = 999,950
        // offset        = 999,950 - 1,000,100 = -150 ms
        setupMomentMock(1000000, 1000100, 999900);
        global.axios = { get: jest.fn().mockResolvedValue({ data: '2026-04-17T11:59:59' }) };

        const offset = await getServerDateOffsetAsync('/api/time');

        expect(offset).toBe(-150);
    });

    test('ネットワーク遅延が 0 ms のときオフセット = サーバー時刻 - ローカル時刻', async () => {
        // beforeRequest = afterRequest = 1,000,000 (遅延なし)
        // サーバー時刻  = 1,000,500
        // serverDate    = 1,000,500 + 0 = 1,000,500
        // offset        = 1,000,500 - 1,000,000 = 500 ms
        setupMomentMock(1000000, 1000000, 1000500);
        global.axios = { get: jest.fn().mockResolvedValue({ data: '2026-04-17T12:00:00' }) };

        const offset = await getServerDateOffsetAsync('/api/time');

        expect(offset).toBe(500);
    });
});

// ─────────────────────────────────────────────
// Number.prototype.rate()
// ─────────────────────────────────────────────
describe('Number.prototype.rate()', () => {
    test('0.756 → 76（JSDoc 記載の例）', () => {
        expect((0.756).rate()).toBe(76);
    });

    test('0 → 0', () => {
        expect((0).rate()).toBe(0);
    });

    test('1.0 → 100', () => {
        expect((1.0).rate()).toBe(100);
    });

    test('0.5 → 50', () => {
        expect((0.5).rate()).toBe(50);
    });

    test('0.001 → 0（切り捨て）', () => {
        expect((0.001).rate()).toBe(0);
    });

    test('0.005 → 1（四捨五入）', () => {
        expect((0.005).rate()).toBe(1);
    });

    test('0.999 → 100（四捨五入）', () => {
        expect((0.999).rate()).toBe(100);
    });
});

// ─────────────────────────────────────────────
// Array.prototype.first()
// ─────────────────────────────────────────────
describe('Array.prototype.first()', () => {
    test('通常の配列の先頭要素を返す', () => {
        expect([1, 2, 3].first()).toBe(1);
    });

    test('要素が1つの配列でも先頭要素を返す', () => {
        expect(['only'].first()).toBe('only');
    });

    test('オブジェクト配列の先頭要素を返す', () => {
        const arr = [{ id: 1 }, { id: 2 }];
        expect(arr.first()).toEqual({ id: 1 });
    });

    test('空配列のとき undefined を返す', () => {
        expect([].first()).toBeUndefined();
    });
});

// ─────────────────────────────────────────────
// Array.prototype.last()
// ─────────────────────────────────────────────
describe('Array.prototype.last()', () => {
    test('通常の配列の末尾要素を返す', () => {
        expect([1, 2, 3].last()).toBe(3);
    });

    test('要素が1つの配列でも末尾要素を返す', () => {
        expect(['only'].last()).toBe('only');
    });

    test('オブジェクト配列の末尾要素を返す', () => {
        const arr = [{ id: 1 }, { id: 2 }];
        expect(arr.last()).toEqual({ id: 2 });
    });

    test('空配列のとき undefined を返す', () => {
        expect([].last()).toBeUndefined();
    });

    test('先頭要素と末尾要素が異なることを確認', () => {
        const arr = [10, 20, 30];
        expect(arr.last()).not.toBe(arr.first());
    });
});

// ─────────────────────────────────────────────
// Array.prototype.sum()
// ─────────────────────────────────────────────
describe('Array.prototype.sum()', () => {
    test('数値配列を恒等関数で合計する', () => {
        expect([1, 2, 3, 4].sum(x => x)).toBe(10);
    });

    test('空配列のとき 0 を返す', () => {
        expect([].sum(x => x)).toBe(0);
    });

    test('オブジェクト配列のプロパティを合計する', () => {
        const items = [{ count: 3 }, { count: 7 }, { count: 5 }];
        expect(items.sum(x => x.count)).toBe(15);
    });

    test('fn の第2引数 index が渡される', () => {
        // ['a', 'b', 'c'] の各 index (0, 1, 2) を合計 → 3
        expect(['a', 'b', 'c'].sum((x, i) => i)).toBe(3);
    });

    test('変換関数で値を2倍にして合計する', () => {
        expect([1, 2, 3].sum(x => x * 2)).toBe(12);
    });

    test('要素が1つのとき fn の結果をそのまま返す', () => {
        expect([42].sum(x => x)).toBe(42);
    });
});

// ─────────────────────────────────────────────
// $.prototype.blink()
// ─────────────────────────────────────────────
describe('$.prototype.blink()', () => {
    test('fadeOut と fadeIn が repeat 回ずつ呼ばれる', () => {
        const el = new MockJQuery();
        el.blink(300, 3);
        expect(el.fadeOut).toHaveBeenCalledTimes(3);
        expect(el.fadeIn).toHaveBeenCalledTimes(3);
    });

    test('fadeOut・fadeIn に duration が渡される', () => {
        const el = new MockJQuery();
        el.blink(500, 2);
        expect(el.fadeOut).toHaveBeenCalledWith(500);
        expect(el.fadeIn).toHaveBeenCalledWith(500);
    });

    test('repeat=0 のとき fadeOut・fadeIn は呼ばれない', () => {
        const el = new MockJQuery();
        el.blink(300, 0);
        expect(el.fadeOut).not.toHaveBeenCalled();
        expect(el.fadeIn).not.toHaveBeenCalled();
    });

    test('メソッドチェーン用に自身を返す', () => {
        const el = new MockJQuery();
        const result = el.blink(300, 1);
        expect(result).toBe(el);
    });

    test('repeat=1 のとき fadeOut → fadeIn の順に1回ずつ呼ばれる', () => {
        const el = new MockJQuery();
        el.blink(100, 1);
        expect(el.fadeOut).toHaveBeenCalledTimes(1);
        expect(el.fadeIn).toHaveBeenCalledTimes(1);
        // 呼び出し順を確認
        const fadeOutOrder = el.fadeOut.mock.invocationCallOrder[0];
        const fadeInOrder = el.fadeIn.mock.invocationCallOrder[0];
        expect(fadeOutOrder).toBeLessThan(fadeInOrder);
    });

    test('実 DOM 相当の要素では点滅完了後に opacity を元へ戻す', () => {
        jest.useFakeTimers();

        const blink = MockJQuery.prototype.blink;
        const element = {};
        const original$ = global.$;
        const state = {
            opacity: '1',
            visibility: 'visible',
            display: '',
        };
        const wrapped = {
            stop: jest.fn().mockReturnThis(),
            css: jest.fn((name, value) => {
                if (value === undefined) {
                    return state[name];
                }
                state[name] = String(value);
                return wrapped;
            }),
        };
        const originalDocument = global.document;
        const listeners = {};
        global.document = {
            visibilityState: 'visible',
            addEventListener: jest.fn((eventName, handler) => {
                listeners[eventName] = handler;
            }),
        };
        global.$ = jest.fn(() => wrapped);

        const collection = {
            each: jest.fn(function (fn) {
                fn.call(element);
                return collection;
            }),
        };

        blink.call(collection, 100, 1);
        jest.runAllTimers();

        expect(state.opacity).toBe('1');
        expect(wrapped.stop).toHaveBeenCalled();

        global.$ = original$;
        global.document = originalDocument;
        jest.useRealTimers();
    });

    test('非表示になったタイミングで点滅を中断して表示状態へ戻す', () => {
        jest.useFakeTimers();

        const blink = MockJQuery.prototype.blink;
        const element = {};
        const original$ = global.$;
        const state = {
            opacity: '1',
            visibility: 'visible',
            display: '',
        };
        const wrapped = {
            stop: jest.fn().mockReturnThis(),
            css: jest.fn((name, value) => {
                if (value === undefined) {
                    return state[name];
                }
                state[name] = String(value);
                return wrapped;
            }),
        };
        const originalDocument = global.document;
        global.document = {
            visibilityState: 'visible',
            addEventListener: jest.fn(),
        };
        global.$ = jest.fn(() => wrapped);

        const collection = {
            each: jest.fn(function (fn) {
                fn.call(element);
                return collection;
            }),
        };

        blink.call(collection, 100, 2);

        global.document.visibilityState = 'hidden';
        jest.advanceTimersByTime(100);

        expect(state.opacity).toBe('1');
        expect(state.visibility).toBe('visible');
        expect(wrapped.stop).toHaveBeenCalled();

        global.$ = original$;
        global.document = originalDocument;
        jest.useRealTimers();
    });
});
