<?php

declare(strict_types=1);

namespace Modules\Wms\Ledger;

use Modules\Banking\Contracts\TransactionTypeRegistry;

/**
 * Tipe transaksi buku besar modul WMS (D41-9).
 */
enum WmsTransactionTypes: string implements TransactionTypeRegistry
{
    case OPENING_BALANCE = 'wms_opening_balance';
    case INBOUND_RECEIPT = 'wms_inbound_receipt';
    case OUTBOUND_ISSUE = 'wms_outbound_issue';
    case TRANSFER_OUT = 'wms_transfer_out';
    case TRANSFER_IN = 'wms_transfer_in';
    case CYCLE_COUNT_GAIN = 'wms_cycle_count_gain';
    case CYCLE_COUNT_SHRINKAGE = 'wms_cycle_count_shrinkage';
    case INVENTORY_WRITEOFF = 'wms_inventory_writeoff';
    case TRANSIT_LOSS = 'wms_transit_loss';
    case COST_REVALUATION_MARKUP = 'wms_cost_revaluation_markup';
    case COST_REVALUATION_MARKDOWN = 'wms_cost_revaluation_markdown';
    case THREE_PL_BILLING = 'wms_3pl_billing';
}
