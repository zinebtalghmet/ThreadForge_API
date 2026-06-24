<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\JsonResponse;

class AuthController extends Controller
{
    public function register(RegisterRequest $request):JsonResponse
    {
        $user= User::create($request->validated());
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user'=> new UserResource($user),
            'token' =>$token,
        ],201);
    }

    public function login (LoginRequest $request):JsonResponse
    {
        if(!Auth::attempt($request->only('email','password'))){
            return response()->json([
                'message'=>'Invalid credentials.',
            ],401);
        }

        $user = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ],200);
    }

    public function logout():JsonResponse
    {
        auth()->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ],200);
    }
}
