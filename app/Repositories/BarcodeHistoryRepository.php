<?php

declare(strict_types=1);

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
     * @return class-string<BarcodeHistory>
     */
    public function model(): string
    {
        return BarcodeHistory::class;
    }

    /**
     * バーコード履歴を新規保存する
     *
     * 永続化のみを担い、呼び出し元で成否判定しやすいよう
     * 保存成功時は作成済みモデル、失敗時は null を返す。
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
