<?php

declare(strict_types=1);

namespace Modules\Contract\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Contract\Domain\Models\ClauseTemplate;
use Modules\Contract\Domain\Models\Contract;
use Modules\Contract\Domain\Models\ContractClause;
use Modules\Contract\Domain\Models\ContractVersion;
use Modules\Shared\Application\BaseAction;

class AttachContractClauseFromTemplateAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): ContractClause
    {
        return DB::transaction(function () use ($params) {
            $contract = Contract::findOrFail($params['contract_id']);
            $template = ClauseTemplate::findOrFail($params['clause_template_id']);
            $variables = (array) ($params['variables'] ?? []);
            $isNegotiated = (bool) ($params['is_negotiated'] ?? false);

            $renderedBody = $template->render($variables);
            $order = ((int) ContractClause::where('contract_id', $contract->id)->max('display_order')) + 1;

            $clause = ContractClause::create([
                'contract_id' => $contract->id,
                'clause_template_id' => $template->id,
                'display_order' => $order,
                'title' => $template->title,
                'body' => $renderedBody,
                'is_negotiated' => $isNegotiated,
            ]);

            // Append contract version to reflect attached clause
            $prevHash = $contract->current_hash ?: ContractVersion::GENESIS_HASH;
            $nextSeq = ((int) ContractVersion::where('contract_id', $contract->id)->max('sequence')) + 1;
            $nowIso = Carbon::now()->toIso8601String();

            $newBody = ($contract->current_body ? $contract->current_body."\n\n" : '')."### Klausul {$order}: {$template->title}\n{$renderedBody}";
            $hash = ContractVersion::calculateHash($prevHash, $nextSeq, 'clause_attached', $newBody, $nowIso);

            ContractVersion::create([
                'id' => (string) Str::uuid(),
                'contract_id' => $contract->id,
                'sequence' => $nextSeq,
                'change_type' => 'clause_attached',
                'body' => $newBody,
                'metadata' => [
                    'clause_id' => $clause->id,
                    'clause_title' => $template->title,
                    'is_negotiated' => $isNegotiated,
                ],
                'prev_hash' => $prevHash,
                'hash' => $hash,
                'created_by_name' => (string) ($params['author'] ?? 'legal_officer'),
                'created_at' => $nowIso,
            ]);

            $contract->update([
                'current_body' => $newBody,
                'current_hash' => $hash,
            ]);

            $this->audit(
                action: 'contract_clause_attached',
                auditable: $clause,
                newValues: ['template_code' => $template->code, 'order' => $order, 'hash' => $hash],
                impactType: 'legal_drafting',
            );

            return $clause;
        });
    }
}
