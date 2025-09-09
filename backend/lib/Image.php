<?php
// backend/lib/Image.php

namespace Lib;

/**
 * Image utilities:
 * - Creates two WebP derivatives (display + thumb) from a source image.
 * - Prefers Imagick when it can truly encode WebP; falls back to GD.
 * - Adds basic safety guards (megapixels, memory) for shared hosting.
 *
 * Notes:
 * - On shared hosts with low memory, very large images can OOM under Imagick.
 *   We estimate memory and route to GD when safer.
 * - GD path includes optional EXIF auto-rotate for JPEG (if exif is enabled).
 */
class Image
{
	/**
	 * Hard safety: refuse > 40 MP decode.
	 * With memory_limit=128M this is already aggressive; lower if needed.
	 * (RGBA ~ 4 bytes/px; working buffers can double that.)
	 */
	private const MAX_MP = 40_000_000;

	/**
	 * Optimize the source image into two WebP derivatives: display + thumb.
	 * Returns metadata for both. Throws on fatal errors; caller may catch and fallback.
	 *
	 * @param string $srcPath  Absolute path to the moved, final source (any supported format)
	 * @param string $destStem Absolute path *stem* for outputs (no extension/suffix)
	 * @return array{
	 *   display: array{path:string,width:int,height:int,bytes:int,mime:string},
	 *   thumb:   array{path:string,width:int,height:int,bytes:int,mime:string}
	 * }
	 */
	public static function optimizeWebp(string $srcPath, string $destStem): array
	{
		if (!is_file($srcPath)) {
			throw new \RuntimeException("Source image not found: $srcPath");
		}

		$displayEdge = (int) Config::get('images.display_max_edge');
		$thumbEdge   = (int) Config::get('images.thumb_max_edge');
		$quality     = max(0, min(100, (int) Config::get('images.webp_quality')));

		// Peek dimensions once; used for safety + routing.
		[$w, $h] = @getimagesize($srcPath) ?: [0, 0];
		if ($w <= 0 || $h <= 0) {
			throw new \RuntimeException("Invalid image");
		}

		$pixels = $w * $h;
		if ($pixels > self::MAX_MP) {
			throw new \RuntimeException("Image too large to process safely");
		}

		// Prefer Imagick only when WEBP is actually supported and projected memory is safe.
		if (self::shouldUseImagick($w, $h)) {
			return self::imagickPipeline($srcPath, $destStem, $displayEdge, $thumbEdge, $quality);
		}

		// Robust fallback (or deliberate choice) to GD.
		return self::gdPipeline($srcPath, $destStem, $displayEdge, $thumbEdge, $quality, $w, $h);
	}

	/* ======================================================================
	 *					Capability checks / routing helpers
	 * ====================================================================== */

	/** True if the imagick extension is present *and* can encode WEBP. */
	private static function hasImagickWebp(): bool
	{
		if (!extension_loaded('imagick') || !class_exists(\Imagick::class)) return false;
		try { return !empty(\Imagick::queryFormats('WEBP')); }
		catch (\Throwable) { return false; }
	}

	/** True if GD can encode WEBP. */
	private static function hasGdWebp(): bool
	{
		if (!function_exists('gd_info') || !function_exists('imagewebp')) return false;
		$info = gd_info();
		return (bool) (($info['WebP Encoding Support'] ?? $info['WebP Support'] ?? false));
	}

	/**
	 * Decide if Imagick should be used given capabilities and rough memory headroom.
	 * Estimate: decoded RGBA ~4 bytes/px, ×2 for working buffers.
	 * Prefer Imagick when <=60% of memory_limit to keep headroom for PHP/IO.
	 */
	private static function shouldUseImagick(int $w, int $h): bool
	{
		if (!self::hasImagickWebp()) return false;
		$mem = self::bytesFromIni(ini_get('memory_limit'));
		$est = (int) ($w * $h * 4 * 2);
		return $est < ($mem * 0.6);
	}

