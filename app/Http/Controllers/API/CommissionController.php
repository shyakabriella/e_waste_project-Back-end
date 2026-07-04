<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\Commission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CommissionController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $query = Commission::with([
            'wasteListing:id,title,status',
            'pickup:id,pickup_code,status',
            'payout:id,payout_reference,status',
            'institution:id,name,email,role,institution_name',
            'confirmedBy:id,name,email,role',
        ]);

        if ($request->filled('institution_id')) {
            $query->where('institution_id', $request->institution_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $commissions = $query->latest()->paginate((int) $request->input('per_page', 15));

        return $this->sendResponse($commissions, 'Commissions retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return $this->sendError('Access denied.', ['error' => 'Only admin can create commission.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'waste_listing_id' => ['required', 'exists:waste_listings,id'],
            'pickup_id' => ['nullable', 'exists:pickups,id'],
            'payout_id' => ['nullable', 'exists:payouts,id'],
            'institution_id' => ['required', 'exists:users,id'],
            'gross_amount' => ['required', 'numeric', 'min:0'],
            'commission_rate' => ['nullable', 'numeric', 'min:0'],
            'commission_amount' => ['nullable', 'numeric', 'min:0'],
            'institution_amount' => ['nullable', 'numeric', 'min:0'],
            'commission_type' => ['nullable', Rule::in(['percentage', 'fixed'])],
            'description' => ['nullable', 'string'],
            'admin_note' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $gross = (float) $request->gross_amount;
        $rate = (float) $request->input('commission_rate', 0);
        $commissionType = $request->input('commission_type', 'percentage');

        $commissionAmount = $request->filled('commission_amount')
            ? (float) $request->commission_amount
            : ($commissionType === 'percentage' ? ($gross * $rate / 100) : $rate);

        $institutionAmount = $request->filled('institution_amount')
            ? (float) $request->institution_amount
            : max(0, $gross - $commissionAmount);

        $commission = Commission::create([
            'waste_listing_id' => $request->waste_listing_id,
            'pickup_id' => $request->pickup_id,
            'payout_id' => $request->payout_id,
            'institution_id' => $request->institution_id,
            'commission_reference' => 'COM-' . now()->format('Ymd') . '-' . strtoupper(Str::random(8)),
            'gross_amount' => $gross,
            'commission_rate' => $rate,
            'commission_amount' => $commissionAmount,
            'institution_amount' => $institutionAmount,
            'currency' => $request->input('currency', 'RWF'),
            'commission_type' => $commissionType,
            'status' => 'pending',
            'description' => $request->description,
            'admin_note' => $request->admin_note,
        ]);

        return $this->sendResponse($commission, 'Commission created successfully.');
    }

    public function show(Request $request, Commission $commission): JsonResponse
    {
        $commission->load(['wasteListing', 'pickup', 'payout', 'institution', 'confirmedBy']);

        return $this->sendResponse($commission, 'Commission retrieved successfully.');
    }

    public function update(Request $request, Commission $commission): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return $this->sendError('Access denied.', ['error' => 'Only admin can update commission.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['nullable', Rule::in(['pending', 'confirmed', 'paid', 'cancelled', 'reversed'])],
            'admin_note' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $data = $request->only(['status', 'admin_note', 'description']);

        if ($request->status === 'confirmed') {
            $data['confirmed_by'] = $request->user()->id;
            $data['confirmed_at'] = now();
        }

        $commission->update($data);

        return $this->sendResponse($commission->fresh(), 'Commission updated successfully.');
    }

    public function destroy(Request $request, Commission $commission): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return $this->sendError('Access denied.', ['error' => 'Only admin can delete commission.'], 403);
        }

        $commission->delete();

        return $this->sendResponse([], 'Commission deleted successfully.');
    }
}