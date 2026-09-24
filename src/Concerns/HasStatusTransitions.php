<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Concerns;

use Illuminate\Database\Eloquent\Model;

trait HasStatusTransitions
{
    private function transitionStatus(Model $model, mixed $newStatus, array $extra = []): void
    {
        if (! method_exists($model->status, 'canTransitionTo')) {
            throw new \RuntimeException('Model status must have canTransitionTo method.');
        }

        if ($model->status === $newStatus) {
            return;
        }

        if (! $model->status->canTransitionTo($newStatus)) {
            throw new \RuntimeException(
                "Invalid status transition from {$model->status->value} to {$newStatus->value}"
            );
        }

        $model->update(array_merge(['status' => $newStatus->value], $extra));
    }

    private function markAs(int $id, string $modelClass, mixed $newStatus, array $extra = []): void
    {
        $model = $modelClass::findOrFail($id);
        $this->transitionStatus($model, $newStatus, $extra);
    }
}
