<?php

namespace App\Domain\Audit\Services;

use App\Domain\Audit\Enums\ItemCondition;
use App\Domain\Audit\Enums\ItemStatus;
use App\Domain\Audit\Models\Audit;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfInstance;

class AuditPdfService
{
    protected ?string $fontPath = null;

    public function __construct()
    {
        $candidateFont = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');
        if (file_exists($candidateFont)) {
            $this->fontPath = $candidateFont;
        }
    }

    /**
     * Generate the DomPDF instance for the given Audit.
     */
    public function generatePdf(Audit $audit): DomPdfInstance
    {
        $audit->loadMissing([
            'property.owner',
            'tenant',
            'inspector',
            'reviewer',
            'completedBy',
            'approvedBy',
            'categories.items.evidence.media',
            'categories.items.reviews',
            'referenceAudit.categories.items.evidence.media',
        ]);

        $totalItems = $audit->items->count();
        $inspectedItems = $audit->items->where('status', '!=', ItemStatus::PENDING)->count();
        $pendingItems = $totalItems - $inspectedItems;
        $progress = $totalItems > 0 ? (int) round(($inspectedItems / $totalItems) * 100) : 0;

        // Group conditions breakdown
        $conditionCounts = [
            'excellent' => 0,
            'good' => 0,
            'fair' => 0,
            'poor' => 0,
            'damaged' => 0,
            'other' => 0,
        ];

        foreach ($audit->items as $item) {
            if (!$item->condition) {
                continue;
            }
            $val = $item->condition instanceof ItemCondition ? $item->condition->value : (string) $item->condition;
            if (isset($conditionCounts[$val])) {
                $conditionCounts[$val]++;
            } else {
                $conditionCounts['other']++;
            }
        }

        // Process photos for each item to base64 for fast and reliable DomPDF embedding
        $allItemsIndex = [];
        $categoriesData = [];
        $itemCounter = 1;

        foreach ($audit->categories as $category) {
            $itemsData = [];
            foreach ($category->items as $item) {
                $photos = [];
                foreach ($item->evidence as $evidence) {
                    $media = $evidence->getFirstMedia('images');
                    if ($media) {
                        $path = $media->getPath();
                        if (file_exists($path)) {
                            $annotationJson = $evidence->annotation_json;
                            $base64 = $this->renderEvidenceImageWithAnnotations($path, $annotationJson, $media->mime_type);
                            if ($base64) {
                                $annotationLayers = $this->extractAnnotationLayers($annotationJson);
                                $isAnnotated = !empty($annotationLayers);

                                $photos[] = [
                                    'id' => $evidence->id,
                                    'file_name' => $media->file_name,
                                    'data' => $base64,
                                    'status' => $evidence->status?->value ?? 'pending',
                                    'is_annotated' => $isAnnotated,
                                    'annotation_layers' => $annotationLayers,
                                ];
                            }
                        }
                    }
                }

                $itemEntry = [
                    'index' => $itemCounter++,
                    'category_name' => $category->name,
                    'item' => $item,
                    'condition_label' => $item->condition?->getLabel() ?? 'Not Set',
                    'condition_color' => $item->condition?->getColor() ?? 'gray',
                    'status_label' => $item->status?->getLabel() ?? 'Pending',
                    'status_color' => $item->status?->getColor() ?? 'gray',
                    'photos' => $photos,
                    'photos_count' => count($photos),
                ];

                $itemsData[] = $itemEntry;
                $allItemsIndex[] = $itemEntry;
            }

            $categoriesData[] = [
                'category' => $category,
                'items' => $itemsData,
            ];
        }

        $pdf = Pdf::loadView('pdf.audit_report', [
            'audit' => $audit,
            'property' => $audit->property,
            'tenant' => $audit->tenant,
            'inspector' => $audit->inspector,
            'reviewer' => $audit->reviewer,
            'totalItems' => $totalItems,
            'inspectedItems' => $inspectedItems,
            'pendingItems' => $pendingItems,
            'progress' => $progress,
            'conditionCounts' => $conditionCounts,
            'allItemsIndex' => $allItemsIndex,
            'categoriesData' => $categoriesData,
            'generatedAt' => now()->timezone(config('app.timezone', 'Asia/Kolkata'))->format('d M Y, h:i A'),
        ]);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('isHtml5ParserEnabled', true);

        return $pdf;
    }

    /**
     * Get the raw PDF binary.
     */
    public function getBinary(Audit $audit): string
    {
        return $this->generatePdf($audit)->output();
    }

