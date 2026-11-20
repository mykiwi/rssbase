# Generated files of the application. Each one is rebuilt only when a file it comes from is newer.
# castor asks for the named targets its tasks need, with --jobs so that independent ones build in parallel.

.DELETE_ON_ERROR:
.PHONY: install vendor importmap bundles stylesheet chromium database

install: vendor importmap bundles stylesheet chromium database

vendor: vendor/composer/installed.json
importmap: assets/vendor/installed.php
bundles: var/make/bundles
stylesheet: var/tailwind/app.built.css
chromium: var/make/chromium
database: var/data_dev.db

# Without the auto-scripts of composer.json: importmap and bundles are targets of their own, built in
# parallel, and the cache needs no clearing because Symfony rebuilds the container when vendors change.
vendor/composer/installed.json: composer.json composer.lock
	composer install -n --prefer-dist --optimize-autoloader --no-scripts
	touch $@

assets/vendor/installed.php: importmap.php vendor/composer/installed.json
	bin/console importmap:install
	touch $@

var/make/bundles: vendor/composer/installed.json
	bin/console assets:install public
	mkdir -p $(@D)
	touch $@

# Tailwind looks for class names in every file of the project that git does not ignore.
var/tailwind/app.built.css: config/packages/symfonycasts_tailwind.yaml assets/vendor/installed.php \
		$(shell find assets templates src -type f -not -path 'assets/vendor/*')
	bin/console tailwind:build
	touch $@

# Rewritten only when the version of playwright-php changes: Composer keeps the dates of its archives,
# so the mtime of the package cannot tell, and Chromium must not be reinstalled for any other package.
var/make/playwright-php.version: vendor/composer/installed.json
	mkdir -p $(@D)
	jq -r '.packages[] | select(.name == "playwright-php/playwright").version' $< > $@.new
	if cmp -s $@.new $@; then rm $@.new; else mv $@.new $@; fi

# Chromium goes to Playwright's own cache, outside the project: this file records the install.
var/make/chromium: var/make/playwright-php.version
	vendor/bin/playwright-install chromium
	touch $@

# SQLite creates the database file on the first connection, so running the migrations creates it.
var/data_dev.db: vendor/composer/installed.json $(wildcard src/Migrations/*.php)
	bin/console doctrine:migrations:migrate -n --allow-no-migration --all-or-nothing
	touch $@
