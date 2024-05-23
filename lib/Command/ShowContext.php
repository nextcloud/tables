<?php

declare(strict_types=1);

namespace OCA\Tables\Command;

use OC\Core\Command\Base;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Service\ContextService;
use OCP\DB\Exception;
use OCP\IConfig;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function json_decode;
use function json_encode;

class ShowContext extends Base {
	protected ContextService $contextService;
	protected LoggerInterface $logger;
	private IConfig $config;

	public function __construct(
		ContextService $contextService,
		LoggerInterface $logger,
		IConfig $config,
	) {
		parent::__construct();
		$this->contextService = $contextService;
		$this->logger = $logger;
		$this->config = $config;
	}

	protected function configure(): void {
		parent::configure();
		$this
			->setName('tables:contexts:show')
			->setDescription('Get all contexts or contexts available to a specified user')
			->addArgument(
				'context-id',
				InputArgument::REQUIRED,
				'The ID of the context to show'
			)
			->addArgument(
				'user-id',
				InputArgument::OPTIONAL,
				'Optionally, showing the context from the perspective of the given user'
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$contextId = trim($input->getArgument('context-id'));
		if ($contextId === '' || !is_numeric($contextId)) {
			$output->writeln('<error>Invalid Context ID</error>');
			return 1;
		}

		$userId = trim($input->getArgument('user-id'));
		if ($userId === '') {
			$userId = null;
		}

		try {
			$context = $this->contextService->findById($contextId, $userId);
		} catch (InternalError|Exception $e) {
			$output->writeln('Error while reading contexts from DB.');
			$this->logger->warning('Following error occurred during executing occ command "{class}"',
				[
					'app' => 'tables',
					'class' => self::class,
					'exception' => $e,
				]
			);
			if ($this->config->getSystemValueBool('debug', false)) {
				$output->writeln(sprintf('<warning>%s</warning>', $e->getMessage()));
				$output->writeln('<error>');
				debug_print_backtrace();
				$output->writeln('</error>');
			}
			return 1;
		}

		$contextArray = json_decode(json_encode($context), true);

		$contextArray['ownerType'] = match ($contextArray['ownerType']) {
			1 => 'group',
			default => 'user',
		};

		$out = ['ID ' . $contextArray['id'] => $contextArray];
		unset($out[$contextArray['id']]['id']);
		$this->writeArrayInOutputFormat($input, $output, $out);

		return 0;
	}
}
