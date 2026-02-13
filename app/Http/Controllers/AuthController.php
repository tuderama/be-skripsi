<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function login(Request $request){
        $request->validate([
            'login'=>'required|string',
            'password'=>'required'
        ]);
        $login = $request->login;
        $user = User::where(function ($query) use ($login) {
            $query->where('email', $login)
                ->orWhere('name', $login);
        })->first();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'statusCode' => Response::HTTP_NOT_FOUND,
                'message' => 'User tidak ditemukan'
            ]);
        }

        if(!Hash::check($request->password, $user->password) ){
            return response()->json([
                'status'=>'error',
                'statusCode'=> Response::HTTP_BAD_REQUEST,
                'message'=>'Email atau name dan password salah'
            ]);
        };

        $token = $user->createToken('tokenLogin')->plainTextToken;
        return response()->json([
            'status'=>'success',
            'statusCode'=>Response::HTTP_OK,
            'message'=>'Login berhasil',
            'data'=>$user,
            'token'=>$token
        ]);
    }

    public function register(Request $request){
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:6'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'statusCode' => Response::HTTP_BAD_REQUEST,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ]);
        }

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password)
        ]);

        return response()->json([
            'status' => 'success',
            'statusCode' => Response::HTTP_CREATED,
            'message' => 'Register berhasil',
            'user'=>$user
        ]);
    }

    public function logout(Request $request){
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'status' => 'success',
            'statusCode' => Response::HTTP_OK,
            'message' => 'Logout berhasil'
        ]);
    }

    public function updateFcmToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id'   => 'required|exists:users,id',
            'fcm_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors()
            ], 400);
        }

        $user = User::find($request->user_id);

        if ($user->token === $request->fcm_token) {
            return response()->json([
                'status'  => true,
                'message' => 'Token FCM sudah sama. Tidak ada perubahan yang dilakukan.',
            ], 200);
        }

        $user->token = $request->fcm_token;
        $user->save();

        return response()->json([
            'status'  => true,
            'message' => 'Token FCM berhasil diperbarui.',
        ], 200);
    }


}
