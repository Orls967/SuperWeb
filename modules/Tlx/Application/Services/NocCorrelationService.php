<?php

declare(strict_types=1);

namespace Modules\Tlx\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Shared\Domain\Numerics\TopologySuppressor;
use Modules\Tlx\Domain\Models\NetworkElement;
use Modules\Tlx\Domain\Models\NocAlarm;
use Modules\Tlx\Domain\Models\NocTicket;

class NocCorrelationService
{
    /**
     * Mendaftarkan elemen jaringan baru ke topologi graf.
     */
    public function createElement(array $params): NetworkElement
    {
        if (empty($params['element_code'])) {
            throw new InvalidArgumentException('Element code is required.');
        }

        if (empty($params['element_type'])) {
            throw new InvalidArgumentException('Element type is required.');
        }

        return NetworkElement::create([
            'id' => (string) Str::uuid(),
            'element_code' => $params['element_code'],
            'element_type' => $params['element_type'],
            'parent_element_id' => $params['parent_element_id'] ?? null,
            'status' => $params['status'] ?? 'OPERATIONAL',
        ]);
    }

    /**
     * Mengatur induk elemen dengan pemeriksaan pencegahan siklus topologi.
     */
    public function setElementParent(string $elementId, string $parentId): NetworkElement
    {
        if ($elementId === $parentId) {
            throw new InvalidArgumentException("Cycle detected: element [{$elementId}] cannot be its own parent.");
        }

        // Trace upwards from parentId to see if elementId is reached
        $curr = $parentId;
        $visited = [$curr => true];

        while ($curr !== null) {
            if ($curr === $elementId) {
                throw new InvalidArgumentException("Cycle detected in topology: setting parent [{$parentId}] introduces a cycle.");
            }

            $parent = NetworkElement::find($curr);
            $curr = $parent ? $parent->parent_element_id : null;

            if ($curr !== null) {
                if (isset($visited[$curr])) {
                    throw new InvalidArgumentException('Existing cycle detected in topology graph.');
                }
                $visited[$curr] = true;
            }
        }

        $element = NetworkElement::findOrFail($elementId);
        $element->update(['parent_element_id' => $parentId]);

        return $element->fresh();
    }

    /**
     * Mencatat alarm baru secara append-only.
     */
    public function ingestAlarm(array $params): NocAlarm
    {
        if (empty($params['alarm_code'])) {
            throw new InvalidArgumentException('Alarm code is required.');
        }

        if (empty($params['element_id'])) {
            throw new InvalidArgumentException('Element ID is required.');
        }

        return NocAlarm::create([
            'id' => (string) Str::uuid(),
            'alarm_code' => $params['alarm_code'],
            'element_id' => $params['element_id'],
            'alarm_type' => $params['alarm_type'] ?? 'LINK_DOWN',
            'severity' => $params['severity'] ?? 'CRITICAL',
            'is_suppressed' => false,
            'suppressed_by_element_id' => null,
            'ticket_id' => null,
            'raised_at' => $params['raised_at'] ?? now(),
        ]);
    }

