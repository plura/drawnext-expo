<?php
// backend/lib/Image.php
namespace Lib;

class Image
{
    // Hard safety: refuse > 40 MP decode
    private const MAX_MP = 40_000_000; // 40 megapixels

    /**
     * Optimize the source image into two WebP derivatives: display + thumb.
     * Returns metadata for both. Throws on fatal errors; caller may catch and fallback.
     *
     * @param string $srcPath   absolute path to the moved, final source (any format)
     * @param string $destStem  absolute path stem for outputs (no extension/suffix)
     * @return array {display:{path,width,height,bytes,mime}, thumb:{...}}
     */
    public static function optimizeWebp(string $srcPath, string $destStem): array
    {
        if (!is_file($srcPath)) {
            throw new \RuntimeException("Source image not found: $srcPath");
        }

        $displayEdge = (int) Config::get('images.display_max_edge');
        $thumbEdge   = (int) Config::get('images.thumb_max_edge');
        $quality     = (int) Config::get('images.webp_quality');

        if (self::hasImagick()) {
            return self::imagickPipeline($srcPath, $destStem, $displayEdge, $thumbEdge, $quality);
        }
        return self::gdPipeline($srcPath, $destStem, $displayEdge, $thumbEdge, $quality);
    }

    /* ===== Imagick path ===== */

    private static function hasImagick(): bool
    {
        return extension_loaded('imagick');
    }

    private static function imagickPipeline(string $src, string $stem, int $edgeDisp, int $edgeThumb, int $q): array
    {
        $img = new \Imagick($src);

        // Safety: mega-pixel limit
        $geom = $img->getImageGeometry();
        $pixels = (int)($geom['width'] ?? 0) * (int)($geom['height'] ?? 0);
        if ($pixels > self::MAX_MP) {
            throw new \RuntimeException("Image too large to process safely");
        }

        // Auto-orient (best effort) & strip metadata
        @$img->autoOrient();
        @$img->stripImage();

        // Display
        $display = clone $img;
        self::fitWithinImagick($display, $edgeDisp);
        self::encodeWebpImagick($display, $q);
        $displayPath = $stem . '__display.webp';
        self::atomicWriteImagick($display, $displayPath);

        // Thumb
        $thumb = clone $img;
        self::fitWithinImagick($thumb, $edgeThumb);
        self::encodeWebpImagick($thumb, $q);
        $thumbPath = $stem . '__thumb.webp';
        self::atomicWriteImagick($thumb, $thumbPath);

        $out = [
            'display' => [
                'path'   => $displayPath,
                'width'  => $display->getImageWidth(),
                'height' => $display->getImageHeight(),
                'bytes'  => filesize($displayPath) ?: 0,
                'mime'   => 'image/webp',
            ],
            'thumb' => [
                'path'   => $thumbPath,
                'width'  => $thumb->getImageWidth(),
                'height' => $thumb->getImageHeight(),
                'bytes'  => filesize($thumbPath) ?: 0,
                'mime'   => 'image/webp',
            ],
        ];

        // Cleanup
        $img->clear(); $img->destroy();
        $display->clear(); $display->destroy();
        $thumb->clear(); $thumb->destroy();

        return $out;
    }

    private static function fitWithinImagick(\Imagick $im, int $maxEdge): void
    {
        $w = $im->getImageWidth();
        $h = $im->getImageHeight();
        if ($w <= 0 || $h <= 0) throw new \RuntimeException("Invalid image dimensions");

        $long = max($w, $h);
        if ($long <= $maxEdge) return; // no upscale

        if ($w >= $h) {
            $im->resizeImage($maxEdge, 0, \Imagick::FILTER_LANCZOS, 1);
        } else {
            $im->resizeImage(0, $maxEdge, \Imagick::FILTER_LANCZOS, 1);
        }
        @$im->stripImage();
        $im->setInterlaceScheme(\Imagick::INTERLACE_NO);
        $im->setImageCompressionQuality(0); // irrelevant for webp, keep clean
    }