	/** Parse PHP ini shorthand sizes (e.g., "128M") into bytes. */
	private static function bytesFromIni(string $val): int
	{
		$val = trim($val);
		if ($val === '' || $val === '-1') return PHP_INT_MAX;
		$unit = strtolower(substr($val, -1));
		$num  = (int) $val;
		return match ($unit) {
			'g' => $num * 1024 * 1024 * 1024,
			'm' => $num * 1024 * 1024,
			'k' => $num * 1024,
			default => (int) $val,
		};
	}

	/* ======================================================================
	 *								 Imagick path
	 * ====================================================================== */

	private static function imagickPipeline(string $src, string $stem, int $edgeDisp, int $edgeThumb, int $q): array
	{
		$img = new \Imagick($src);

		// Safety: megapixel limit (defense-in-depth; we also checked earlier).
		$geom = $img->getImageGeometry();
		$pixels = (int) ($geom['width'] ?? 0) * (int) ($geom['height'] ?? 0);
		if ($pixels > self::MAX_MP) {
			$img->clear(); $img->destroy();
			throw new \RuntimeException("Image too large to process safely");
		}

		// Auto-orient (EXIF) & strip metadata (smaller output).
		// Use @ to tolerate older builds missing the method.
		@$img->autoOrient();
		@$img->stripImage();

		// --- Display derivative ---
		$display = clone $img;
		self::fitWithinImagick($display, $edgeDisp);
		self::encodeWebpImagick($display, $q);
		$displayPath = $stem . '__display.webp';
		self::atomicWriteImagick($display, $displayPath);

		// --- Thumb derivative ---
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

	/** Resize with Lanczos (no upscaling), strip metadata, and ensure no interlace. */
	private static function fitWithinImagick(\Imagick $im, int $maxEdge): void
	{
		$w = $im->getImageWidth();
		$h = $im->getImageHeight();
		if ($w <= 0 || $h <= 0) throw new \RuntimeException("Invalid image dimensions");

		$long = max($w, $h);
		if ($long > $maxEdge) {
			if ($w >= $h) $im->resizeImage($maxEdge, 0, \Imagick::FILTER_LANCZOS, 1);
			else $im->resizeImage(0, $maxEdge, \Imagick::FILTER_LANCZOS, 1);
		}
		@$im->stripImage();
		$im->setInterlaceScheme(\Imagick::INTERLACE_NO);
	}

	/** Configure WebP encoding for Imagick (lossy by default). */
	private static function encodeWebpImagick(\Imagick $im, int $quality): void
	{
		$im->setImageFormat('webp');
		$im->setOption('webp:method', '6');            // speed/quality trade-off
		$im->setOption('webp:use-sharp-yuv', 'true');  // slightly better chroma
		$im->setOption('webp:lossless', 'false');      // set 'true' if you want lossless
		$im->setImageCompressionQuality($quality);
		@$im->stripImage();
	}

	/** Atomic write: write to .tmp then rename into place (same filesystem). */
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

	/* ======================================================================
	 *									GD path
	 * ====================================================================== */

	/**
	 * GD pipeline. Accepts optional $w/$h if caller already probed them.
	 * Adds: alpha-safe cloning/resizing, EXIF auto-orient for JPEG, and quality clamping.
	 */
	private static function gdPipeline(
		string $src,
		string $stem,
		int $edgeDisp,
		int $edgeThumb,
		int $q,
		int $w = 0,
		int $h = 0
	): array {
		if ($w <= 0 || $h <= 0) {
			[$w, $h, $type] = @getimagesize($src);
			if (!$w || !$h) throw new \RuntimeException("Invalid image");
		} else {
			[, , $type] = @getimagesize($src);
			if (!$type) throw new \RuntimeException("Invalid image");
		}

		$pixels = $w * $h;
		if ($pixels > self::MAX_MP) throw new \RuntimeException("Image too large to process safely");

		$srcIm = self::gdLoad($src, $type);
		if (!$srcIm) throw new \RuntimeException("Unsupported image type");

		// Optional EXIF auto-rotate (JPEG only).
		if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
			$srcIm = self::gdAutoOrientJpeg($src, $srcIm);
			$w = imagesx($srcIm);
			$h = imagesy($srcIm);
		}

		// Display derivative
		$displayIm = self::gdFitWithin($srcIm, $w, $h, $edgeDisp);
		$displayPath = $stem . '__display.webp';
		self::gdWriteWebp($displayIm, $displayPath, $q);

		// Thumb derivative
		$thumbIm = self::gdFitWithin($srcIm, $w, $h, $edgeThumb);
		$thumbPath = $stem . '__thumb.webp';
		self::gdWriteWebp($thumbIm, $thumbPath, $q);

		imagedestroy($srcIm);
		imagedestroy($displayIm);
		imagedestroy($thumbIm);

		// Collect metadata (re-probe saved files).
		[$dw, $dh] = @getimagesize($displayPath);
		[$tw, $th] = @getimagesize($thumbPath);

		return [
			'display' => [
				'path'   => $displayPath,
				'width'  => (int) $dw,
				'height' => (int) $dh,
				'bytes'  => filesize($displayPath) ?: 0,
				'mime'   => 'image/webp',
			],
			'thumb' => [
				'path'   => $thumbPath,
				'width'  => (int) $tw,
				'height' => (int) $th,
				'bytes'  => filesize($thumbPath) ?: 0,
				'mime'   => 'image/webp',
			],
		];
	}

