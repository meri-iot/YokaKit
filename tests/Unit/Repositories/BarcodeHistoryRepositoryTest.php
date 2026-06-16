<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\BarcodeHistory;
use App\Repositories\BarcodeHistoryRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BarcodeHistoryRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_modelはBarcodeHistoryクラスを返す(): void
    {
        $repository = new BarcodeHistoryRepository();

        $this->assertSame(BarcodeHistory::class, $repository->model());
    }

    public function test_storeBarcodeは履歴を保存してモデルを返す(): void
    {
        $repository = new BarcodeHistoryRepository();

        $stored = $repository->storeBarcode('192.168.0.10', 'AA:BB:CC:DD:EE:FF', 'barcode-001');

        $this->assertInstanceOf(BarcodeHistory::class, $stored);
        $this->assertDatabaseHas('barcode_histories', [
            'barcode_history_id' => $stored?->barcode_history_id,
            'ip_address' => '192.168.0.10',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'barcode' => 'barcode-001',
        ]);
    }

    public function test_storeBarcodeは保存失敗時にnullを返す(): void
    {
        $repository = new class extends BarcodeHistoryRepository
        {
            protected function storeModel(\Illuminate\Database\Eloquent\Model $model): bool
            {
                return false;
            }
        };

        $stored = $repository->storeBarcode('192.168.0.11', '11:22:33:44:55:66', 'barcode-002');

        $this->assertNull($stored);
        $this->assertDatabaseMissing('barcode_histories', [
            'ip_address' => '192.168.0.11',
            'mac_address' => '11:22:33:44:55:66',
            'barcode' => 'barcode-002',
        ]);
    }
}
