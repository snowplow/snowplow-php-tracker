# PHP Analytics for Snowplow

[![early-release]][tracker-classificiation]
[![Build Status][travis-image]][travis]
[![Coverage Status][coveralls-image]][coveralls]
[![Latest Stable Version][packagist-image-1]][packagist-1]
[![Total Downloads][packagist-image-2]][packagist-2]
[![License][license-image]][license]

## Overview

Add analytics into your PHP apps and scripts with the **[Snowplow][1]** event tracker for **[PHP][2]**.

With this tracker you can collect event data from your PHP based applications, games and frameworks.

## Quickstart & Testing

Make sure `docker` & `docker-compose` are installed.

* `git clone git@github.com:snowplow/snowplow-php-tracker.git`
* `cd snowplow-php-tracker`
* `docker-compose run --rm snowplow composer.phar install`
* `docker-compose run --rm snowplow script/tests.sh`

## Find out more

| **[Technical Docs][techdocs]** | **[Setup Guide][setup]** | **[Roadmap][roadmap]** | **[Contributing][contributing]** |
|:------------------------------:|:------------------------:|:----------------------:|:--------------------------------:|
| ![i1][techdocs-image]          | ![i2][setup-image]       | ![i3][roadmap-image]   | ![i4][contributing-image]        |

## Copyright and license

The Snowplow PHP Tracker is copyright 2014-2019 Snowplow Analytics Ltd.

Licensed under the **[Apache License, Version 2.0][license]** (the "License");
you may not use this software except in compliance with the License.

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.

[1]: https://snowplowanalytics.com/
[2]: https://php.net/

[travis]: https://travis-ci.org/snowplow/snowplow-php-tracker
[travis-image]: https://travis-ci.org/snowplow/snowplow-php-tracker.svg?branch=master
[coveralls]: https://coveralls.io/github/snowplow/snowplow-php-tracker?branch=master
[coveralls-image]: https://coveralls.io/repos/github/snowplow/snowplow-php-tracker/badge.svg?branch=master

[packagist-1]: https://packagist.org/packages/snowplow/snowplow-tracker
[packagist-image-1]: https://poser.pugx.org/snowplow/snowplow-tracker/v/stable.png
[packagist-2]: https://packagist.org/packages/snowplow/snowplow-tracker
[packagist-image-2]: https://poser.pugx.org/snowplow/snowplow-tracker/downloads.png
[license-image]: https://img.shields.io/badge/license-Apache--2-blue.svg?style=flat

[techdocs-image]: https://d3i6fms1cm1j0i.cloudfront.net/github/images/techdocs.png
[setup-image]: https://d3i6fms1cm1j0i.cloudfront.net/github/images/setup.png
[roadmap-image]: https://d3i6fms1cm1j0i.cloudfront.net/github/images/roadmap.png
[contributing-image]: https://d3i6fms1cm1j0i.cloudfront.net/github/images/contributing.png
[techdocs]: https://github.com/snowplow/snowplow/wiki/PHP-Tracker
[setup]: https://github.com/snowplow/snowplow/wiki/PHP-Tracker-Setup
[roadmap]: https://github.com/snowplow/snowplow/wiki/PHP-Tracker-Roadmap
[contributing]: https://github.com/snowplow/snowplow/wiki/PHP-Tracker-Contributing

[license]: https://www.apache.org/licenses/LICENSE-2.0

[tracker-classificiation]: https://github.com/snowplow/snowplow/wiki/Tracker-Maintenance-Classification
[early-release]: https://img.shields.io/static/v1?style=flat&label=Snowplow&message=Early%20Release&color=014477&labelColor=9ba0aa&logo=data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAMAAAAoLQ9TAAAAeFBMVEVMaXGXANeYANeXANZbAJmXANeUANSQAM+XANeMAMpaAJhZAJeZANiXANaXANaOAM2WANVnAKWXANZ9ALtmAKVaAJmXANZaAJlXAJZdAJxaAJlZAJdbAJlbAJmQAM+UANKZANhhAJ+EAL+BAL9oAKZnAKVjAKF1ALNBd8J1AAAAKHRSTlMAa1hWXyteBTQJIEwRgUh2JjJon21wcBgNfmc+JlOBQjwezWF2l5dXzkW3/wAAAHpJREFUeNokhQOCA1EAxTL85hi7dXv/E5YPCYBq5DeN4pcqV1XbtW/xTVMIMAZE0cBHEaZhBmIQwCFofeprPUHqjmD/+7peztd62dWQRkvrQayXkn01f/gWp2CrxfjY7rcZ5V7DEMDQgmEozFpZqLUYDsNwOqbnMLwPAJEwCopZxKttAAAAAElFTkSuQmCC 
