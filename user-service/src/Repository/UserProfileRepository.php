<?php

namespace App\Repository;

use App\Entity\UserProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserProfile::class);
    }

    public function save(UserProfile $profile, bool $flush = true): void
    {
        $this->getEntityManager()->persist($profile);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByUserId(int $userId): ?UserProfile
    {
        return $this->findOneBy(['userId' => $userId]);
    }
}
