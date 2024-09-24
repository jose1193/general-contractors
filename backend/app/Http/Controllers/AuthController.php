<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;


use App\Http\Requests\LoginRequest;
use Illuminate\Foundation\Http\FormRequest;
use App\Http\Resources\UserResource;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

use Illuminate\Support\Facades\Cache;

class AuthController extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;


// USER LOGIN
    
    public function login(LoginRequest $request)
{
    $loginField = filter_var($request->input('email'), FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

    $user = User::where($loginField, $request->input('email'))->firstOrFail();

    if (!Hash::check($request->password, $user->password)) {
        return $this->sendFailedLoginResponse();
    }

    $token = $this->createTokenForUser($user, $request->filled('remember'));
    //$cookie = $this->createCookieForToken($token);

    $this->cacheUser($user);

    return $this->sendSuccessLoginResponse($user, $token);
}

   // Private methods for token and cookie creation
private function createTokenForUser($user, $remember = false)
{
    $token = $user->createToken('auth_token')->plainTextToken;
    if ($remember) {
        $rememberToken = Str::random(60);
        $user->forceFill(['remember_token' => hash('sha256', $rememberToken)])->save();
    }
    return $token;
}


private function createCookieForToken($token) {
     return cookie('token', $token, 60 * 24 * 365); 
}



    private function sendSuccessLoginResponse($user, $token)
{
    // El token completo está en formato "id|token". Solo queremos la parte del token real después del '|'
    $tokenParts = explode('|', $token);
    $tokenString = isset($tokenParts[1]) ? $tokenParts[1] : $token; // En caso de que no haya '|', usa el token completo

    return response()->json([
        'message' => 'User logged successfully',
        'token' => $tokenString,
        'token_type' => 'Bearer',
        'token_created_at' => $user->tokens()->where('name', 'auth_token')->first()->created_at->format('Y-m-d H:i:s'),
        'user' => new UserResource($user),
    ], 200);
}



    private function sendFailedLoginResponse()
    {
        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    // User logout
    public function logout(Request $request)
    {
        try {
            $request->user()->tokens()->delete();

            $cookie = cookie()->forget('token');

            return response()->json([
                'message' => 'Logged out successfully!'
            ])->withCookie($cookie);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while logging out.'
            ], 500);
        }
    }

   

    // Get current user details
public function user(Request $request)
{
    $user = $this->getCachedUser($request->user()->id);

    $userResource = new UserResource($user);
    $userRoles = $user->roles->pluck('name')->all();
    $userResource->additional(['user_role' => $userRoles]);

    $responseData = $userResource->toArray($request);

    return response()->json($responseData);
}

    // Update user password
    public function updatePassword(Request $request)
    {
        try {
            $request->validate([
                'current_password' => ['required', 'string'],
                'new_password' => [
                    'required', 'string', 'min:5', 'max:30',
                    'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()\-_=+{};:,<.>])[A-Za-z\d!@#$%^&*()\-_=+{};:,<>.]{5,}$/'
                ],
            ]);

            $user = Auth::user();

            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json(['error' => 'Current password does not match'], 401);
            }

            $user->password = Hash::make($request->new_password);
            $user->save();

            $this->cacheUser($user);

            return response()->json(['success' => 'Password updated successfully']);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    
    // Private method to cache user data
private function cacheUser($user)
{
    $user->load('roles'); // Cargar roles u otras relaciones necesarias
    Cache::put('user:' . $user->id, $user, 60 * 60 * 24); // Cache for 1 day
}

private function getCachedUser($userId)
{
    $cachedUser = Cache::get('user:' . $userId);
    if ($cachedUser) {
        return $cachedUser;
    }

    $user = User::findOrFail($userId);
    $this->cacheUser($user);

    return $user;
}


 public function checkEmailAvailability($email)
{
   

    // Obtener el usuario actualmente autenticado
    $currentUser = auth()->user();

    // Verificar si el correo pertenece al usuario autenticado
    if ($currentUser && $currentUser->email === $email) {
        return response()->json([
            'available' => true,
            'message' => ''
        ], 200);
    }

    // Verificar si el correo ya está en la base de datos
    $exists = User::where('email', $email)->exists();

    return response()->json([
        'available' => !$exists,
        'message' => $exists ? 'Email is already taken' : 'Email is available'
    ], 200);
}

public function checkUsernameAvailability($username)
{
   

    // Obtener el usuario actualmente autenticado
    $currentUser = auth()->user();

    // Verificar si el nombre de usuario pertenece al usuario autenticado
    if ($currentUser && $currentUser->username === $username) {
        return response()->json([
            'available' => true,
            'message' => ''
        ], 200);
    }

    // Verificar si el nombre de usuario ya está en la base de datos
    $exists = User::where('username', $username)->exists();

    return response()->json([
        'available' => !$exists,
        'message' => $exists ? 'Username is already taken' : 'Username is available'
    ], 200);
}

}