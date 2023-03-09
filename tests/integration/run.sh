#!/usr/bin/env bash

APP_NAME=tables

APP_INTEGRATION_DIR=$PWD
ROOT_DIR=${APP_INTEGRATION_DIR}/../../../..
echo ''
echo '#'
echo '# Installing composer dependencies from tests/integration/'
echo '#'
composer install

echo ''
echo '#'
echo '# Starting PHP webserver'
echo '#'
php -S localhost:8080 -t ${ROOT_DIR} &
PHPPID1=$!
echo 'Running on process ID:'
echo $PHPPID1

# also kill php process in case of ctrl+c
trap 'kill -TERM $PHPPID1; wait $PHPPID1' TERM

# The federated server is started and stopped by the tests themselves
PORT_FED=8180
export PORT_FED

php -S localhost:${PORT_FED} -t ${ROOT_DIR} &
PHPPID2=$!
echo 'Running on process ID:'
echo $PHPPID2

# also kill php process in case of ctrl+c
trap 'kill -TERM $PHPPID2; wait $PHPPID2' TERM

NEXTCLOUD_ROOT_DIR=${ROOT_DIR}
export NEXTCLOUD_ROOT_DIR
export TEST_SERVER_URL="http://localhost:8080/"
export TEST_REMOTE_URL="http://localhost:8180/"

echo ''
echo '#'
echo '# Setting up apps'
echo '#'


${ROOT_DIR}/occ app:enable tables || exit 1

${ROOT_DIR}/occ app:list | grep spreed

echo ''
echo '#'
echo '# Optimizing configuration'
echo '#'
# Disable bruteforce protection because the integration tests do trigger them
${ROOT_DIR}/occ config:system:set auth.bruteforce.protection.enabled --value false --type bool
# Allow local remote urls otherwise we can not share
${ROOT_DIR}/occ config:system:set allow_local_remote_servers --value true --type bool
# Temporarily opt-out of storing crypted passwords, as they have a bug and make our tests time out
${ROOT_DIR}/occ config:system:set auth.storeCryptedPassword --value false --type bool

echo ''
echo '#'
echo '# Running tests'
echo '#'
${APP_INTEGRATION_DIR}/vendor/bin/behat --colors -f junit -f pretty $1 $2
RESULT=$?

echo ''
echo '#'
echo '# Stopping PHP webserver and disabling spreedcheats'
echo '#'
kill $PHPPID1
kill $PHPPID2

wait $PHPPID1
wait $PHPPID2

exit $RESULT
