<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * @method \Illuminate\Testing\TestResponse get(string $uri, array $headers = [])
 * @method \Illuminate\Testing\TestResponse getJson(string $uri, array $headers = [], int $options = 0)
 * @method \Illuminate\Testing\TestResponse postJson(string $uri, array $data = [], array $headers = [], int $options = 0)
 * @method static withoutExceptionHandling(array $except = [])
 * @method static actingAs(\Illuminate\Contracts\Auth\Authenticatable $user, string|null $guard = null)
 */
abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function makeProductionSummaryData(): \App\Data\ProductionSummaryData
    {
        return new \App\Data\ProductionSummaryData(
            productionHistoryId: 0,
            processId: 0,
            processName: '',
            partNumberId: 0,
            partNumberName: '',
            goal: null,
            start: '',
            countSwitch: false,
            statusName: '',
            breakdownCount: 0,
            inPlannedOutage: false,
            count: 0,
            lineId: 0,
            defectiveCounts: [],
            at: '',
            isComplete: false,
            workingTime: 0,
            loadingTime: 0,
            operatingTime: 0,
            netTime: 0,
            autoResumeCount: 0,
            breakdowns: [],
            cycleTimeMs: 0,
            overTimeMs: 0,
            plannedOutages: [],
            changeovers: [],
            indicator: false,
        );
    }
}
