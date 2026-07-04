<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\QrTag;
use App\Models\User;
use App\Models\WasteListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class QrTagController extends BaseController
{
    private function isStaff(User $user): bool
    {
        return in_array($user->role, ['admin', 'enviroserve_staff'], true);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = QrTag::with([
            'wasteListing:id,title,institution_id,status,estimated_weight_kg,verified_weight_kg,final_price,currency',
            'wasteListing.institution:id,name,email,role,institution_name,phone',
            'pickup:id,pickup_code,status',
            'createdBy:id,name,email,role',
            'printedBy:id,name,email,role',
            'scannedBy:id,name,email,role',
        ]);

        if (!$this->isStaff($user)) {
            $query->whereHas('wasteListing', function ($builder) use ($user) {
                $builder->where('institution_id', $user->id);
            });
        }

        if ($request->filled('waste_listing_id')) {
            $query->where('waste_listing_id', $request->waste_listing_id);
        }

        if ($request->filled('pickup_id')) {
            $query->where('pickup_id', $request->pickup_id);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('qr_code')) {
            $query->where('qr_code', 'like', '%' . trim((string) $request->qr_code) . '%');
        }

        $perPage = (int) $request->input('per_page', 15);
        $perPage = max(5, min($perPage, 100));

        $tags = $query->latest()->paginate($perPage);

        return $this->sendResponse($tags, 'QR tags retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        if (!$this->isStaff($request->user())) {
            return $this->sendError(
                'Access denied.',
                ['error' => 'Only admin or Enviroserve staff can create QR tags.'],
                403
            );
        }

        $validator = Validator::make($request->all(), [
            'waste_listing_id' => ['required', 'exists:waste_listings,id'],
            'pickup_id' => ['nullable', 'exists:pickups,id'],
            'qr_type' => ['nullable', Rule::in([
                'waste_tracking',
                'pickup_tracking',
                'verification_tracking',
                'collection_tracking',
            ])],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $listing = WasteListing::findOrFail($request->waste_listing_id);

        if (!in_array($listing->status, [
            'verified',
            'offer_sent',
            'offer_accepted',
            'pickup_scheduled',
            'collected',
        ], true)) {
            return $this->sendError(
                'QR generation denied.',
                ['error' => 'QR tag can only be generated after waste is verified or offer is accepted.'],
                422
            );
        }

        $existingActiveTag = QrTag::where('waste_listing_id', $listing->id)
            ->whereNotIn('status', ['cancelled', 'used'])
            ->latest()
            ->first();

        if ($existingActiveTag) {
            return $this->sendResponse(
                $existingActiveTag->load([
                    'wasteListing',
                    'wasteListing.institution:id,name,email,role,institution_name,phone',
                    'pickup',
                    'createdBy',
                ]),
                'Active QR tag already exists for this waste listing.'
            );
        }

        $qrCode = $this->generateUniqueQrCode();

        $tag = QrTag::create([
            'waste_listing_id' => $listing->id,
            'pickup_id' => $request->pickup_id,
            'qr_code' => $qrCode,
            'qr_type' => $request->input('qr_type', 'waste_tracking'),
            'status' => 'generated',
            'created_by' => $request->user()->id,
            'notes' => $request->notes,
        ]);

        return $this->sendResponse(
            $tag->load([
                'wasteListing',
                'wasteListing.institution:id,name,email,role,institution_name,phone',
                'pickup',
                'createdBy',
            ]),
            'QR tag generated successfully.'
        );
    }

    public function show(Request $request, QrTag $qrTag): JsonResponse
    {
        if (!$this->canViewTag($request->user(), $qrTag)) {
            return $this->sendError(
                'Access denied.',
                ['error' => 'You cannot view this QR tag.'],
                403
            );
        }

        $qrTag->load([
            'wasteListing',
            'wasteListing.institution:id,name,email,role,institution_name,phone',
            'pickup',
            'createdBy',
            'printedBy',
            'scannedBy',
        ]);

        return $this->sendResponse($qrTag, 'QR tag retrieved successfully.');
    }

    public function update(Request $request, QrTag $qrTag): JsonResponse
    {
        if (!$this->isStaff($request->user())) {
            return $this->sendError(
                'Access denied.',
                ['error' => 'Only admin or Enviroserve staff can update QR tags.'],
                403
            );
        }

        $validator = Validator::make($request->all(), [
            'pickup_id' => ['nullable', 'exists:pickups,id'],
            'qr_image_path' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in([
                'generated',
                'printed',
                'attached',
                'scanned',
                'used',
                'cancelled',
            ])],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $data = $request->only([
            'pickup_id',
            'qr_image_path',
            'status',
            'notes',
        ]);

        if ($request->status === 'printed') {
            $data['printed_by'] = $request->user()->id;
            $data['printed_at'] = now();
        }

        if ($request->status === 'attached' && !$qrTag->printed_at) {
            $data['printed_by'] = $request->user()->id;
            $data['printed_at'] = now();
        }

        $qrTag->update($data);

        return $this->sendResponse(
            $qrTag->fresh()->load([
                'wasteListing',
                'wasteListing.institution:id,name,email,role,institution_name,phone',
                'pickup',
                'createdBy',
                'printedBy',
                'scannedBy',
            ]),
            'QR tag updated successfully.'
        );
    }

    public function scan(Request $request, QrTag $qrTag): JsonResponse
    {
        if (!in_array($request->user()->role, ['admin', 'enviroserve_staff', 'driver'], true)) {
            return $this->sendError(
                'Access denied.',
                ['error' => 'Only staff or driver can scan QR tag.'],
                403
            );
        }

        $validator = Validator::make($request->all(), [
            'scan_purpose' => ['required', Rule::in([
                'verification',
                'pickup',
                'collection_confirmation',
                'other',
            ])],
            'scan_latitude' => ['nullable', 'numeric'],
            'scan_longitude' => ['nullable', 'numeric'],
            'status' => ['nullable', Rule::in(['scanned', 'used'])],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $qrTag->update([
            'status' => $request->input('status', 'scanned'),
            'scanned_by' => $request->user()->id,
            'scanned_at' => now(),
            'scan_purpose' => $request->scan_purpose,
            'scan_latitude' => $request->scan_latitude,
            'scan_longitude' => $request->scan_longitude,
        ]);

        if ($request->input('scan_purpose') === 'pickup') {
            $qrTag->wasteListing?->update([
                'status' => 'pickup_scheduled',
            ]);
        }

        if ($request->input('scan_purpose') === 'collection_confirmation') {
            $qrTag->wasteListing?->update([
                'status' => 'collected',
            ]);
        }

        return $this->sendResponse(
            $qrTag->fresh()->load([
                'wasteListing',
                'pickup',
                'createdBy',
                'printedBy',
                'scannedBy',
            ]),
            'QR tag scanned successfully.'
        );
    }

    public function destroy(Request $request, QrTag $qrTag): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return $this->sendError(
                'Access denied.',
                ['error' => 'Only admin can delete QR tag.'],
                403
            );
        }

        $qrTag->delete();

        return $this->sendResponse([], 'QR tag deleted successfully.');
    }

    private function generateUniqueQrCode(): string
    {
        do {
            $code = 'QR-WASTE-' . now()->format('Ymd-His') . '-' . strtoupper(Str::random(6));
        } while (QrTag::where('qr_code', $code)->exists());

        return $code;
    }

    private function canViewTag(User $user, QrTag $tag): bool
    {
        if ($this->isStaff($user)) {
            return true;
        }

        $tag->loadMissing(['wasteListing']);

        return $tag->wasteListing && $tag->wasteListing->institution_id === $user->id;
    }
}
