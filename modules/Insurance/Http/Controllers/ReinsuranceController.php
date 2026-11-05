<?php

declare(strict_types=1);

namespace Modules\Insurance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Insurance\Application\Actions\CreateTreatyAction;
use Modules\Insurance\Application\Actions\ExportBordereauCsvAction;
use Modules\Insurance\Application\Actions\GenerateAccountCurrentAction;
use Modules\Insurance\Application\Actions\GenerateBordereauAction;
use Modules\Insurance\Application\Actions\RequestTreatyTransitionAction;
use Modules\Insurance\Application\Actions\SendBordereauAction;
use Modules\Insurance\Application\Actions\SettleAccountCurrentAction;
use Modules\Insurance\Domain\Enums\TreatyStatus;
use Modules\Insurance\Domain\Models\ReinsuranceAccountCurrent;
use Modules\Insurance\Domain\Models\ReinsuranceBordereau;
use Modules\Insurance\Domain\Models\ReinsuranceTreaty;
use Modules\Party\Contracts\Party;

final class ReinsuranceController extends Controller
{
    public function treaties(Request $request)
    {
        $treaties = ReinsuranceTreaty::with('panel.reinsurer')->orderByDesc('id')->paginate(15);
        $reinsurers = Party::all();

        return view('insurance::reinsurance.treaties.index', compact('treaties', 'reinsurers'));
    }

    public function treatyDetail(int $id)
    {
        $treaty = ReinsuranceTreaty::with(['panel.reinsurer', 'history'])->findOrFail($id);

        return view('insurance::reinsurance.treaties.show', compact('treaty'));
    }

    public function storeTreaty(Request $request, CreateTreatyAction $action)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string'],
            'line_category' => ['required', 'string'],
            'inception_date' => ['required', 'date'],
            'expiry_date' => ['required', 'date'],
            'quota_share_bps' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'surplus_retention_idr' => ['nullable', 'integer', 'min:0'],
            'surplus_lines' => ['nullable', 'integer', 'min:0'],
            'attachment_idr' => ['nullable', 'integer', 'min:0'],
            'limit_idr' => ['nullable', 'integer', 'min:0'],
            'layer_premium_idr' => ['nullable', 'integer', 'min:0'],
            'commission_bps' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'panel' => ['required', 'array', 'min:1'],
            'panel.*.reinsurer_party_id' => ['required', 'integer'],
            'panel.*.share_bps' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $treaty = $action->execute($validated, $user->id);

        return redirect()->route('insurance.reinsurance.treaties.show', $treaty->id)
            ->with('status', "Treaty {$treaty->treaty_code} berhasil dibuat.");
    }

    public function transitionTreaty(Request $request, int $id, RequestTreatyTransitionAction $action)
    {
        $treaty = ReinsuranceTreaty::findOrFail($id);
        $validated = $request->validate([
            'target_status' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $target = TreatyStatus::from($validated['target_status']);
        $action->execute($treaty, $target, $user, $validated['reason'] ?? null);

        return redirect()->route('insurance.reinsurance.treaties.show', $treaty->id)
            ->with('status', "Status treaty dialihkan ke {$target->label()}.");
    }

    public function bordereaux(Request $request)
    {
        $bordereaux = ReinsuranceBordereau::with(['treaty', 'reinsurer'])->orderByDesc('id')->paginate(15);
        $treaties = ReinsuranceTreaty::where('status', 'active')->get();
        $reinsurers = Party::all();

        return view('insurance::reinsurance.bordereaux.index', compact('bordereaux', 'treaties', 'reinsurers'));
    }

    public function bordereauDetail(int $id)
    {
        $bordereau = ReinsuranceBordereau::with(['treaty', 'reinsurer', 'lines'])->findOrFail($id);

        return view('insurance::reinsurance.bordereaux.show', compact('bordereau'));
    }

    public function generateBordereau(Request $request, GenerateBordereauAction $action)
    {
        $validated = $request->validate([
            'treaty_id' => ['required', 'integer'],
            'reinsurer_party_id' => ['required', 'string'],
            'kind' => ['required', 'in:premium,claim'],
            'period_ym' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $bordereau = $action->execute(
            treatyId: (int) $validated['treaty_id'],
            reinsurerPartyId: (string) $validated['reinsurer_party_id'],
            kind: $validated['kind'],
            periodYm: $validated['period_ym'],
            actorId: $user->id
        );

        return redirect()->route('insurance.reinsurance.bordereaux.show', $bordereau->id)
            ->with('status', "Bordereau {$bordereau->number} berhasil dibuat.");
    }

    public function sendBordereau(int $id, SendBordereauAction $action)
    {
        $bordereau = ReinsuranceBordereau::findOrFail($id);
        $action->execute($bordereau);

        return redirect()->route('insurance.reinsurance.bordereaux.show', $bordereau->id)
            ->with('status', "Bordereau {$bordereau->number} telah dikirim ke reinsurer.");
    }

    public function exportBordereauCsv(int $id, ExportBordereauCsvAction $action): Response
    {
        $bordereau = ReinsuranceBordereau::with(['treaty', 'lines'])->findOrFail($id);
        $csv = $action->execute($bordereau);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"bordereau-{$bordereau->number}.csv\"",
        ]);
    }

    public function accountCurrents(Request $request)
    {
        $accountCurrents = ReinsuranceAccountCurrent::with('reinsurer')->orderByDesc('id')->paginate(15);
        $reinsurers = Party::all();

        return view('insurance::reinsurance.account_current.index', compact('accountCurrents', 'reinsurers'));
    }

    public function accountCurrentDetail(int $id)
    {
        $accountCurrent = ReinsuranceAccountCurrent::with('reinsurer')->findOrFail($id);

        return view('insurance::reinsurance.account_current.show', compact('accountCurrent'));
    }

    public function generateAccountCurrent(Request $request, GenerateAccountCurrentAction $action)
    {
        $validated = $request->validate([
            'reinsurer_party_id' => ['required', 'string'],
            'period_ym' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $ac = $action->execute(
            reinsurerPartyId: (string) $validated['reinsurer_party_id'],
            periodYm: $validated['period_ym'],
            actorId: $user->id
        );

        return redirect()->route('insurance.reinsurance.account-current.show', $ac->id)
            ->with('status', "Account Current {$ac->number} berhasil dibuat.");
    }

    public function settleAccountCurrent(Request $request, int $id, SettleAccountCurrentAction $action)
    {
        $ac = ReinsuranceAccountCurrent::findOrFail($id);
        $key = $request->header('Idempotency-Key') ?? (string) $request->input('idempotency_key', 'settle-ac-'.$ac->id);

        /** @var User $user */
        $user = $request->user();
        $action->execute($ac, $key, $user->id);

        return redirect()->route('insurance.reinsurance.account-current.show', $ac->id)
            ->with('status', "Account Current {$ac->number} telah diselesaikan (settled).");
    }
}