	/** Load source into GD from type. */
	private static function gdLoad(string $path, int $type)
	{
		return match ($type) {
			IMAGETYPE_JPEG => imagecreatefromjpeg($path),
			IMAGETYPE_PNG  => imagecreatefrompng($path),
			IMAGETYPE_WEBP => imagecreatefromwebp($path),
			default        => null,
		};
	}

	/**
	 * Fit within a max edge, preserving aspect ratio.
	 * - No upscaling: clones when already <= max edge.
	 * - Alpha-safe: preserves transparency for PNG/WebP.
	 */
	private static function gdFitWithin($srcIm, int $w, int $h, int $maxEdge)
	{
		$long = max($w, $h);

		// Clone without upscale
		if ($long <= $maxEdge) {
			$im = imagecreatetruecolor($w, $h);
			imagealphablending($im, false);
			imagesavealpha($im, true);
			$transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
			imagefilledrectangle($im, 0, 0, $w, $h, $transparent);
			imagecopy($im, $srcIm, 0, 0, 0, 0, $w, $h);
			return $im;
		}

		// Downscale
		if ($w >= $h) { $nw = $maxEdge; $nh = (int) round($h * ($maxEdge / $w)); }
		else          { $nh = $maxEdge; $nw = (int) round($w * ($maxEdge / $h)); }

		$im = imagecreatetruecolor($nw, $nh);
		imagealphablending($im, false);
		imagesavealpha($im, true);
		$transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
		imagefilledrectangle($im, 0, 0, $nw, $nh, $transparent);
		imagecopyresampled($im, $srcIm, 0, 0, 0, 0, $nw, $nh, $w, $h);
		return $im;
	}

	/** Encode WebP with clamped quality and atomic rename. */
	private static function gdWriteWebp($im, string $path, int $quality): void
	{
		$quality = max(0, min(100, (int) $quality));
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

	/**
	 * Best-effort EXIF auto-rotate for JPEG on GD.
	 * Returns a (potentially) new resource; if rotation not needed, returns original.
	 */
	private static function gdAutoOrientJpeg(string $path, $im)
	{
		try {
			$exif = @exif_read_data($path);
			if (!$exif || empty($exif['Orientation'])) return $im;

			switch ((int) $exif['Orientation']) {
				case 3: $rot = imagerotate($im, 180, 0); break;
				case 6: $rot = imagerotate($im, -90, 0); break; // 90° CW
				case 8: $rot = imagerotate($im, 90, 0);  break; // 90° CCW
				default: return $im;
			}

			if ($rot) {
				imagedestroy($im);
				return $rot;
			}
		} catch (\Throwable) {
			// ignore; keep original
		}
		return $im;
	}

	/* ======================================================================
	 *								Legacy probes (fixed)
	 * ====================================================================== */

	private static function probeWidth(string $path): int
	{
		$s = @getimagesize($path);
		return (int) ($s[0] ?? 0);
	}

	private static function probeHeight(string $path): int
	{
		$s = @getimagesize($path);
		return (int) ($s[1] ?? 0);
	}
}
