box.phar:
	curl -Ll https://github.com/humbug/box/releases/download/3.8.4/box.phar -o box.phar
	chmod +x box.phar

phpspec.phar: box.phar vendor
	./box.phar compile
	vendor/bin/behat --profile=phar --format=progress

vendor: composer.phar
	./composer.phar install

composer.phar:
	curl -Lls https://getcomposer.org/installer | php

.PHONY: clean
clean:
	rm -f phpspec.phar box.phar composer.phar
