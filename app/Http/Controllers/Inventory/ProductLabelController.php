<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
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
        $this->authorize('view', $product);

        $qrSvg = $this->makeQrSvg($product->sku);

        return Inertia::render('inventory/labels/ProductLabel', [
            'product' => (new ProductResource($product))->resolve(),
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
