<?php

namespace DhavalPtel\QueueMonitor\Support;

class JobPayloadParser
{
    /**
     * Extract the job class name from a queue job payload.
     */
    public function extractJobClass(string $payload): string
    {
        $data = json_decode($payload, true);

        if (! $data) {
            return 'Unknown';
        }

        // Laravel wraps jobs in CallQueuedHandler - the real class is in data.commandName
        if (isset($data['displayName'])) {
            return $data['displayName'];
        }

        if (isset($data['data']['commandName'])) {
            return $data['data']['commandName'];
        }

        if (isset($data['job'])) {
            return $data['job'];
        }

        return 'Unknown';
    }

    /**
     * Extract the attempt number from a queue job payload.
     */
    public function extractAttempts(string $payload): int
    {
        $data = json_decode($payload, true);

        return $data['attempts'] ?? 0;
    }

    /**
     * Extract the max tries from a queue job payload.
     */
    public function extractMaxTries(string $payload): ?int
    {
        $data = json_decode($payload, true);

        return $data['maxTries'] ?? null;
    }

    /**
     * Extract basic metadata without storing the full payload.
     */
    public function extractMetadata(string $payload): array
    {
        $data = json_decode($payload, true);

        if (! $data) {
            return [
                'job' => 'Unknown',
                'attempts' => 0,
                'max_tries' => null,
                'timeout' => null,
            ];
        }

        return [
            'job' => $this->extractJobClass($payload),
            'attempts' => $data['attempts'] ?? 0,
            'max_tries' => $data['maxTries'] ?? null,
            'timeout' => $data['timeout'] ?? null,
        ];
    }
}
