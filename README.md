# CiviContacts

```sh
git clone git@github.com:elifesciences/civi-contacts.git
cd ./civi-contacts
composer install
```

## Environment variables required:

- `CIVI_SITE_KEY`
- `CIVI_API_KEY`

## Install standalone dependencies

```
COMPOSER=composer-standalone.json composer install
```

## Check setup

```
./console subscriber:urls --help
```

## Run tests

```
PHPUNIT_CONFIG=phpunit-standalone.xml.dist ./project_tests.sh
```