    /**
     * Extract structured annotation layer information from annotation JSON.
     */
    protected function extractAnnotationLayers(?array $annotationJson): array
    {
        if (empty($annotationJson)) {
            return [];
        }

        $objects = $annotationJson['canvas']['objects'] ?? ($annotationJson['objects'] ?? []);
        if (!is_array($objects) || empty($objects)) {
            return [];
        }

        $layers = [];
        $index = 1;

        foreach ($objects as $obj) {
            $customType = $obj['customType'] ?? $obj['type'] ?? 'shape';
            if (($obj['id'] ?? null) === 'bg-image' || $customType === 'background') {
                continue;
            }

            $typeName = match (strtolower($customType)) {
                'rect', 'rectangle' => 'Rectangle',
                'circle' => 'Circle',
                'line' => 'Line',
                'arrow' => 'Arrow',
                'freehand', 'path' => 'Freehand',
                'itext', 'text' => 'Text Note',
                'number' => 'Numbered Marker',
                default => ucfirst($customType)
            };

            $remark = trim($obj['remark'] ?? '');
            if (empty($remark) && in_array(strtolower($customType), ['itext', 'text']) && !empty($obj['text'])) {
                $remark = trim($obj['text']);
            }

            $layers[] = [
                'index' => $index++,
                'type' => $typeName,
                'color' => $obj['stroke'] ?? $obj['fill'] ?? '#ff0000',
                'remark' => $remark,
            ];
        }

        return $layers;
    }

    /**
     * Render the evidence photo with all annotation drawings overlaid using GD.
     */
    protected function renderEvidenceImageWithAnnotations(string $path, ?array $annotationJson, ?string $mimeType = null): ?string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $img = null;

        if (in_array($extension, ['jpeg', 'jpg'])) {
            $img = @imagecreatefromjpeg($path);
        } elseif ($extension === 'png') {
            $img = @imagecreatefrompng($path);
        } elseif ($extension === 'webp') {
            $img = @imagecreatefromwebp($path);
        }

        if (!$img) {
            $data = @file_get_contents($path);
            if (!$data) {
                return null;
            }
            $mime = $mimeType ?: ('image/' . ($extension ?: 'jpeg'));
            return 'data:' . $mime . ';base64,' . base64_encode($data);
        }

        // Apply visual annotations directly onto the image
        if (!empty($annotationJson)) {
            $this->drawAnnotationsOnImage($img, $annotationJson);
        }

        // Resize / optimize image for PDF
        $width = imagesx($img);
        $height = imagesy($img);
        $maxWidth = 950;
        $maxHeight = 750;

        if ($width > $maxWidth || $height > $maxHeight) {
            $ratio = min($maxWidth / $width, $maxHeight / $height);
            $newWidth = (int) round($width * $ratio);
            $newHeight = (int) round($height * $ratio);

            $newImg = imagecreatetruecolor($newWidth, $newHeight);
            if ($extension === 'png') {
                imagealphablending($newImg, false);
                imagesavealpha($newImg, true);
            }
            imagecopyresampled($newImg, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            ob_start();
            if ($extension === 'png') {
                imagepng($newImg, null, 6);
            } else {
                imagejpeg($newImg, null, 82);
            }
            $data = ob_get_clean();
            imagedestroy($newImg);
        } else {
            ob_start();
            if ($extension === 'png') {
                imagepng($img, null, 6);
            } else {
                imagejpeg($img, null, 85);
            }
            $data = ob_get_clean();
        }

        imagedestroy($img);

        $mime = ($extension === 'png') ? 'image/png' : 'image/jpeg';
        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }

