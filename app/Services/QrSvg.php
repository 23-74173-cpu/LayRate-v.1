<?php

namespace App\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

/**
 * Server-side QR codes as plain SVG, for the printable PDF labels.
 *
 * The screen QR page draws its code in the browser (public/js/qrcode.min.js),
 * but a PDF is built on the server, so the code is encoded here with
 * bacon/bacon-qr-code at the same error correction level (M) and drawn as
 * one SVG path. dompdf renders SVG as vectors without the GD extension, so
 * labels print sharp and also work on a Pi without GD.
 */
class QrSvg
{
    /**
     * @param  int  $quietZone  Blank modules around the code (scanners need some; 2 is enough on a label).
     */
    public static function svg(string $data, int $quietZone = 2): string
    {
        $matrix = Encoder::encode($data, ErrorCorrectionLevel::M())->getMatrix();
        $width = $matrix->getWidth();
        // Drawn in final units (10 per module) with no viewBox scaling: dompdf's
        // SVG library ignores viewBox scaling, which drew the code ~10x too small.
        $m = 10;
        $size = ($width + 2 * $quietZone) * $m;

        // One rectangle per horizontal run of dark modules keeps the path short.
        $path = '';
        for ($y = 0; $y < $width; $y++) {
            $x = 0;
            while ($x < $width) {
                if ($matrix->get($x, $y) !== 1) {
                    $x++;
                    continue;
                }
                $start = $x;
                while ($x < $width && $matrix->get($x, $y) === 1) {
                    $x++;
                }
                $run = ($x - $start) * $m;
                $path .= 'M' . (($start + $quietZone) * $m) . ' ' . (($y + $quietZone) * $m) . 'h' . $run . 'v' . $m . 'h-' . $run . 'z';
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '"'
            . ' viewBox="0 0 ' . $size . ' ' . $size . '" shape-rendering="crispEdges">'
            . '<rect width="' . $size . '" height="' . $size . '" fill="#ffffff"/>'
            . '<path fill="#000000" d="' . $path . '"/></svg>';
    }

    /** As a data: URI, for <img src> in dompdf views. */
    public static function dataUri(string $data, int $quietZone = 2): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode(self::svg($data, $quietZone));
    }
}
