<?php

declare(strict_types=1);

namespace Modules\Med\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Med\Domain\Models\MedIpLicense;
use Modules\Med\Domain\Models\MedRoyaltyStatement;
use Modules\Med\Domain\Models\MedStudio;

class MediaMvpController extends Controller
{
    public function dashboard(Request $request)
    {
        return view('med::dashboard');
    }

    public function studios(Request $request)
    {
        $studios = MedStudio::all();

        return view('med::mvp_studios', compact('studios'));
    }

    public function licensing(Request $request)
    {
        $licenses = MedIpLicense::with('ipAsset')->get();

        return view('med::mvp_licensing', compact('licenses'));
    }

    public function royaltyStatementDetail(Request $request, string $id)
    {
        $statement = MedRoyaltyStatement::with(['license.ipAsset', 'lines'])->findOrFail($id);

        $user = $request->user();
        if ($user) {
            $userEntityId = $user->getAttribute('entity_id');
            // Anti-IDOR: if user is scoped to a specific entity_id, enforce match
            if ($userEntityId !== null) {
                $license = $statement->license;
                $isLicensee = $license && $license->licensee_entity_id === $userEntityId;
                $isIpOwner = $license && $license->ipAsset && $license->ipAsset->owner_entity_id === $userEntityId;
                $isRightsholder = $statement->lines->contains('rightsholder_party_id', $userEntityId);

                if (! $isLicensee && ! $isIpOwner && ! $isRightsholder) {
                    abort(403, 'Unauthorized access to royalty statement for entity '.$userEntityId);
                }
            }
        }

        return view('med::royalty_statement_detail', compact('statement'));
    }
}
