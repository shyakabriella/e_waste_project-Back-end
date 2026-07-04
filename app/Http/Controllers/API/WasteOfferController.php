<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\User;
use App\Models\WasteListing;
use App\Models\WasteOffer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class WasteOfferController extends BaseController
{
    private function isStaff(User $user): bool
    {
        return in_array($user->role, ['admin', 'enviroserve_staff'], true);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = WasteOffer::with([
            'wasteListing:id,title,institution_id,status,estimated_weight_kg,expected_price,verified_weight_kg,final_price,currency',
            'wasteListing.institution:id,name,email,role,institution_name,phone',
            'offeredBy:id,name,email,role',
            'offeredTo:id,name,email,role,institution_name,phone',
            'respondedBy:id,name,email,role',
        ]);

        if (!$this->isStaff($user)) {
            $query->where(function ($builder) use ($user) {
                $builder->where('offered_to', $user->id)
                    ->orWhere('offered_by', $user->id)
                    ->orWhereHas('wasteListing', function ($listingQuery) use ($user) {
                        $listingQuery->where('institution_id', $user->id);
                    });
            });
        }

        if ($request->filled('waste_listing_id')) {
            $query->where('waste_listing_id', $request->waste_listing_id);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('offer_type') && $request->offer_type !== 'all') {
            $query->where('offer_type', $request->offer_type);
        }

        $perPage = (int) $request->input('per_page', 15);
        $perPage = max(5, min($perPage, 100));

        $offers = $query->latest()->paginate($perPage);

        return $this->sendResponse($offers, 'Waste offers retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        if (!$this->isStaff($request->user())) {
            return $this->sendError(
                'Access denied.',
                ['error' => 'Only admin or Enviroserve staff can create waste offers.'],
                403
            );
        }

        $validator = Validator::make($request->all(), [
            'waste_listing_id' => ['required', 'exists:waste_listings,id'],
            'offered_to' => ['nullable', 'exists:users,id'],
            'offer_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'offer_type' => ['nullable', Rule::in([
                'initial_offer',
                'counter_offer',
                'final_offer',
            ])],
            'message' => ['nullable', 'string'],
            'expires_at' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $listing = WasteListing::with(['institution'])->findOrFail($request->waste_listing_id);

        if (!in_array($listing->status, ['verified', 'offer_sent', 'offer_accepted'], true)) {
            return $this->sendError(
                'Offer denied.',
                ['error' => 'Waste must be verified before sending an offer.'],
                422
            );
        }

        $offerAmount = $request->filled('offer_amount')
            ? (float) $request->offer_amount
            : (float) ($listing->final_price ?: $listing->expected_price ?: 0);

        if ($offerAmount <= 0) {
            return $this->sendError(
                'Validation Error.',
                ['offer_amount' => ['Offer amount is required because listing final price is missing.']],
                422
            );
        }

        $offeredTo = $request->input('offered_to') ?: $listing->institution_id;

        $offer = WasteOffer::create([
            'waste_listing_id' => $listing->id,
            'offered_by' => $request->user()->id,
            'offered_to' => $offeredTo,
            'offer_amount' => $offerAmount,
            'currency' => $request->input('currency', $listing->currency ?: 'RWF'),
            'offer_type' => $request->input('offer_type', 'initial_offer'),
            'status' => 'pending',
            'message' => $request->message ?: $this->defaultOfferMessage($listing, $offerAmount),
            'expires_at' => $request->expires_at,
        ]);

        $listing->update([
            'final_price' => $offerAmount,
            'status' => 'offer_sent',
        ]);

        return $this->sendResponse(
            $offer->load([
                'wasteListing',
                'wasteListing.institution:id,name,email,role,institution_name,phone',
                'offeredBy:id,name,email,role',
                'offeredTo:id,name,email,role,institution_name,phone',
            ]),
            'Offer created successfully.'
        );
    }

    public function show(Request $request, WasteOffer $wasteOffer): JsonResponse
    {
        $user = $request->user();
        $wasteOffer->load([
            'wasteListing',
            'wasteListing.institution:id,name,email,role,institution_name,phone',
            'offeredBy:id,name,email,role',
            'offeredTo:id,name,email,role,institution_name,phone',
            'respondedBy:id,name,email,role',
        ]);

        if (!$this->canViewOffer($user, $wasteOffer)) {
            return $this->sendError(
                'Access denied.',
                ['error' => 'You cannot view this offer.'],
                403
            );
        }

        return $this->sendResponse($wasteOffer, 'Offer retrieved successfully.');
    }

    public function update(Request $request, WasteOffer $wasteOffer): JsonResponse
    {
        if (!$this->isStaff($request->user()) && $wasteOffer->offered_by !== $request->user()->id) {
            return $this->sendError(
                'Access denied.',
                ['error' => 'Only offer creator, admin, or staff can update this offer.'],
                403
            );
        }

        if ($wasteOffer->status !== 'pending') {
            return $this->sendError(
                'Update denied.',
                ['error' => 'Only pending offers can be updated.'],
                403
            );
        }

        $validator = Validator::make($request->all(), [
            'offer_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'offer_type' => ['nullable', Rule::in([
                'initial_offer',
                'counter_offer',
                'final_offer',
            ])],
            'message' => ['nullable', 'string'],
            'expires_at' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $wasteOffer->update($request->only([
            'offer_amount',
            'currency',
            'offer_type',
            'message',
            'expires_at',
        ]));

        if ($request->filled('offer_amount')) {
            $wasteOffer->wasteListing?->update([
                'final_price' => $request->offer_amount,
                'status' => 'offer_sent',
            ]);
        }

        return $this->sendResponse(
            $wasteOffer->fresh()->load([
                'wasteListing',
                'offeredBy',
                'offeredTo',
                'respondedBy',
            ]),
            'Offer updated successfully.'
        );
    }

    public function respond(Request $request, WasteOffer $wasteOffer): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', Rule::in([
                'accepted',
                'rejected',
                'cancelled',
            ])],
            'response_note' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        if ($wasteOffer->status !== 'pending') {
            return $this->sendError(
                'Action denied.',
                ['error' => 'This offer is no longer pending.'],
                403
            );
        }

        $wasteOffer->load(['wasteListing']);

        if (!$wasteOffer->wasteListing) {
            return $this->sendError(
                'Invalid offer.',
                ['error' => 'Offer does not have a linked listing.'],
                422
            );
        }

        $user = $request->user();

        $isAllowed = $user->role === 'admin'
            || $wasteOffer->offered_to === $user->id
            || $wasteOffer->wasteListing->institution_id === $user->id
            || $this->isStaff($user);

        if (!$isAllowed) {
            return $this->sendError(
                'Access denied.',
                ['error' => 'You cannot respond to this offer.'],
                403
            );
        }

        $wasteOffer->update([
            'status' => $request->status,
            'responded_by' => $user->id,
            'responded_at' => now(),
            'response_note' => $request->response_note,
        ]);

        if ($request->status === 'accepted') {
            WasteOffer::where('waste_listing_id', $wasteOffer->waste_listing_id)
                ->where('id', '!=', $wasteOffer->id)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);

            $wasteOffer->wasteListing->update([
                'final_price' => $wasteOffer->offer_amount,
                'status' => 'offer_accepted',
            ]);
        }

        if ($request->status === 'rejected') {
            $wasteOffer->wasteListing->update([
                'status' => 'verified',
            ]);
        }

        if ($request->status === 'cancelled') {
            $wasteOffer->wasteListing->update([
                'status' => 'verified',
            ]);
        }

        return $this->sendResponse(
            $wasteOffer->fresh()->load([
                'wasteListing',
                'offeredBy',
                'offeredTo',
                'respondedBy',
            ]),
            'Offer response saved successfully.'
        );
    }

    public function destroy(Request $request, WasteOffer $wasteOffer): JsonResponse
    {
        if (!$this->isStaff($request->user()) && $wasteOffer->offered_by !== $request->user()->id) {
            return $this->sendError(
                'Access denied.',
                ['error' => 'Only admin, staff, or offer creator can delete this offer.'],
                403
            );
        }

        $listing = $wasteOffer->wasteListing;

        $wasteOffer->delete();

        if ($listing && $listing->status === 'offer_sent') {
            $hasPendingOffer = WasteOffer::where('waste_listing_id', $listing->id)
                ->where('status', 'pending')
                ->exists();

            if (!$hasPendingOffer) {
                $listing->update(['status' => 'verified']);
            }
        }

        return $this->sendResponse([], 'Offer deleted successfully.');
    }

    private function canViewOffer(User $user, WasteOffer $offer): bool
    {
        if ($this->isStaff($user)) {
            return true;
        }

        if ($offer->offered_by === $user->id || $offer->offered_to === $user->id) {
            return true;
        }

        return $offer->wasteListing && $offer->wasteListing->institution_id === $user->id;
    }

    private function defaultOfferMessage(WasteListing $listing, float $amount): string
    {
        $weight = $listing->verified_weight_kg ?: $listing->estimated_weight_kg ?: 0;
        $currency = $listing->currency ?: 'RWF';

        return "Offer generated after staff verification. Verified weight: {$weight} kg. Offer amount: {$currency} {$amount}.";
    }
}
