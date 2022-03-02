#!/usr/bin/env bash
set -e

PHPUNIT_CONFIG="${PHPUNIT_CONFIG:-phpunit.xml.dist}"

rm -f build/*.xml
vendor/bin/phpcs --warning-severity=0 -p src/ tests/
vendor/bin/phpunit -c "$PHPUNIT_CONFIG" --log-junit build/phpunit.xml
