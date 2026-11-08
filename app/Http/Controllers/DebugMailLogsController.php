<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class DebugMailLogsController extends Controller
{
    private const MAX_BYTES = 262144;
    private const MAX_ENTRIES = 20;

    public function __invoke(): JsonResponse
    {
        $path = storage_path('logs/laravel.log');

        if (! is_file($path)) {
            return response()->json(['message' => 'The application log is not available.'], 404);
        }

        $file = fopen($path, 'rb');

        if ($file === false) {
            return response()->json(['message' => 'The application log is not readable.'], 503);
        }

        $size = filesize($path);

        if ($size === false || fseek($file, max(0, $size - self::MAX_BYTES)) !== 0) {
            fclose($file);

            return response()->json(['message' => 'The application log could not be read.'], 503);
        }

        if ($size > self::MAX_BYTES) {
            fgets($file);
        }

        $entries = [];

        while (($line = fgets($file)) !== false) {
            if (preg_match('/Mail failed:|OTP email delivery failed\.|OTP send request failed\./i', $line)) {
                $entries[] = $this->redact(trim($line));
            }
        }

        fclose($file);

        return response()->json([
            'errors' => array_reverse(array_slice($entries, -self::MAX_ENTRIES)),
        ]);
    }

    private function redact(string $line): string
    {
        $line = preg_replace('/\bxkeysib-[A-Za-z0-9._-]+\b/i', '[REDACTED_SECRET]', $line);
        $line = preg_replace('/\bBearer\s+[A-Za-z0-9._~+\/=-]+/i', 'Bearer [REDACTED_SECRET]', $line);
        $line = preg_replace('/\b(api[_ -]?key|authorization|password|secret|token)\b\s*[:=]\s*("[^"]*"|\'[^\']*\'|[^\s,;]+)/i', '$1=[REDACTED]', $line);
        $line = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[REDACTED_EMAIL]', $line);
        $line = preg_replace('/\b\d{6}\b/', '[REDACTED_CODE]', $line);

        return $line;
    }
}
