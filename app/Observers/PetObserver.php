<?php

namespace App\Observers;

use App\Models\Pet;
use App\Http\Controllers\Api\AuditLogController;

class PetObserver
{
    public function created(Pet $pet): void
    {
        if ($pet->client) {
            $pet->client->recalculateLevel();
        }
        AuditLogController::log(
            'pet.created',
            Pet::class,
            (int) $pet->id,
            null,
            optional($pet->fresh())->toArray(),
            "Mascota creada: {$pet->name}"
        );
    }

    public function updated(Pet $pet): void
    {
        if ($pet->wasChanged('fallecido') || $pet->wasChanged('client_id')) {
            if ($pet->client) {
                $pet->client->recalculateLevel();
            }
        }
        $originalClientId = (int) ($pet->getOriginal('client_id') ?? 0);
        if ($originalClientId > 0 && $originalClientId !== (int) $pet->client_id) {
            $previousClient = \App\Models\Client::find($originalClientId);
            if ($previousClient) {
                $previousClient->recalculateLevel();
            }
        }
        AuditLogController::log(
            'pet.updated',
            Pet::class,
            (int) $pet->id,
            $pet->getOriginal(),
            $pet->getChanges(),
            "Mascota actualizada: {$pet->name}"
        );
    }

    public function deleted(Pet $pet): void
    {
        if ($pet->client) {
            $pet->client->recalculateLevel();
        }
        AuditLogController::log(
            'pet.deleted',
            Pet::class,
            (int) $pet->id,
            $pet->toArray(),
            null,
            "Mascota eliminada: {$pet->name}"
        );
    }

    public function restored(Pet $pet): void
    {
        if ($pet->client) {
            $pet->client->recalculateLevel();
        }
    }

    public function forceDeleted(Pet $pet): void
    {
        if ($pet->client) {
            $pet->client->recalculateLevel();
        }
    }
}
