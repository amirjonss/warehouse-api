<?php

declare(strict_types=1);

namespace App\Component\Inventory;

use App\Component\Core\Enums\DocStatus;
use App\Component\InventoryItem\Exceptions\InventoryNotEditableException;
use App\Component\User\CurrentUser;
use App\Entity\Inventory;

/**
 * One rule, five call sites: a count sheet may only be touched while it is a draft, and only by
 * the person who opened it — or by the owner, who has to be able to fix anything.
 *
 * The role is read straight off the user rather than through isGranted(), because this runs in
 * services that live outside the controller context.
 */
class InventoryAccess
{
    public function __construct(private readonly CurrentUser $currentUser)
    {
    }

    public function assertEditable(Inventory $inventory): void
    {
        if ($inventory->getStatus() !== DocStatus::DRAFT) {
            throw new InventoryNotEditableException(
                'Инвентаризация уже проведена или отменена — изменить её нельзя.'
            );
        }

        if ($this->isAdmin()) {
            return;
        }

        if ($inventory->getCreatedBy()?->getId() !== $this->currentUser->getUser()->getId()) {
            throw new InventoryNotEditableException(
                'Это чужая инвентаризация — изменить её может только тот, кто её начал.'
            );
        }
    }

    private function isAdmin(): bool
    {
        return in_array('ROLE_ADMIN', $this->currentUser->getUser()->getRoles(), true);
    }
}
