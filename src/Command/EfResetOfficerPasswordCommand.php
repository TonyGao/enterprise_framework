<?php

namespace App\Command;

use App\Entity\Organization\Employee;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'ef:reset-officer-password',
    description: '重置安全管理员（sys_admin / sec_admin / auditor）的密码',
)]
class EfResetOfficerPasswordCommand extends Command
{
    private const OFFICERS = [
        'sys_admin' => 'System Administrator',
        'sec_admin' => 'Security Administrator',
        'auditor'   => 'Security Auditor',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::OPTIONAL, '管理员用户名（sys_admin / sec_admin / auditor）')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, '指定密码（不指定则自动生成）');
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        if (!$input->getArgument('username')) {
            $io = new SymfonyStyle($input, $output);
            $choice = $io->choice('选择要重置密码的管理员', array_keys(self::OFFICERS));
            $input->setArgument('username', $choice);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = $input->getArgument('username');

        if (!isset(self::OFFICERS[$username])) {
            $io->error("未知的管理员: $username，可选: " . implode(', ', array_keys(self::OFFICERS)));
            return Command::FAILURE;
        }

        $user = $this->entityManager->getRepository(Employee::class)->findOneBy(['username' => $username]);

        if (!$user) {
            $io->error("管理员账号 \"$username\" 不存在，请先运行 app:init-officers 创建");
            return Command::FAILURE;
        }

        $password = $input->getOption('password');
        if (!$password) {
            $password = bin2hex(random_bytes(8));
            $io->note("未指定密码，已自动生成");
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);
        $user->setForcePasswordReset(false);
        $user->setIsPasswordModifiedByUser(false);

        $this->entityManager->flush();

        $io->success(sprintf('管理员 "%s" (%s) 密码已重置', $username, self::OFFICERS[$username]));
        $io->note(sprintf('新密码: %s', $password));

        return Command::SUCCESS;
    }
}
