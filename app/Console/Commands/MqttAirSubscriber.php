<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use PhpMqtt\Client\MqttClient;
use App\Models\User;
use App\Models\Notifikasi;
use Google\Auth\Credentials\ServiceAccountCredentials;

class MqttAirSubscriber extends Command{
    protected $signature = 'mqtt:air';
    protected $description = 'Subscribe MQTT air topic, kirim ke InfluxDB, dan notifikasi FCM v1';

    // Cache token di memori agar tidak generate ulang setiap detik
    private $accessToken = null;
    private $tokenExpiry = 0;

    public function handle()
    {
        $server   = '127.0.0.1';
        $port     = 1883;
        $clientId = 'laravel_backend_subscriber';

        try {
            $mqtt = new MqttClient($server, $port, $clientId);
            $mqtt->connect(null, true);

            $this->info("MQTT Connected. Waiting for messages...");

            $mqtt->subscribe('sensor/air', function ($topic, $message) {
                $message = trim($message, "\"\r\n\t ");
                $data = json_decode($message, true);
                if (!$data) return;

                // 1. Kirim ke InfluxDB
                $this->sendToInflux($data);

                // 2. Cek Ambang Batas
                $this->checkThresholdAndNotify($data);
            });

            $mqtt->loop(true);
        } catch (\Exception $e) {
            $this->error("MQTT Error: " . $e->getMessage());
        }
    }

    private function sendToInflux($data)
    {
        $line = sprintf(
            "air,device_id=%s co=%f,pm_2_5=%f",
            $data['device_id'],
            $data['co'],
            $data['pm_2_5']
        );

        // Pastikan URL InfluxDB Anda benar
        Http::withToken(env('INFLUX_TOKEN'))
            ->withBody($line, 'text/plain')
            ->post('http://localhost:8181/api/v3/write_lp?db=skripsi_db');
    }

    private function checkThresholdAndNotify($data)
    {
        $co = $data['co'];
        $pm = $data['pm_2_5'];
        $alerts = [];

        if ($co > 9)  $alerts[] = "CO mencapai {$co} ppm";
        if ($pm > 25) $alerts[] = "PM2.5 mencapai {$pm} µg/m³";

        if (!empty($alerts)) {
            $alertMessage = implode(' dan ', $alerts);
            $fullMessage  = "{$alertMessage} dan telah melebihi batas aman.";

            $this->warn("BAHAYA: $fullMessage");

            // Ambil user yang punya token
            $users = User::whereNotNull('token')->get();

            foreach ($users as $user) {
                // Simpan DB
                Notifikasi::create([
                    'user_id' => $user->id,
                    'message' => $fullMessage,
                    'is_read' => false,
                ]);

                // Kirim FCM v1
                $this->sendFcmNotification($user->token, "Udara Buruk Terdeteksi!", $fullMessage);
            }
        }
    }

    /**
     * Mengirim Notifikasi menggunakan FCM HTTP v1
     */
    private function sendFcmNotification($fcmToken, $title, $body)
    {
        // 1. Dapatkan Access Token (OAuth 2.0)
        $token = $this->getGoogleAccessToken();

        if (!$token) {
            $this->error("Gagal mendapatkan Google Access Token");
            return;
        }

        // 2. Ambil Project ID dari .env
        $projectId = env('FIREBASE_PROJECT_ID');
        if (!$projectId) {
            $this->error("FIREBASE_PROJECT_ID belum disetting di .env");
            return;
        }

        // 3. Kirim Request ke Endpoint v1
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $payload = [
            'message' => [
                'token' => $fcmToken,
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                ],
                // Data opsional untuk handling di Flutter (background/click)
                'data' => [
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'type'         => 'alert_udara',
                    'message_body' => $body
                ]
            ]
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token, // Perhatikan pakai Bearer
            'Content-Type'  => 'application/json',
        ])->post($url, $payload);

        if ($response->successful()) {
            $this->info("FCM v1 Terkirim ke: " . substr($fcmToken, 0, 10) . "...");
        } else {
            $this->error("Gagal kirim FCM: " . $response->body());
        }
    }

    /**
     * Fungsi Helper untuk Generate/Refresh Token Google
     */
    private function getGoogleAccessToken()
    {
        // Cek jika token masih valid (reuse token)
        if ($this->accessToken && time() < $this->tokenExpiry) {
            return $this->accessToken;
        }

        try {
            $credentialsPath = storage_path('app/firebase_credentials.json');

            if (!file_exists($credentialsPath)) {
                $this->error("File JSON Service Account tidak ditemukan di: $credentialsPath");
                return null;
            }

            // Scope untuk FCM
            $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];

            $credentials = new ServiceAccountCredentials($scopes, $credentialsPath);
            $tokenArray = $credentials->fetchAuthToken();

            if (isset($tokenArray['access_token'])) {
                $this->accessToken = $tokenArray['access_token'];
                // Token biasanya valid 1 jam (3600 detik), kita set buffer aman
                $this->tokenExpiry = time() + 3500;
                return $this->accessToken;
            }

            return null;
        } catch (\Exception $e) {
            $this->error("Error generating token: " . $e->getMessage());
            return null;
        }
    }
}
