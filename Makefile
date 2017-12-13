.PHONY : all clean

all : dist/phastpress.zip

clean :
	rm -f dist/phastpress.zip

dist/composer.phar :
	mkdir -p dist
	wget -O $@~ https://github.com/composer/composer/releases/download/1.5.2/composer.phar
	chmod +x $@~
	mv $@~ $@

dist/phastpress.zip : dist/composer.phar
	rm -rf phastpress
	mkdir -p phastpress
	git archive HEAD src | tar x --strip-components 1 -C phastpress
	cd phastpress && ../dist/composer.phar install
	rm phastpress/composer.*
	find phastpress -name .git\* -print0 | xargs -0 rm -rf
	zip -r9 - phastpress > $@~
	mv $@~ $@