    private static function encodeWebpImagick(\Imagick $im, int $quality): void
    {
        $im->setImageFormat('webp');
        $im->setOption('webp:method', '6');        // good speed/quality
        $im->setOption('webp:lossless', 'false');  // lossy by default
        $im->setImageCompressionQuality($quality);
        @$im->stripImage();
    }

    private static function atomicWriteImagick(\Imagick $im, string $finalPath): void
    {
        $tmp = $finalPath . '.tmp';
        if (!$im->writeImage($tmp)) {
            @unlink($tmp);
            throw new \RuntimeException("Failed to write image: $finalPath");
        }
        if (!@rename($tmp, $finalPath)) {
            @unlink($tmp);
            throw new \RuntimeException("Failed to move image into place: $finalPath");
        }
    }

    /* ===== GD fallback ===== */

    private static function gdPipeline(string $src, string $stem, int $edgeDisp, int $edgeThumb, int $q): array
    {
        [$w, $h, $type] = @getimagesize($src);
        if (!$w || !$h) throw new \RuntimeException("Invalid image");

        $pixels = $w * $h;
        if ($pixels > self::MAX_MP) throw new \RuntimeException("Image too large to process safely");

        $srcIm = self::gdLoad($src, $type);
        if (!$srcIm) throw new \RuntimeException("Unsupported image type");

        // NOTE: GD has no built-in EXIF auto-rotate; skipping for fallback path.

        // Display
        $displayIm = self::gdFitWithin($srcIm, $w, $h, $edgeDisp);
        $displayPath = $stem . '__display.webp';
        self::gdWriteWebp($displayIm, $displayPath, $q);

        // Thumb
        $thumbIm = self::gdFitWithin($srcIm, $w, $h, $edgeThumb);
        $thumbPath = $stem . '__thumb.webp';
        self::gdWriteWebp($thumbIm, $thumbPath, $q);

        imagedestroy($srcIm);
        imagedestroy($displayIm);
        imagedestroy($thumbIm);

        return [
            'display' => [
                'path'   => $displayPath,
                'width'  => self::probeWidth($displayPath),
                'height' => self::probeHeight($displayPath),
                'bytes'  => filesize($displayPath) ?: 0,
                'mime'   => 'image/webp',
            ],
            'thumb' => [
                'path'   => $thumbPath,
                'width'  => self::probeWidth($thumbPath),
                'height' => self::probeHeight($thumbPath),
                'bytes'  => filesize($thumbPath) ?: 0,
                'mime'   => 'image/webp',
            ],
        ];
    }

    private static function gdLoad(string $path, int $type)
    {
        return match ($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => imagecreatefrompng($path),
            IMAGETYPE_WEBP => imagecreatefromwebp($path),
            default        => null
        };
    }

    private static function gdFitWithin($srcIm, int $w, int $h, int $maxEdge)
    {
        $long = max($w, $h);
        if ($long <= $maxEdge) {
            // clone
            $im = imagecreatetruecolor($w, $h);
            imagecopy($im, $srcIm, 0, 0, 0, 0, $w, $h);
            return $im;
        }
        if ($w >= $h) {
            $nw = $maxEdge; $nh = (int)round($h * ($maxEdge / $w));
        } else {
            $nh = $maxEdge; $nw = (int)round($w * ($maxEdge / $h));
        }
        $im = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($im, $srcIm, 0, 0, 0, 0, $nw, $nh, $w, $h);
        return $im;
    }

    private static function gdWriteWebp($im, string $path, int $quality): void
    {
        $tmp = $path . '.tmp';
        if (!imagewebp($im, $tmp, $quality)) {
            @unlink($tmp);
            throw new \RuntimeException("Failed to write webp: $path");
        }
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException("Failed to move image into place: $path");
        }
    }

    private static function probeWidth(string $path): int  { [$w] = getimagesize($path); return (int)$w; }
    private static function probeHeight(string $path): int { [,,$h] = array_values(getimagesize($path)); return (int)$h; }
}
