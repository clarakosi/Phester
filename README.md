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

Example: `./phester.php test https://www.mediawiki.org example.yaml`

Note: Phester will only output errors. If there are no errors in the test suite files then there will be no output.

### Spec format

```yaml
suite: TestActionQuery #REQUIRED: Test suite name
description: Testing ActionAPI #REQUIRED: Test suite description
type: RPC #OPTIONAL: Type of API 

setup: #OPTIONAL: Runs before tests
  - request: #REQUIRED: setup must have at least one request. 
   # description and interaction do not belong in setup
      method: post
      path: /w/api.php
      parameters:
        action: validatepassword
        format: json
      form-data:
        password: foobarbaz
# ...

tests: #REQUIRED: Test suite must have tests
  - description: Get information about Main Page #REQUIRED: Each test sequence must have
    # a description & interaction
    interaction: #REQUIRED: Includes one or more requests 
      - request: #REQUIRED: Must provide a request
          method: get #REQUIRED: Must provide a method
          path: /w/api.php #OPTIONAL: Defaults to an empty string
          parameters: #OPTIONAL: Additional query parameters go here
            action: query
            prop: info
            titles: Main Page
            format: json
          headers: #OPTIONAL
            accept: application/json
        response: #OPTIONAL: Defaults to a status 200
          status: 200
          headers:
            content-type: !pcre/pattern: /application\/json/ 
          body: !pcre/pattern: /.+/ # !pcre/pattern: is a tag for regex. The pattern must be
          # between two slashes
  - description: Get image info
    interaction:
      - request:
          method: post
          path: /w/api.php
          form-data: # Defaults to application/www-x-form-urlencoded without a specified content-type
            action: query
            prop: images
            titles: Main Page
            format: json
          headers:
            content-type: multipart/form-data
            accept: application/json
        response:
          status: 200
          headers:
            content-type: !pcre/pattern: /application\/json/
          body: # Recursive body match
            batchcomplete: ""
            query:
              pages:
                1423:
                  pageid: 1423
                  ns: 0
                  title: Main Page
        - request:
            method: post
            path: /w/api.php
            body: # Can be a string. If object with no content-type then it defaults to application/json. 
            # Otherwise can be application/www-x-form-urlencoded or multipart/form-data
              action: query
              prop: images
              titles: Main Page
              format: json
            headers:
              content-type: multipart/form-data
              accept: application/json
```
