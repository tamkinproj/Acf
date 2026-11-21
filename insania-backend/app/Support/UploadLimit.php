<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * PHP drops a file that is larger than the server allows before the app sees it, and the app would only see
 * "no file". This turns that into a message that says what happened and how to fix it in hPanel.
 */
final class UploadLimit
{
    public static function refused(Request $request, string $field): ?string
    {
        $file = $request->files->get($field);
        if ($file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile && in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return 'The file is larger than this server accepts (upload_max_filesize = '.ini_get('upload_max_filesize')
                .', post_max_size = '.ini_get('post_max_size').'). In hPanel, PHP Configuration, PHP options, set both to 64M (or more), save, then upload again.';
        }

        return null;
    }
}
