#!/usr/bin/env bash

# SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

set -x

# remove old dependency file
rm .scoper-production-dependencies

# get direct production dependencies, only ignore the bin plugin
DIRECT_DEPENDENCIES=$(composer show --direct --no-dev 2>/dev/null | grep -v 'bamarni/composer-bin-plugin' | awk '{ print $1 }')
for DEPENDENCY in ${DIRECT_DEPENDENCIES}; do
	echo "${DEPENDENCY}" >> .scoper-production-dependencies

	# add all its sub-dependencies (recursively) to that file
	composer show "${DEPENDENCY}" --format json --tree \
		| jq -r '.installed[].requires[]' \
		| jq -r 'def names: if type == "array" then .[] | names elif type == "object" then .name, (.requires[]? | names) else empty end; names' \
		| grep / | sort | uniq >> .scoper-production-dependencies
done
