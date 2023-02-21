app_name=tables
project_dir=$(CURDIR)/../$(app_name)
build_dir=$(CURDIR)/build/artifacts
cert_dir=$(HOME)/.nextcloud/certificates
php_dirs=appinfo/ lib/ tests/api/


all: dev-setup build


##### Environment ####

dev-setup: clean clean-dev init

init: composer-init npm-init

composer-init:
	composer install

npm-init:
	npm install

npm-upgrade:
	npm upgrade
	npm install

npm-update:
	npm update



##### Building #####

build: clean build-js-production assemble

appstore: build
	@echo "Signing…"
	tar -czf $(build_dir)/$(app_name).tar.gz \
		-C $(build_dir) $(app_name)
	openssl dgst -sha512 -sign $(cert_dir)/$(app_name).key $(build_dir)/$(app_name).tar.gz | openssl base64

assemble:
	mkdir -p $(build_dir)
	rsync -a \
	--exclude=babel.config.js \
	--exclude=build \
	--exclude=composer.* \
	--exclude=CONTRIBUTING.md \
	--exclude=.editorconfig \
	--exclude=.eslintrc.js \
	--exclude=.git \
	--exclude=.github \
	--exclude=.gitignore \
	--exclude=l10n/no-php \
	--exclude=Makefile \
	--exclude=node_modules \
	--exclude=package*.json \
	--exclude=.php_cs.* \
	--exclude=phpunit*xml \
	--exclude=.scrutinizer.yml \
	--exclude=src \
	--exclude=.stylelintrc.js \
	--exclude=tests \
	--exclude=.travis.yml \
	--exclude=.tx \
	--exclude=.idea \
	--exclude=vendor \
	--exclude=webpack*.js \
	--exclude=doc \
	$(project_dir) $(build_dir)

build-js:
	npm run dev

build-js-production:
	npm run build

watch-js:
	npm run watch


##### Testing #####
test: test-api

test-api:
	phpunit --bootstrap vendor/autoload.php --testdox tests/api/

ci: lint-js lint-xml lint-php-cs-fixer lint-php-psalm

##### Linting #####

lint: lint-php lint-js lint-css lint-xml


lint-php: lint-phpfast lint-php-phan

lint-phpfast: lint-php-lint lint-php-cs-fixer lint-php-phpcs

lint-php-lint:
	# Check PHP syntax errors
	@! find $(php_dirs) -name "*.php" | xargs -I{} php -l '{}' | grep -v "No syntax errors detected"

lint-php-phan:
	# PHAN - TODO
	vendor/bin/phan --allow-polyfill-parser -k tests/phan-config.php --no-progress-bar -m checkstyle | vendor/bin/cs2pr --graceful-warnings --colorize

lint-php-cs-fixer:
	# PHP Coding Standards Fixer (with Nextcloud coding standards)
	vendor/bin/php-cs-fixer fix --dry-run --diff

lint-php-psalm:
	composer psalm

lint-js:
	npm run lint

lint-css:
	npm run stylelint

lint-xml:
	# Check info.xml schema validity
	wget https://apps.nextcloud.com/schema/apps/info.xsd -P appinfo/ -N --no-verbose || [ -f appinfo/info.xsd ]
	xmllint appinfo/info.xml --schema appinfo/info.xsd --noout



##### Fix lint #####

lint-fix: lint-php-fix lint-js-fix lint-css-fix

lint-php-fix:
	# vendor/bin/phpcbf --standard=tests/phpcs.xml $(php_dirs)
	vendor/bin/php-cs-fixer fix

lint-js-fix:
	npm run lint:fix

lint-css-fix:
	npm run stylelint:fix



##### Cleaning #####

clean:
	rm -rf js/
	rm -rf $(build_dir)

clean-dev:
	rm -rf node_modules
	rm -rf vendor