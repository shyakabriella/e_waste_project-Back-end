<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    /**
     * Only admin can manage roles.
     */
    private function authorizeAdmin(Request $request): ?JsonResponse
    {
        $user = $request->user();

        if (!$user || $user->role !== 'admin') {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Access denied. Only admin can manage roles.',
            ], 403);
        }

        return null;
    }

    /**
     * Display all roles.
     */
    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request)) {
            return $response;
        }

        $roles = Role::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Roles retrieved successfully.',
            'data' => $roles,
        ]);
    }

    /**
     * Store a new role.
     */
    public function store(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request)) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100', 'unique:roles,name'],
            'slug' => ['nullable', 'string', 'max:100', 'unique:roles,slug'],
            'description' => ['nullable', 'string'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $slug = $request->filled('slug')
            ? Str::slug($request->slug)
            : Str::slug($request->name);

        if (Role::where('slug', $slug)->exists()) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'This role slug already exists.',
            ], 422);
        }

        $role = Role::create([
            'name' => $request->name,
            'slug' => $slug,
            'description' => $request->description,
            'permissions' => $request->permissions ?? [],
        ]);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Role created successfully.',
            'data' => $role,
        ], 201);
    }

    /**
     * Display one role.
     */
    public function show(Request $request, Role $role): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request)) {
            return $response;
        }

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Role retrieved successfully.',
            'data' => $role,
        ]);
    }

    /**
     * Update role.
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request)) {
            return $response;
        }

        if ($role->slug === 'admin') {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Admin role cannot be edited from this endpoint.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('roles', 'name')->ignore($role->id),
            ],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('roles', 'slug')->ignore($role->id),
            ],
            'description' => ['nullable', 'string'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $newSlug = $request->has('slug')
            ? Str::slug($request->slug)
            : $role->slug;

        $oldSlug = $role->slug;

        $role->update([
            'name' => $request->input('name', $role->name),
            'slug' => $newSlug,
            'description' => $request->input('description', $role->description),
            'permissions' => $request->input('permissions', $role->permissions ?? []),
        ]);

        /**
         * Because users table stores role as string slug,
         * update users when role slug changes.
         */
        if ($oldSlug !== $newSlug) {
            User::where('role', $oldSlug)->update([
                'role' => $newSlug,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Role updated successfully.',
            'data' => $role->fresh(),
        ]);
    }

    /**
     * Delete role.
     */
    public function destroy(Request $request, Role $role): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request)) {
            return $response;
        }

        if ($role->slug === 'admin') {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Admin role cannot be deleted.',
            ], 403);
        }

        $usersUsingRole = User::where('role', $role->slug)->count();

        if ($usersUsingRole > 0) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'This role cannot be deleted because some users are using it.',
                'users_count' => $usersUsingRole,
            ], 409);
        }

        $role->delete();

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Role deleted successfully.',
        ]);
    }
}