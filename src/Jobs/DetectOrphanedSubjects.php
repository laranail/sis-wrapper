<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Jobs;

use Illuminate\Support\Facades\Event;
use Simtabi\Laranail\SIS\Events\OrphanedSubjectDetected;
use Simtabi\Laranail\SIS\Exception\UnknownMorphAliasException;
use Simtabi\Laranail\SIS\Models\SisRecord;
use Simtabi\Laranail\SIS\Services\MorphResolver;
use Simtabi\SIS\Identifier\SubjectRef;

/** A morph subject that points at a model that is gone (§2.8). Reports, NEVER deletes. */
final class DetectOrphanedSubjects extends SisJob
{
    public function handle(MorphResolver $morphs): void
    {
        SisRecord::query()
            ->whereNotNull('subject_type')
            ->get()
            ->each(function (SisRecord $record) use ($morphs): void {
                $type = $record->subject_type;
                $id = $record->subject_id;

                if ($type === null || $id === null) {
                    return;
                }

                $subject = SubjectRef::of($type, $id);

                try {
                    $model = $morphs->resolve($subject);
                } catch (UnknownMorphAliasException) {
                    Event::dispatch(new OrphanedSubjectDetected($record->identifier, $subject->reference()));

                    return;
                }

                if ($model === null) {
                    Event::dispatch(new OrphanedSubjectDetected($record->identifier, $subject->reference()));
                }
            });
    }
}
