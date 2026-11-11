<?php

declare(strict_types=1);

namespace Modules\Core\Application\Api;

use Modules\Core\Contracts\EdiDocumentHandler;
use Modules\Core\Domain\Algorithms\Edi\EdiInterchangeCodec;
use Modules\Core\Domain\Models\CoreEdiInterchange;
use Modules\Core\Domain\StateMachines\EdiInterchangeStateMachine;

class EdiProcessingService
{
    /** @var array<int, EdiDocumentHandler> */
    private array $handlers = [];

    public function __construct(
        private readonly EdiInterchangeCodec $codec,
        private readonly EdiInterchangeStateMachine $stateMachine,
    ) {}

    public function registerHandler(EdiDocumentHandler $handler): void
    {
        $this->handlers[] = $handler;
    }

    /**
     * @return array<int, EdiDocumentHandler>
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }

    public function processInbound(
        string $tenantId,
        string $rawPayload,
        string $idempotencyKey,
        ?int $actorUserId = null,
    ): CoreEdiInterchange {
        return $this->processInterchange($tenantId, $rawPayload, $idempotencyKey, $actorUserId);
    }

    /**
     * Ingest, decode, validate, acknowledge, and route EDI interchange through state machine.
     */
    public function processInterchange(
        string $tenantId,
        string $rawPayload,
        string $idempotencyKey,
        ?int $actorUserId = null,
    ): CoreEdiInterchange {
        /** @var CoreEdiInterchange|null $existing */
        $existing = CoreEdiInterchange::where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        $interchange = CoreEdiInterchange::create([
            'tenant_id' => $tenantId,
            'control_ref' => 'PENDING',
            'standard' => 'UNKNOWN',
            'message_type' => 'UNKNOWN',
            'sender_ref' => 'UNKNOWN',
            'receiver_ref' => 'UNKNOWN',
            'document_number' => '',
            'status' => EdiInterchangeStateMachine::STATUS_RECEIVED,
            'raw_payload' => $rawPayload,
            'canonical_json' => null,
            'contrl_payload' => null,
            'handler_module' => null,
            'handler_idempotency_key' => null,
            'target_reference' => null,
            'error_codes' => null,
            'rejection_reason' => null,
            'cert_hash' => null,
            'idempotency_key' => $idempotencyKey,
        ]);

        try {
            $doc = $this->codec->decode($rawPayload);
        } catch (\Throwable $e) {
            $this->stateMachine->transition(
                $interchange,
                EdiInterchangeStateMachine::STATUS_REJECTED,
                'EDI decode syntax error: '.$e->getMessage(),
                $actorUserId,
                ['error_codes' => ['SYNTAX_ERROR']]
            );

            return $interchange->fresh();
        }

        $certHash = $doc->certificate['sha256'] ?? null;
        $interchange->update([
            'control_ref' => $doc->interchangeControlRef ?: 'CTRL-0',
            'standard' => $doc->standard,
            'message_type' => $doc->messageType,
            'sender_ref' => $doc->senderId,
            'receiver_ref' => $doc->receiverId,
            'document_number' => $doc->documentNumber,
            'contrl_payload' => $doc->acknowledgmentPayload,
            'error_codes' => $doc->errorCodes,
            'cert_hash' => $certHash,
            'canonical_json' => [
                'standard' => $doc->standard,
                'message_type' => $doc->messageType,
                'control_ref' => $doc->interchangeControlRef,
                'sender_ref' => $doc->senderId,
                'receiver_ref' => $doc->receiverId,
                'document_number' => $doc->documentNumber,
                'buyer_identifier' => $doc->buyerIdentifier,
                'line_items' => $doc->lineItems,
            ],
        ]);

        // Transition: received -> parsed
        $this->stateMachine->transition(
            $interchange,
            EdiInterchangeStateMachine::STATUS_PARSED,
            'Interchange parsed successfully',
            $actorUserId
        );

        if (! $doc->isValid) {
            $reason = ! empty($doc->errorCodes) ? implode(', ', $doc->errorCodes) : 'Invalid EDI payload';
            $this->stateMachine->transition(
                $interchange,
                EdiInterchangeStateMachine::STATUS_REJECTED,
                $reason,
                $actorUserId,
                ['contrl_payload' => $doc->acknowledgmentPayload]
            );

            return $interchange->fresh();
        }

        // Transition: parsed -> validated
        $this->stateMachine->transition(
            $interchange,
            EdiInterchangeStateMachine::STATUS_VALIDATED,
            'Syntax and structure validated',
            $actorUserId
        );

        // Find supporting handler
        $handler = $this->findHandler($doc->messageType);
        if ($handler === null) {
            $this->stateMachine->transition(
                $interchange,
                EdiInterchangeStateMachine::STATUS_REJECTED,
                "No registered EdiDocumentHandler for message type [{$doc->messageType}]",
                $actorUserId
            );

            return $interchange->fresh();
        }

        // Transition: validated -> mapped
        $this->stateMachine->transition(
            $interchange,
            EdiInterchangeStateMachine::STATUS_MAPPED,
            "Mapped to handler {$handler->moduleName()}",
            $actorUserId,
            ['handler_module' => $handler->moduleName()]
        );

        // Transition: mapped -> acknowledged
        $this->stateMachine->transition(
            $interchange,
            EdiInterchangeStateMachine::STATUS_ACKNOWLEDGED,
            'CONTRL/997 acknowledgment emitted',
            $actorUserId,
            ['contrl_payload' => $doc->acknowledgmentPayload]
        );

        // Invoke handler
        $handlerKey = sprintf('edi_post:%d:%s', $interchange->id, $idempotencyKey);
        try {
            $result = $handler->handle($doc, $handlerKey);

            if ($result->isSuccess) {
                // Transition: acknowledged -> posted
                $this->stateMachine->transition(
                    $interchange,
                    EdiInterchangeStateMachine::STATUS_POSTED,
                    'Applied by handler: '.$result->message,
                    $actorUserId,
                    [
                        'handler_module' => $handler->moduleName(),
                        'handler_idempotency_key' => $handlerKey,
                        'target_reference' => $result->targetReference,
                    ]
                );
            } else {
                $this->stateMachine->transition(
                    $interchange,
                    EdiInterchangeStateMachine::STATUS_REJECTED,
                    'Handler rejected document: '.$result->message,
                    $actorUserId
                );
            }
        } catch (\Throwable $e) {
            $this->stateMachine->transition(
                $interchange,
                EdiInterchangeStateMachine::STATUS_REJECTED,
                'Handler execution exception: '.$e->getMessage(),
                $actorUserId
            );
        }

        return $interchange->fresh();
    }

    private function findHandler(string $messageType): ?EdiDocumentHandler
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($messageType)) {
                return $handler;
            }
        }

        return null;
    }
}
