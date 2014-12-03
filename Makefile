init vendor: composer.phar
	./composer.phar update

composer.phar:
	curl https://getcomposer.org/composer.phar > composer.phar
	chmod +x composer.phar

phpDocumentor.phar: composer.phar
	curl http://phpdoc.org/phpDocumentor.phar > phpDocumentor.phar
	chmod +x phpDocumentor.phar

tests: vendor
	./vendor/bin/phpunit \
		-c phpunit.xml.dist \
		--coverage-html ./build/coverage \
		--coverage-clover ./build/logs/clover.xml

lint: force
	php -l src/J/

phpdoc: phpDocumentor.phar
	./phpDocumentor.phar -c phpdoc.dist.xml

clean: force
	rm composer.phar
	rm phpDocumentor.phar
	rm -rf build
	rm -rf vendor

travis-init: vendor
travis-run: tests
travis-report: force
	php vendor/bin/test-reporter --stdout > codeclimate.json
	curl -X POST -d @codeclimate.json -H "Content-Type: application/json" -H "User-Agent: Code Climate (PHP Test Reporter v1.0.1-dev)"  https://codeclimate.com/test_reports

force:
