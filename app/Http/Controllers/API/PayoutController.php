<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\Payout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PayoutController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Payout::with([
            'user:id,name,email,role,institution_name,phone',
            'wasteListing:id,title,status',
            'pickup:id,pickup_code,status',
            'walletTransaction:id,transaction_reference,type,status',
            'approvedBy:id,name,email,role',
            'paidBy:id,name,email,role',
        ]);

        if ($user->role === 'institution') {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $payouts = $query->latest()->paginate((int) $request->input('per_page', 15));

        return $this->sendResponse($payouts, 'Payouts retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        if (!in_array($request->user()->role, ['admin', 'finance_officer'])) {
            return $this->sendError('Access denied.', ['error' => 'Only admin or finance officer can create payout.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => ['required', 'exists:users,id'],
            'waste_listing_id' => ['nullable', 'exists:waste_listings,id'],
            'pickup_id' => ['nullable', 'exists:pickups,id'],
            'wallet_transaction_id' => ['nullable', 'exists:wallet_transactions,id'],
            'gross_amount' => ['required', 'numeric', 'min:0'],
            'commission_amount' => ['nullable', 'numeric', 'min:0'],
            'net_amount' => ['nullable', 'numeric', 'min:0'],
            'points' => ['nullable', 'integer', 'min:0'],
            'payment_method' => ['required', Rule::in(['cash', 'mobile_money', 'bank_transfer', 'wallet', 'points'])],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'mobile_money_phone' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'admin_note' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $gross = (float) $request->gross_amount;
        $commission = (float) $request->input('commission_amount', 0);
        $net = $request->filled('net_amount')
            ? (float) $request->net_amount
            : max(0, $gross - $commission);

        $payout = Payout::create([
            'user_id' => $request->user_id,
            'waste_listing_id' => $request->waste_listing_id,
            'pickup_id' => $request->pickup_id,
            'wallet_transaction_id' => $request->wallet_transaction_id,
            'payout_reference' => 'PAY-' . now()->format('Ymd') . '-' . strtoupper(Str::random(8)),
            'gross_amount' => $gross,
            'commission_amount' => $commission,
            'net_amount' => $net,
            'currency' => $request->input('currency', 'RWF'),
            'points' => $request->input('points', 0),
            'payment_method' => $request->payment_method,
            'payment_reference' => $request->payment_reference,
            'account_name' => $request->account_name,
            'account_number' => $request->account_number,
            'bank_name' => $request->bank_name,
            'mobile_money_phone' => $request->mobile_money_phone,
            'status' => 'pending',
            'description' => $request->description,
            'admin_note' => $request->admin_note,
        ]);

        return $this->sendResponse($payout, 'Payout created successfully.');
    }

    public function show(Request $request, Payout $payout): JsonResponse
    {
        if ($request->user()->role === 'institution' && $payout->user_id !== $request->user()->id) {
            return $this->sendError('Access denied.', ['error' => 'You cannot view this payout.'], 403);
        }

        $payout->load(['user', 'wasteListing', 'pickup', 'walletTransaction', 'approvedBy', 'paidBy']);

        return $this->sendResponse($payout, 'Payout retrieved successfully.');
    }

    public function update(Request $request, Payout $payout): JsonResponse
    {
        if (!in_array($request->user()->role, ['admin', 'finance_officer'])) {
            return $this->sendError('Access denied.', ['error' => 'Only admin or finance officer can update payout.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['nullable', Rule::in(['pending', 'approved', 'paid', 'failed', 'cancelled', 'reversed'])],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'failure_reason' => ['nullable', 'string'],
            'cancellation_reason' => ['nullable', 'string'],
            'admin_note' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $data = $request->only([
            'status',
            'payment_reference',
            'failure_reason',
            'cancellation_reason',
            'admin_note',
        ]);

        if ($request->status === 'approved') {
            $data['approved_by'] = $request->user()->id;
            $data['approved_at'] = now();
        }

        if ($request->status === 'paid') {
            $data['paid_by'] = $request->user()->id;
            $data['paid_at'] = now();
        }

        $payout->update($data);

        return $this->sendResponse($payout->fresh(), 'Payout updated successfully.');
    }

    public function destroy(Request $request, Payout $payout): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return $this->sendError('Access denied.', ['error' => 'Only admin can delete payout.'], 403);
        }

        $payout->delete();

        return $this->sendResponse([], 'Payout deleted successfully.');
    }
}