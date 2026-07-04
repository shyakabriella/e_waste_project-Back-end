<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\Pickup;
use App\Models\PickupLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PickupLocationController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $query = PickupLocation::with(['pickup:id,pickup_code,status', 'driver:id,name,email,role,phone']);

        if ($request->filled('pickup_id')) {
            $query->where('pickup_id', $request->pickup_id);
        }

        if ($request->boolean('current_only', false)) {
            $query->where('is_current', true);
        }

        $locations = $query->latest('recorded_at')->paginate((int) $request->input('per_page', 30));

        return $this->sendResponse($locations, 'Pickup locations retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'pickup_id' => ['required', 'exists:pickups,id'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'accuracy' => ['nullable', 'numeric'],
            'speed' => ['nullable', 'numeric'],
            'heading' => ['nullable', 'numeric'],
            'altitude' => ['nullable', 'numeric'],
            'source' => ['nullable', 'string', 'max:100'],
            'device_id' => ['nullable', 'string', 'max:255'],
            'battery_level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $pickup = Pickup::findOrFail($request->pickup_id);

        if (
            !in_array($request->user()->role, ['admin', 'enviroserve_staff', 'driver']) &&
            $pickup->driver_id !== $request->user()->id
        ) {
            return $this->sendError('Access denied.', ['error' => 'You cannot update GPS for this pickup.'], 403);
        }

        PickupLocation::where('pickup_id', $pickup->id)->update(['is_current' => false]);

        $location = PickupLocation::create([
            'pickup_id' => $pickup->id,
            'driver_id' => $request->user()->id,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'accuracy' => $request->accuracy,
            'speed' => $request->speed,
            'heading' => $request->heading,
            'altitude' => $request->altitude,
            'status' => $request->accuracy && $request->accuracy > 100 ? 'inaccurate' : 'active',
            'is_current' => true,
            'source' => $request->input('source', 'android_app'),
            'device_id' => $request->device_id,
            'battery_level' => $request->battery_level,
            'recorded_at' => $request->recorded_at ?? now(),
        ]);

        return $this->sendResponse($location, 'Pickup location saved successfully.');
    }

    public function show(Request $request, PickupLocation $pickupLocation): JsonResponse
    {
        $pickupLocation->load(['pickup', 'driver']);

        return $this->sendResponse($pickupLocation, 'Pickup location retrieved successfully.');
    }

    public function update(Request $request, PickupLocation $pickupLocation): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return $this->sendError('Access denied.', ['error' => 'Only admin can update GPS records.'], 403);
        }

        $pickupLocation->update($request->only([
            'status',
            'is_current',
        ]));

        return $this->sendResponse($pickupLocation->fresh(), 'Pickup location updated successfully.');
    }

    public function destroy(Request $request, PickupLocation $pickupLocation): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return $this->sendError('Access denied.', ['error' => 'Only admin can delete GPS records.'], 403);
        }

        $pickupLocation->delete();

        return $this->sendResponse([], 'Pickup location deleted successfully.');
    }
}