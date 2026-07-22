<?php

declare(strict_types=1);

use App\Helpers\Env;

return [
    'max_request_images' => (int) Env::get('MAX_REQUEST_IMAGES', 6),
    'max_image_mb'       => (int) Env::get('MAX_IMAGE_UPLOAD_MB', 8),
    'max_document_mb'    => (int) Env::get('MAX_DOCUMENT_UPLOAD_MB', 10),
    'max_promotion_image_mb' => (int) Env::get('MAX_PROMOTION_IMAGE_MB', 2),
    'image_max_width'    => (int) Env::get('IMAGE_MAX_WIDTH', 1800),
    'image_max_dimension' => (int) Env::get('IMAGE_MAX_DIMENSION', 12000),
    'image_max_pixels'   => (int) Env::get('IMAGE_MAX_PIXELS', 24000000),
    'thumbnail_width'    => (int) Env::get('THUMBNAIL_WIDTH', 480),

    // Allowed image MIME types validated via finfo (server-side inspection).
    'allowed_image_mimes' => [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ],

    // Allowed verification-document MIME types (provider licences, insurance).
    'allowed_document_mimes' => [
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
    ],

    'paths' => [
        'request_images'     => 'storage/private/request-images',
        'provider_documents' => 'storage/private/provider-documents',
        'park_documents'     => 'storage/private/park-documents',
        'park_logos'         => 'public/uploads-public/park-logos',
        'provider_promotions' => 'public/uploads-public/provider-promotions',
        'social_media_assets' => 'storage/private/social-media-assets',
        'exports'            => 'storage/private/exports',
        'public_uploads'     => 'public/uploads-public',
    ],
];
