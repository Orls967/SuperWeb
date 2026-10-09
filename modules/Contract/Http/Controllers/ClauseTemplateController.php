<?php

declare(strict_types=1);

namespace Modules\Contract\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Contract\Domain\Models\ClauseTemplate;

class ClauseTemplateController extends Controller
{
    /** List all clause templates. */
    public function index(Request $request): View
    {
        $query = ClauseTemplate::query();

        if ($search = $request->get('q')) {
            $query->where(fn ($q) => $q
                ->where('code', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")
            );
        }

        if ($cat = $request->get('category')) {
            $query->where('category', $cat);
        }

        $clauses = $query->where('is_active', true)->orderBy('category')->orderBy('title')->paginate(25)->withQueryString();
        $categories = ['general', 'payment', 'liability', 'confidentiality', 'termination', 'jurisdiction', 'warranty', 'force_majeure'];

        return view('contract::clauses.index', compact('clauses', 'categories'));
    }

    /** Create clause form. */
    public function create(): View
    {
        $categories = ['general', 'payment', 'liability', 'confidentiality', 'termination', 'jurisdiction', 'warranty', 'force_majeure'];

        return view('contract::clauses.create', compact('categories'));
    }

    /** Store new clause template. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:50|unique:ctr_clause_templates,code',
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:50',
            'body_template' => 'required|string|min:10',
            'is_standard' => 'boolean',
        ]);

        ClauseTemplate::create($data + ['is_active' => true, 'version' => 1]);

        return redirect()
            ->route('contract.clauses.index')
            ->with('success', "Klausul [{$data['code']}] berhasil ditambahkan ke library.");
    }

    /** Edit clause (creates new version). */
    public function edit(ClauseTemplate $clause): View
    {
        $categories = ['general', 'payment', 'liability', 'confidentiality', 'termination', 'jurisdiction', 'warranty', 'force_majeure'];

        return view('contract::clauses.edit', compact('clause', 'categories'));
    }

    /** Update clause (bumps version number). */
    public function update(Request $request, ClauseTemplate $clause): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:50',
            'body_template' => 'required|string|min:10',
        ]);

        $clause->update($data + ['version' => $clause->version + 1]);

        return redirect()
            ->route('contract.clauses.index')
            ->with('success', "Klausul [{$clause->code}] diperbarui ke v{$clause->version}.");
    }
}
