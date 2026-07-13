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
use Simtabi\SIS\Exception\UnknownIdentifierException;
use Simtabi\SIS\Identifier\Identifier;
use Simtabi\SIS\Identifier\LifecycleState;

/**
 * Applies a Decision's effects to the register and the audit trail. The core produced these as pure
 * descriptions; this is the only place they become writes. The caller runs it inside one transaction.
 */
final class EffectApplier
{
    public function apply(Decision $decision): void
    {
        foreach ($decision->effects() as $effect) {
            match (true) {
                $effect instanceof InsertRecord => $this->insert($effect),
                $effect instanceof ChangeState => $this->changeState($effect),
                $effect instanceof AssignAlias => $this->assignAlias($effect),
                $effect instanceof SetSubject => $this->setSubject($effect),
                $effect instanceof SetSupersededBy => $this->setSupersededBy($effect),
                $effect instanceof DeleteRecord => $this->delete($effect),
                $effect instanceof AppendAudit => $this->appendAudit($effect),
                default => null,
            };
        }
    }

    private function insert(InsertRecord $effect): void
    {
        $id = $effect->identifier;
        $record = new SisRecord;
        $record->setAttribute('identifier', (string) $id);
        $record->setAttribute('class', $id->class);
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

    private function changeState(ChangeState $effect): void
    {
        $record = $this->record($effect->identifier);
        $record->setAttribute('state', $effect->to);

        if ($effect->to === LifecycleState::Commissioned && $record->getAttribute('commissioned_at') === null) {
            $record->setAttribute('commissioned_at', $effect->at);
        }

        if ($effect->to === LifecycleState::Decommissioned) {
            $record->setAttribute('decommissioned_at', $effect->at);
        }

        $record->save();
    }

    private function assignAlias(AssignAlias $effect): void
    {
        $record = $this->record($effect->identifier);
        $record->setAttribute('alias', $effect->alias->value);
        $record->save();
    }

    private function setSubject(SetSubject $effect): void
    {
        $record = $this->record($effect->identifier);
        $record->setAttribute('subject_type', $effect->subject->type);
        $record->setAttribute('subject_id', $effect->subject->id);
        $record->save();
    }

    private function setSupersededBy(SetSupersededBy $effect): void
    {
        $record = $this->record($effect->identifier);
        $record->setAttribute('superseded_by', (string) $effect->successor);
        $record->save();
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

    private function record(Identifier $identifier): SisRecord
    {
        $record = SisRecord::query()->find((string) $identifier);

        if (!$record instanceof SisRecord) {
            throw UnknownIdentifierException::of((string) $identifier);
        }

        return $record;
    }
}
