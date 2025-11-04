<?php

namespace App\Traits;

use App\Models\Auditoria;

trait Auditable
{
    public static function bootAuditable()
    {
        static::created(function ($model) {
            Auditoria::registrar(
                'create',
                class_basename($model),
                $model->id,
                'Se creó un nuevo registro de ' . class_basename($model),
                null,
                $model->toArray()
            );
        });

        static::updated(function ($model) {
            Auditoria::registrar(
                'update',
                class_basename($model),
                $model->id,
                'Se actualizó el registro de ' . class_basename($model) . ' (ID: ' . $model->id . ')',
                $model->getOriginal(),
                $model->getChanges()
            );
        });

        static::deleted(function ($model) {
            Auditoria::registrar(
                'delete',
                class_basename($model),
                $model->id,
                'Se eliminó el registro de ' . class_basename($model) . ' (ID: ' . $model->id . ')',
                $model->toArray(),
                null
            );
        });
    }
}

