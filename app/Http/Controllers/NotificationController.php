<?php

namespace App\Http\Controllers;

use App\Models\Notifikasi;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function getUserNotifications($user_id)
    {

        $notifications = Notifikasi::where('user_id', $user_id)
            ->orderBy('created_at', 'desc')
            ->get();

        if ($notifications->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Belum ada notifikasi',
                'data' => []
            ], 200);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data notifikasi ditemukan',
            'data' => $notifications
        ], 200);
    }

    public function deleteAllNotifications($user_id)
    {

        $deletedCount = Notifikasi::where('user_id', $user_id)->delete();

        if ($deletedCount == 0) {
            return response()->json([
                'status' => 'success',
                'message' => 'Tidak ada notifikasi yang perlu dihapus',
            ], 200);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Semua notifikasi berhasil dihapus',
        ], 200);
    }
}
