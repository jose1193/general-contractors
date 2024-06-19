<?php

namespace App\Http\Controllers;
use App\Http\Controllers\BaseController as BaseController;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use Hash;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\Cache;

use App\Interfaces\UsersRepositoryInterface;
use App\Http\Requests\CreateUserRequest;
use App\Classes\ApiResponseClass;
use App\Http\Resources\UserResource;

class UsersController extends BaseController
{

    protected $cacheKey;
    
    protected $cacheTime = 720;
    protected $userId;

    private UsersRepositoryInterface $usersRepositoryInterface;

    public function __construct(UsersRepositoryInterface $usersRepositoryInterface)
    {
        // Asignar el repositorio a la propiedad privada
        $this->usersRepositoryInterface = $usersRepositoryInterface;

        // Middleware para permisos
        $this->middleware('check.permission:Super Master')->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
        
        // Middleware para establecer el userId y la clave de cache
        $this->middleware(function ($request, $next) {
            $this->userId = Auth::id();
            $this->cacheKey = 'users_' . $this->userId . '_total_list';
            return $next($request);
        });
    }


    
    // SHOW LIST OF USERS

      public function index()
    {
        try {
           
            // Invalidar y actualizar la caché con nuevos datos
            $this->refreshCache($this->cacheKey, $this->cacheTime, function () {
                return $this->usersRepositoryInterface->index();
            });

            // Obtener los datos actualizados de la caché
            $data = Cache::get($this->cacheKey);

            // Verifica si la colección es válida
            if ($data === null || !is_iterable($data)) {
                Log::warning('Data fetched from cache is null or not iterable');
                return response()->json(['message' => 'No users found or invalid data structure'], 404);
            }

            // Transformar la colección en recursos de usuario
            $userResources = UserResource::collection($data);

            return ApiResponseClass::sendResponse($userResources, '', 200);

        } catch (QueryException $e) {
            Log::error('Database error occurred while fetching users: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            return response()->json(['message' => 'Database error occurred while fetching users'], 500);
        } catch (\Exception $e) {
            Log::error('Error occurred while fetching users: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            return response()->json(['message' => 'Error occurred while fetching users'], 500);
        }
    }
    



private function updateUsersCache()
{
    $this->refreshCache($this->cacheKey, $this->cacheTime, function () {
         return User::withTrashed()->orderBy('id', 'DESC')->get();
    });
}



    // SYNC ROLES
    public function create()
{
    $cacheKey = 'roles_list';
    $roles = $this->getCachedData($cacheKey, 360, function () {
        return Role::orderBy('id', 'DESC')->get();
    });
    return response()->json(['roles' => $roles], 200);
}



    // STORE USER
   

 public function store(CreateUserRequest $request)
    {
        // Validar y obtener los detalles de la solicitud
        $details = $request->validated();
        $details['uuid'] = Uuid::uuid4()->toString();

        DB::beginTransaction();

        try {
            // Guardar el usuario en la base de datos
            $data = $this->usersRepositoryInterface->store($details);

            // Sincronizar roles del usuario
            $this->syncRoles($data, $details['role_id']);

            // Obtener el primer rol del usuario
            $role = $data->roles->first(); 
            $data->user_role = $role->name ?? null;
            $data->role_id = $role->id ?? null;

            // Actualizar la caché de usuarios
            $this->updateUsersCache();

            DB::commit();

            // Retornar la respuesta con el usuario creado
            return ApiResponseClass::sendSimpleResponse(new UserResource($data), 200);

        } catch (\Exception $ex) {
            DB::rollBack();
            // Retornar la respuesta de error
            return response()->json(['message' => 'Error occurred while creating user', 'error' => $ex->getMessage()], 500);
        }
    }


    // Método para sincronizar roles del usuario
    protected function syncRoles(User $user, $roleId)
    {
        $user->roles()->sync($roleId);
    }



   // Método para actualizar un usuario
    public function update(CreateUserRequest $request, $uuid)
    {
        // Validar y obtener los detalles de la solicitud
        $updateDetails = $request->validated();

        DB::beginTransaction();

        try {
            // Actualizar la contraseña si se proporciona una nueva
            $this->updatePassword($updateDetails);

            // Actualizar el usuario en la base de datos y obtener el usuario actualizado
            $user = $this->usersRepositoryInterface->update($updateDetails, $uuid);

            // Sincronizar roles del usuario
            $this->syncRoles($user, $updateDetails['role_id']);

            // Actualizar la caché de usuarios
            $this->updateUsersCache();

            DB::commit();

            // Retornar la respuesta con el usuario actualizado
            return ApiResponseClass::sendSimpleResponse(new UserResource($user), 200);

        } catch (\Exception $ex) {
            DB::rollBack();
            return response()->json(['message' => 'Error occurred while updating user', 'error' => $ex->getMessage()], 500);
        }
    }



    // UPDATE PASSWORD USER
    private function updatePassword(array &$input)
    {
        if (!empty($input['password'])) {
            // Encriptar la nueva contraseña
            $input['password'] = bcrypt($input['password']);
        }
    }


    // SHOW PROFILE USER
   public function show($uuid)
{
    try {
        $cacheKey = 'user_' . $uuid;
        
        // Buscar el usuario en caché o en la base de datos

        $user = $this->getCachedData($cacheKey, $this->cacheTime, function () use ($uuid) {
            return $this->usersRepositoryInterface->getByUuid($uuid);
        });

        return ApiResponseClass::sendSimpleResponse(new UserResource($user),'',200);

    } catch (\Exception $e) {
        // Manejar cualquier excepción y devolver una respuesta de error
        return response()->json(['message' => 'Error occurred while fetching user'], 500);
    }
}




    // USER DELETE
    public function destroy($uuid)
{
    try {


        $this->usersRepositoryInterface->delete($uuid);
         // Invalidar el caché del usuario
        $this->invalidateCache('user_' . $uuid);
        return ApiResponseClass::sendResponse('User Delete Successful','',200);


    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        // Manejar el caso donde el usuario no fue encontrado
        return response()->json(['message' => 'User not found'], 404);
    } catch (\Exception $e) {
        // Manejar cualquier otra excepción y devolver una respuesta de error
        return response()->json(['message' => 'Error occurred while deleting user'], 500);
    }
}


// USER RESTORE
public function restore($uuid)
    {
        try {
            // Usar el repositorio para restaurar el usuario
            $user = $this->usersRepositoryInterface->restore($uuid);

            // Invalidar la caché del usuario
            $this->invalidateCache('user_' . $uuid);

            // Actualizar la caché de la lista de usuarios
            $this->updateUsersCache();

            // Enviar la respuesta utilizando el método simplificado
            return ApiResponseClass::sendSimpleResponse(new UserResource($user), 200);
        } catch (\InvalidArgumentException $e) {
            // Manejar errores de validación del UUID
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            // Manejar cualquier otra excepción
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }



}
