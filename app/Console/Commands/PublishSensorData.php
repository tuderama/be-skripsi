<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

class PublishSensorData extends Command
{
    protected $signature = 'mqtt:publish-sensor';
    protected $description = 'Publish sensor air data to Mosquitto every 3 seconds';

    public function handle()
    {
        $host = '127.0.0.1';
        $port = 1883;
        $clientId = 'laravel_publisher_' . uniqid();

        $mqtt = new MqttClient($host, $port, $clientId);

        $connectionSettings = (new ConnectionSettings)
            ->setKeepAliveInterval(20)
            ->setUseTls(false);

        $this->info('Connecting to MQTT broker...');

        $mqtt->connect($connectionSettings, true);

        $this->info('Connected. Publishing data every 3 seconds...');

        while (true) {
            $deviceNumber = rand(1, 3);

            $payload = [
                'device_id' => 'device_' . $deviceNumber,
                'co' => round(mt_rand(100, 1500) / 100, 2),
                'pm_2_5' => round(mt_rand(500, 3000) / 100, 2)
            ];

            $mqtt->publish(
                'sensor/air',
                json_encode($payload),
                0
            );

            $this->line('Published: ' . json_encode($payload));

            sleep(3);
        }
        $mqtt->disconnect();
    }
}
