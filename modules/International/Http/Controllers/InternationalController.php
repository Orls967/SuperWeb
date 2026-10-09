<?php

declare(strict_types=1);

namespace Modules\International\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\International\Domain\Models\ForeignEntity;
use Modules\International\Domain\Models\JointVenture;
use Modules\International\Domain\Models\OemContract;
use Modules\International\Domain\Models\TechnologyLicense;
use Modules\International\Domain\Models\TechTransfer;

class InternationalController extends Controller
{
    public function index(Request $request): View
    {
        $entities = ForeignEntity::with(['jointVentures', 'technologyLicenses'])->orderByDesc('created_at')->limit(10)->get();
        $jvs = JointVenture::with('foreignEntity')->orderByDesc('created_at')->limit(10)->get();
        $licenses = TechnologyLicense::with('foreignEntity')->orderByDesc('created_at')->limit(10)->get();
        $oems = OemContract::with('foreignEntity')->orderByDesc('created_at')->limit(10)->get();
        $transfers = TechTransfer::with('foreignEntity')->orderByDesc('created_at')->limit(10)->get();

        return view('international::index', compact('entities', 'jvs', 'licenses', 'oems', 'transfers'));
    }
}
