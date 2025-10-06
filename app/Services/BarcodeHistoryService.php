<?php

namespace App\Services;

use App\Repositories\BarcodeHistoryRepository;
use Illuminate\Support\Facades\App;

/**
 * バーコード履歴サービス
 */
class BarcodeHistoryService
{

    private readonly BarcodeHistoryRepository $barcodeHistory;

    /**
     * コンストラクタ
     */
    public function __construct()
    {
        $this->barcodeHistory = App::make(BarcodeHistoryRepository::class);
    }

    /**
     * バーコード履歴を登録する
     *
     * @param string $ipAddress IPアドレス
     * @param string $macAddress MACアドレス
     * @param string $barcode バーコード
     * @return boolean 成否
     */
    public function store(string $ipAddress, string $macAddress, string $barcode): bool
    {
        $stored = $this->barcodeHistory->storeBarcode($ipAddress, $macAddress, $barcode);
        return !is_null($stored);
    }
}
