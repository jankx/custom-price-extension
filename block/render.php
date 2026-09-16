<?php
use Jankx\Extensions\CustomPrice\CustomPriceResolver;

if (!class_exists(CustomPriceResolver::class)) {
    require_once dirname(__DIR__) . '/src/CustomPriceResolver.php';
}

echo CustomPriceResolver::render($attributes ?? [], $content ?? '', $block ?? null);