    /**
     * Draw shapes, lines, arrows, freehand drawings, and numbered markers on the GD image with exact coordinate alignment.
     */
    protected function drawAnnotationsOnImage($img, array $annotationJson): void
    {
        $objects = $annotationJson['canvas']['objects'] ?? ($annotationJson['objects'] ?? []);
        if (!is_array($objects) || empty($objects)) {
            return;
        }

        $imgW = imagesx($img);
        $imgH = imagesy($img);

        // Determine base coordinate space:
        // If baseWidth was recorded by the editor, scale = imgW / baseWidth.
        // If baseWidth is missing, objects were already scaled to native image pixels by JS serialize(),
        // so scaleX = 1.0 and scaleY = 1.0!
        $baseW = $annotationJson['baseWidth'] ?? null;
        $baseH = $annotationJson['baseHeight'] ?? null;

        if ($baseW && $baseW > 0 && $baseH && $baseH > 0) {
            $scaleX = $imgW / (float) $baseW;
            $scaleY = $imgH / (float) $baseH;
        } else {
            $scaleX = 1.0;
            $scaleY = 1.0;
        }

        imagealphablending($img, true);

        $markerCounter = 1;

        foreach ($objects as $obj) {
            $type = strtolower($obj['type'] ?? ($obj['customType'] ?? ''));
            $customType = strtolower($obj['customType'] ?? '');

            if (($obj['id'] ?? null) === 'bg-image' || $customType === 'background') {
                continue;
            }

            $strokeHex = $obj['stroke'] ?? '#ef4444';
            $rawStrokeWidth = (float) ($obj['strokeWidth'] ?? 3);
            $strokeWidth = max(2, (int) round($rawStrokeWidth * $scaleX));
            $strokeColor = $this->allocateColor($img, $strokeHex);

            $fillHex = $obj['fill'] ?? null;
            $hasFill = !empty($fillHex) && $fillHex !== 'transparent';
            $fillColor = $hasFill ? $this->allocateColor($img, $fillHex, 0.4) : null;

            if ($type === 'rect' || $customType === 'rectangle') {
                $rawLeft = (float) ($obj['left'] ?? 0);
                $rawTop = (float) ($obj['top'] ?? 0);
                $rawW = (float) ($obj['width'] ?? 0) * (float) ($obj['scaleX'] ?? 1);
                $rawH = (float) ($obj['height'] ?? 0) * (float) ($obj['scaleY'] ?? 1);

                $left = (int) round($rawLeft * $scaleX);
                $top = (int) round($rawTop * $scaleY);
                $w = (int) round($rawW * $scaleX);
                $h = (int) round($rawH * $scaleY);

                if (($obj['originX'] ?? '') === 'center') {
                    $left -= (int) round($w / 2);
                }
                if (($obj['originY'] ?? '') === 'center') {
                    $top -= (int) round($h / 2);
                }

                $x2 = $left + $w;
                $y2 = $top + $h;

                if ($fillColor) {
                    imagefilledrectangle($img, $left, $top, $x2, $y2, $fillColor);
                }
                $this->drawThickRectangle($img, $left, $top, $x2, $y2, $strokeColor, $strokeWidth);
                $this->drawBadgeNumber($img, $left + 14, $top + 14, (string) $markerCounter, $strokeColor);
                $markerCounter++;
            } elseif ($type === 'circle') {
                $radius = (float) ($obj['radius'] ?? 50);
                $scaleObjX = (float) ($obj['scaleX'] ?? 1) * $scaleX;
                $scaleObjY = (float) ($obj['scaleY'] ?? 1) * $scaleY;
                $radiusX = $radius * $scaleObjX;
                $radiusY = $radius * $scaleObjY;
                $diameterX = (int) round($radiusX * 2);
                $diameterY = (int) round($radiusY * 2);

                $rawLeft = (float) ($obj['left'] ?? 0) * $scaleX;
                $rawTop = (float) ($obj['top'] ?? 0) * $scaleY;

                if (($obj['originX'] ?? '') === 'center') {
                    $cx = (int) round($rawLeft);
                } else {
                    $cx = (int) round($rawLeft + $radiusX);
                }

                if (($obj['originY'] ?? '') === 'center') {
                    $cy = (int) round($rawTop);
                } else {
                    $cy = (int) round($rawTop + $radiusY);
                }

                if ($fillColor) {
                    imagefilledellipse($img, $cx, $cy, $diameterX, $diameterY, $fillColor);
                }
                $this->drawThickEllipse($img, $cx, $cy, $diameterX, $diameterY, $strokeColor, $strokeWidth);
                $this->drawBadgeNumber($img, $cx, $cy - (int) round($radiusY) + 12, (string) $markerCounter, $strokeColor);
                $markerCounter++;
            } elseif ($type === 'line' || $customType === 'line' || ($type === 'path' && $customType === 'arrow')) {
                $rawLeft = (float) ($obj['left'] ?? 0);
                $rawTop = (float) ($obj['top'] ?? 0);
                $scaleObjX = (float) ($obj['scaleX'] ?? 1);
                $scaleObjY = (float) ($obj['scaleY'] ?? 1);

                $x1 = isset($obj['x1']) ? (float) $obj['x1'] : null;
                $y1 = isset($obj['y1']) ? (float) $obj['y1'] : null;
                $x2 = isset($obj['x2']) ? (float) $obj['x2'] : null;
                $y2 = isset($obj['y2']) ? (float) $obj['y2'] : null;

                if ($x1 !== null && $x2 !== null && $y1 !== null && $y2 !== null) {
                    if (($obj['originX'] ?? '') === 'center' || $x1 < 0) {
                        $p1x = $rawLeft + ($x1 * $scaleObjX);
                        $p1y = $rawTop + ($y1 * $scaleObjY);
                        $p2x = $rawLeft + ($x2 * $scaleObjX);
                        $p2y = $rawTop + ($y2 * $scaleObjY);
                    } else {
                        $minX = min($x1, $x2);
                        $minY = min($y1, $y2);
                        $p1x = $rawLeft + (($x1 - $minX) * $scaleObjX);
                        $p1y = $rawTop + (($y1 - $minY) * $scaleObjY);
                        $p2x = $rawLeft + (($x2 - $minX) * $scaleObjX);
                        $p2y = $rawTop + (($y2 - $minY) * $scaleObjY);
                    }
                } else {
                    $p1x = $rawLeft;
                    $p1y = $rawTop;
                    $p2x = $rawLeft + (100 * $scaleObjX);
                    $p2y = $rawTop + (100 * $scaleObjY);
                }

                $finalX1 = (int) round($p1x * $scaleX);
                $finalY1 = (int) round($p1y * $scaleY);
                $finalX2 = (int) round($p2x * $scaleX);
                $finalY2 = (int) round($p2y * $scaleY);

                $this->drawThickLine($img, $finalX1, $finalY1, $finalX2, $finalY2, $strokeColor, $strokeWidth);

                if ($customType === 'arrow') {
                    $this->drawArrowHead($img, $finalX1, $finalY1, $finalX2, $finalY2, $strokeColor, max(14, $strokeWidth * 4));
                }
                $this->drawBadgeNumber($img, $finalX1, $finalY1, (string) $markerCounter, $strokeColor);
                $markerCounter++;
            } elseif ($type === 'path') {
                $this->drawSvgPath($img, $obj, $scaleX, $scaleY, $strokeColor, $strokeWidth);
                $left = (int) round((float) ($obj['left'] ?? 0) * $scaleX);
                $top = (int) round((float) ($obj['top'] ?? 0) * $scaleY);
                $this->drawBadgeNumber($img, $left, $top, (string) $markerCounter, $strokeColor);
                $markerCounter++;
            } elseif ($type === 'itext' || $type === 'text') {
                $left = (int) round((float) ($obj['left'] ?? 0) * $scaleX);
                $top = (int) round((float) ($obj['top'] ?? 0) * $scaleY);
                $text = $obj['text'] ?? (string) $markerCounter;
                $fontSize = max(14, (int) round((float) ($obj['fontSize'] ?? 20) * $scaleX));
                $textColor = $this->allocateColor($img, $obj['fill'] ?? '#ffffff');
                $bgColor = !empty($obj['backgroundColor']) ? $this->allocateColor($img, $obj['backgroundColor']) : $this->allocateColor($img, '#1e293b', 0.15);

                $this->drawTextBadge($img, $left, $top, $text, $textColor, $bgColor, $fontSize);
                $markerCounter++;
            }
        }
    }

