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
`./phester.php test <file_name> <base_uri>`

##### Example: 
`./phester.php test example.yaml https://www.mediawiki.org`
