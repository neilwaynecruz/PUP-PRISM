<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Product;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Inertia\Inertia;
use Inertia\Response;

class ProductLabelController extends Controller
{
    public function show(Product $product): Response
    {
        $qrSvg = $this->makeQrSvg($product->sku);

        return Inertia::render('inventory/labels/ProductLabel', [
            'product' => $product->only(['id', 'sku', 'name', 'type']),
            'qr_svg' => $qrSvg,
        ]);
    }

    private function makeQrSvg(string $value): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(256),
            new SvgImageBackEnd
        );

        $writer = new Writer($renderer);

        return $writer->writeString($value);
    }
}
