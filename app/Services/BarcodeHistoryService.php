<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\BarcodeHistoryRepository;

/**
 * バーコード読取履歴の保存を扱うサービス。
 *
 * MQTT 受信側からは保存成否だけが必要なため、
 * リポジトリの戻り値を bool に正規化して返す。
 */
class BarcodeHistoryService
{
    /**
     * 保存用リポジトリを受け取る。
     */
    public function __construct(private readonly BarcodeHistoryRepository $barcodeHistory) {}

    /**
     * バーコード履歴を登録する
     *
     * @param string $ipAddress IPアドレス
     * @param string $macAddress MACアドレス
     * @param string $barcode バーコード
     * @return bool 成否
     */
    public function store(string $ipAddress, string $macAddress, string $barcode): bool
    {
        return !is_null($this->barcodeHistory->storeBarcode($ipAddress, $macAddress, $barcode));
    }
}
