ifndef PHP
	PHP=7.2
endif
ifndef PHPUNIT
	PHPUNIT=7.5
endif

dockerized=docker run --init -it --rm \
	-u $(shell id -u):$(shell id -g) \
	-v $(shell pwd):/app -w /app
qa=${dockerized} \
	-e COMPOSER_HOME=/app/var/composer \
	-e SYMFONY_PHPUNIT_VERSION=${PHPUNIT} \
	jakzal/phpqa:php${PHP}-alpine
mkdocs=${dockerized} -p 8000:8000 squidfunk/mkdocs-material

phpunit=${qa} var/composer/vendor/bin/simple-phpunit
phpunit_coverage=${qa} phpdbg -qrr var/composer/vendor/bin/simple-phpunit
composer=${qa} composer
composer_args=--prefer-dist --no-progress --no-interaction --no-suggest

# deps
install:
	${composer} install ${composer_args}
update:
	${composer} update ${composer_args}
install-standalone:
	for package in $$(find src/*/composer.json -type f); do \
		${composer} install ${composer_args} --working-dir=$$(dirname $${package}); \
	done
update-standalone:
	for package in $$(find src/*/composer.json -type f); do \
		${composer} update ${composer_args} --working-dir=$$(dirname $${package}); \
	done
update-lowest-standalone:
	for package in $$(find src/*/composer.json -type f); do \
		${composer} update ${composer_args} --prefer-stable --prefer-lowest --working-dir=$$(dirname $${package}); \
	done

# tests
phpunit-install:
	${composer} global require --dev ${composer_args} symfony/phpunit-bridge
	${phpunit} install
phpunit: phpunit-install
	for package in $$(find src/*/composer.json -type f); do \
		${phpunit} -c $$(dirname $${package}); \
	done
phpunit-coverage: phpunit-install
	for package in $$(find src/*/composer.json -type f); do \
		${phpunit_coverage} -c $$(dirname $${package}) --coverage-clover=$$(dirname $${package})/coverage.xml; \
	done

# code style / static analysis
cs:
	${qa} php-cs-fixer fix --dry-run --verbose --diff --config=.php_cs.dist src/ tests/
sa: install phpunit-install
	${qa} phpstan analyse src/ tests/fixtures/sa

# docs
docs-serve:
	${mkdocs}
docs-build:
	${mkdocs} build --site-dir var/build/docs

# CI
ci-install:
	for package in $$(find src/*/composer.json -type f); do \
		${composer} require --dev --no-update --quiet --working-dir=$$(dirname $${package}) symfony/debug:^4.2.2; \
	done
	${qa} bin/ci-packager HEAD^ $$(find src/*/composer.json -type f -printf '%h\n')

# misc
smoke-test: update update-standalone phpunit cs sa
shell:
	${qa} /bin/sh
link: install-standalone
	${composer} global require --dev ${composer_args} ro0nl/link
	for package in $$(find src/*/composer.json -type f); do \
		${composer} link $$(dirname $${package}); \
	done
test-project:
	${composer} create-project --prefer-dist --no-progress --no-interaction symfony/skeleton var/test-project
	${composer} config --working-dir var/test-project extra.symfony.allow-contrib true
	${composer} require --working-dir var/test-project ${composer_args} orm
	${composer} require --working-dir var/test-project --dev ${composer_args} debug maker server
