<?php

declare(strict_types=1);

namespace Modules\Contract\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Contract\Domain\Enums\ContractType;
use Modules\Contract\Domain\Models\ClauseTemplate;
use Modules\Contract\Domain\Models\ContractTemplate;

class ContractTemplateController extends Controller
{
    /** Template library. */
    public function index(): View
    {
        $templates = ContractTemplate::where('is_active', true)
            ->orderBy('contract_type')
            ->orderBy('name')
            ->get();

        $types = ContractType::cases();

        return view('contract::templates.index', compact('templates', 'types'));
    }

    /** Create template form. */
    public function create(): View
    {
        $types = ContractType::cases();
        $clauses = ClauseTemplate::where('is_active', true)->orderBy('category')->orderBy('title')->get();

        return view('contract::templates.create', compact('types', 'clauses'));
    }

    /** Store new contract template. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:50|unique:ctr_contract_templates,code',
            'name' => 'required|string|max:255',
            'contract_type' => 'required|string',
            'description' => 'nullable|string|max:1000',
            'default_clause_ids' => 'nullable|array',
            'default_clause_ids.*' => 'uuid|exists:ctr_clause_templates,id',
            'required_variables' => 'nullable|string',
        ]);

        $vars = null;
        if (! empty($data['required_variables'])) {
            $vars = array_map('trim', explode(',', $data['required_variables']));
        }

        ContractTemplate::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'contract_type' => $data['contract_type'],
            'description' => $data['description'] ?? null,
            'default_clause_ids' => $data['default_clause_ids'] ?? [],
            'required_variables' => $vars,
            'is_active' => true,
        ]);

        return redirect()
            ->route('contract.templates.index')
            ->with('success', "Template [{$data['name']}] berhasil dibuat.");
    }
}
