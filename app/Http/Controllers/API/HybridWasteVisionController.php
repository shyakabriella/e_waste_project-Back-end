<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Services\DeepImageAnalyzerService;
use App\Services\LocalEwasteVisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class HybridWasteVisionController extends BaseController
{
    public function preview(
        Request $request,
        LocalEwasteVisionService $localEwasteVisionService,
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
            $localResult = $localEwasteVisionService->analyze(
                $request->file('image'),
                $request->all()
            );

            $localAi = $localResult['ai_result'] ?? [];
            $localVision = $localResult['local_vision'] ?? [];

            $shouldTryGemini = $this->shouldTryGemini($localAi, $localVision);

            if (!$shouldTryGemini) {
                $localResult['hybrid_ai'] = [
                    'used_engine' => 'local_vision',
                    'gemini_attempted' => false,
                    'reason' => 'Local vision result was acceptable.',
                ];

                return $this->sendResponse(
                    $localResult,
                    'Hybrid AI preview generated using local vision.'
                );
            }

            try {
                $geminiResult = $deepImageAnalyzerService->analyze(
                    $request->file('image'),
                    $request->all()
                );

                $geminiAi = $geminiResult['ai_result'] ?? [];
                $deepAnalysis = $geminiResult['deep_analysis'] ?? [];

                $geminiQuotaExhausted = (bool) ($deepAnalysis['quota_exhausted'] ?? false);
                $geminiDetectedItem = strtolower((string) ($geminiAi['detected_item'] ?? ''));

                if (
                    !$geminiQuotaExhausted
                    && !str_contains($geminiDetectedItem, 'quota exhausted')
                    && count($geminiAi['item_breakdown'] ?? []) > 0
                ) {
                    $geminiResult['local_backup'] = $localResult;
                    $geminiResult['hybrid_ai'] = [
                        'used_engine' => 'gemini_with_local_backup',
                        'gemini_attempted' => true,
                        'gemini_success' => true,
                        'reason' => 'Local vision was uncertain, so Gemini enhanced the preview.',
                    ];

                    return $this->sendResponse(
                        $geminiResult,
                        'Hybrid AI preview generated using Gemini with local backup.'
                    );
                }

                $localResult['gemini_backup'] = [
                    'attempted' => true,
                    'success' => false,
                    'quota_exhausted' => $geminiQuotaExhausted,
                    'message' => $geminiAi['analysis_note'] ?? 'Gemini could not improve the result.',
                ];

                $localResult['hybrid_ai'] = [
                    'used_engine' => 'local_vision',
                    'gemini_attempted' => true,
                    'gemini_success' => false,
                    'reason' => 'Gemini was unavailable or quota exhausted. Local vision result returned.',
                ];

                return $this->sendResponse(
                    $localResult,
                    'Hybrid AI preview generated using local vision. Gemini was unavailable.'
                );
            } catch (\Throwable $geminiException) {
                Log::warning('Hybrid AI Gemini fallback failed', [
                    'message' => $geminiException->getMessage(),
                    'file' => $geminiException->getFile(),
                    'line' => $geminiException->getLine(),
                ]);

                $localResult['gemini_backup'] = [
                    'attempted' => true,
                    'success' => false,
                    'error' => app()->environment('local') ? $geminiException->getMessage() : null,
                ];

                $localResult['hybrid_ai'] = [
                    'used_engine' => 'local_vision',
                    'gemini_attempted' => true,
                    'gemini_success' => false,
                    'reason' => 'Gemini failed, so local vision result was returned.',
                ];

                return $this->sendResponse(
                    $localResult,
                    'Hybrid AI preview generated using local vision. Gemini fallback failed.'
                );
            }
        } catch (\Throwable $exception) {
            Log::error('Hybrid AI preview failed', [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            return $this->sendError(
                'Hybrid AI preview failed.',
                [
                    'error' => $exception->getMessage(),
                    'fix' => 'Check Laravel backend, local Python detector, ewaste_items table, and Gemini configuration.',
                ],
                422
            );
        }
    }

    private function shouldTryGemini(array $localAi, array $localVision): bool
    {
        $enabled = filter_var(env('HYBRID_AI_ENABLE_GEMINI', true), FILTER_VALIDATE_BOOLEAN);

        if (!$enabled) {
            return false;
        }

        $engine = strtolower((string) ($localVision['engine'] ?? ''));
        $modelLoaded = (bool) ($localVision['model_loaded'] ?? false);
        $detectedItem = strtolower((string) ($localAi['detected_item'] ?? ''));
        $quotationMode = strtolower((string) ($localAi['quotation_mode'] ?? ''));
        $confidence = (float) ($localAi['confidence'] ?? 0);

        if (!$modelLoaded) {
            return true;
        }

        if (str_contains($engine, 'fallback')) {
            return true;
        }

        if (str_contains($detectedItem, 'unknown')) {
            return true;
        }

        if ($quotationMode === 'range_only' && $confidence < 75) {
            return true;
        }

        if ($confidence < (float) env('HYBRID_AI_GEMINI_MIN_CONFIDENCE', 70)) {
            return true;
        }

        return false;
    }
}
