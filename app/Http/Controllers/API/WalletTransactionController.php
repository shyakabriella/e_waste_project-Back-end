<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WalletTransactionController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = WalletTransaction::with([
            'user:id,name,email,role,institution_name,wallet_balance,points_balance',
            'wasteListing:id,title,status',
            'pickup:id,pickup_code,status',
            'createdBy:id,name,email,role',
            'approvedBy:id,name,email,role',
        ]);

        if ($user->role === 'institution') {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('user_id') && $user->role !== 'institution') {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $transactions = $query->latest()->paginate((int) $request->input('per_page', 15));

        return $this->sendResponse($transactions, 'Wallet transactions retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        if (!in_array($request->user()->role, ['admin', 'finance_officer'])) {
            return $this->sendError('Access denied.', ['error' => 'Only admin or finance officer can create wallet transactions.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => ['required', 'exists:users,id'],
            'waste_listing_id' => ['nullable', 'exists:waste_listings,id'],
            'pickup_id' => ['nullable', 'exists:pickups,id'],
            'type' => ['required', Rule::in([
                'credit',
                'debit',
                'points_credit',
                'points_debit',
                'payout',
                'commission',
                'adjustment',
            ])],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'points' => ['nullable', 'integer'],
            'payment_method' => ['nullable', Rule::in(['wallet', 'cash', 'mobile_money', 'bank_transfer', 'points', 'system'])],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['pending', 'completed', 'failed', 'cancelled', 'reversed'])],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'admin_note' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $transaction = DB::transaction(function () use ($request) {
            $walletUser = User::lockForUpdate()->findOrFail($request->user_id);

            $amount = (float) $request->input('amount', 0);
            $points = (int) $request->input('points', 0);

            $balanceBefore = (float) $walletUser->wallet_balance;
            $pointsBefore = (int) $walletUser->points_balance;

            $balanceAfter = $balanceBefore;
            $pointsAfter = $pointsBefore;

            $type = $request->type;
            $status = $request->input('status', 'completed');

            if ($status === 'completed') {
                if (in_array($type, ['credit', 'adjustment'])) {
                    $balanceAfter += $amount;
                }

                if (in_array($type, ['debit', 'payout', 'commission'])) {
                    $balanceAfter -= $amount;
                }

                if ($type === 'points_credit') {
                    $pointsAfter += $points;
                }

                if ($type === 'points_debit') {
                    $pointsAfter -= $points;
                }

                $walletUser->update([
                    'wallet_balance' => max(0, $balanceAfter),
                    'points_balance' => max(0, $pointsAfter),
                ]);
            }

            return WalletTransaction::create([
                'user_id' => $walletUser->id,
                'waste_listing_id' => $request->waste_listing_id,
                'pickup_id' => $request->pickup_id,
                'transaction_reference' => 'WTX-' . now()->format('Ymd') . '-' . strtoupper(Str::random(8)),
                'type' => $type,
                'amount' => $amount,
                'currency' => $request->input('currency', 'RWF'),
                'points' => $points,
                'balance_before' => $balanceBefore,
                'balance_after' => $status === 'completed' ? max(0, $balanceAfter) : $balanceBefore,
                'points_before' => $pointsBefore,
                'points_after' => $status === 'completed' ? max(0, $pointsAfter) : $pointsBefore,
                'payment_method' => $request->payment_method,
                'payment_reference' => $request->payment_reference,
                'status' => $status,
                'title' => $request->title,
                'description' => $request->description,
                'admin_note' => $request->admin_note,
                'created_by' => $request->user()->id,
                'approved_by' => $status === 'completed' ? $request->user()->id : null,
                'approved_at' => $status === 'completed' ? now() : null,
            ]);
        });

        return $this->sendResponse($transaction, 'Wallet transaction created successfully.');
    }

    public function show(Request $request, WalletTransaction $walletTransaction): JsonResponse
    {
        if ($request->user()->role === 'institution' && $walletTransaction->user_id !== $request->user()->id) {
            return $this->sendError('Access denied.', ['error' => 'You cannot view this transaction.'], 403);
        }

        $walletTransaction->load(['user', 'wasteListing', 'pickup', 'createdBy', 'approvedBy']);

        return $this->sendResponse($walletTransaction, 'Wallet transaction retrieved successfully.');
    }

    public function update(Request $request, WalletTransaction $walletTransaction): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return $this->sendError('Access denied.', ['error' => 'Only admin can update wallet transaction.'], 403);
        }

        $walletTransaction->update($request->only([
            'status',
            'admin_note',
            'payment_reference',
        ]));

        return $this->sendResponse($walletTransaction->fresh(), 'Wallet transaction updated successfully.');
    }

    public function destroy(Request $request, WalletTransaction $walletTransaction): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return $this->sendError('Access denied.', ['error' => 'Only admin can delete wallet transaction.'], 403);
        }

        $walletTransaction->delete();

        return $this->sendResponse([], 'Wallet transaction deleted successfully.');
    }
}