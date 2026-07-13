<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Panel;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Simtabi\Laranail\SIS\Authorization\AuthorizationContext;
use Simtabi\Laranail\SIS\Authorization\SisAbility;
use Simtabi\Laranail\SIS\Contract\PermissionResolver;
use Simtabi\Laranail\SIS\Models\SisRecord;
use Simtabi\Laranail\SIS\Read\SisReadModel;
use Simtabi\SIS\Identifier\Actor;
use Simtabi\SIS\Identifier\LifecycleState;

/**
 * The framework-agnostic bridge an admin panel binds to. SIS is headless by design — it ships no UI — but a
 * Filament, Nova, Livewire, or Blade panel needs two things the register can answer authoritatively: a
 * display-ready row, and the set of lifecycle actions an actor may take on a record *right now*. That set is
 * the intersection of the state machine (what is legal from this state) and the resolver (what this actor is
 * permitted) — so a panel never renders a button that the register would reject. No panel package is a
 * dependency; a consumer wires this into whichever one they run.
 */
final class RegisterPanelPresenter
{
    public function __construct(
        private readonly SisReadModel $read,
        private readonly PermissionResolver $resolver,
    ) {}

    /** @return LengthAwarePaginator<int, SisRecord> */
    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        return $this->read->paginate($perPage);
    }

    /** @return array<string, mixed> a display-ready row — the same stable field set as the API resource */
    public function present(SisRecord $record): array
    {
        return [
            'identifier' => $record->identifier,
            'class' => $record->class->value,
            'class_label' => $record->class->label(),
            'scope' => $record->scope,
            'serial' => $record->serial,
            'alias' => $record->alias,
            'state' => $record->state->value,
            'subject' => $record->subject_type !== null
                ? ['type' => $record->subject_type, 'id' => $record->subject_id]
                : null,
            'superseded_by' => $record->superseded_by,
            'commissioned_at' => $record->commissioned_at?->toIso8601String(),
        ];
    }

    /**
     * The lifecycle actions this actor may take on this record now — legal by the state machine AND permitted
     * by the resolver. A panel binds its action buttons to exactly this list.
     *
     * @return list<SisAbility>
     */
    public function permittedActions(SisRecord $record, Actor $actor): array
    {
        $context = new AuthorizationContext($record->class, $record->scope, $record->identifier());

        return array_values(array_filter(
            self::actionsFor($record->state),
            fn (SisAbility $ability): bool => $this->resolver->allows($actor, $ability, $context),
        ));
    }

    /**
     * The abilities that are legal from a given state, before authorization narrows them.
     *
     * @return list<SisAbility>
     */
    private static function actionsFor(LifecycleState $state): array
    {
        return match ($state) {
            LifecycleState::Reserved => [SisAbility::Commission, SisAbility::AttachSubject, SisAbility::Release],
            LifecycleState::Commissioned => [SisAbility::Suspend, SisAbility::Decommission, SisAbility::Supersede],
            LifecycleState::Suspended => [SisAbility::Restore, SisAbility::Decommission],
            LifecycleState::Decommissioned, LifecycleState::Void => [],
        };
    }
}
