# PHP Analytics for Snowplow

[![early-release]][tracker-classificiation]
[![Build Status][gh-actions-image]][gh-actions]
[![Coverage Status][coveralls-image]][coveralls]
[![License][license-image]][license]

[![Latest Stable Version][packagist-image-1]][packagist-1]
[![PHP_Version][php-version-image]][php-version]
[![Total Downloads][packagist-image-2]][packagist-2]


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

| **[Technical Docs][techdocs]** | **[Setup Guide][setup]** | **[Contributing][contributing]** |
|:------------------------------:|:------------------------:|:--------------------------------:|
| ![i1][techdocs-image]          | ![i2][setup-image]       | ![i3][contributing-image]        |

## Copyright and license

The Snowplow PHP Tracker is copyright 2014-2021 Snowplow Analytics Ltd.

Licensed under the **[Apache License, Version 2.0][license]** (the "License");
you may not use this software except in compliance with the License.

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.

[1]: https://snowplowanalytics.com/
[2]: https://php.net/

[gh-actions]: https://github.com/snowplow/snowplow-php-tracker/actions
[gh-actions-image]: https://github.com/snowplow/snowplow-php-tracker/workflows/ci/badge.svg?branch=master
[coveralls]: https://coveralls.io/github/snowplow/snowplow-php-tracker?branch=master
[coveralls-image]: https://img.shields.io/coveralls/github/snowplow/snowplow-php-tracker/master
[license]: https://www.apache.org/licenses/LICENSE-2.0
[license-image]: https://img.shields.io/badge/license-Apache--2-blue.svg?style=flat

[packagist-1]: https://packagist.org/packages/snowplow/snowplow-tracker
[packagist-image-1]: https://img.shields.io/packagist/v/snowplow/snowplow-tracker
[packagist-2]: https://packagist.org/packages/snowplow/snowplow-tracker
[packagist-image-2]: https://img.shields.io/packagist/dm/snowplow/snowplow-tracker
[php-version]: https://packagist.org/packages/snowplow/snowplow-tracker
[php-version-image]: https://img.shields.io/packagist/php-v/snowplow/snowplow-tracker

[techdocs-image]: https://d3i6fms1cm1j0i.cloudfront.net/github/images/techdocs.png
[setup-image]: https://d3i6fms1cm1j0i.cloudfront.net/github/images/setup.png
[contributing-image]: https://d3i6fms1cm1j0i.cloudfront.net/github/images/contributing.png
[techdocs]: https://docs.snowplowanalytics.com/docs/collecting-data/collecting-from-own-applications/php-tracker/
[setup]: https://docs.snowplowanalytics.com/docs/collecting-data/collecting-from-own-applications/php-tracker/setup/
[contributing]: https://github.com/snowplow/snowplow-php-tracker/blob/master/CONTRIBUTING.md

[tracker-classificiation]: https://docs.snowplowanalytics.com/docs/collecting-data/collecting-from-own-applications/tracker-maintenance-classification/
[early-release]: https://img.shields.io/static/v1?style=flat&label=Snowplow&message=Early%20Release&color=014477&labelColor=9ba0aa&logo=data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAMAAAAoLQ9TAAAAeFBMVEVMaXGXANeYANeXANZbAJmXANeUANSQAM+XANeMAMpaAJhZAJeZANiXANaXANaOAM2WANVnAKWXANZ9ALtmAKVaAJmXANZaAJlXAJZdAJxaAJlZAJdbAJlbAJmQAM+UANKZANhhAJ+EAL+BAL9oAKZnAKVjAKF1ALNBd8J1AAAAKHRSTlMAa1hWXyteBTQJIEwRgUh2JjJon21wcBgNfmc+JlOBQjwezWF2l5dXzkW3/wAAAHpJREFUeNokhQOCA1EAxTL85hi7dXv/E5YPCYBq5DeN4pcqV1XbtW/xTVMIMAZE0cBHEaZhBmIQwCFofeprPUHqjmD/+7peztd62dWQRkvrQayXkn01f/gWp2CrxfjY7rcZ5V7DEMDQgmEozFpZqLUYDsNwOqbnMLwPAJEwCopZxKttAAAAAElFTkSuQmCC
