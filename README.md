MediaWiki CLI
=============

A command line client for [MediaWiki](https://www.mediawiki.org/) wikis.

[![CI](https://github.com/samwilson/mwcli/actions/workflows/ci.yml/badge.svg)](https://github.com/samwilson/mwcli/actions/workflows/ci.yml)

## About

MediaWiki CLI (or `mwcli` for short) is a multilingual cross-platform PHP command-line tool
for interacting with MediaWiki installations,
to carry out tasks such as uploading and downloading files, and exporting pages.

It is designed to operate on multiple wikis
and the configuration for these is stored in the `config.yml` file.
The wikis are usually remote from the computer on which wmcli is run,
but can also be local (i.e. it can be used on a server to perform some tasks).
The config file can be edited directly,
or via commands such as `sites:add` and `sites:modify`.

## Installation

1. Clone the repository:

       git clone https://github.com/samwilson/mwcli

2. Install dependencies:

       cd mwcli
       composer install --no-dev

3. Optionally add `mwcli` to your path:

       echo 'export PATH=$PATH:'$(pwd)/bin >> ~/.profile

## Usage

### sites:add

Add a new site to the config file.

    sites:add [-c|--config [CONFIG]] [--url URL]

* `--config` `-c` — Path of the Yaml config file to use.
  Default: '[CWD]/config.yml'
* `--url` — The URL of the wiki. Can be to any page or even api.php.
  *Required.*

### sites:info

Get general information about a wiki.

    sites:info [-c|--config [CONFIG]] [-w|--wiki WIKI]

* `--config` `-c` — Path of the Yaml config file to use.
  Default: '[CWD]/config.yml'
* `--wiki` `-w` — The mwcli name of the wiki to use. Use <info>sites:list</info> to list all.
  *Required.*

### sites:list

List all configured sites.

    sites:list [-c|--config [CONFIG]]

* `--config` `-c` — Path of the Yaml config file to use.
  Default: '[CWD]/config.yml'

### sites:remove

Remove a site from the config file.

    sites:remove [-c|--config [CONFIG]] [-w|--wiki WIKI]

* `--config` `-c` — Path of the Yaml config file to use.
  Default: '[CWD]/config.yml'
* `--wiki` `-w` — The mwcli name of the wiki to use. Use <info>sites:list</info> to list all.
  *Required.*

### export:contribs

Export a user's contributions.

    export:contribs [-c|--config [CONFIG]] [-w|--wiki WIKI] [-u|--user USER] [-d|--dest DEST] [-o|--only-author]

* `--config` `-c` — Path of the Yaml config file to use.
  Default: '[CWD]/config.yml'
* `--wiki` `-w` — The mwcli name of the wiki to use. Use <info>sites:list</info> to list all.
  *Required.*
* `--user` `-u` — Export contributions of this username.
  *Required.*
* `--dest` `-d` — The destination directory for exported files.
  Default: '[CWD]/contribs'
* `--only-author` `-o` — Export only where the given user is the original author of a page.

### export:wikitext

Export a wiki's pages as text files.

    export:wikitext [-c|--config [CONFIG]] [-w|--wiki WIKI] [-d|--dest DEST] [-e|--ext EXT]

* `--config` `-c` — Path of the Yaml config file to use.
  Default: '[CWD]/config.yml'
* `--wiki` `-w` — The mwcli name of the wiki to use. Use <info>sites:list</info> to list all.
  *Required.*
* `--dest` `-d` — The destination directory for exported files.
  Default: '[CWD]/wikitext'
* `--ext` `-e` — File extension to use for the exported files.
  Default: 'txt'

### extension:install

Install an extension into a local wiki. Requires 'install_path' to be set in a site's config.

    extension:install [-c|--config [CONFIG]] [-w|--wiki WIKI] [-g|--git] [-u|--gituser GITUSER] [--] <extension-name>

* `--config` `-c` — Path of the Yaml config file to use.
  Default: '[CWD]/config.yml'
* `--wiki` `-w` — The mwcli name of the wiki to use. Use <info>sites:list</info> to list all.
  *Required.*
* `--git` `-g` — Use Git to install the extension, instead of the default tarball method.
* `--gituser` `-u` — The username to use for Git. Implies <info>--git</info>
  *Required.*
* `<extension-name>` The extension's name (CamelCase, with underscores for spaces).

### extension:outdated

Shows a list of installed extensions that have updates available, including their latest version.

    extension:outdated [-c|--config [CONFIG]] [-w|--wiki WIKI]

* `--config` `-c` — Path of the Yaml config file to use.
  Default: '[CWD]/config.yml'
* `--wiki` `-w` — The mwcli name of the wiki to use. Use <info>sites:list</info> to list all.
  *Required.*

### upload:files

Upload local files to a wiki.

    upload:files [-c|--config [CONFIG]] [-w|--wiki WIKI] [-m|--comment COMMENT] [--] [<files>...]

* `--config` `-c` — Path of the Yaml config file to use.
  Default: '[CWD]/config.yml'
* `--wiki` `-w` — The mwcli name of the wiki to use. Use <info>sites:list</info> to list all.
  *Required.*
* `--comment` `-m` — Revision comment.
  *Required.*
* `<files>` Filenames of files to upload.

### upload:pages

Upload local text files as wiki pages.

    upload:pages [-c|--config [CONFIG]] [-w|--wiki WIKI] [-m|--comment [COMMENT]] [-t|--watch] [--] <pages-dir>

* `--config` `-c` — Path of the Yaml config file to use.
  Default: '[CWD]/config.yml'
* `--wiki` `-w` — The mwcli name of the wiki to use. Use <info>sites:list</info> to list all.
  *Required.*
* `--comment` `-m` — Revision comment.
* `--watch` `-t` — Keep watching the files and upload on any change.
* `<pages-dir>` Directory from which to import wiki pages.

## License: MIT

Copyright 2019 Sam Wilson.

Permission is hereby granted, free of charge, to any person obtaining a copy of this software
and associated documentation files (the "Software"), to deal in the Software without
restriction, including without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the
Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or
substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING
BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
