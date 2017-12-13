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
	rm -rf workbench
	mkdir workbench
	git archive HEAD src | tar x --strip-components 1 -C workbench
	cd workbench && ../dist/composer.phar install
	rm workbench/composer.*
	find workbench -name .git\* -print0 | xargs -0 rm -rf
	(cd workbench && zip -r9 - .) > $@~
	mv $@~ $@
