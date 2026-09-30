<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Core\Application\Services\ActivityLogger;

class ActivityFeedController extends Controller
{
    public function __construct(
        private ActivityLogger $logger,
    ) {}

    /**
     * Activity feed page — shows user's own feed or admin global feed.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            $activities = $this->logger->recentAll(50);
        } else {
            $activities = $this->logger->recentForUser($user->id, 50);
        }

        return view('core::activity.index', compact('activities'));
    }
}
