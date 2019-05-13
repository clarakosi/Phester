# Phester

API testing framework


### Technologies (so far):
#### Required Dependencies:
- php
- ext-json
- guzzlehttp/guzzle
- psr/log
- symfony/console
- symfony/monolog-bundle
- symfony/yaml

#### Dev dependencies:
- jakub-onderka/php-parallel-lint
- mediawiki/mediawiki-codesniffer
- phpunit/phpunit

### Installation
- Clone/Download the repo
- `cd` into the repo
- Run `composer install` to install dependencies

### Run Test
- Run `composer test`

#### Fix linter errors:
- Run `composer fix`

### Usage
`./phester.php test <base_uri> <file_name> <another_file_name> ...`

##### Example: 
`./phester.php test https://www.mediawiki.org example.yaml`