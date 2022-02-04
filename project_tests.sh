#!/usr/bin/env bash
set -e

rm -f build/*.xml
vendor/bin/phpcs --warning-severity=0 -p src/ tests/
vendor/bin/phpunit --log-junit build/phpunit.xml
