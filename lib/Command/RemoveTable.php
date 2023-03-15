<?php
/**
 * @copyright Copyright (c) 2023 Florian Steffens <flost-dev@mailbox.org>
 *
 * @author Florian Steffens <flost-dev@mailbox.org>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Tables\Command;

use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Service\TableService;
use OCP\DB\Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveTable extends Command {
	protected TableService $tableService;

	public function __construct(TableService $tableService) {
		parent::__construct();
		$this->tableService = $tableService;
	}

	protected function configure(): void {
		$this
			->setName('tables:remove')
			->setDescription('Remove a table.')
			->addArgument(
				'ID',
				InputArgument::REQUIRED,
				'ID for the table.'
			)
		;
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$id = $input->getArgument('ID');

		try {
			$table = $this->tableService->delete($id, '');
			$output->writeln('Table deleted.');
		} catch (InternalError|PermissionError|Exception $e) {
			$output->writeln('Error occurred: '.$e->getMessage());
		}
		return 0;
	}
}
