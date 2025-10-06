<?php

namespace App\Repositories;

use App\Models\BarcodeHistory;

/**
 * バーコード履歴リポジトリ
 *
 * @extends AbstractRepository<BarcodeHistory>
 */
class BarcodeHistoryRepository extends AbstractRepository
{
    /**
     * モデルクラス
     *
     * @return class-string
     */
    public function model(): string
    {
        return BarcodeHistory::class;
    }

    /**
     * バーコードデータを追加する
     *
     * @param string $ipAddress IPアドレス
     * @param string $macAddress MACアドレス
     * @param string $barcode バーコード
     * @return BarcodeHistory|null 追加されたバーコードデータ (失敗時はnull)
     */
    public function storeBarcode(string $ipAddress, string $macAddress, string $barcode): ?BarcodeHistory
    {
        $barcodeData = new BarcodeHistory([
            'ip_address' => $ipAddress,
            'mac_address' => $macAddress,
            'barcode' => $barcode,
        ]);
        return $this->storeModel($barcodeData) ? $barcodeData : null;
    }
}
