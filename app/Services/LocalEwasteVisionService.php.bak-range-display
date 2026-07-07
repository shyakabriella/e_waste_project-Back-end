<?php

namespace App\Services;

use App\Models\EwasteItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class LocalEwasteVisionService
{
    public function analyze(UploadedFile $image, array $context = []): array
    {
        $analysisId = (string) Str::uuid();

        $sourcePath = $this->storeSourceImage($image, $analysisId);
        $detectorResult = $this->runDetector($sourcePath);

        if (!($detectorResult['success'] ?? false)) {
            throw new RuntimeException($detectorResult['error'] ?? 'Local e-waste detector failed.');
        }

        $detections = $detectorResult['detections'] ?? [];

        $itemBreakdown = $this->buildItemBreakdown($detections);
        $aiResult = $this->buildPreviewResult($itemBreakdown, $detectorResult, $context);

        return [
            'category' => null,
            'ai_result' => $aiResult,
            'local_vision' => [
                'analysis_id' => $analysisId,
                'engine' => $detectorResult['engine'] ?? 'unknown',
                'model_loaded' => (bool) ($detectorResult['model_loaded'] ?? false),
                'density' => $detectorResult['density'] ?? null,
                'raw_detections' => $detections,
                'raw_boxes' => $detectorResult['raw_boxes'] ?? [],
                'warning' => $detectorResult['warning'] ?? null,
            ],
        ];
    }

    private function storeSourceImage(UploadedFile $image, string $analysisId): string
    {
        $extension = $image->getClientOriginalExtension() ?: 'jpg';
        $fileName = "{$analysisId}.{$extension}";

        $storedPath = $image->storeAs('local-vision/source', $fileName, 'local');

        if (!$storedPath) {
            throw new RuntimeException('Failed to store image for local vision analysis.');
        }

        $fullPath = Storage::disk('local')->path($storedPath);

        if (!is_file($fullPath)) {
            throw new RuntimeException('Stored image was not found at: ' . $fullPath);
        }

        return $fullPath;
    }

    private function runDetector(string $imagePath): array
    {
        $python = env('LOCAL_YOLO_PYTHON_BIN', 'python3');
        $script = base_path('python/local_ewaste_detector.py');

        $modelPath = env('LOCAL_YOLO_MODEL_PATH', storage_path('app/models/ewaste-yolo.pt'));

        if (!str_starts_with($modelPath, '/')) {
            $modelPath = base_path($modelPath);
        }

        if (!is_file($script)) {
            throw new RuntimeException('Local e-waste detector script was not found.');
        }

        $process = new Process([
            $python,
            $script,
            '--image',
            $imagePath,
            '--model',
            $modelPath,
            '--confidence',
            (string) env('LOCAL_YOLO_CONFIDENCE', 0.35),
        ]);

        $process->setTimeout((int) env('LOCAL_YOLO_TIMEOUT', 90));
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException(
                'Local e-waste detector failed: ' . trim($process->getErrorOutput() ?: $process->getOutput())
            );
        }

        $json = trim($process->getOutput());
        $data = json_decode($json, true);

        if (!is_array($data)) {
            throw new RuntimeException('Local e-waste detector returned invalid JSON: ' . $json);
        }

        return $data;
    }

    private function buildItemBreakdown(array $detections): array
    {
        $breakdown = [];

        foreach ($detections as $detection) {
            $className = $this->normalizeClassName((string) ($detection['class_name'] ?? ''));

            if ($className === '') {
                continue;
            }

            $quantity = max(1, (int) ($detection['quantity'] ?? 1));
            $confidence = min(max((float) ($detection['confidence'] ?? 50), 0), 100);

            $item = EwasteItem::query()
                ->where('status', 'active')
                ->where('ai_class_name', $className)
                ->first();

            if (!$item) {
                $item = EwasteItem::query()
                    ->where('status', 'active')
                    ->where('ai_class_name', 'unknown_electronic_item')
                    ->first();
            }

            if (!$item) {
                $breakdown[] = $this->unknownBreakdown($className, $quantity, $confidence);
                continue;
            }

            $minWeight = $item->min_weight_kg ?: $item->avg_weight_kg;
            $maxWeight = $item->max_weight_kg ?: $item->avg_weight_kg;
            $avgWeight = $item->avg_weight_kg ?: (($minWeight + $maxWeight) / 2);

            $totalMinWeight = round($minWeight * $quantity, 2);
            $totalMaxWeight = round($maxWeight * $quantity, 2);
            $totalAvgWeight = round($avgWeight * $quantity, 2);

            $pricePerKg = $item->price_per_kg ?: (float) env('E_WASTE_DEFAULT_PRICE_PER_KG', 700);
            $pricePerItem = $item->price_per_item;

            if ($pricePerItem) {
                $priceMin = round($pricePerItem * $quantity);
                $priceMax = round($pricePerItem * $quantity);
                $priceAvg = round($pricePerItem * $quantity);
            } else {
                $priceMin = round($totalMinWeight * $pricePerKg);
                $priceMax = round($totalMaxWeight * $pricePerKg);
                $priceAvg = round($totalAvgWeight * $pricePerKg);
            }

            $breakdown[] = [
                'item_id' => $item->id,
                'item_name' => $item->name,
                'ai_class_name' => $className,
                'category_name' => $item->category_name,
                'quantity' => $quantity,
                'confidence' => round($confidence, 2),
                'is_batch' => (bool) $item->is_batch,
                'is_hazardous' => (bool) $item->is_hazardous,
                'requires_staff_verification' => (bool) $item->requires_staff_verification,
                'unit_weight_kg' => round($avgWeight, 2),
                'min_weight_kg' => $totalMinWeight,
                'max_weight_kg' => $totalMaxWeight,
                'total_weight_kg' => $totalAvgWeight,
                'price_per_kg' => $pricePerKg,
                'price_per_item' => $pricePerItem,
                'expected_price_min' => $priceMin,
                'expected_price_max' => $priceMax,
                'expected_price' => $priceAvg,
                'source' => $detection['source'] ?? 'local_vision',
                'reason' => $detection['reason'] ?? 'Detected by local vision engine.',
            ];
        }

        return $breakdown;
    }

    private function buildPreviewResult(array $itemBreakdown, array $detectorResult, array $context): array
    {
        $totalQty = collect($itemBreakdown)->sum(fn ($item) => (int) ($item['quantity'] ?? 0));

        $minWeight = collect($itemBreakdown)->sum(fn ($item) => (float) ($item['min_weight_kg'] ?? 0));
        $maxWeight = collect($itemBreakdown)->sum(fn ($item) => (float) ($item['max_weight_kg'] ?? 0));
        $avgWeight = collect($itemBreakdown)->sum(fn ($item) => (float) ($item['total_weight_kg'] ?? 0));

        $priceMin = collect($itemBreakdown)->sum(fn ($item) => (float) ($item['expected_price_min'] ?? 0));
        $priceMax = collect($itemBreakdown)->sum(fn ($item) => (float) ($item['expected_price_max'] ?? 0));
        $priceAvg = collect($itemBreakdown)->sum(fn ($item) => (float) ($item['expected_price'] ?? 0));

        $requiresStaffVerification = collect($itemBreakdown)->contains(
            fn ($item) => (bool) ($item['requires_staff_verification'] ?? true)
        );

        $hasBatch = collect($itemBreakdown)->contains(
            fn ($item) => (bool) ($item['is_batch'] ?? false)
        );

        $isHazardous = collect($itemBreakdown)->contains(
            fn ($item) => (bool) ($item['is_hazardous'] ?? false)
        );

        $confidence = collect($itemBreakdown)->avg(fn ($item) => (float) ($item['confidence'] ?? 50)) ?: 0;

        $detectedNames = collect($itemBreakdown)
            ->pluck('item_name')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $detectedItem = count($detectedNames)
            ? implode(', ', array_slice($detectedNames, 0, 12))
            : 'Unknown E-Waste Item';

        $density = $detectorResult['density'] ?? null;
        $densityScore = is_array($density) ? (float) ($density['density_score'] ?? 0) : 0;
        $isDense = is_array($density) ? (bool) ($density['is_dense'] ?? false) : $hasBatch;

        $quotationMode = ($requiresStaffVerification || $hasBatch || abs($maxWeight - $minWeight) > 0.01)
            ? 'range_only'
            : 'estimate';

        return [
            'detected_item' => $detectedItem,
            'title' => $context['title'] ?? $context['name'] ?? 'Local Vision E-Waste Preview',
            'description' => $this->description($itemBreakdown, $isDense),
            'detected_category_id' => null,
            'detected_category_name' => collect($itemBreakdown)->pluck('category_name')->filter()->first() ?: 'Mixed E-Waste',
            'waste_nature' => 'ibitabora',
            'is_e_waste' => count($itemBreakdown) > 0,
            'is_hazardous' => $isHazardous,
            'quantity' => $totalQty,
            'estimated_weight_kg' => round($avgWeight, 2),
            'estimated_weight_min_kg' => round($minWeight, 2),
            'estimated_weight_max_kg' => round($maxWeight, 2),
            'expected_price' => round($priceAvg),
            'expected_price_min' => round($priceMin),
            'expected_price_max' => round($priceMax),
            'price_per_kg' => (float) env('E_WASTE_DEFAULT_PRICE_PER_KG', 700),
            'currency' => 'RWF',
            'confidence' => round($confidence, 2),
            'false_estimation_probability' => round(max(5, 100 - $confidence), 2),
            'condition' => 'used',
            'load_type' => $isDense || $hasBatch ? 'mixed_visible_batch_pile' : 'visible_items',
            'quotation_mode' => $quotationMode,
            'visual_count_reliability' => $isDense || $hasBatch ? 'medium_to_low' : 'medium',
            'requires_staff_verification' => true,
            'density_score' => round($densityScore, 2),
            'item_breakdown' => $itemBreakdown,
            'analysis_note' => $isDense || $hasBatch
                ? 'Local vision detected a visible batch or dense pile. The result is a provisional range only. Final kg and price must be confirmed by staff weighing.'
                : 'Local vision detected visible items. The result is provisional until staff verification.',
        ];
    }

    private function description(array $itemBreakdown, bool $isDense): string
    {
        if (!count($itemBreakdown)) {
            return 'No clear e-waste item was detected. Staff verification is required.';
        }

        $items = collect($itemBreakdown)
            ->map(fn ($item) => "{$item['quantity']} × {$item['item_name']}")
            ->take(15)
            ->implode(', ');

        if ($isDense) {
            return "Visible mixed e-waste batch detected: {$items}. Some items may be hidden or overlapping.";
        }

        return "Visible e-waste detected: {$items}.";
    }

    private function unknownBreakdown(string $className, int $quantity, float $confidence): array
    {
        $pricePerKg = (float) env('E_WASTE_DEFAULT_PRICE_PER_KG', 700);

        return [
            'item_id' => null,
            'item_name' => 'Unknown Electronic Item',
            'ai_class_name' => $className,
            'category_name' => 'Unknown E-Waste',
            'quantity' => $quantity,
            'confidence' => round($confidence, 2),
            'is_batch' => false,
            'is_hazardous' => false,
            'requires_staff_verification' => true,
            'unit_weight_kg' => 1,
            'min_weight_kg' => 0.2 * $quantity,
            'max_weight_kg' => 5 * $quantity,
            'total_weight_kg' => 1 * $quantity,
            'price_per_kg' => $pricePerKg,
            'price_per_item' => null,
            'expected_price_min' => round(0.2 * $quantity * $pricePerKg),
            'expected_price_max' => round(5 * $quantity * $pricePerKg),
            'expected_price' => round(1 * $quantity * $pricePerKg),
            'source' => 'local_vision',
            'reason' => 'Detected class is not configured in ewaste_items table.',
        ];
    }

    private function normalizeClassName(string $className): string
    {
        $className = strtolower(trim($className));
        $className = str_replace([' ', '-'], '_', $className);
        $className = preg_replace('/[^a-z0-9_]/', '', $className) ?: '';

        return $className;
    }
}
