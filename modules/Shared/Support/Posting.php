<?php

declare(strict_types=1);

namespace Modules\Shared\Support;

use Modules\Banking\Support\Posting as BankingPosting;
use Modules\Banking\Support\PostingLines;

final class Posting
{
    public static function lines(): PostingLines
    {
        return BankingPosting::lines();
    }
}
