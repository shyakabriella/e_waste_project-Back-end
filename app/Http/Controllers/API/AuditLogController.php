<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends BaseController
{
    private function authorizeAdmin(Request $request): ?JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return $this->sendError('Access denied.', ['error' => 'Only admin can view audit logs.'], 403);
        }

        return null;
    }

    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request)) {
            return $response;
        }

        $query = AuditLog::with('user:id,name,email,role');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $logs = $query->latest()->paginate((int) $request->input('per_page', 30));

        return $this->sendResponse($logs, 'Audit logs retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request)) {
            return $response;
        }

        $log = AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $request->input('action', 'manual_log'),
            'module' => $request->input('module', 'system'),
            'event' => $request->event,
            'description' => $request->description,
            'auditable_type' => $request->auditable_type,
            'auditable_id' => $request->auditable_id,
            'old_values' => $request->old_values,
            'new_values' => $request->new_values,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_method' => $request->method(),
            'request_url' => $request->fullUrl(),
            'status' => $request->input('status', 'success'),
            'error_message' => $request->error_message,
            'metadata' => $request->metadata,
        ]);

        return $this->sendResponse($log, 'Audit log created successfully.');
    }

    public function show(Request $request, AuditLog $auditLog): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request)) {
            return $response;
        }

        $auditLog->load('user:id,name,email,role');

        return $this->sendResponse($auditLog, 'Audit log retrieved successfully.');
    }

    public function update(Request $request, AuditLog $auditLog): JsonResponse
    {
        return $this->sendError('Action denied.', ['error' => 'Audit logs should not be updated.'], 403);
    }

    public function destroy(Request $request, AuditLog $auditLog): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request)) {
            return $response;
        }

        $auditLog->delete();

        return $this->sendResponse([], 'Audit log deleted successfully.');
    }
}