    /**
     * Draw thick rectangle outline.
     */
    protected function drawThickRectangle($img, int $x1, int $y1, int $x2, int $y2, int $color, int $thickness): void
    {
        for ($i = 0; $i < $thickness; $i++) {
            imagerectangle($img, $x1 + $i, $y1 + $i, $x2 - $i, $y2 - $i, $color);
        }
    }

    /**
     * Draw thick ellipse outline.
     */
    protected function drawThickEllipse($img, int $cx, int $cy, int $w, int $h, int $color, int $thickness): void
    {
        for ($i = 0; $i < $thickness; $i++) {
            imageellipse($img, $cx, $cy, max(1, $w - ($i * 2)), max(1, $h - ($i * 2)), $color);
        }
    }

    /**
     * Draw thick line between two points.
     */
    protected function drawThickLine($img, int $x1, int $y1, int $x2, int $y2, int $color, int $thickness): void
    {
        if ($thickness <= 1) {
            imageline($img, $x1, $y1, $x2, $y2, $color);
            return;
        }

        $angle = atan2($y2 - $y1, $x2 - $x1);
        $dx = sin($angle) * ($thickness / 2);
        $dy = cos($angle) * ($thickness / 2);

        $points = [
            (int) round($x1 + $dx), (int) round($y1 - $dy),
            (int) round($x2 + $dx), (int) round($y2 - $dy),
            (int) round($x2 - $dx), (int) round($y2 + $dy),
            (int) round($x1 - $dx), (int) round($y1 + $dy),
        ];

        imagefilledpolygon($img, $points, $color);
        imagefilledellipse($img, $x1, $y1, $thickness, $thickness, $color);
        imagefilledellipse($img, $x2, $y2, $thickness, $thickness, $color);
    }

