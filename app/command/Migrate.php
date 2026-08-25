<?php

namespace app\command;

use support\Db;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'migrate', description: '执行数据库迁移。migrate [run] 应用未执行的迁移；migrate fresh 清库后重建')]
class Migrate extends Command
{
    protected function configure(): void
    {
        $this->addArgument('action', InputArgument::OPTIONAL, 'run | fresh', 'run');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = $input->getArgument('action');
        /** @var \Illuminate\Database\Connection $db */
        $db = Db::connection('mysql');
        $pdo = $db->getPdo();
        $pdo->exec('SET NAMES utf8mb4');

        $this->ensureMigrationsTable($pdo);

        if ($action === 'fresh') {
            $output->writeln('<comment>清空全部表...</comment>');
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            $rows = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_NUM);
            foreach ($rows as $row) {
                $pdo->exec("DROP TABLE IF EXISTS `{$row[0]}`");
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            $this->ensureMigrationsTable($pdo);
            $output->writeln('<info>已清空</info>');
        }

        $files = glob(base_path() . '/database/migrations/*.sql');
        if ($files === false || $files === []) {
            $output->writeln('<comment>没有找到迁移文件（database/migrations/*.sql）</comment>');
            return Command::SUCCESS;
        }
        sort($files);

        $applied = $pdo->query('SELECT `name` FROM `migrations`')->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $applied, true)) {
                $output->writeln("<comment>跳过 {$name}（已执行）</comment>");
                continue;
            }
            $sql = (string)file_get_contents($file);
            $statements = preg_split('/;\s*\n/', $sql);
            if ($statements === false) {
                $output->writeln("<error>{$name} 解析失败</error>");
                return Command::FAILURE;
            }
            foreach ($statements as $statement) {
                // 去除语句内的注释行与空行
                $lines = preg_split('/\r?\n/', $statement);
                $clean = [];
                foreach ($lines as $line) {
                    $trimmed = trim($line);
                    if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                        continue;
                    }
                    $clean[] = $line;
                }
                $statement = trim(implode("\n", $clean));
                if ($statement === '') {
                    continue;
                }
                try {
                    $pdo->exec($statement);
                } catch (\Throwable $e) {
                    $output->writeln("<error>{$name} 执行失败: {$e->getMessage()}</error>");
                    $output->writeln("<error>SQL: " . mb_substr($statement, 0, 200) . "</error>");
                    return Command::FAILURE;
                }
            }
            $pdo->prepare('INSERT INTO `migrations` (`name`, `applied_at`) VALUES (?, ?)')
                ->execute([$name, now()]);
            $output->writeln("<info>已执行 {$name}</info>");
        }

        $output->writeln('<info>迁移完成 ✔</info>');
        return Command::SUCCESS;
    }

    private function ensureMigrationsTable(\PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `migrations` (
            `name` VARCHAR(255) NOT NULL,
            `applied_at` DATETIME NULL,
            PRIMARY KEY (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}
