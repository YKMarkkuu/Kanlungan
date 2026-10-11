<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Photos on posts. Every upload is decoded and re-encoded as a plain JPEG (max 1280px wide), which
 * removes EXIF data such as GPS location and the camera, so an "anonymous" post cannot leak it.
 * Saved under public/uploads/posts (no storage:link needed).
 */
class ImageService
{
    private const MAX_WIDTH = 1280;

    public function storePostPhoto(UploadedFile $file): string
    {
        if (! extension_loaded('gd')) {
            throw ValidationException::withMessages(['photo' => 'Photos are not available on this server (PHP GD is missing).']);
        }

        $path = $file->getRealPath();
        $image = @imagecreatefromstring((string) file_get_contents($path));

        if ($image === false) {
            throw ValidationException::withMessages(['photo' => 'That file is not a valid image.']);
        }

        $image = $this->applyOrientation($image, $path, (string) $file->getMimeType());

        $width = imagesx($image);
        $height = imagesy($image);

        if ($width > self::MAX_WIDTH) {
            $scaled = imagescale($image, self::MAX_WIDTH);
            if ($scaled !== false) {
                $image = $scaled;
                $width = imagesx($image);
                $height = imagesy($image);
            }
        }

        // Flatten onto white so transparent PNGs do not turn black.
        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);

        $directory = public_path('uploads/posts');

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $name = Str::uuid()->toString().'.jpg';
        imagejpeg($canvas, $directory.DIRECTORY_SEPARATOR.$name, 82);

        return 'uploads/posts/'.$name;
    }

    /** @param  \GdImage  $image */
    private function applyOrientation($image, string $path, string $mime)
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = (int) (@exif_read_data($path)['Orientation'] ?? 1);
        $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;

        if ($angle !== 0) {
            $rotated = imagerotate($image, $angle, 0);
            if ($rotated !== false) {
                return $rotated;
            }
        }

        return $image;
    }
}
