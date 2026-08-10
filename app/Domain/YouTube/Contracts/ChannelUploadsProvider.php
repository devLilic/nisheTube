<?php

namespace App\Domain\YouTube\Contracts;

use App\Domain\YouTube\Data\ChannelUploadsPage;
use App\Domain\YouTube\Data\ChannelUploadsRequest;

interface ChannelUploadsProvider
{
    public function listUploads(ChannelUploadsRequest $request): ChannelUploadsPage;
}
