<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\User\CurrentUser;
use App\Component\Writeoff\WriteoffFactory;
use App\Controller\Base\AbstractController;
use App\Entity\Writeoff;
use Symfony\Component\Serializer\SerializerInterface;

class WriteoffCreateAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private WriteoffFactory $writeoffFactory,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(Writeoff $data): Writeoff
    {
        return $this->writeoffFactory->create($this->getUser(), $data->getReason(), $data->getDocDate());
    }
}
