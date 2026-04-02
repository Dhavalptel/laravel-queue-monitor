<?php

use DhavalPtel\QueueMonitor\Support\JobPayloadParser;

beforeEach(function () {
    $this->parser = new JobPayloadParser();
});

it('parses a standard Laravel job payload', function () {
    $payload = json_encode([
        'uuid' => 'abc-123',
        'displayName' => 'App\\Jobs\\ProcessPayment',
        'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
        'maxTries' => 3,
        'timeout' => 60,
        'data' => [
            'commandName' => 'App\\Jobs\\ProcessPayment',
            'command' => 'O:...',
        ],
        'attempts' => 1,
    ]);

    $result = $this->parser->parse($payload);

    expect($result)
        ->job->toBe('App\\Jobs\\ProcessPayment')
        ->uuid->toBe('abc-123')
        ->attempts->toBe(1)
        ->max_tries->toBe(3)
        ->timeout->toBe(60);
});

it('falls back to commandName when displayName is missing', function () {
    $payload = json_encode([
        'uuid' => 'def-456',
        'data' => [
            'commandName' => 'App\\Jobs\\SendEmail',
        ],
    ]);

    $result = $this->parser->parse($payload);

    expect($result)->job->toBe('App\\Jobs\\SendEmail');
});

it('returns Unknown for invalid JSON', function () {
    $result = $this->parser->parse('not-json');

    expect($result)->job->toBe('Unknown');
});

it('returns Unknown for empty payload', function () {
    $result = $this->parser->parse('{}');

    expect($result)
        ->job->toBe('Unknown')
        ->uuid->toBeNull()
        ->attempts->toBe(0);
});

it('extracts just the job class name', function () {
    $payload = json_encode([
        'displayName' => 'App\\Jobs\\GenerateReport',
    ]);

    expect($this->parser->extractJobClass($payload))
        ->toBe('App\\Jobs\\GenerateReport');
});

it('handles null max_tries and timeout gracefully', function () {
    $payload = json_encode([
        'displayName' => 'App\\Jobs\\SomeJob',
        'uuid' => 'xyz-789',
    ]);

    $result = $this->parser->parse($payload);

    expect($result)
        ->max_tries->toBeNull()
        ->timeout->toBeNull();
});
