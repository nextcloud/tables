<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use OCP\IURLGenerator;
use OCP\Server;

$urlGenerator = Server::get(IURLGenerator::class);
?>
<style nonce="<?php p(\OC::$server->getContentSecurityPolicyNonceManager()->getNonce()) ?>">
    .tables-error-wrapper {
        display: flex;
        justify-content: center;
        align-items: center;
        width: 100%;
    }

    .body-public-container {
        --color-text-maxcontrast: var(--color-text-maxcontrast-background-blur, var(--color-main-text));
        color: var(--color-main-text);
        background-color: var(--color-main-background-blur);
        padding: calc(3 * var(--default-grid-baseline));
        border-radius: var(--border-radius-container);
        box-shadow: 0 0 10px var(--color-box-shadow);
        display: inline-block;
        -webkit-backdrop-filter: var(--filter-background-blur);
        backdrop-filter: var(--filter-background-blur);
        display: flex;
        flex-direction: column;
        text-align: start;
        word-wrap: break-word;
        border-radius: 10px;
        cursor: default;
        -moz-user-select: text;
        -webkit-user-select: text;
        -ms-user-select: text;
        user-select: text;
        height: fit-content;
        width: 100%;
        max-width: 700px;
        margin-block: 10vh auto;
    }

    .body-public-container .icon-big {
        background-size: 70px;
        height: 70px;
    }

    .body-public-container form {
        width: initial;
    }

    .body-public-container p:not(:last-child) {
        margin-bottom: 12px;
    }

    .infogroup {
        margin: 8px 0;
    }

    .infogroup:last-child {
        margin-bottom: 0;
    }

    .update {
        width: calc(100% - 32px);
        text-align: center;
    }
</style>
<div class="tables-error-wrapper">
    <div class="body-public-container update">
        <div>
            <svg xmlns="http://www.w3.org/2000/svg" height="70" viewBox="0 -960 960 960" width="70">
                <path fill="currentColor" d="m674-456-50-50 69-70-69-69 50-51 70 70 69-70 51 51-70 69 70 70-51 50-69-69-70 69Zm-290-24q-60 0-102-42t-42-102q0-60 42-102t102-42q60 0 102 42t42 102q0 60-42 102t-102 42ZM96-192v-92q0-26 12.5-47.5T143-366q55-32 116-49t125-17q64 0 125 17t116 49q22 13 34.5 34.5T672-284v92H96Z" />
            </svg>
        </div>
        <h2><?php p($l->t('Share not found')); ?></h2>
        <p class="infogroup"><?php p($_['message'] ?: $l->t('This share does not exist or is no longer available')); ?></p>
        <p><a class="button primary" href="<?php p($urlGenerator->linkTo('', 'index.php')) ?>">
                <?php p($l->t('Back to %s', [$theme->getName()])); ?>
            </a></p>
    </div>
</div>