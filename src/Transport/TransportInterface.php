<?php

namespace Cardinal\Transport;

interface TransportInterface
{
    public function send(array $metrics, array $issues): void;
}
