<?php

declare(strict_types=1);

namespace Modules\Banking\Support;

final class Posting
{
    public static function lines(): PostingLines
    {
        return new PostingLines;
    }
}
