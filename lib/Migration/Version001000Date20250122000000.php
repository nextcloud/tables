<?php
/** @noinspection PhpUnused */
declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Tables\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version001000Date20250122000000 extends SimpleMigrationStep {
        /**
         * @param IOutput $output
         * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
         * @param array $options
         * @return null|ISchemaWrapper
         */
        public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
                /** @var ISchemaWrapper $schema */
                $schema = $schemaClosure();

                if ($schema->hasTable('tables_views')) {
                        $table = $schema->getTable('tables_views');
                        if (!$table->hasColumn('view_group')) {
                                $table->addColumn('view_group', Types::STRING, [
                                        'notnull' => false,
                                        'default' => null,
                                        'length' => 255,
                                ]);
                        }
                }

                return $schema;
        }
}
