<?php

namespace App\Command;

use App\Entity\Organization\Employee;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:init-officers',
    description: 'Initialize the three security officers (System Admin, Security Admin, Auditor)',
)]
class InitOfficersCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $officers = [
            'sys_admin' => ['role' => 'ROLE_SYS_ADMIN', 'name' => 'System Administrator'],
            'sec_admin' => ['role' => 'ROLE_SEC_ADMIN', 'name' => 'Security Administrator'],
            'auditor'   => ['role' => 'ROLE_AUDITOR', 'name' => 'Security Auditor'],
        ];

        $employeeRepo = $this->entityManager->getRepository(Employee::class);

        foreach ($officers as $username => $data) {
            $user = $employeeRepo->findOneBy(['username' => $username]);
            
            if (!$user) {
                $user = new Employee();
                $user->setUsername($username);
                $user->setEmail($username . '@system.local');
                $user->setName($data['name']);
                $user->setEmployeeNo('SYS_' . strtoupper($username));
                $user->setEmploymentStatus('active');
                $user->setWorkStatus('working');
                $io->note(sprintf('Creating user "%s"...', $username));
            } else {
                $io->note(sprintf('Updating user "%s"...', $username));
            }

            $user->setRoles([$data['role']]);
            $user->setIsSystem(true);

            $password = bin2hex(random_bytes(8));
            $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);

            $this->entityManager->persist($user);
            $io->success(sprintf('User "%s" set with role %s.', $username, $data['role']));
            $io->note(sprintf('  Password: %s', $password));
        }

        $this->entityManager->flush();

        $io->success('Three officers initialization completed.');

        return Command::SUCCESS;
    }
}
