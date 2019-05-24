PHP Analytics for Snowplow
==========================
[![Build Status][travis-image]][travis]
[![Coverage Status][coveralls-image]][coveralls]
[![Latest Stable Version][packagist-image-1]][packagist-1]
[![Total Downloads][packagist-image-2]][packagist-2]
[![License][license-image]][license]

## Overview

Add analytics into your PHP apps and scripts with the **[Snowplow][1]** event tracker for **[PHP][2]**.

With this tracker you can collect event data from your PHP based applications, games and frameworks.

## Find out more

| **[Technical Docs][techdocs]** | **[Setup Guide][setup]** | **[Roadmap][roadmap]** | **[Contributing][contributing]** |
|:------------------------------:|:------------------------:|:----------------------:|:--------------------------------:|
| ![i1][techdocs-image]          | ![i2][setup-image]       | ![i3][roadmap-image]   | ![i4][contributing-image]        |

## Run tests

* Clone this repo
* run `cd <project_path>`
* run `docker-compose run --rm snowplow composer.phar install`
* run `docker-compose run --rm snowplow script/tests.sh`

## Copyright and license

The Snowplow PHP Tracker is copyright 2014-2019 Snowplow Analytics Ltd.

Licensed under the **[Apache License, Version 2.0] [license]** (the "License");
you may not use this software except in compliance with the License.

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.

[1]: http://snowplowanalytics.com/
[2]: http://php.net/

[travis]: https://travis-ci.org/snowplow/snowplow-php-tracker
[travis-image]: https://travis-ci.org/snowplow/snowplow-php-tracker.svg?branch=master
[coveralls]: https://coveralls.io/r/snowplow/snowplow-php-tracker?branch=master
[coveralls-image]: https://coveralls.io/repos/snowplow/snowplow-php-tracker/badge.png?branch=master

[packagist-1]: https://packagist.org/packages/snowplow/snowplow-tracker
[packagist-image-1]: https://poser.pugx.org/snowplow/snowplow-tracker/v/stable.png
[packagist-2]: https://packagist.org/packages/snowplow/snowplow-tracker
[packagist-image-2]: https://poser.pugx.org/snowplow/snowplow-tracker/downloads.png
[license-image]: http://img.shields.io/badge/license-Apache--2-blue.svg?style=flat

[techdocs-image]: https://d3i6fms1cm1j0i.cloudfront.net/github/images/techdocs.png
[setup-image]: https://d3i6fms1cm1j0i.cloudfront.net/github/images/setup.png
[roadmap-image]: https://d3i6fms1cm1j0i.cloudfront.net/github/images/roadmap.png
[contributing-image]: https://d3i6fms1cm1j0i.cloudfront.net/github/images/contributing.png
[techdocs]: https://github.com/snowplow/snowplow/wiki/PHP-Tracker
[setup]: https://github.com/snowplow/snowplow/wiki/PHP-Tracker-Setup
[roadmap]: https://github.com/snowplow/snowplow/wiki/PHP-Tracker-Roadmap
[contributing]: https://github.com/snowplow/snowplow/wiki/PHP-Tracker-Contributing

[license]: http://www.apache.org/licenses/LICENSE-2.0
