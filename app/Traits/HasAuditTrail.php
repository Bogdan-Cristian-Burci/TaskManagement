<?php

namespace App\Traits;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

trait HasAuditTrail
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName($this->getLogName())
            ->setDescriptionForEvent(function(string $eventName) {
                return ucfirst(class_basename($this)) . " was {$eventName}";
            });
    }

    protected function getLogName(): string
    {
        return strtolower(class_basename($this));
    }
}
