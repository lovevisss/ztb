<?php

namespace App\Services;

use InvalidArgumentException;

class LegacyAttachmentPath
{
    public static function storedPath(string $legacyPath): string
    {
        $normalized = str_replace('\\', '/', $legacyPath);
        if (! preg_match('~\Aupload/\d{4}/\d{2}/\d{2}/[A-Za-z0-9][A-Za-z0-9._-]*\z~D', $normalized)) {
            throw new InvalidArgumentException('旧附件路径格式无效。');
        }

        return 'legacy/'.substr($normalized, strlen('upload/'));
    }
}
