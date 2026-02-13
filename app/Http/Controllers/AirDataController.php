<?php

namespace App\Http\Controllers;

use HosseinHezami\LaravelGemini\Facades\Gemini;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

class AirDataController extends Controller
{
    private function getRangeInterval(string $range)
    {
        return [
            '1' => "'1 day'",
            '2' => "'2 days'",
            '3' => "'3 days'",
            '5' => "'5 days'",
            '7' => "'7 days'",
        ][$range] ?? null;
    }

    public function getCOData(Request $request)
    {
        $range  = $request->query('days', '1');
        $device = $request->query('device', 'device_1');

        $interval = $this->getRangeInterval($range);

        if (!$interval) {
            return response()->json([
                'status' => 'error',
                'message' => 'Parameter days harus 1, 2, 3, 5, atau 7'
            ], 400);
        }

        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $device)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Parameter device tidak valid'
            ], 400);
        }

        $sql = "
        SELECT time, device_id, co
        FROM air
        WHERE device_id = '$device'
          AND time >= (
              SELECT MAX(time)
              FROM air
              WHERE device_id = '$device'
          ) - INTERVAL $interval
        ORDER BY time ASC
    ";

        $response = Http::withToken(env('INFLUX_TOKEN'))
            ->post('http://localhost:8181/api/v3/query_sql', [
                'db' => 'skripsi_db',
                'q'  => $sql,
            ]);

        if (!$response->successful()) {
            return response()->json(['error' => 'Gagal ambil data'], 500);
        }

        $data = collect($response->json());

        if ($data->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'device' => $device,
                'parameter_days' => $range,
                'stats' => null,
                'ai' => null,
                'data' => []
            ]);
        }

        $stats = [
            'avg' => round($data->avg('co'), 2),
            'min' => round($data->min('co'), 2),
            'max' => round($data->max('co'), 2),
        ];

        $ai = $this->askGeminiCO(
            $range,
            $stats['avg'],
            $stats['min'],
            $stats['max']
        );

        return response()->json([
            'status' => 'success',
            'device' => $device,
            'parameter_days' => $range,
            'stats' => $stats,
            'ai' => $ai,
            'data' => $data,
        ]);
    }


    public function getPM25Data(Request $request)
    {
        $range  = $request->query('days', '1');
        $device = $request->query('device', 'device_1');

        $interval = $this->getRangeInterval($range);

        if (!$interval) {
            return response()->json([
                'status' => 'error',
                'message' => 'Parameter days harus 1, 2, 3, 5, atau 7'
            ], 400);
        }

        // validasi device (anti SQL injection ringan)
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $device)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Parameter device tidak valid'
            ], 400);
        }

        $sql = "
        SELECT time, device_id, pm_2_5
        FROM air
        WHERE device_id = '$device'
          AND time >= (
              SELECT MAX(time)
              FROM air
              WHERE device_id = '$device'
          ) - INTERVAL $interval
        ORDER BY time ASC
    ";

        $response = Http::withToken(env('INFLUX_TOKEN'))
            ->post('http://localhost:8181/api/v3/query_sql', [
                'db' => 'skripsi_db',
                'q'  => $sql,
            ]);

        if (!$response->successful()) {
            return response()->json(['error' => 'Gagal ambil data'], 500);
        }

        $data = collect($response->json());

        if ($data->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'device' => $device,
                'parameter_days' => $range,
                'stats' => null,
                'ai' => null,
                'data' => []
            ]);
        }

        $stats = [
            'avg' => round($data->avg('pm_2_5'), 2),
            'min' => round($data->min('pm_2_5'), 2),
            'max' => round($data->max('pm_2_5'), 2),
        ];

        $ai = $this->askGeminiPM25(
            $range,
            $stats['avg'],
            $stats['min'],
            $stats['max']
        );

        return response()->json([
            'status' => 'success',
            'device' => $device,
            'parameter_days' => $range,
            'stats' => $stats,
            'ai' => $ai,
            'data' => $data,
        ]);
    }


    private function askGeminiCO($days, $avg, $min, $max)
    {
        $prompt = "
Data CO dalam ruangan selama $days hari:
Rata-rata $avg ppm, minimum $min ppm, maksimum $max ppm.

Berikan jawaban DALAM 1 PARAGRAF SAJA dengan format PERSIS seperti ini:
kategori:<baik|sedang|buruk>,ringkasan:<maks 2 kalimat>,saran:<maks 3 poin dipisahkan titik koma>

Aturan:
- Jangan gunakan baris baru
- Jangan gunakan bullet atau numbering
- Jangan menambahkan teks lain di luar format
- Gunakan bahasa Indonesia sederhana
";

        $response = Gemini::text()
            ->prompt($prompt)
            ->temperature(0.3)
            ->maxTokens(150)
            ->generate();

        return $response->content() ?? 'kategori:tidak diketahui,ringkasan:-,saran:-';
    }


    private function askGeminiPM25($days, $avg, $min, $max)
    {
        $prompt = "
Data PM2.5 dalam ruangan selama $days hari:
Rata-rata $avg µg/m³, minimum $min µg/m³, maksimum $max µg/m³.

Berikan jawaban DALAM 1 PARAGRAF SAJA dengan format PERSIS seperti ini:
kategori:<baik|sedang|buruk>,ringkasan:<maks 2 kalimat>,saran:<maks 3 poin dipisahkan titik koma>

Aturan:
- Jangan gunakan baris baru
- Jangan gunakan bullet atau numbering
- Jangan menambahkan teks lain di luar format
- Gunakan bahasa Indonesia sederhana
";

        $response = Gemini::text()
            ->prompt($prompt)
            ->temperature(0.3)
            ->maxTokens(150)
            ->generate();

        return $response->content() ?? 'kategori:tidak diketahui,ringkasan:-,saran:-';
    }

    public function getDevices()
    {
        $sql = "
        SELECT DISTINCT device_id
        FROM air
        ORDER BY device_id ASC
    ";

        $response = Http::withToken(env('INFLUX_TOKEN'))
            ->post('http://localhost:8181/api/v3/query_sql', [
                'db' => 'skripsi_db',
                'q'  => $sql,
            ]);

        if (!$response->successful()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil daftar device'
            ], 500);
        }

        $devices = collect($response->json())
            ->pluck('device_id')
            ->unique()
            ->values();

        return response()->json([
            'status' => 'success',
            'total'  => $devices->count(),
            'devices' => $devices
        ]);
    }
}