    /**
     * Draw arrowhead polygon at (x2, y2).
     */
    protected function drawArrowHead($img, int $x1, int $y1, int $x2, int $y2, int $color, int $headSize): void
    {
        $angle = atan2($y2 - $y1, $x2 - $x1);
        $arrowAngle = M_PI / 6; // 30 degrees

        $p1x = (int) round($x2 - $headSize * cos($angle - $arrowAngle));
        $p1y = (int) round($y2 - $headSize * sin($angle - $arrowAngle));
        $p2x = (int) round($x2 - $headSize * cos($angle + $arrowAngle));
        $p2y = (int) round($y2 - $headSize * sin($angle + $arrowAngle));

        imagefilledpolygon($img, [$x2, $y2, $p1x, $p1y, $p2x, $p2y], $color);
    }

    /**
     * Draw freehand / SVG path on image using Fabric pathOffset calculations.
     */
    protected function drawSvgPath($img, array $obj, float $scaleX, float $scaleY, int $color, int $thickness): void
    {
        $pathData = $obj['path'] ?? null;
        if (empty($pathData)) {
            return;
        }

        $left = (float) ($obj['left'] ?? 0);
        $top = (float) ($obj['top'] ?? 0);
        $scaleObjX = (float) ($obj['scaleX'] ?? 1);
        $scaleObjY = (float) ($obj['scaleY'] ?? 1);
        $pathOffsetX = (float) ($obj['pathOffset']['x'] ?? 0);
        $pathOffsetY = (float) ($obj['pathOffset']['y'] ?? 0);

        if (is_string($pathData)) {
            preg_match_all('/([MLQCZ])\s*([^MLQCZ]*)/i', $pathData, $matches, PREG_SET_ORDER);
            $commands = [];
            foreach ($matches as $m) {
                $cmd = strtoupper($m[1]);
                $coords = preg_split('/[\s,]+/', trim($m[2]), -1, PREG_SPLIT_NO_EMPTY);
                $commands[] = array_merge([$cmd], array_map('floatval', $coords));
            }
            $pathData = $commands;
        }

        if (!is_array($pathData)) {
            return;
        }

        $curX = ($left + ((0 - $pathOffsetX) * $scaleObjX)) * $scaleX;
        $curY = ($top + ((0 - $pathOffsetY) * $scaleObjY)) * $scaleY;
        $firstX = $curX;
        $firstY = $curY;

        foreach ($pathData as $cmdArr) {
            $cmd = strtoupper($cmdArr[0] ?? '');
            if ($cmd === 'M') {
                $px = (float) ($cmdArr[1] ?? 0);
                $py = (float) ($cmdArr[2] ?? 0);
                $curX = ($left + (($px - $pathOffsetX) * $scaleObjX)) * $scaleX;
                $curY = ($top + (($py - $pathOffsetY) * $scaleObjY)) * $scaleY;
                $firstX = $curX;
                $firstY = $curY;
            } elseif ($cmd === 'L') {
                $px = (float) ($cmdArr[1] ?? 0);
                $py = (float) ($cmdArr[2] ?? 0);
                $nextX = ($left + (($px - $pathOffsetX) * $scaleObjX)) * $scaleX;
                $nextY = ($top + (($py - $pathOffsetY) * $scaleObjY)) * $scaleY;
                $this->drawThickLine($img, (int) round($curX), (int) round($curY), (int) round($nextX), (int) round($nextY), $color, $thickness);
                $curX = $nextX;
                $curY = $nextY;
            } elseif ($cmd === 'Q' || $cmd === 'C') {
                $px = (float) end($cmdArr);
                $py = (float) prev($cmdArr);
                $nextX = ($left + (($px - $pathOffsetX) * $scaleObjX)) * $scaleX;
                $nextY = ($top + (($py - $pathOffsetY) * $scaleObjY)) * $scaleY;
                $this->drawThickLine($img, (int) round($curX), (int) round($curY), (int) round($nextX), (int) round($nextY), $color, $thickness);
                $curX = $nextX;
                $curY = $nextY;
            } elseif ($cmd === 'Z') {
                $this->drawThickLine($img, (int) round($curX), (int) round($curY), (int) round($firstX), (int) round($firstY), $color, $thickness);
            }
        }
    }

