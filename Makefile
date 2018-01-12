.PHONY : all clean

all : dist/phastpress.zip

clean :
	rm -rf dist

publish : dist/phastpress.zip
	./bin/publish

dist/composer.phar :
	mkdir -p dist
	wget -O $@~ https://github.com/composer/composer/releases/download/1.5.2/composer.phar
	chmod +x $@~
	mv $@~ $@

dist/phastpress.zip : dist/composer.phar .git/refs/heads/master
	./bin/package
