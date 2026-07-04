<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\User;
use App\Models\WasteAiAnalysis;
use App\Models\WasteCategory;
use App\Models\WasteListing;
use App\Models\WastePhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class WasteAiAnalysisController extends BaseController
{
    private function isStaff(User $user): bool
    {
        return in_array($user->role, ['admin', 'enviroserve_staff']);
    }

    public function index(Request $request): JsonResponse
    {
        $query = WasteAiAnalysis::with([
            'wasteListing:id,title,institution_id,status',
            'wastePhoto:id,waste_listing_id,photo_path',
            'detectedCategory',
            'verifiedBy:id,name,email,role',
        ]);

        if ($request->filled('waste_listing_id')) {
            $query->where('waste_listing_id', $request->waste_listing_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $analyses = $query->latest()->paginate((int) $request->input('per_page', 15));

        return $this->sendResponse($analyses, 'AI analyses retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->isStaff($user)) {
            return $this->sendError('Access denied.', ['error' => 'Only admin or Enviroserve staff can save AI analysis.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'waste_listing_id' => ['required', 'exists:waste_listings,id'],
            'waste_photo_id' => ['nullable', 'exists:waste_photos,id'],
            'ai_provider' => ['nullable', 'string', 'max:100'],
            'ai_model' => ['nullable', 'string', 'max:100'],
            'detected_item' => ['nullable', 'string', 'max:255'],
            'detected_category_id' => ['nullable', 'exists:waste_categories,id'],
            'detected_category_name' => ['nullable', 'string', 'max:255'],
            'waste_nature' => ['nullable', Rule::in(['ibibora', 'ibitabora'])],
            'is_e_waste' => ['nullable', 'boolean'],
            'is_hazardous' => ['nullable', 'boolean'],
            'quantity_detected' => ['nullable', 'integer', 'min:1'],
            'estimated_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'confidence' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'analysis_note' => ['nullable', 'string'],
            'analysis_result' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $category = $request->filled('detected_category_id')
            ? WasteCategory::find($request->detected_category_id)
            : null;

        $quantity = $request->input('quantity_detected', 1);
        $estimatedWeight = $request->estimated_weight_kg;

        if (!$estimatedWeight && $category && $category->average_weight_kg) {
            $estimatedWeight = $quantity * (float) $category->average_weight_kg;
        }

        $analysis = WasteAiAnalysis::create([
            'waste_listing_id' => $request->waste_listing_id,
            'waste_photo_id' => $request->waste_photo_id,
            'ai_provider' => $request->input('ai_provider', 'manual_ai_result'),
            'ai_model' => $request->input('ai_model', 'manual'),
            'detected_item' => $request->detected_item,
            'detected_category_id' => $request->detected_category_id,
            'detected_category_name' => $request->detected_category_name ?? $category?->name,
            'waste_nature' => $request->waste_nature ?? $category?->waste_nature,
            'is_e_waste' => $request->boolean('is_e_waste', $category?->is_e_waste ?? false),
            'is_hazardous' => $request->boolean('is_hazardous', $category?->is_hazardous ?? false),
            'quantity_detected' => $quantity,
            'estimated_weight_kg' => $estimatedWeight,
            'min_estimated_weight_kg' => $category?->min_weight_kg,
            'max_estimated_weight_kg' => $category?->max_weight_kg,
            'confidence' => $request->confidence,
            'analysis_note' => $request->analysis_note,
            'analysis_result' => $request->analysis_result,
            'status' => 'completed',
            'analyzed_at' => now(),
        ]);

        WasteListing::where('id', $request->waste_listing_id)->update([
            'ai_detected_item' => $analysis->detected_item,
            'ai_detected_category' => $analysis->detected_category_name,
            'ai_waste_nature' => $analysis->waste_nature,
            'ai_is_e_waste' => $analysis->is_e_waste,
            'ai_estimated_weight_kg' => $analysis->estimated_weight_kg,
            'ai_confidence' => $analysis->confidence,
            'ai_analysis_note' => $analysis->analysis_note,
            'status' => 'ai_analyzed',
        ]);

        if ($request->filled('waste_photo_id')) {
            WastePhoto::where('id', $request->waste_photo_id)->update([
                'is_ai_processed' => true,
                'ai_processed_at' => now(),
            ]);
        }

        return $this->sendResponse(
            $analysis->load(['wasteListing', 'wastePhoto', 'detectedCategory']),
            'AI analysis saved successfully.'
        );
    }

    public function show(Request $request, WasteAiAnalysis $wasteAiAnalysis): JsonResponse
    {
        $wasteAiAnalysis->load([
            'wasteListing',
            'wastePhoto',
            'detectedCategory',
            'verifiedBy',
            'staffCorrectedCategory',
        ]);

        return $this->sendResponse($wasteAiAnalysis, 'AI analysis retrieved successfully.');
    }

    public function update(Request $request, WasteAiAnalysis $wasteAiAnalysis): JsonResponse
    {
        if (!$this->isStaff($request->user())) {
            return $this->sendError('Access denied.', ['error' => 'Only admin or staff can update AI analysis.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['nullable', Rule::in(['pending', 'processing', 'completed', 'failed', 'verified', 'rejected'])],
            'verified_by_staff' => ['nullable', 'boolean'],
            'staff_corrected_category_id' => ['nullable', 'exists:waste_categories,id'],
            'staff_corrected_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'staff_feedback' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $data = $request->only([
            'status',
            'verified_by_staff',
            'staff_corrected_category_id',
            'staff_corrected_weight_kg',
            'staff_feedback',
        ]);

        if ($request->boolean('verified_by_staff', false)) {
            $data['verified_by'] = $request->user()->id;
            $data['verified_at'] = now();
            $data['status'] = 'verified';
        }

        $wasteAiAnalysis->update($data);

        return $this->sendResponse($wasteAiAnalysis->fresh(), 'AI analysis updated successfully.');
    }

    public function destroy(Request $request, WasteAiAnalysis $wasteAiAnalysis): JsonResponse
    {
        if (!$this->isStaff($request->user())) {
            return $this->sendError('Access denied.', ['error' => 'Only admin or staff can delete AI analysis.'], 403);
        }

        $wasteAiAnalysis->delete();

        return $this->sendResponse([], 'AI analysis deleted successfully.');
    }
}