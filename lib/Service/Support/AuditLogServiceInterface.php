<?php

declare(strict_types=1);

namespace OCA\Tables\Service\Support;

interface AuditLogServiceInterface
{
    public function log(string $message, array $context): void;
}