<?php

namespace App\Providers;

use App\Repositories\AndonConfigRepository;
use App\Repositories\AndonLayoutRepository;
use App\Repositories\BarcodeHistoryRepository;
use App\Repositories\CycleTimeRepository;
use App\Repositories\DefectiveProductionRepository;
use App\Repositories\GanttChartEventRepository;
use App\Repositories\GanttChartRepository;
use App\Repositories\LineRepository;
use App\Repositories\OnOffEventRepository;
use App\Repositories\OnOffRepository;
use App\Repositories\PartNumberRepository;
use App\Repositories\PayloadRepository;
use App\Repositories\PlannedOutageRepository;
use App\Repositories\ProcessPlannedOutageRepository;
use App\Repositories\ProcessRepository;
use App\Repositories\ProducerRepository;
use App\Repositories\ProductionHistoryRepository;
use App\Repositories\ProductionLineRepository;
use App\Repositories\ProductionPlannedOutageRepository;
use App\Repositories\ProductionRepository;
use App\Repositories\RaspberryPiRepository;
use App\Repositories\SensorEventRepository;
use App\Repositories\SensorRepository;
use App\Repositories\UserRepository;
use App\Repositories\WorkerRepository;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        App::singleton(AndonConfigRepository::class);
        App::singleton(AndonLayoutRepository::class);
        App::singleton(BarcodeHistoryRepository::class);
        App::singleton(CycleTimeRepository::class);
        App::singleton(DefectiveProductionRepository::class);
        App::singleton(LineRepository::class);
        App::singleton(OnOffEventRepository::class);
        App::singleton(OnOffRepository::class);
        App::singleton(PartNumberRepository::class);
        App::singleton(PayloadRepository::class);
        App::singleton(PlannedOutageRepository::class);
        App::singleton(ProcessPlannedOutageRepository::class);
        App::singleton(ProcessRepository::class);
        App::singleton(ProducerRepository::class);
        App::singleton(ProductionHistoryRepository::class);
        App::singleton(ProductionLineRepository::class);
        App::singleton(ProductionPlannedOutageRepository::class);
        App::singleton(ProductionRepository::class);
        App::singleton(RaspberryPiRepository::class);
        App::singleton(SensorEventRepository::class);
        App::singleton(SensorRepository::class);
        App::singleton(UserRepository::class);
        App::singleton(WorkerRepository::class);
        App::singleton(GanttChartRepository::class);
        App::singleton(GanttChartEventRepository::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Paginator::useBootstrap();
        Validator::extend('color', fn($attribute, $value, $parameters, $validator) => preg_match('/^#[0-9a-fA-F]{6}$/', $value));
        Validator::extend('current_password', fn($attribute, $value, $parameters, $validator) => Hash::check($value, Auth::user()->password));
    }
}
