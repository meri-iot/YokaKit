<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\AndonController;
use App\Http\Controllers\CycleTimeController;
use App\Http\Controllers\DataTables\DataTablesLocaleController;
use App\Http\Controllers\GanttChartController;
use App\Http\Controllers\LineController;
use App\Http\Controllers\OnOffController;
use App\Http\Controllers\PartNumberController;
use App\Http\Controllers\PlannedOutageController;
use App\Http\Controllers\ProcessController;
use App\Http\Controllers\ProcessPlannedOutageController;
use App\Http\Controllers\ProductionHistoryController;
use App\Http\Controllers\RaspberryPiController;
use App\Http\Controllers\SensorController;
use App\Http\Controllers\ServerDateController;
use App\Http\Controllers\SwitchController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Auth::routes(['register' => false]);

// アンドン
Route::redirect('/', 'home');
Route::group(['prefix' => 'home'], function () {
    Route::controller(AndonController::class)->group(function () {
        Route::get('', 'index')->name('home');
        Route::get('/edit', 'edit')->name('andon.config');
        Route::put('', 'update')->name('andon.update');
    });
});

// 品番切り替え
Route::group(['prefix' => 'switch', 'as' => 'switch.'], function () {
    Route::controller(SwitchController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/{process}/store', 'store')->name('store');
        Route::put('/{process}/stop', 'stop')->name('stop');
        Route::put('/{process}/changeover/start', 'startChangeover')->name('start_changeover');
        Route::put('/{process}/changeover/stop', 'stopChangeover')->name('stop_changeover');
        Route::put('/{process}/worker', 'changeWorker')->name('change_worker');
    });
});

// DataTables日本語
Route::get('/datatables/lang', DataTablesLocaleController::class)->name('datatables');

// サーバー時刻
Route::get('/date', ServerDateController::class)->name('date');

// アバウト
Route::get('/about', AboutController::class)->name('about');

// ユーザー
Route::group(['prefix' => 'user', 'as' => 'user.'], function () {
    Route::controller(UserController::class)->group(function () {
        Route::get('', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/profile', 'show')->name('show');
        Route::get('/edit', 'edit')->name('edit');
        Route::delete('/{user}', 'destroy')->name('destroy');
        Route::put('', 'profile')->name('update');
        Route::post('/token', 'token')->name('token');
        Route::get('/password', 'password')->name('password');
        Route::put('/password', 'change')->name('password.change');
    });
});

// 工程
Route::group(['prefix' => 'process', 'as' => 'process.'], function () {
    Route::controller(ProcessController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{process}', 'show')->name('show');
        Route::get('/{process}/edit', 'edit')->name('edit');
        Route::put('/{process}', 'update')->name('update');
        Route::delete('/{process}', 'destroy')->name('destroy');
        Route::get('/{process}/select', 'select')->name('select');
        Route::put('/{process}/switching', 'switching')->name('switching');
        Route::put('/{process}/stop', 'stop')->name('stop');
    });
});

// 計画停止時間
Route::group(['prefix' => 'planned-outage', 'as' => 'planned-outage.'], function () {
    Route::controller(PlannedOutageController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{plannedOutage}/edit', 'edit')->name('edit');
        Route::put('/{plannedOutage}', 'update')->name('update');
        Route::delete('/{plannedOutage}', 'destroy')->name('destroy');
    });
});

// 品番
Route::group(['prefix' => 'part-number', 'as' => 'part-number.'], function () {
    Route::controller(PartNumberController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{partNumber}/edit', 'edit')->name('edit');
        Route::put('/{partNumber}', 'update')->name('update');
        Route::delete('/{partNumber}', 'destroy')->name('destroy');
    });
});

// サイクルタイム
Route::group(['prefix' => 'process/{process}/cycle-time', 'as' => 'cycle-time.'], function () {
    Route::controller(CycleTimeController::class)->group(function () {
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{cycleTime}/edit', 'edit')->name('edit');
        Route::put('/{cycleTime}', 'update')->name('update');
        Route::delete('/{cycleTime}', 'destroy')->name('destroy');
    });
});

// 作業者
Route::group(['prefix' => 'worker', 'as' => 'worker.'], function () {
    Route::controller(WorkerController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{worker}/edit', 'edit')->name('edit');
        Route::put('/{worker}', 'update')->name('update');
        Route::delete('/{worker}', 'destroy')->name('destroy');
    });
});

// 工程計画停止時間
Route::group(['prefix' => 'process/{process}/planned-outage', 'as' => 'process.planned-outage.'], function () {
    Route::controller(ProcessPlannedOutageController::class)->group(function () {
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::delete('/{processPlannedOutage}', 'destroy')->name('destroy');
    });
});

// ラズベリーパイ
Route::group(['prefix' => 'raspberry-pi', 'as' => 'raspberry-pi.'], function () {
    Route::controller(RaspberryPiController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{raspberryPi}/edit', 'edit')->name('edit');
        Route::put('/{raspberryPi}', 'update')->name('update');
        Route::delete('/{raspberryPi}', 'destroy')->name('destroy');
    });
});

// ライン
Route::group(['prefix' => 'process/{process}/line', 'as' => 'line.'], function () {
    Route::controller(LineController::class)->group(function () {
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{line}/edit', 'edit')->name('edit');
        Route::put('/{line}', 'update')->name('update');
        Route::delete('/{line}', 'destroy')->name('destroy');
        Route::get('/sorting', 'sorting')->name('sorting');
        Route::post('/sort', 'sort')->name('sort');
    });
});

// アラーム
Route::group(['prefix' => 'process/{process}/alarm', 'as' => 'alarm.'], function () {
    Route::controller(SensorController::class)->group(function () {
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{sensor}/edit', 'edit')->name('edit');
        Route::put('/{sensor}', 'update')->name('update');
        Route::delete('/{sensor}', 'destroy')->name('destroy');
    });
});

// ON-OFF
Route::group(['prefix' => 'process/{process}/on-off', 'as' => 'onoff.'], function () {
    Route::controller(OnOffController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{onOff}/edit', 'edit')->name('edit');
        Route::put('/{onOff}', 'update')->name('update');
        Route::delete('/{onOff}', 'destroy')->name('destroy');
    });
});

// 生産履歴
Route::group(['prefix' => 'process/{process}/production', 'as' => 'production.'], function () {
    Route::controller(ProductionHistoryController::class)->group(function () {
        Route::get('/history', 'index')->name('index');
        Route::get('/history/{history}', 'show')->name('show');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::put('/stop', 'stop')->name('stop');
        Route::put('/changeover/start', 'startChangeover')->name('start_changeover');
        Route::put('/changeover/stop', 'stopChangeover')->name('stop_changeover');
        Route::get('/history/{history}/download', 'download')->name('download');
        Route::delete('/', 'destroy')->name('destroy');
    });
});

// ガントチャート
Route::group(['prefix' => 'process/{process}/gantt', 'as' => 'gantt-chart.'], function () {
    Route::controller(GanttChartController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{ganttChart}/edit', 'edit')->name('edit');
        Route::put('/{ganttChart}', 'update')->name('update');
        Route::delete('/{ganttChart}', 'destroy')->name('destroy');
        Route::get('/sorting', 'sorting')->name('sorting');
        Route::post('/sort', 'sort')->name('sort');
        Route::get('/history', 'history')->name('history');
        Route::get('/download', 'download')->name('download');
    });
});

// ガントチャート
Route::group(['prefix' => 'gantt', 'as' => 'gantt.'], function () {
    Route::controller(GanttChartController::class)->group(function () {
        Route::get('/', 'all')->name('index');
    });
});
