'use strict';

const activeBlinkStates = new Map();
let hasBoundBlinkVisibilityHandler = false;

function restoreBlinkState(element) {
    const state = activeBlinkStates.get(element);
    if (!state) {
        return;
    }

    clearTimeout(state.timerId);
    if (typeof state.$el.stop === 'function') {
        state.$el.stop(true, true);
    }

    state.$el
        .css('display', '')
        .css('visibility', 'visible')
        .css('opacity', state.originalOpacity);

    activeBlinkStates.delete(element);
}

function bindBlinkVisibilityHandler() {
    if (hasBoundBlinkVisibilityHandler || typeof document === 'undefined' || typeof document.addEventListener !== 'function') {
        return;
    }

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            return;
        }

        for (const element of activeBlinkStates.keys()) {
            restoreBlinkState(element);
        }
    });

    hasBoundBlinkVisibilityHandler = true;
}

/**
 * サーバー時刻とのオフセットミリ秒を取得する。
 *
 * @param {string} route サーバー時刻取得URL
 * @returns {number} サーバー時刻とのオフセットミリ秒
 */
export async function getServerDateOffsetAsync(route) {
    const beforeRequest = moment();
    const response = await axios.get(route);
    const afterRequest = moment();
    const serverDate = moment(response.data).add((afterRequest - beforeRequest) / 2, 'ms');
    const offset = serverDate - afterRequest;
    return offset;
}

/**
 * 比率（0〜1）をパーセント整数に変換する。
 * 例: (0.756).rate() => 76
 *
 * @returns {number} パーセント整数値
 */
Number.prototype.rate = function () {
    return Math.round(this * 100);
}

/**
 * 配列の先頭要素を返す。空配列の場合は undefined を返す。
 *
 * @returns {*} 先頭要素
 */
Array.prototype.first = function () {
    return this[0];
}

/**
 * 配列の末尾要素を返す。空配列の場合は undefined を返す。
 *
 * @returns {*} 末尾要素
 */
Array.prototype.last = function () {
    return this[this.length - 1];
}

/**
 * 各要素に関数を適用した結果の合計を返す。
 *
 * @param {function(*,number):number} fn 各要素に適用する関数（value, index を受け取り数値を返す）
 * @returns {number} 合計値
 */
Array.prototype.sum = function (fn) {
    return this.reduce((sum, value, index) => sum + fn(value, index), 0);
}

/**
 * 要素を指定回数点滅させる。
 *
 * @param {number} duration フェードアウト／フェードインそれぞれの時間[ms]
 * @param {number} times 点滅回数
 * @returns {jQuery} メソッドチェーン用に自身を返す
 */
$.prototype.blink = function (duration, times) {
    if (times <= 0) {
        return this;
    }

    // 単体テストの軽量モックや単一要素に対しても後方互換で動かす。
    if (typeof this.each !== 'function') {
        for (let i = 0; i < times; i++) {
            this.fadeOut(duration).fadeIn(duration);
        }
        return this;
    }

    return this.each(function () {
        bindBlinkVisibilityHandler();

        const element = this;
        const $el = $(this);

        if (typeof $el.css !== 'function') {
            for (let i = 0; i < times; i++) {
                $el.fadeOut(duration).fadeIn(duration);
            }
            return;
        }

        const parsedOpacity = Number.parseFloat($el.css('opacity'));
        const originalOpacity = Number.isFinite(parsedOpacity) ? String(parsedOpacity) : '1';
        const fadedOpacity = String(Math.max(Number.parseFloat(originalOpacity) * 0.35, 0.25));
        let remainingTransitions = times * 2;
        let isFaded = false;

        // 直前の点滅が残っていても、次の描画前に必ず見える状態へ戻す。
        restoreBlinkState(element);
        if (typeof $el.stop === 'function') {
            $el.stop(true, true);
        }
        activeBlinkStates.set(element, {
            $el,
            originalOpacity,
            timerId: null,
        });

        function doBlink() {
            if (typeof document !== 'undefined' && document.visibilityState !== 'visible') {
                restoreBlinkState(element);
                return;
            }

            isFaded = !isFaded;
            $el.css('opacity', isFaded ? fadedOpacity : originalOpacity);
            remainingTransitions--;

            if (remainingTransitions > 0) {
                activeBlinkStates.get(element).timerId = setTimeout(doBlink, duration);
                return;
            }

            restoreBlinkState(element);
        }

        doBlink();
    });
};
