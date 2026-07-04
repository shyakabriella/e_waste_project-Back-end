<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\User;
use App\Models\WasteListing;
use App\Models\WastePhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class WastePhotoController extends BaseController
{
    private function canAccess(User $user, WasteListing $listing): bool
    {
        return in_array($user->role, ['admin', 'enviroserve_staff']) || $listing->institution_id === $user->id;
    }

    public function index(Request $request): JsonResponse
    {
        $query = WastePhoto::with(['wasteListing:id,institution_id,title,status', 'uploadedBy:id,name,email,role']);

        if ($request->filled('waste_listing_id')) {
            $query->where('waste_listing_id', $request->waste_listing_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $photos = $query->latest()->paginate((int) $request->input('per_page', 15));

        return $this->sendResponse($photos, 'Waste photos retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'waste_listing_id' => ['required', 'exists:waste_listings,id'],
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'photo_type' => ['nullable', Rule::in([
                'main',
                'front',
                'back',
                'side',
                'serial_number',
                'damage',
                'other',
            ])],
            'is_primary' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $listing = WasteListing::findOrFail($request->waste_listing_id);

        if (!$this->canAccess($request->user(), $listing)) {
            return $this->sendError('Access denied.', ['error' => 'You cannot upload photo for this listing.'], 403);
        }

        $file = $request->file('photo');
        $path = $file->store('waste_photos', 'public');

        $isPrimary = $request->boolean('is_primary', false);

        if ($isPrimary) {
            WastePhoto::where('waste_listing_id', $listing->id)->update(['is_primary' => false]);
        }

        $photo = WastePhoto::create([
            'waste_listing_id' => $listing->id,
            'uploaded_by' => $request->user()->id,
            'photo_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'storage_disk' => 'public',
            'photo_type' => $request->input('photo_type', 'main'),
            'is_primary' => $isPrimary,
            'sort_order' => $request->input('sort_order', 0),
            'status' => 'active',
        ]);

        return $this->sendResponse($photo, 'Waste photo uploaded successfully.');
    }

    public function show(Request $request, WastePhoto $wastePhoto): JsonResponse
    {
        $wastePhoto->load('wasteListing');

        if (!$this->canAccess($request->user(), $wastePhoto->wasteListing)) {
            return $this->sendError('Access denied.', ['error' => 'You cannot view this photo.'], 403);
        }

        return $this->sendResponse($wastePhoto, 'Waste photo retrieved successfully.');
    }

    public function update(Request $request, WastePhoto $wastePhoto): JsonResponse
    {
        $wastePhoto->load('wasteListing');

        if (!$this->canAccess($request->user(), $wastePhoto->wasteListing)) {
            return $this->sendError('Access denied.', ['error' => 'You cannot update this photo.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'photo_type' => ['nullable', Rule::in([
                'main',
                'front',
                'back',
                'side',
                'serial_number',
                'damage',
                'other',
            ])],
            'is_primary' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'deleted'])],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        if ($request->boolean('is_primary', false)) {
            WastePhoto::where('waste_listing_id', $wastePhoto->waste_listing_id)
                ->where('id', '!=', $wastePhoto->id)
                ->update(['is_primary' => false]);
        }

        $wastePhoto->update($request->only([
            'photo_type',
            'is_primary',
            'sort_order',
            'status',
        ]));

        return $this->sendResponse($wastePhoto->fresh(), 'Waste photo updated successfully.');
    }

    public function destroy(Request $request, WastePhoto $wastePhoto): JsonResponse
    {
        $wastePhoto->load('wasteListing');

        if (!$this->canAccess($request->user(), $wastePhoto->wasteListing)) {
            return $this->sendError('Access denied.', ['error' => 'You cannot delete this photo.'], 403);
        }

        if ($wastePhoto->photo_path && Storage::disk($wastePhoto->storage_disk)->exists($wastePhoto->photo_path)) {
            Storage::disk($wastePhoto->storage_disk)->delete($wastePhoto->photo_path);
        }

        $wastePhoto->delete();

        return $this->sendResponse([], 'Waste photo deleted successfully.');
    }
}