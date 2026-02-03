<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Share;

use DateTime;
use LogicException;
use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\Share;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\Node;
use OCP\Share\IAttributes;
use OCP\Share\IShare;

/**
 * This class is being used when using OCP/core infrastructure regarded to
 * sharing, where an IShare representation is expected.
 */
class SharePageShareDataDecorator implements IShare {
	public const TYPE_EMAIL = -500; // not implemented, but referenced from the template

	public function __construct(
		private readonly Share $share,
	) {
	}

	public function setId($id): never {
		throw new LogicException('Not implemented: read only object');
	}

	public function getNodeId(): int {
		return $this->share->getNodeId();
	}

	public function getNodeType(): string {
		return $this->share->getNodeType();
	}

	public function setShareType($shareType): never {
		throw new LogicException('Not implemented: read only object');
	}

	public function getShareType(): string {
		return $this->share->getReceiverType();
	}

	public function setSharedWith($sharedWith): never {
		throw new LogicException('Not implemented: read only object');
	}

	public function getSharedWith(): string {
		return $this->share->getReceiver();
	}

	public function setSharedWithDisplayName($displayName): never {
		throw new LogicException('Not implemented: read only object');
	}

	public function getSharedWithDisplayName(): string {
		return $this->share->getReceiverDisplayName();
	}

	public function getSharedWithAvatar(): never {
		throw new LogicException('Not implemented');
	}

	public function setPermissions($permissions): never {
		throw new LogicException('Not implemented: read only object');
	}

	public function getPermissions(): int {
		return \OCP\Constants::PERMISSION_READ;
	}

	public function newAttributes(): never {
		throw new LogicException('Not implemented');
	}

	public function getAttributes(): ?IAttributes {
		return null;
	}

	public function getStatus(): never {
		throw new LogicException('Not implemented');
	}

	public function setNote($note): never {
		throw new LogicException('Not implemented: read only object');
	}

	public function setNoExpirationDate(bool $noExpirationDate): never {
		throw new LogicException('Not implemented: read only object');
	}

	public function getNoExpirationDate(): bool {
		return true;
	}

	public function getLabel(): never {
		throw new LogicException('Not implemented');
	}

	public function setSharedBy($sharedBy): never {
		throw new LogicException('Not implemented: read only object');
	}

	public function getSharedBy(): string {
		return $this->share->getSender();
	}

	public function setPassword($password): never {
		throw new LogicException('Not implemented: read only object');
	}

	public function setPasswordExpirationTime(?\DateTimeInterface $passwordExpirationTime = null): never {
		throw new LogicException('Not implemented: read only object');
	}

	public function getPasswordExpirationTime(): ?\DateTimeInterface {
		return null;
	}

	public function setSendPasswordByTalk(bool $sendPasswordByTalk): never {
		throw new \LogicException('Not implemented: read only object');
	}

	public function getSendPasswordByTalk(): bool {
		return false;
	}

	public function getTarget(): never {
		throw new LogicException('Not implemented');
	}

	public function getShareTime(): DateTime {
		return new DateTime($this->share->getCreatedAt());
	}

	public function setMailSend($mailSend): never {
		throw new \LogicException('Not implemented: read only object');
	}

	public function setHideDownload(bool $hide): IShare {
		throw new \LogicException('Not implemented: read only object');
	}

	public function getReminderSent(): never {
		throw new LogicException('Not implemented');
	}

	public function canSeeContent(): never {
		throw new LogicException('Not implemented');
	}

	public function getId(): string {
		return (string)$this->share->getId();
	}

	public function getFullId(): string {
		return Application::APP_ID . ':' . $this->getId();
	}

	public function setProviderId($id): never {
		throw new LogicException('Not implemented: read only object');
	}

	public function setNode(Node $node): never {
		throw new LogicException('Not implemented: read only object');
	}

	public function getNode(): never {
		throw new LogicException('Not implemented');
	}

	public function setNodeId($fileId): never {
		throw new LogicException('Not implemented: read only object');
	}

	public function setNodeType($type): never {
		throw new LogicException('Not implemented: read only object');
	}

	public function setSharedWithAvatar($src): never {
		throw new LogicException('Not implemented: read only object');
	}

	public function setAttributes(?IAttributes $attributes): never {
		throw new LogicException('Not implemented: read only object');
	}

	public function setStatus(int $status): never {
		throw new LogicException('Not implemented: read only object');
	}

	public function getNote(): never {
		throw new LogicException('Not implemented');
	}

	public function setExpirationDate(?DateTime $expireDate): never {
		throw new LogicException('Not implemented: read only object');
	}

	public function getExpirationDate(): never {
		throw new LogicException('Not implemented');
	}

	public function isExpired(): never {
		throw new LogicException('Not implemented');
	}

	public function setLabel($label): never {
		throw new LogicException('Not implemented: read only object');
	}

	public function setShareOwner($shareOwner): never {
		throw new LogicException('Not implemented: read only object');
	}

	public function getShareOwner(): never {
		throw new LogicException('Not implemented');
	}

	public function getPassword(): string {
		return $this->share->getPassword();
	}

	public function setToken($token): never {
		throw new LogicException('Not implemented: read only object');
	}

	public function getToken(): string {
		return $this->share->getToken();
	}

	public function setTarget($target): never {
		throw new LogicException('Not implemented: read only object');
	}

	public function setShareTime(DateTime $shareTime): never {
		throw new LogicException('Not implemented: read only object');
	}

	public function getMailSend(): never {
		throw new LogicException('Not implemented');
	}

	public function setNodeCacheEntry(ICacheEntry $entry): never {
		throw new LogicException('Not implemented: read only object');
	}

	public function getNodeCacheEntry(): never {
		throw new LogicException('Not implemented');
	}

	public function setReminderSent(bool $reminderSent): IShare {
		throw new LogicException('Not implemented: read only object');
	}

	public function setParent(int $parent): IShare {
		throw new LogicException('Not implemented: read only object');
	}

	public function getParent(): never {
		throw new LogicException('Not implemented');
	}

	public function getHideDownload(): never {
		throw new LogicException('Not implemented');
	}
}
