<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Registrar;

use Simtabi\Laranail\SIS\Models\SisAudit;
use Simtabi\Laranail\SIS\Models\SisRecord;
use Simtabi\SIS\Decision\AppendAudit;
use Simtabi\SIS\Decision\AssignAlias;
use Simtabi\SIS\Decision\ChangeState;
use Simtabi\SIS\Decision\Decision;
use Simtabi\SIS\Decision\DeleteRecord;
use Simtabi\SIS\Decision\InsertRecord;
use Simtabi\SIS\Decision\SetSubject;
use Simtabi\SIS\Decision\SetSupersededBy;
use Simtabi\SIS\Enums\LifecycleState;
use Simtabi\SIS\Exception\UnknownIdentifierException;
use Simtabi\SIS\Identifier\Identifier;

/**
 * Applies a Decision's effects to the register and the audit trail. The core produced these as pure
 * descriptions; this is the only place they become writes. The caller runs it inside one transaction.
 *
 * Register-row mutations are coalesced into ONE UPDATE per identifier. The storage-layer immutability
 * trigger keys on OLD.state: it only blocks a segment change once the row has left 'reserved'. Commission
 * changes state → commissioned AND assigns the alias/subject in the same Decision; applying those as
 * separate saves would flip the state first, then trip the trigger on the following alias write. Staging the
 * mutations and saving once keeps OLD.state = 'reserved' for the whole write, so the trigger correctly
 * permits it — a bug that only surfaces on a trigger-capable driver (PostgreSQL/MySQL), never on SQLite.
 */
final class EffectApplier
{
    public function apply(Decision $decision): void
    {
        /** @var array<string, SisRecord> $updates one loaded record per identifier, mutated in place */
        $updates = [];

        foreach ($decision->effects() as $effect) {
            match (true) {
                $effect instanceof InsertRecord => $this->insert($effect),
                $effect instanceof ChangeState => $this->changeState($effect, $this->staged($effect->identifier, $updates)),
                $effect instanceof AssignAlias => $this->assignAlias($effect, $this->staged($effect->identifier, $updates)),
                $effect instanceof SetSubject => $this->setSubject($effect, $this->staged($effect->identifier, $updates)),
                $effect instanceof SetSupersededBy => $this->setSupersededBy($effect, $this->staged($effect->identifier, $updates)),
                $effect instanceof DeleteRecord => $this->delete($effect),
                $effect instanceof AppendAudit => $this->appendAudit($effect),
                default => null,
            };
        }

        foreach ($updates as $record) {
            $record->save();
        }
    }

    private function insert(InsertRecord $effect): void
    {
        $id = $effect->identifier;
        $record = new SisRecord;
        $record->setAttribute('identifier', (string) $id);
        $record->setAttribute('class', $id->class->code);
        $record->setAttribute('scope', $id->scope);
        $record->setAttribute('serial', $id->serial);
        $record->setAttribute('spec_edition', (string) $effect->specEdition);
        $record->setAttribute('state', $effect->state);
        $record->setAttribute('reserved_at', $effect->reservedAt);
        $record->setAttribute('reserved_by', $effect->reservedBy);
        $record->setAttribute('reserved_reason', $effect->reservedReason);
        $record->setAttribute('expires_at', $effect->expiresAt);

        if ($effect->subject !== null) {
            $record->setAttribute('subject_type', $effect->subject->type);
            $record->setAttribute('subject_id', $effect->subject->id);
        }

        $record->save();
    }

    private function changeState(ChangeState $effect, SisRecord $record): void
    {
        $record->setAttribute('state', $effect->to);

        if ($effect->to === LifecycleState::Commissioned && $record->getAttribute('commissioned_at') === null) {
            $record->setAttribute('commissioned_at', $effect->at);
        }

        if ($effect->to === LifecycleState::Decommissioned) {
            $record->setAttribute('decommissioned_at', $effect->at);
        }
    }

    private function assignAlias(AssignAlias $effect, SisRecord $record): void
    {
        $record->setAttribute('alias', $effect->alias->value);
    }

    private function setSubject(SetSubject $effect, SisRecord $record): void
    {
        $record->setAttribute('subject_type', $effect->subject->type);
        $record->setAttribute('subject_id', $effect->subject->id);
    }

    private function setSupersededBy(SetSupersededBy $effect, SisRecord $record): void
    {
        $record->setAttribute('superseded_by', (string) $effect->successor);
    }

    private function delete(DeleteRecord $effect): void
    {
        $this->record($effect->identifier)->delete();
    }

    private function appendAudit(AppendAudit $effect): void
    {
        $prevHash = null;
        $hash = null;

        if ((bool) config('sis.audit.hash_chain', true)) {
            /** @var string|null $prevHash */
            $prevHash = SisAudit::query()->orderByDesc('id')->value('hash');
            $content = json_encode([
                $effect->identifier, $effect->action, $effect->actor->reference(),
                $effect->before, $effect->after, $effect->correlationId, $effect->context,
            ], JSON_THROW_ON_ERROR);
            $hash = hash('sha256', (string) $prevHash . $content);
        }

        SisAudit::query()->create([
            'identifier' => $effect->identifier,
            'action' => $effect->action,
            'actor_type' => $effect->actor->type,
            'actor_id' => $effect->actor->id,
            'before_state' => $effect->before,
            'after_state' => $effect->after,
            'correlation_id' => $effect->correlationId,
            'idempotency_key' => $effect->idempotencyKey,
            'context' => $effect->context,
            'hash' => $hash,
            'prev_hash' => $prevHash,
            'created_at' => $effect->at,
        ]);
    }

    /**
     * The single loaded record for an identifier within this Decision, loaded once and mutated in place so
     * all of a Decision's register updates land in one save (see the class docblock).
     *
     * @param  array<string, SisRecord>  $updates
     */
    private function staged(Identifier $identifier, array &$updates): SisRecord
    {
        return $updates[(string) $identifier] ??= $this->record($identifier);
    }

    private function record(Identifier $identifier): SisRecord
    {
        $record = SisRecord::query()->find((string) $identifier);

        if (!$record instanceof SisRecord) {
            throw UnknownIdentifierException::of((string) $identifier);
        }

        return $record;
    }
}
