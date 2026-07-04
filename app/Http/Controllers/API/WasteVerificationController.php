<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\User;
use App\Models\WasteAiAnalysis;
use App\Models\WasteCategory;
use App\Models\WasteListing;
use App\Models\WasteVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class WasteVerificationController extends BaseController
{
    private function isStaff(User $user): bool
    {
        return in_array($user->role, ['admin', 'enviroserve_staff'], true);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = WasteVerification::with([
            'wasteListing:id,title,institution_id,status,estimated_weight_kg,expected_price,ai_detected_item,ai_detected_category',
            'wasteListing.institution:id,name,email,role,institution_name,phone',
            'verifiedBy:id,name,email,role',
            'confirmedCategory',
            'aiAnalysis',
        ]);

        if (!$this->isStaff($user)) {
            $query->whereHas('wasteListing', function ($builder) use ($user) {
                $builder->where('institution_id', $user->id);
            });
        }

        if ($request->filled('waste_listing_id')) {
            $query->where('waste_listing_id', $request->waste_listing_id);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $perPage = (int) $request->input('per_page', 15);
        $perPage = max(5, min($perPage, 100));

        $verifications = $query->latest()->paginate($perPage);

        return $this->sendResponse($verifications, 'Waste verifications retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        if (!$this->isStaff($request->user())) {
            return $this->sendError(
                'Access denied.',
                ['error' => 'Only admin or Enviroserve staff can verify waste.'],
                403
            );
        }

        $validator = Validator::make($request->all(), [
            'waste_listing_id' => ['required', 'exists:waste_listings,id'],
            'waste_ai_analysis_id' => ['nullable', 'exists:waste_ai_analyses,id'],
            'confirmed_category_id' => ['nullable', 'exists:waste_categories,id'],

            'client_estimated_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'ai_estimated_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'verified_weight_kg' => ['nullable', 'numeric', 'min:0'],

            'client_quantity' => ['nullable', 'integer', 'min:0'],
            'verified_quantity' => ['nullable', 'integer', 'min:0'],

            'condition_status' => ['required', Rule::in([
                'good',
                'damaged',
                'partially_damaged',
                'hazardous',
                'not_e_waste',
                'not_accepted',
            ])],

            'is_accepted' => ['nullable', 'boolean'],
            'is_hazardous' => ['nullable', 'boolean'],

            'status' => ['nullable', Rule::in([
                'pending',
                'approved',
                'rejected',
                'corrected',
            ])],

            'price_per_kg' => ['nullable', 'numeric', 'min:0'],
            'price_per_item' => ['nullable', 'numeric', 'min:0'],
            'verified_total_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],

            'verification_notes' => ['nullable', 'string'],
            'rejection_reason' => ['nullable', 'string'],

            /*
            |--------------------------------------------------------------------------
            | Item-level verification from frontend
            |--------------------------------------------------------------------------
            | This does not require a new DB table. We store the summary inside
            | verification_notes, while scalar totals remain in DB columns.
            */
            'verified_items' => ['nullable', 'array'],
            'verified_items.*.item_name' => ['nullable', 'string', 'max:255'],
            'verified_items.*.category_id' => ['nullable', 'exists:waste_categories,id'],
            'verified_items.*.category_name' => ['nullable', 'string', 'max:255'],
            'verified_items.*.verified_quantity' => ['nullable', 'integer', 'min:0'],
            'verified_items.*.verified_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'verified_items.*.condition_status' => ['nullable', 'string', 'max:100'],
            'verified_items.*.notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $listing = WasteListing::with(['category'])->findOrFail($request->waste_listing_id);

        $latestAiAnalysis = $request->filled('waste_ai_analysis_id')
            ? WasteAiAnalysis::find($request->waste_ai_analysis_id)
            : WasteAiAnalysis::where('waste_listing_id', $listing->id)->latest()->first();

        $confirmedCategory = $request->filled('confirmed_category_id')
            ? WasteCategory::find($request->confirmed_category_id)
            : $listing->category;

        $verifiedItems = $this->prepareVerifiedItems($request->input('verified_items', []));
        $verifiedItemsWeight = $this->sumVerifiedItemsWeight($verifiedItems);
        $verifiedItemsQuantity = $this->sumVerifiedItemsQuantity($verifiedItems);

        $verifiedWeightKg = $request->filled('verified_weight_kg')
            ? (float) $request->verified_weight_kg
            : $verifiedItemsWeight;

        if ($verifiedWeightKg <= 0 && $request->condition_status !== 'not_e_waste') {
            return $this->sendError(
                'Validation Error.',
                ['verified_weight_kg' => ['Verified weight is required unless the item is not e-waste.']],
                422
            );
        }

        $verifiedQuantity = $request->filled('verified_quantity')
            ? (int) $request->verified_quantity
            : ($verifiedItemsQuantity > 0 ? $verifiedItemsQuantity : (int) $listing->quantity);

        $pricePerKg = $request->filled('price_per_kg')
            ? (float) $request->price_per_kg
            : (float) ($confirmedCategory?->price_per_kg ?? 0);

        $pricePerItem = $request->filled('price_per_item')
            ? (float) $request->price_per_item
            : (float) ($confirmedCategory?->price_per_item ?? 0);

        $verifiedTotalPrice = $request->filled('verified_total_price')
            ? (float) $request->verified_total_price
            : $this->calculateVerifiedTotalPrice(
                $verifiedWeightKg,
                $verifiedQuantity,
                $pricePerKg,
                $pricePerItem,
                $verifiedItems
            );

        $conditionStatus = $request->condition_status;

        $isAccepted = $request->boolean('is_accepted', true);

        if (in_array($conditionStatus, ['not_e_waste', 'not_accepted'], true)) {
            $isAccepted = false;
            $verifiedTotalPrice = 0;
        }

        $status = $request->input('status', $isAccepted ? 'approved' : 'rejected');

        if (!$isAccepted) {
            $status = 'rejected';
        }

        $notes = $this->buildVerificationNotes(
            $request->input('verification_notes'),
            $verifiedItems,
            $latestAiAnalysis
        );

        $verification = WasteVerification::create([
            'waste_listing_id' => $listing->id,
            'verified_by' => $request->user()->id,
            'waste_ai_analysis_id' => $latestAiAnalysis?->id,
            'confirmed_category_id' => $confirmedCategory?->id,

            'client_estimated_weight_kg' => $request->client_estimated_weight_kg ?? $listing->estimated_weight_kg,
            'ai_estimated_weight_kg' => $request->ai_estimated_weight_kg ?? $listing->ai_estimated_weight_kg,
            'verified_weight_kg' => $verifiedWeightKg,

            'client_quantity' => $request->client_quantity ?? $listing->quantity,
            'verified_quantity' => $verifiedQuantity,

            'condition_status' => $conditionStatus,
            'is_accepted' => $isAccepted,
            'is_hazardous' => $request->boolean(
                'is_hazardous',
                (bool) ($confirmedCategory?->is_hazardous ?? false)
            ),

            'status' => $status,

            'price_per_kg' => $pricePerKg,
            'price_per_item' => $pricePerItem,
            'verified_total_price' => $verifiedTotalPrice,
            'currency' => $request->input('currency', 'RWF'),

            'verification_notes' => $notes,
            'rejection_reason' => $request->rejection_reason,
            'verified_at' => now(),
        ]);

        $listing->update([
            'waste_category_id' => $confirmedCategory?->id ?? $listing->waste_category_id,
            'verified_weight_kg' => $verifiedWeightKg,
            'final_price' => $verifiedTotalPrice,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'verification_notes' => $notes,
            'status' => $isAccepted ? 'verified' : 'rejected',
        ]);

        return $this->sendResponse(
            $verification->load([
                'wasteListing',
                'verifiedBy',
                'confirmedCategory',
                'aiAnalysis',
            ]),
            'Waste verified successfully.'
        );
    }

    public function show(Request $request, WasteVerification $wasteVerification): JsonResponse
    {
        $wasteVerification->load([
            'wasteListing',
            'wasteListing.institution:id,name,email,role,institution_name,phone',
            'verifiedBy',
            'aiAnalysis',
            'confirmedCategory',
        ]);

        return $this->sendResponse($wasteVerification, 'Waste verification retrieved successfully.');
    }

    public function update(Request $request, WasteVerification $wasteVerification): JsonResponse
    {
        if (!$this->isStaff($request->user())) {
            return $this->sendError(
                'Access denied.',
                ['error' => 'Only admin or Enviroserve staff can update verification.'],
                403
            );
        }

        $validator = Validator::make($request->all(), [
            'confirmed_category_id' => ['nullable', 'exists:waste_categories,id'],
            'verified_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'verified_quantity' => ['nullable', 'integer', 'min:0'],
            'condition_status' => ['nullable', Rule::in([
                'good',
                'damaged',
                'partially_damaged',
                'hazardous',
                'not_e_waste',
                'not_accepted',
            ])],
            'is_accepted' => ['nullable', 'boolean'],
            'is_hazardous' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'corrected'])],
            'price_per_kg' => ['nullable', 'numeric', 'min:0'],
            'price_per_item' => ['nullable', 'numeric', 'min:0'],
            'verified_total_price' => ['nullable', 'numeric', 'min:0'],
            'verification_notes' => ['nullable', 'string'],
            'rejection_reason' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $data = $request->only([
            'confirmed_category_id',
            'verified_weight_kg',
            'verified_quantity',
            'condition_status',
            'is_accepted',
            'is_hazardous',
            'status',
            'price_per_kg',
            'price_per_item',
            'verified_total_price',
            'verification_notes',
            'rejection_reason',
        ]);

        if ($request->filled('verified_weight_kg') && !$request->filled('verified_total_price')) {
            $pricePerKg = $request->filled('price_per_kg')
                ? (float) $request->price_per_kg
                : (float) $wasteVerification->price_per_kg;

            $pricePerItem = $request->filled('price_per_item')
                ? (float) $request->price_per_item
                : (float) $wasteVerification->price_per_item;

            $verifiedQuantity = $request->filled('verified_quantity')
                ? (int) $request->verified_quantity
                : (int) $wasteVerification->verified_quantity;

            $data['verified_total_price'] = $this->calculateVerifiedTotalPrice(
                (float) $request->verified_weight_kg,
                $verifiedQuantity,
                $pricePerKg,
                $pricePerItem,
                []
            );
        }

        $wasteVerification->update($data);

        $wasteVerification->load('wasteListing');

        if ($wasteVerification->wasteListing) {
            $wasteVerification->wasteListing->update([
                'waste_category_id' => $wasteVerification->confirmed_category_id
                    ?? $wasteVerification->wasteListing->waste_category_id,
                'verified_weight_kg' => $wasteVerification->verified_weight_kg,
                'final_price' => $wasteVerification->verified_total_price,
                'verified_by' => $request->user()->id,
                'verified_at' => now(),
                'verification_notes' => $wasteVerification->verification_notes,
                'status' => $wasteVerification->is_accepted ? 'verified' : 'rejected',
            ]);
        }

        return $this->sendResponse(
            $wasteVerification->fresh()->load([
                'wasteListing',
                'verifiedBy',
                'confirmedCategory',
                'aiAnalysis',
            ]),
            'Waste verification updated successfully.'
        );
    }

    public function destroy(Request $request, WasteVerification $wasteVerification): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return $this->sendError(
                'Access denied.',
                ['error' => 'Only admin can delete verification.'],
                403
            );
        }

        $wasteVerification->delete();

        return $this->sendResponse([], 'Waste verification deleted successfully.');
    }

    private function prepareVerifiedItems(array $items): array
    {
        $prepared = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $itemName = trim((string) ($item['item_name'] ?? ''));

            if ($itemName === '') {
                continue;
            }

            $category = !empty($item['category_id'])
                ? WasteCategory::find($item['category_id'])
                : null;

            $quantity = max((int) ($item['verified_quantity'] ?? 0), 0);
            $weight = max((float) ($item['verified_weight_kg'] ?? 0), 0);

            $pricePerKg = (float) ($category?->price_per_kg ?? 0);
            $pricePerItem = (float) ($category?->price_per_item ?? 0);

            $linePrice = 0;

            if ($weight > 0 && $pricePerKg > 0) {
                $linePrice = $weight * $pricePerKg;
            } elseif ($quantity > 0 && $pricePerItem > 0) {
                $linePrice = $quantity * $pricePerItem;
            }

            $prepared[] = [
                'item_name' => $itemName,
                'category_id' => $category?->id,
                'category_name' => $category?->name ?? ($item['category_name'] ?? null),
                'verified_quantity' => $quantity,
                'verified_weight_kg' => round($weight, 2),
                'price_per_kg' => $pricePerKg,
                'price_per_item' => $pricePerItem,
                'line_verified_price' => round($linePrice, 2),
                'condition_status' => $item['condition_status'] ?? null,
                'notes' => $item['notes'] ?? null,
            ];
        }

        return $prepared;
    }

    private function sumVerifiedItemsWeight(array $items): float
    {
        return round(array_reduce($items, function ($total, $item) {
            return $total + (float) ($item['verified_weight_kg'] ?? 0);
        }, 0), 2);
    }

    private function sumVerifiedItemsQuantity(array $items): int
    {
        return (int) array_reduce($items, function ($total, $item) {
            return $total + (int) ($item['verified_quantity'] ?? 0);
        }, 0);
    }

    private function sumVerifiedItemsPrice(array $items): float
    {
        return round(array_reduce($items, function ($total, $item) {
            return $total + (float) ($item['line_verified_price'] ?? 0);
        }, 0), 2);
    }

    private function calculateVerifiedTotalPrice(
        float $verifiedWeightKg,
        int $verifiedQuantity,
        float $pricePerKg,
        float $pricePerItem,
        array $verifiedItems
    ): float {
        $itemsPrice = $this->sumVerifiedItemsPrice($verifiedItems);

        if ($itemsPrice > 0) {
            return $itemsPrice;
        }

        if ($verifiedWeightKg > 0 && $pricePerKg > 0) {
            return round($verifiedWeightKg * $pricePerKg, 2);
        }

        if ($verifiedQuantity > 0 && $pricePerItem > 0) {
            return round($verifiedQuantity * $pricePerItem, 2);
        }

        return 0;
    }

    private function buildVerificationNotes(?string $notes, array $verifiedItems, ?WasteAiAnalysis $aiAnalysis): string
    {
        $parts = [];

        if ($notes) {
            $parts[] = trim($notes);
        }

        if ($aiAnalysis) {
            $parts[] = 'Linked AI analysis ID: ' . $aiAnalysis->id;
        }

        if (count($verifiedItems)) {
            $parts[] = 'Verified item breakdown: ' . json_encode(
                $verifiedItems,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }

        return implode("\n\n", $parts);
    }
}
