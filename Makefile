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
	rm -rf dist/phastpress
	mkdir -p dist/phastpress
	git archive master | tar x -C dist/phastpress
	cd dist/phastpress && ../composer.phar install
	find dist/phastpress -name .git\* -print0 | xargs -0 rm -rf
	cd dist/phastpress && cat .distignore | xargs rm -rf
	cd dist && zip -r9 - phastpress > $(shell basename $@~)
	mv $@~ $@
