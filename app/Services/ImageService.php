<?php

namespace App\Services;

use App\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Photos on posts. Every upload is decoded and re-encoded as a plain JPEG (max 1600px on either edge), which
 * removes EXIF data such as GPS location and the camera, so an "anonymous" post cannot leak it.
 * Saved under public/uploads/posts (no storage:link needed).
 */
class ImageService
{
    private const MAX_EDGE = 1600;

    /** getimagesize() reads only the header, so this check happens before GD allocates ~4 bytes per pixel. */
    private const MAX_SOURCE_PIXELS = 24_000_000;

    private const AVATAR_SIZE = 150;

    /**
     * Decode, auto-rotate, shrink so the longest side is at most 1600px, flatten onto white and
     * re-encode as a progressive JPEG. Re-encoding drops EXIF (GPS, camera model).
     *
     * @return array{path: string, width: int, height: int}
     */
    public function storePostPhoto(UploadedFile $file): array
    {
        if (! extension_loaded('gd')) {
            throw ValidationException::withMessages(['photos' => 'Photos are not available on this server (PHP GD is missing).']);
        }

        $source = $file->getRealPath();

        if ($source === false) {
            throw ValidationException::withMessages(['photos' => 'That file could not be read.']);
        }

        $info = @getimagesize($source);

        if ($info === false) {
            throw ValidationException::withMessages(['photos' => 'That file is not a valid image.']);
        }

        if ($info[0] * $info[1] > self::MAX_SOURCE_PIXELS) {
            throw ValidationException::withMessages(['photos' => 'That photo has too many pixels to process. Try a smaller one.']);
        }

        $image = @imagecreatefromstring((string) file_get_contents($source));

        if ($image === false) {
            throw ValidationException::withMessages(['photos' => 'That file is not a valid image.']);
        }

        $image = $this->applyOrientation($image, $source, (string) $file->getMimeType());

        $width = imagesx($image);
        $height = imagesy($image);
        $longest = max($width, $height);

        $targetW = $width;
        $targetH = $height;

        if ($longest > self::MAX_EDGE) {
            $ratio = self::MAX_EDGE / $longest;
            $targetW = max(1, (int) round($width * $ratio));
            $targetH = max(1, (int) round($height * $ratio));
        }

        // One allocation: scale and flatten in a single step (transparent PNGs would otherwise turn black).
        $canvas = imagecreatetruecolor($targetW, $targetH);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetW, $targetH, $width, $height);
        unset($image);

        $directory = public_path('uploads/posts');

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Failed to create the post uploads directory.');
        }

        imageinterlace($canvas, true);

        $name = Str::uuid()->toString().'.jpg';
        $saved = imagejpeg($canvas, $directory.DIRECTORY_SEPARATOR.$name, 82);
        unset($canvas);

        if (! $saved) {
            throw new RuntimeException('Failed to save the uploaded photo.');
        }

        return ['path' => 'uploads/posts/'.$name, 'width' => $targetW, 'height' => $targetH];
    }

    /** Deletes every photo file of a post and its rows. Used for author delete, community vote and moderator removal. */
    public function deletePostImages(Post $post): void
    {
        $paths = $post->images()->pluck('path')->all();

        $this->deletePaths($paths);

        $post->images()->delete();
        $post->unsetRelation('images');
    }

    /**
     * Best-effort cleanup of files by path. One bad path must not stop the rest, so each failure
     * is reported and skipped. Also used to undo a half-finished upload.
     *
     * @param  array<int, string>  $paths
     */
    public function deletePaths(array $paths): void
    {
        foreach ($paths as $path) {
            try {
                $this->deletePhoto($path);
            } catch (RuntimeException $e) {
                report($e);
            }
        }
    }

    public function storeAvatar(UploadedFile $file): string
    {
        if (! extension_loaded('gd')) {
            throw ValidationException::withMessages(['avatar' => 'Photo uploads are not available on this server (PHP GD is missing).']);
        }

        $path = $file->getRealPath();

        if ($path === false) {
            throw ValidationException::withMessages(['avatar' => 'That file could not be read.']);
        }

        $image = @imagecreatefromstring((string) file_get_contents($path));

        if ($image === false) {
            throw ValidationException::withMessages(['avatar' => 'That file is not a valid image.']);
        }

        $image = $this->applyOrientation($image, $path, (string) $file->getMimeType());
        $width = imagesx($image);
        $height = imagesy($image);
        if ($width < 1 || $height < 1) {
            imagedestroy($image);

            throw ValidationException::withMessages(['avatar' => 'That image has invalid dimensions.']);
        }

        // Crop to the largest centered square before resizing, preserving the cover ratio.
        $side = min($width, $height);
        $sourceX = (int) floor(($width - $side) / 2);
        $sourceY = (int) floor(($height - $side) / 2);
        $canvas = imagecreatetruecolor(self::AVATAR_SIZE, self::AVATAR_SIZE);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));

        $resampled = imagecopyresampled(
            $canvas,
            $image,
            0,
            0,
            $sourceX,
            $sourceY,
            self::AVATAR_SIZE,
            self::AVATAR_SIZE,
            $side,
            $side,
        );

        if (! $resampled) {
            imagedestroy($image);
            imagedestroy($canvas);

            throw new RuntimeException('Failed to resize the uploaded avatar.');
        }

        $directory = public_path('uploads/avatars');

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            imagedestroy($image);
            imagedestroy($canvas);

            throw new RuntimeException('Failed to create the avatar uploads directory.');
        }

        $name = Str::uuid()->toString().'.jpg';
        $destination = $directory.DIRECTORY_SEPARATOR.$name;
        $saved = imagejpeg($canvas, $destination, 82);
        imagedestroy($image);
        imagedestroy($canvas);

        if (! $saved) {
            throw new RuntimeException('Failed to save the uploaded avatar.');
        }

        return 'uploads/avatars/'.$name;
    }

    public function deleteAvatar(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        if (! preg_match('~\Auploads/avatars/[A-Za-z0-9-]+\.jpg\z~D', $path)) {
            throw new RuntimeException('Cannot delete an avatar outside the avatar uploads directory.');
        }

        $file = public_path($path);

        if (! is_file($file)) {
            return;
        }

        if (! unlink($file)) {
            throw new RuntimeException("Failed to delete avatar: {$path}");
        }
    }

    public function deletePhoto(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        if (! preg_match('~\Auploads/posts/[A-Za-z0-9-]+\.jpg\z~D', $path)) {
            throw new RuntimeException('Cannot delete a photo outside the post uploads directory.');
        }

        $file = public_path($path);

        if (! is_file($file)) {
            return;
        }

        if (! unlink($file)) {
            throw new RuntimeException("Failed to delete post photo: {$path}");
        }
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
