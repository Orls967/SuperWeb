<?php

declare(strict_types=1);

namespace Modules\Integration\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Integration\Domain\Models\ApiClient;
use Modules\Integration\Domain\Models\EdiMessage;
use Modules\Integration\Domain\Models\WebhookDelivery;
use Modules\Integration\Domain\Models\WebhookSubscription;

class IntegrationController extends Controller
{
    public function index(Request $request): View
    {
        $subscriptions = WebhookSubscription::orderByDesc('created_at')->limit(10)->get();
        $deliveries = WebhookDelivery::orderByDesc('created_at')->limit(10)->get();
        $ediMessages = EdiMessage::orderByDesc('created_at')->limit(10)->get();
        $clients = ApiClient::orderByDesc('created_at')->limit(10)->get();

        return view('integration::index', compact('subscriptions', 'deliveries', 'ediMessages', 'clients'));
    }
}
