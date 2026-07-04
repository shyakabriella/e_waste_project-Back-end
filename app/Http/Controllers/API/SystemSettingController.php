<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SystemSettingController extends BaseController
{
    private function isAdmin(Request $request): bool
    {
        return $request->user() && $request->user()->role === 'admin';
    }

    public function index(Request $request): JsonResponse
    {
        $query = SystemSetting::with('updatedBy:id,name,email,role');

        if (!$this->isAdmin($request)) {
            $query->where('is_public', true)->where('status', 'active');
        }

        if ($request->filled('group')) {
            $query->where('group', $request->group);
        }

        if ($request->filled('status') && $this->isAdmin($request)) {
            $query->where('status', $request->status);
        }

        $settings = $query->orderBy('group')->orderBy('key')->get();

        return $this->sendResponse($settings, 'System settings retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        if (!$this->isAdmin($request)) {
            return $this->sendError('Access denied.', ['error' => 'Only admin can create system settings.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'key' => ['required', 'string', 'max:255', 'unique:system_settings,key'],
            'value' => ['nullable'],
            'type' => ['required', Rule::in(['string', 'integer', 'decimal', 'boolean', 'json'])],
            'group' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_public' => ['nullable', 'boolean'],
            'is_editable' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $setting = SystemSetting::create([
            'key' => $request->key,
            'value' => is_array($request->value) ? json_encode($request->value) : $request->value,
            'type' => $request->type,
            'group' => $request->input('group', 'general'),
            'description' => $request->description,
            'is_public' => $request->boolean('is_public', false),
            'is_editable' => $request->boolean('is_editable', true),
            'updated_by' => $request->user()->id,
            'status' => $request->input('status', 'active'),
        ]);

        return $this->sendResponse($setting, 'System setting created successfully.');
    }

    public function show(Request $request, SystemSetting $systemSetting): JsonResponse
    {
        if (!$this->isAdmin($request) && (!$systemSetting->is_public || $systemSetting->status !== 'active')) {
            return $this->sendError('Not found.', ['error' => 'System setting not found.'], 404);
        }

        $systemSetting->load('updatedBy:id,name,email,role');

        return $this->sendResponse($systemSetting, 'System setting retrieved successfully.');
    }

    public function update(Request $request, SystemSetting $systemSetting): JsonResponse
    {
        if (!$this->isAdmin($request)) {
            return $this->sendError('Access denied.', ['error' => 'Only admin can update system settings.'], 403);
        }

        if (!$systemSetting->is_editable) {
            return $this->sendError('Update denied.', ['error' => 'This setting is not editable.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'value' => ['nullable'],
            'type' => ['nullable', Rule::in(['string', 'integer', 'decimal', 'boolean', 'json'])],
            'group' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_public' => ['nullable', 'boolean'],
            'is_editable' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $systemSetting->update([
            'value' => $request->has('value')
                ? (is_array($request->value) ? json_encode($request->value) : $request->value)
                : $systemSetting->value,
            'type' => $request->input('type', $systemSetting->type),
            'group' => $request->input('group', $systemSetting->group),
            'description' => $request->input('description', $systemSetting->description),
            'is_public' => $request->has('is_public')
                ? $request->boolean('is_public')
                : $systemSetting->is_public,
            'is_editable' => $request->has('is_editable')
                ? $request->boolean('is_editable')
                : $systemSetting->is_editable,
            'status' => $request->input('status', $systemSetting->status),
            'updated_by' => $request->user()->id,
        ]);

        return $this->sendResponse($systemSetting->fresh(), 'System setting updated successfully.');
    }

    public function destroy(Request $request, SystemSetting $systemSetting): JsonResponse
    {
        if (!$this->isAdmin($request)) {
            return $this->sendError('Access denied.', ['error' => 'Only admin can delete system settings.'], 403);
        }

        if (!$systemSetting->is_editable) {
            return $this->sendError('Delete denied.', ['error' => 'This setting is protected.'], 403);
        }

        $systemSetting->delete();

        return $this->sendResponse([], 'System setting deleted successfully.');
    }
}