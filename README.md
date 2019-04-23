# Phester

API testing framework


### Technologies (so far):
#### Required Dependencies:
- PHP
- symfony/Console
- symfony/yaml
- guzzlehttp/guzzle

#### Dev dependencies:
- mediawiki/mediawiki-codesniffer
- jakub-onderka/php-parallel-lint

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