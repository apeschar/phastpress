.PHONY : all dist clean

all : vendor/autoload.php

dist : dist/phastpress.zip

clean :
	rm -rf dist vendor

publish : dist/phastpress.zip
	./bin/publish


vendor/autoload.php : vendor/bin/composer composer.json composer.lock
	composer install

dist/phastpress.zip : vendor/bin/composer $(wildcard .git/refs/heads/master)
	./bin/package

vendor/bin/composer :
	mkdir -p $(dir $@)
	wget -O $@~ https://github.com/composer/composer/releases/download/1.6.3/composer.phar
	chmod +x $@~
	mv $@~ $@
