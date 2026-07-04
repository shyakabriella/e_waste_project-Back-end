<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Services\DeepImageAnalyzerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class DeepWasteListingController extends BaseController
{
    public function deepAiPreview(
        Request $request,
        DeepImageAnalyzerService $deepImageAnalyzerService
    ): JsonResponse {
        $user = $request->user();

        if (!$user) {
            return $this->sendError('Unauthenticated.', ['error' => 'Login is required.'], 401);
        }

        $allowedListingRoles = [
            'customer',
            'company',
            'institution',
            'organization',
            'client',
            'admin',
        ];

        if (!in_array($user->role, $allowedListingRoles, true)) {
            return $this->sendError(
                'Access denied.',
                ['error' => 'Only customer, company, institution, organization, client or admin can analyze waste listing.'],
                403
            );
        }

        $validator = Validator::make($request->all(), [
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'name' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'pickup_address' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        try {
            $result = $deepImageAnalyzerService->analyze(
                $request->file('image'),
                $request->all()
            );

            if ((bool) data_get($result, 'deep_analysis.quota_exhausted', false)) {
                return $this->sendResponse(
                    $result,
                    'Deep AI quota is temporarily exhausted. Staff verification is required.'
                );
            }

            return $this->sendResponse($result, 'Deep AI listing preview generated successfully.');
        } catch (\Throwable $exception) {
            Log::error('Deep AI image analysis failed', [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);

            $message = $exception->getMessage();

            if (
                str_contains($message, 'RESOURCE_EXHAUSTED')
                || str_contains($message, 'quota')
                || str_contains($message, 'Quota')
                || str_contains($message, '429')
            ) {
                return $this->sendResponse([
                    'category' => null,
                    'ai_result' => [
                        'detected_item' => 'Deep AI quota exhausted',
                        'title' => 'Deep AI Preview Temporarily Limited',
                        'description' => 'Gemini quota is exhausted. The image can be submitted, but staff must verify visible items, final kg and final price.',
                        'detected_category_id' => null,
                        'detected_category_name' => 'Mixed E-Waste',
                        'waste_nature' => 'ibitabora',
                        'is_e_waste' => true,
                        'is_hazardous' => false,
                        'quantity' => 0,
                        'estimated_weight_kg' => 0,
                        'estimated_weight_min_kg' => 0,
                        'estimated_weight_max_kg' => 0,
                        'expected_price' => 0,
                        'expected_price_min' => 0,
                        'expected_price_max' => 0,
                        'price_per_kg' => (float) env('E_WASTE_DEFAULT_PRICE_PER_KG', 700),
                        'currency' => 'RWF',
                        'confidence' => 0,
                        'false_estimation_probability' => 100,
                        'condition' => 'used',
                        'load_type' => 'quota_limited',
                        'quotation_mode' => 'staff_verification_only',
                        'visual_count_reliability' => 'unavailable_due_to_ai_quota',
                        'requires_staff_verification' => true,
                        'item_breakdown' => [],
                        'analysis_note' => 'Gemini quota is exhausted. Staff verification is required before final quotation.',
                    ],
                    'deep_analysis' => [
                        'quota_exhausted' => true,
                        'tiles_analyzed' => 0,
                        'technical_error' => $message,
                    ],
                ], 'Deep AI quota exhausted. Staff verification is required.');
            }

            return $this->sendError(
                'Deep AI image analysis failed.',
                [
                    'error' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'fix' => 'Check python3, python3-pil, python3-opencv, GEMINI_API_KEY, internet connection, Gemini model name, and Symfony Process.',
                ],
                422
            );
        }
    }
}
