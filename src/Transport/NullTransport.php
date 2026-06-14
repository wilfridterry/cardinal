<?php

namespace Cardinal\Transport;

class NullTransport implements TransportInterface
{
    public function send(array $metrics, array $issues): void {}
}
