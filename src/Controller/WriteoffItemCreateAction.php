<?php

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\User\CurrentUser;
use App\Component\WriteoffItem\WriteoffItemFactory;
use App\Controller\Base\AbstractController;
use App\Entity\WriteoffItem;
use App\Service\WriteoffItemValidationService;
use Symfony\Component\Serializer\SerializerInterface;

class WriteoffItemCreateAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private WriteoffItemValidationService $writeoffItemValidationService,
        private WriteoffItemFactory $writeoffItemFactory,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(WriteoffItem $data): WriteoffItem
    {
        $this->writeoffItemValidationService->validate($data);

        return $this->writeoffItemFactory->create($data);
    }
}
