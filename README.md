# Critical path CSS generator for SilverStripe (EXPERIMENTAL)

Assists in producing the Critical path CSS for every page of the website, including that code and 
postponing every other css. Uses [penthouse](https://github.com/pocketjoso/penthouse) to generate the CSS.

## How it works

The module provides a dev task `GenerateCriticalPathCSS` that calls every Live page on the website,
and processes the HTML with [penthouse](https://github.com/pocketjoso/penthouse), caches the resulting CSS, 
injects the latter into pages' during regular requests HTML and postpones all the already inlcluded local(!) CSS files using 
a special javascript snippet.

### Limitations
Firefox doesn't seem to like the postponing of CSS files and will most likely not gain any real speed boost.

## Requirements

* `nodejs`
* `yarn` or `npm`

They are required to install `penthouse` after package installation.
## Installation

Install via composer and do `?flush=1`.
```bash
composer require --prefer-dist gurucomkz/silverstripe-critical-path-css "dev-master"
```

Install required packages inside the package's directory with `yarn` or `npm`.
```bash
cd vendor/gurucomkz/silverstripe-critical-path-css
yarn || npm
```

## Usage

This is intended to be run on the target server.

```
sake dev/tasks/GenerateCriticalPathCSS
```
This task may take a lot of time depending on amount of pages on the website and effort required to produce their HTML. Please, note that since this command is executed in CLI caches produced by your web server are not applicable.

## TODO

* Allow configuring selectors that must be included into the critical path CSS
## Contributing

Please create an issue or a PR for any bugs you've found, or features you're missing.

## Special thanks

* [Jonas Ohlsson Aden](https://github.com/pocketjoso) for [penthouse](https://github.com/pocketjoso/penthouse)
