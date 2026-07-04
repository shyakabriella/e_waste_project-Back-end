<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\Pickup;
use App\Models\WasteListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PickupController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Pickup::with([
            'wasteListing:id,title,status,final_price',
            'institution:id,name,email,role,institution_name,phone',
            'assignedStaff:id,name,email,role',
            'driver:id,name,email,role,phone',
        ]);

        if ($user->role === 'institution') {
            $query->where('institution_id', $user->id);
        }

        if ($user->role === 'driver') {
            $query->where('driver_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('pickup_date')) {
            $query->whereDate('pickup_date', $request->pickup_date);
        }

        $pickups = $query->latest()->paginate((int) $request->input('per_page', 15));

        return $this->sendResponse($pickups, 'Pickups retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        if (!in_array($request->user()->role, ['admin', 'enviroserve_staff'])) {
            return $this->sendError('Access denied.', ['error' => 'Only admin or Enviroserve staff can schedule pickup.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'waste_listing_id' => ['required', 'exists:waste_listings,id'],
            'driver_id' => ['nullable', 'exists:users,id'],
            'pickup_date' => ['nullable', 'date'],
            'pickup_time' => ['nullable', 'date_format:H:i'],
            'scheduled_at' => ['nullable', 'date'],
            'pickup_address' => ['nullable', 'string'],
            'district' => ['nullable', 'string', 'max:100'],
            'sector' => ['nullable', 'string', 'max:100'],
            'cell' => ['nullable', 'string', 'max:100'],
            'village' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $listing = WasteListing::findOrFail($request->waste_listing_id);

        $pickup = Pickup::create([
            'waste_listing_id' => $listing->id,
            'institution_id' => $listing->institution_id,
            'assigned_staff_id' => $request->user()->id,
            'driver_id' => $request->driver_id,
            'pickup_code' => 'PCK-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
            'pickup_date' => $request->pickup_date,
            'pickup_time' => $request->pickup_time,
            'scheduled_at' => $request->scheduled_at,
            'pickup_address' => $request->pickup_address ?? $listing->pickup_address,
            'district' => $request->district ?? $listing->district,
            'sector' => $request->sector ?? $listing->sector,
            'cell' => $request->cell ?? $listing->cell,
            'village' => $request->village ?? $listing->village,
            'latitude' => $request->latitude ?? $listing->latitude,
            'longitude' => $request->longitude ?? $listing->longitude,
            'status' => 'scheduled',
        ]);

        $listing->update(['status' => 'pickup_scheduled']);

        return $this->sendResponse($pickup->load(['wasteListing', 'institution', 'assignedStaff', 'driver']), 'Pickup scheduled successfully.');
    }

    public function show(Request $request, Pickup $pickup): JsonResponse
    {
        $pickup->load([
            'wasteListing',
            'institution',
            'assignedStaff',
            'driver',
            'locations',
            'qrTags',
        ]);

        return $this->sendResponse($pickup, 'Pickup retrieved successfully.');
    }

    public function update(Request $request, Pickup $pickup): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'driver_id' => ['nullable', 'exists:users,id'],
            'pickup_date' => ['nullable', 'date'],
            'pickup_time' => ['nullable', 'date_format:H:i'],
            'scheduled_at' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in([
                'pending',
                'scheduled',
                'on_the_way',
                'arrived',
                'collected',
                'completed',
                'cancelled',
                'failed',
            ])],
            'collected_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'collected_quantity' => ['nullable', 'integer', 'min:1'],
            'collection_notes' => ['nullable', 'string'],
            'cancellation_reason' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $data = $request->only([
            'driver_id',
            'pickup_date',
            'pickup_time',
            'scheduled_at',
            'status',
            'collected_weight_kg',
            'collected_quantity',
            'collection_notes',
            'cancellation_reason',
        ]);

        if ($request->status === 'on_the_way') {
            $data['started_at'] = now();
            $data['gps_tracking_started_at'] = now();
        }

        if ($request->status === 'arrived') {
            $data['arrived_at'] = now();
        }

        if ($request->status === 'collected') {
            $data['collected_at'] = now();
            $pickup->wasteListing?->update(['status' => 'collected']);
        }

        if ($request->status === 'completed') {
            $data['completed_at'] = now();
            $data['gps_tracking_stopped_at'] = now();
            $pickup->wasteListing?->update(['status' => 'completed']);
        }

        if ($request->status === 'cancelled') {
            $data['cancelled_at'] = now();
            $data['gps_tracking_stopped_at'] = now();
            $pickup->wasteListing?->update(['status' => 'cancelled']);
        }

        $pickup->update($data);

        return $this->sendResponse($pickup->fresh(), 'Pickup updated successfully.');
    }

    public function confirmInstitution(Request $request, Pickup $pickup): JsonResponse
    {
        if ($pickup->institution_id !== $request->user()->id && $request->user()->role !== 'admin') {
            return $this->sendError('Access denied.', ['error' => 'Only institution can confirm this pickup.'], 403);
        }

        $pickup->update([
            'institution_confirmed' => true,
            'institution_confirmed_by' => $request->user()->id,
            'institution_confirmed_at' => now(),
        ]);

        return $this->sendResponse($pickup->fresh(), 'Institution pickup confirmation saved.');
    }

    public function confirmStaff(Request $request, Pickup $pickup): JsonResponse
    {
        if (!in_array($request->user()->role, ['admin', 'enviroserve_staff', 'driver'])) {
            return $this->sendError('Access denied.', ['error' => 'Only staff/driver can confirm pickup.'], 403);
        }

        $pickup->update([
            'staff_confirmed' => true,
            'staff_confirmed_by' => $request->user()->id,
            'staff_confirmed_at' => now(),
        ]);

        return $this->sendResponse($pickup->fresh(), 'Staff pickup confirmation saved.');
    }

    public function destroy(Request $request, Pickup $pickup): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return $this->sendError('Access denied.', ['error' => 'Only admin can delete pickup.'], 403);
        }

        $pickup->delete();

        return $this->sendResponse([], 'Pickup deleted successfully.');
    }
}