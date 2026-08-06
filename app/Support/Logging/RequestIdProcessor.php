<?php

namespace App\Support\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Ensure every structured log record carries request_id when the shared
 * log context has one (set by AssignRequestId).
 */
class RequestIdProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $context = [];
        if (function_exists('app') && app()->bound('log')) {
            // Laravel's shared context is merged by the framework; this is a
            // belt-and-braces copy for channels that bypass that path.
            $shared = logger()->sharedContext();
            if (! empty($shared['request_id'])) {
                $context['request_id'] = $shared['request_id'];
            }
        }

        if ($context === []) {
            return $record;
        }

        return $record->with(extra: array_merge($record->extra, $context));
    }
}
