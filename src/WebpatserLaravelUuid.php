<?php

namespace Snowplow\Tracker;

use Snowplow\Tracker\AdapterGeneratorUuidInterface;
use Webpatser\Uuid\Uuid;

class WebpatserLaravelUuid implements AdapterGeneratorUuidInterface
{

    public function generateUuid()
    {
        return Uuid::generate()->string;
    }

}