    /**
     * Draw high-contrast numbered marker badge on top of annotated shapes.
     */
    protected function drawBadgeNumber($img, int $x, int $y, string $text, int $badgeColor): void
    {
        $radius = 12;
        $white = imagecolorallocate($img, 255, 255, 255);

        // White shadow circle
        imagefilledellipse($img, $x, $y, ($radius * 2) + 4, ($radius * 2) + 4, $white);
        // Colored badge circle
        imagefilledellipse($img, $x, $y, $radius * 2, $radius * 2, $badgeColor);

        // Render number text centered inside circle
        if ($this->fontPath && function_exists('imagettftext')) {
            $bbox = imagettfbbox(9, 0, $this->fontPath, $text);
            $tw = abs($bbox[4] - $bbox[0]);
            $th = abs($bbox[5] - $bbox[1]);
            imagettftext($img, 9, 0, (int) round($x - ($tw / 2)), (int) round($y + ($th / 2)), $white, $this->fontPath, $text);
        } else {
            imagestring($img, 3, $x - 3, $y - 6, $text, $white);
        }
    }

    /**
     * Draw text badge on image.
     */
    protected function drawTextBadge($img, int $x, int $y, string $text, int $textColor, int $bgColor, int $fontSize): void
    {
        if ($this->fontPath && function_exists('imagettftext')) {
            $bbox = imagettfbbox($fontSize, 0, $this->fontPath, $text);
            $tw = abs($bbox[4] - $bbox[0]);
            $th = abs($bbox[5] - $bbox[1]);
            $pad = 6;
            imagefilledrectangle($img, $x - $pad, $y - $th - $pad, $x + $tw + $pad, $y + $pad, $bgColor);
            imagerectangle($img, $x - $pad, $y - $th - $pad, $x + $tw + $pad, $y + $pad, $textColor);
            imagettftext($img, $fontSize, 0, $x, $y, $textColor, $this->fontPath, $text);
        } else {
            $tw = strlen($text) * 8;
            $th = 14;
            imagefilledrectangle($img, $x - 3, $y - 3, $x + $tw + 3, $y + $th + 3, $bgColor);
            imagestring($img, 4, $x, $y, $text, $textColor);
        }
    }

    /**
     * Parse and allocate color with optional alpha transparency for GD image.
     */
    protected function allocateColor($img, ?string $colorStr, float $alpha = 0): int
    {
        if (empty($colorStr) || $colorStr === 'transparent') {
            return imagecolorallocatealpha($img, 255, 0, 0, 127);
        }

        $colorStr = trim($colorStr);

        // Hex format
        if (str_starts_with($colorStr, '#')) {
            $hex = ltrim($colorStr, '#');
            if (strlen($hex) === 3) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            }
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            $gdAlpha = (int) round($alpha * 127);
            return imagecolorallocatealpha($img, $r, $g, $b, $gdAlpha);
        }

        // RGB / RGBA format
        if (preg_match('/rgba?\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)(?:\s*,\s*([\d.]+))?\s*\)/i', $colorStr, $m)) {
            $r = (int) $m[1];
            $g = (int) $m[2];
            $b = (int) $m[3];
            $a = isset($m[4]) ? (1.0 - (float) $m[4]) : $alpha;
            $gdAlpha = (int) min(127, max(0, round($a * 127)));
            return imagecolorallocatealpha($img, $r, $g, $b, $gdAlpha);
        }

        // Named colors
        $named = [
            'red' => [239, 68, 68],
            'blue' => [37, 99, 235],
            'green' => [22, 163, 74],
            'yellow' => [234, 179, 8],
            'orange' => [249, 115, 22],
            'purple' => [147, 51, 234],
            'black' => [15, 23, 42],
            'white' => [255, 255, 255],
        ];

        $lower = strtolower($colorStr);
        if (isset($named[$lower])) {
            $gdAlpha = (int) round($alpha * 127);
            return imagecolorallocatealpha($img, $named[$lower][0], $named[$lower][1], $named[$lower][2], $gdAlpha);
        }

        return imagecolorallocate($img, 239, 68, 68);
    }
}
