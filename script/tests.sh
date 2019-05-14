#!/bin/bash

mb --configfile tests/mountebank_mocks/imposter.json --nologfile --pidfile /tmp/mb.pid > /dev/null &
sleep 1
vendor/bin/phpunit
