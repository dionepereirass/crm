<?php

namespace App\Enums;

enum EventProcessingStatus: string
{
    case RECEIVED = 'RECEIVED';
    case QUEUED = 'QUEUED';
    case PROCESSING = 'PROCESSING';
    case PROCESSED = 'PROCESSED';
    case DUPLICATE = 'DUPLICATE';
    case FAILED = 'FAILED';
    case PLAYER_NOT_FOUND = 'PLAYER_NOT_FOUND';
    case INVALID_SIGNATURE = 'INVALID_SIGNATURE';
    case INVALID_PAYLOAD = 'INVALID_PAYLOAD';

    public function isFinal(): bool
    {
        return in_array($this, [
            self::PROCESSED,
            self::DUPLICATE,
            self::FAILED,
            self::PLAYER_NOT_FOUND,
            self::INVALID_SIGNATURE,
            self::INVALID_PAYLOAD,
        ]);
    }
}
