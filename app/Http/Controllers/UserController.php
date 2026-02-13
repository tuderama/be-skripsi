<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function getUserLogin(Request $request){
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'statusCode' => Response::HTTP_UNAUTHORIZED,
                'message' => 'User belum login',
            ], Response::HTTP_UNAUTHORIZED);
        }
        return response()->json([
            'status' => 'success',
            'statusCode' => Response::HTTP_OK,
            'message' => 'Data user berhasil diambil',
            'data' => $user
        ]);
    }
    public function updateImageProfile(Request $request){
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'statusCode' => Response::HTTP_BAD_REQUEST,
                'message' => 'Image wajib diupload dan harus berupa gambar (jpg, jpeg, png) max 2 mb',
            ]);
        }
        if ($user->image && Storage::disk('public')->exists($user->image)) {
            Storage::disk('public')->delete($user->image);
        }
        $path = $request->file('image')->store('profile', 'public');
        $user->update([
            'image' => $path,
        ]);

        return response()->json([
            'status' => 'success',
            'statusCode' => Response::HTTP_OK,
            'message' => 'Foto profil berhasil diperbarui',
        ]);
    }

    public function updateProfile(Request $request){
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'name'             => 'required|string|max:255',
            'email'            => 'required|email|unique:users,email,' . $user->id,
            'old_password'     => 'required|string',
            'new_password'     => 'required|string|min:8',
            'confirm_password' => 'required|string|same:new_password',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'statusCode' => Response::HTTP_BAD_REQUEST,
                'message' => 'Semua field wajib diis, password min 8 karakter dan email harus sesuai dan unik',
            ]);
        }
        if (!Hash::check($request->old_password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'statusCode' => Response::HTTP_BAD_REQUEST,
                'message' => 'Password lama tidak sesuai',
            ]);
        }
        if ($request->new_password !== $request->confirm_password) {
            return response()->json([
                'status' => 'error',
                'statusCode' => Response::HTTP_BAD_REQUEST,
                'message' => 'Konfirmasi password tidak sama dengan password baru',
            ]);
        }
        $user->update([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json([
            'status' => 'success',
            'statusCode' => Response::HTTP_OK,
            'message' => 'Profil berhasil diperbarui',
        ]);
    }
}
