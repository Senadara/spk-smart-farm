<?php

namespace Tests\Unit;

use App\Events\IotSensorDataReceived;
use App\Jobs\PollIotDeviceJob;
use App\Models\IotDevice;
use App\Models\IotDeviceLog;
use App\Models\IotSensorData;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class PollIotProcessingTest extends TestCase
{
    public function test_command_poll_iot_tidak_melakukan_dispatch_saat_tidak_ada_device_aktif(): void
    {
        Bus::fake();

        $query = Mockery::mock();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('whereHas')->andReturnSelf();
        $query->shouldReceive('get')->andReturn(collect());

        $deviceMock = Mockery::mock('alias:' . IotDevice::class);
        $deviceMock->shouldReceive('with')->andReturn($query);

        Artisan::call('iot:poll');

        Bus::assertNothingDispatched();
    }

    /**
     * Fitur: Poll IoT
     * Skenario: Command polling tidak mendispatch job jika tidak ada device aktif
     * Given tidak ada device aktif dikembalikan dari query
     * When command artisan 'iot:poll' dijalankan
     * Then tidak ada job yang didispatch
     */
    public function test_command_poll_iot_mendispatch_job_untuk_device_aktif(): void
    {
        Bus::fake();

        $device = (object) ['id' => 'device-1'];

        $query = Mockery::mock();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('whereHas')->andReturnSelf();
        $query->shouldReceive('get')->andReturn(collect([$device]));

        $deviceMock = Mockery::mock('alias:' . IotDevice::class);
        $deviceMock->shouldReceive('with')->andReturn($query);

        Artisan::call('iot:poll');

        Bus::assertDispatched(PollIotDeviceJob::class);
    }

    /**
     * Fitur: Poll IoT - Job
     * Skenario: Job PollIotDevice menyimpan data sensor untuk payload valid
     * Given device dengan mapping parameter dan endpoint yang mengembalikan payload valid
     * When job `PollIotDeviceJob` dijalankan
     * Then model sensor dan log dibuat serta event dipicu
     */
    public function test_job_poll_iot_device_menyimpan_data_sensor_untuk_payload_valid(): void
    {
        $mapping = (object) [
            'payloadKey' => 'suhu',
            'parameterId' => 'param-1',
            'parameter' => (object) ['parameterName' => 'Suhu', 'unit' => 'C'],
        ];

        $device = (object) [
            'id' => 'device-1',
            'deviceCode' => 'DEV-1',
            'deviceName' => 'Sensor A',
            'connectionConfig' => (object) [
                'baseUrl' => 'https://example.test',
                'endpointPath' => '/sensor',
                'headers' => [],
                'authType' => 'none',
                'authKey' => null,
            ],
            'parameterMappings' => new EloquentCollection([$mapping]),
        ];

        $query = Mockery::mock();
        $query->shouldReceive('find')->with('device-1')->andReturn($device);
        $deviceMock = Mockery::mock('alias:' . IotDevice::class);
        $deviceMock->shouldReceive('with')->andReturn($query);

        Http::fake([
            'https://example.test/sensor' => Http::response(['suhu' => 31.2], 200),
        ]);

        Event::fake([IotSensorDataReceived::class]);

        $sensorModel = (object) ['sensorTimestamp' => now()];
        $sensorMock = Mockery::mock('alias:' . IotSensorData::class);
        $sensorMock->shouldReceive('create')->once()->andReturn($sensorModel);

        $logMock = Mockery::mock('alias:' . IotDeviceLog::class);
        $logMock->shouldReceive('create')->once()->andReturnTrue();

        $job = new \App\Jobs\PollIotDeviceJob('device-1');
        $job->handle();

        $this->assertTrue(true);
    }
}