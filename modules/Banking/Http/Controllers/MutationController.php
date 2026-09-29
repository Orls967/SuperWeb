<?php

declare(strict_types=1);

namespace Modules\Banking\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Banking\Application\Queries\StatementQuery;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MutationController extends Controller
{
    public function index(Request $request, StatementQuery $statementQuery): View
    {
        $account = $request->user()->walletAccount('IDR');

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $type = $request->query('type');

        $entries = $statementQuery->paginate(
            account: $account,
            startDate: $startDate ? (string) $startDate : null,
            endDate: $endDate ? (string) $endDate : null,
            type: $type ? (string) $type : null,
            perPage: 20,
        );

        return view('banking::mutation.index', [
            'account' => $account,
            'entries' => $entries,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'selectedType' => $type,
        ]);
    }

    public function export(Request $request, StatementQuery $statementQuery): StreamedResponse
    {
        $account = $request->user()->walletAccount('IDR');

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $type = $request->query('type');

        return $statementQuery->exportCsv(
            account: $account,
            startDate: $startDate ? (string) $startDate : null,
            endDate: $endDate ? (string) $endDate : null,
            type: $type ? (string) $type : null,
        );
    }
}