    /**
     * Menjalankan korelasi alarm topologi menggunakan TopologySuppressor.
     *
     * @return Collection<int, NocTicket>
     */
    public function correlateActiveAlarms(): Collection
    {
        $elements = NetworkElement::all();
        $alarms = NocAlarm::whereNull('ticket_id')->orWhere('is_suppressed', false)->get();

        $topology = [];
        $elementCodeToId = [];
        $elementIdToCode = [];

        foreach ($elements as $el) {
            $elementCodeToId[$el->element_code] = $el->id;
            $elementIdToCode[$el->id] = $el->element_code;
        }

        foreach ($elements as $el) {
            if ($el->parent_element_id !== null && isset($elementIdToCode[$el->parent_element_id])) {
                $parentCode = $elementIdToCode[$el->parent_element_id];
                $topology[] = [
                    'parent' => $parentCode,
                    'child' => $el->element_code,
                ];
            }
        }

        $alarmsInput = [];
        $alarmById = [];

        foreach ($alarms as $al) {
            $code = $elementIdToCode[$al->element_id] ?? $al->element_id;
            $alarmsInput[] = [
                'id' => $al->id,
                'node' => $code,
                'severity' => strtolower($al->severity),
            ];
            $alarmById[$al->id] = $al;
        }

        if (empty($alarmsInput)) {
            return collect();
        }

        $result = TopologySuppressor::suppress($topology, $alarmsInput, ['critical', 'major', 'down']);

        $ticketsCreated = collect();

        // 1. Process suppressed alarms
        foreach ($result['alarms'] as $item) {
            $alarmId = (string) $item['id'];
            if (! isset($alarmById[$alarmId])) {
                continue;
            }

            $alarm = $alarmById[$alarmId];

            if ($item['status'] === 'suppressed') {
                $rootNodeCode = (string) $item['root_cause_node'];
                $rootNodeId = $elementCodeToId[$rootNodeCode] ?? $rootNodeCode;

                $alarm->update([
                    'is_suppressed' => true,
                    'suppressed_by_element_id' => $rootNodeId,
                ]);
            }
        }

        // 2. Process root-cause tickets
        foreach ($result['tickets'] as $ticketData) {
            $rootNodeCode = (string) $ticketData['root_node'];
            $rootNodeId = $elementCodeToId[$rootNodeCode] ?? $rootNodeCode;

            $rootElement = NetworkElement::find($rootNodeId);
            $alarmId = (string) $ticketData['alarm_id'];
            $rootAlarm = $alarmById[$alarmId] ?? null;

            $severity = $rootAlarm ? $rootAlarm->severity : 'CRITICAL';
            $slaMinutes = strtoupper($severity) === 'CRITICAL' ? 60 : 120;
            $openedAt = $rootAlarm ? $rootAlarm->raised_at : now();

            $ticketNumber = 'TCK-'.now()->format('Ym').'-'.strtoupper(substr(md5($alarmId), 0, 6));

            $ticket = NocTicket::firstOrCreate(
                ['root_element_id' => $rootNodeId, 'status' => 'OPEN'],
                [
                    'id' => (string) Str::uuid(),
                    'ticket_number' => $ticketNumber,
                    'severity' => $severity,
                    'sla_target_minutes' => $slaMinutes,
                    'is_sla_breached' => false,
                    'opened_at' => $openedAt,
                ]
            );

            if ($rootAlarm) {
                $rootAlarm->update([
                    'is_suppressed' => false,
                    'ticket_id' => $ticket->id,
                ]);
            }

            $ticketsCreated->push($ticket);
        }

        return $ticketsCreated;
    }

    /**
     * Menyelesaikan tiket perbaikan NOC dengan pencatatan MTTR dan verifikasi SLA.
     */
    public function resolveTicket(string $ticketId, string $resolvedAt, string $notes): NocTicket
    {
        $ticket = NocTicket::findOrFail($ticketId);
        $resolvedTime = CarbonImmutable::parse($resolvedAt);
        $openedTime = CarbonImmutable::parse($ticket->opened_at);

        $mttr = (int) $openedTime->diffInMinutes($resolvedTime);
        $isBreached = $mttr > $ticket->sla_target_minutes;

        $ticket->update([
            'status' => 'RESOLVED',
            'resolved_at' => $resolvedTime,
            'mttr_minutes' => $mttr,
            'is_sla_breached' => $isBreached,
            'resolution_notes' => $notes,
        ]);

        return $ticket->fresh();
    }

    /**
     * Menghasilkan sertifikat korelasi topologi lengkap untuk verifikasi independen.
     */
    public function generateSuppressionCertificate(): array
    {
        $elements = NetworkElement::all();
        $alarms = NocAlarm::all();

        $topology = [];
        $elementIdToCode = [];
        foreach ($elements as $el) {
            $elementIdToCode[$el->id] = $el->element_code;
        }

        foreach ($elements as $el) {
            if ($el->parent_element_id !== null && isset($elementIdToCode[$el->parent_element_id])) {
                $topology[] = [
                    'parent' => $elementIdToCode[$el->parent_element_id],
                    'child' => $el->element_code,
                ];
            }
        }

        $alarmsInput = [];
        foreach ($alarms as $al) {
            $alarmsInput[] = [
                'id' => $al->id,
                'node' => $elementIdToCode[$al->element_id] ?? $al->element_id,
                'severity' => strtolower($al->severity),
            ];
        }

        $result = TopologySuppressor::suppress($topology, $alarmsInput, ['critical', 'major', 'down']);
        $result['topology'] = $topology;

        return $result;
    }
}
