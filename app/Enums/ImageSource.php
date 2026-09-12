<?php

declare(strict_types=1);

namespace App\Enums;

enum ImageSource: string
{
    /** Processed and stored on the application's media disk. */
    case Local = 'local';

    /** Hot-linked from its original location. */
    case Remote = 'remote';
}
