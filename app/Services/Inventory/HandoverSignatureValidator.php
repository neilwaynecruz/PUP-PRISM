<?php

namespace App\Services\Inventory;

use Illuminate\Validation\ValidationException;

class HandoverSignatureValidator
{
    private const Prefix = 'data:image/png;base64,';

    private const MaxDecodedBytes = 512000;

    private const PngSignature = "\x89PNG";

    /**
     * @throws ValidationException
     */
    public function validate(string $signature): string
    {
        if (! str_starts_with($signature, self::Prefix)) {
            throw ValidationException::withMessages([
                'signature_png' => __('The signature must be a valid PNG image.'),
            ]);
        }

        $encoded = substr($signature, strlen(self::Prefix));

        if ($encoded === '') {
            throw ValidationException::withMessages([
                'signature_png' => __('The signature must be a valid PNG image.'),
            ]);
        }

        $decoded = base64_decode($encoded, true);

        if ($decoded === false) {
            throw ValidationException::withMessages([
                'signature_png' => __('The signature must be a valid PNG image.'),
            ]);
        }

        if (strlen($decoded) > self::MaxDecodedBytes) {
            throw ValidationException::withMessages([
                'signature_png' => __('The signature is too large.'),
            ]);
        }

        if (! str_starts_with($decoded, self::PngSignature)) {
            throw ValidationException::withMessages([
                'signature_png' => __('The signature must be a valid PNG image.'),
            ]);
        }

        return self::Prefix.base64_encode($decoded);
    }
}
