<?php

namespace App\Repository\Organization;

use App\Entity\Organization\Employee;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Webauthn\Bundle\Repository\PublicKeyCredentialUserEntityRepositoryInterface;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * @method Employee|null find($id, $lockMode = null, $lockVersion = null)
 * @method Employee|null findOneBy(array $criteria, array $orderBy = null)
 * @method Employee[]    findAll()
 * @method Employee[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EmployeeRepository extends ServiceEntityRepository implements PasswordUpgraderInterface, UserLoaderInterface, PublicKeyCredentialUserEntityRepositoryInterface
{
    public function __construct(ManagerRegistry $registry, private \Psr\Log\LoggerInterface $logger)
    {
        parent::__construct($registry, Employee::class);
    }

    public function findOneByUsername(string $username): ?PublicKeyCredentialUserEntity
    {
        $this->logger->info('WebAuthn: Finding user by username', ['username' => $username]);
        $user = $this->loadUserByIdentifier($username);
        
        if (!$user instanceof Employee) {
            $this->logger->warning('WebAuthn: User not found by username', ['username' => $username]);
            return null;
        }

        return $this->convertUserEntity($user);
    }

    public function findOneByUserHandle(string $userHandle): ?PublicKeyCredentialUserEntity
    {
        $this->logger->info('WebAuthn: Finding user by userHandle', ['userHandle' => $userHandle, 'hex' => bin2hex($userHandle)]);
        
        // Try to find by ID (userHandle)
        // Since ID is UUID, we might need to handle it.
        // Assuming userHandle is the UUID string.
        try {
            $user = $this->find($userHandle);
        } catch (\Exception $e) {
            $this->logger->error('WebAuthn: Error finding user by handle', ['error' => $e->getMessage()]);
            return null;
        }

        if (!$user instanceof Employee) {
            $this->logger->warning('WebAuthn: User not found by handle', ['userHandle' => $userHandle]);
            return null;
        }

        $this->logger->info('WebAuthn: User found', ['id' => $user->getId(), 'username' => $user->getUserIdentifier()]);
        return $this->convertUserEntity($user);
    }

    private function convertUserEntity(Employee $user): PublicKeyCredentialUserEntity
    {
        return new PublicKeyCredentialUserEntity(
            $user->getUserIdentifier(), // Name
            (string) $user->getId(),    // ID (UserHandle)
            $user->getName() ?? $user->getUserIdentifier() // DisplayName
        );
    }

    public function loadUserByIdentifier(string $identifier): ?UserInterface
    {
        $entityManager = $this->getEntityManager();

        return $entityManager->createQuery(
            'SELECT e
            FROM App\Entity\Organization\Employee e
            WHERE e.email = :query
            OR e.username = :query
            OR e.employeeNo = :query'
        )
        ->setParameter('query', $identifier)
        ->getOneOrNullResult();
    }

    /**
     * 用于自动升级密码的哈希算法
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof Employee) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($user)));
        }

        $user->setPassword($newHashedPassword);
        $this->_em->persist($user);
        $this->_em->flush();
    }

    /**
     * 查找在职员工
     */
    public function findActiveEmployees()
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.employmentStatus = :status')
            ->andWhere('e.isSystem = :isSystem OR e.isSystem IS NULL')
            ->setParameter('status', 'active')
            ->setParameter('isSystem', false)
            ->orderBy('e.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 根据部门查找员工
     */
    public function findByDepartment($departmentId)
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.department = :departmentId')
            ->andWhere('e.isSystem = :isSystem OR e.isSystem IS NULL')
            ->setParameter('departmentId', $departmentId)
            ->setParameter('isSystem', false)
            ->orderBy('e.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 根据岗位查找员工
     */
    public function findByPosition($positionId)
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.position = :positionId')
            ->andWhere('e.isSystem = :isSystem OR e.isSystem IS NULL')
            ->setParameter('positionId', $positionId)
            ->setParameter('isSystem', false)
            ->orderBy('e.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 查找所有系统账号 (isSystem = true)
     */
    public function findSystemUsers(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.isSystem = :isSystem')
            ->setParameter('isSystem', true)
            ->orderBy('e.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 查找某个经理的所有下属
     */
    public function findSubordinates($managerId)
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.manager = :managerId')
            ->andWhere('e.isSystem = :isSystem OR e.isSystem IS NULL')
            ->setParameter('managerId', $managerId)
            ->setParameter('isSystem', false)
            ->orderBy('e.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}