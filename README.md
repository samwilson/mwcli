MediaWiki CLI
=============

A command line client for MediaWiki wikis.

[![CircleCI](https://circleci.com/gh/samwilson/mwcli.svg)](https://circleci.com/gh/samwilson/mwcli)

*[2019-07-01: This is a work in progress and not all documented features are working yet.]*

## About

MediaWiki CLI (or `mwcli` for short) is a multilingual cross-platform PHP tool
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

@TODO

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
