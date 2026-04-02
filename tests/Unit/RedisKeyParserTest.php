<?php

use DhavalPtel\QueueMonitor\Support\RedisKeyParser;

beforeEach(function () {
    $this->parser = new RedisKeyParser();
});

it('parses a pending queue key', function () {
    $result = $this->parser->parse('queues:default');

    expect($result)
        ->queue->toBe('default')
        ->type->toBe('pending');
});

it('parses a delayed queue key', function () {
    $result = $this->parser->parse('queues:high:delayed');

    expect($result)
        ->queue->toBe('high')
        ->type->toBe('delayed');
});

it('parses a reserved queue key', function () {
    $result = $this->parser->parse('queues:emails:reserved');

    expect($result)
        ->queue->toBe('emails')
        ->type->toBe('reserved');
});

it('returns null for notification channel keys', function () {
    $result = $this->parser->parse('queues:default:notify');

    expect($result)->toBeNull();
});

it('returns null for non-queue keys', function () {
    $result = $this->parser->parse('some:other:key');

    expect($result)->toBeNull();
});

it('handles queue names with colons', function () {
    // Edge case: queue named "my:queue"
    // "queues:my:queue:delayed" — our parser takes everything before the last :delayed
    $result = $this->parser->parse('queues:my:queue:delayed');

    expect($result)
        ->queue->toBe('my:queue')
        ->type->toBe('delayed');
});

it('builds pending queue key', function () {
    expect($this->parser->buildKey('default', 'pending'))
        ->toBe('queues:default');
});

it('builds delayed queue key', function () {
    expect($this->parser->buildKey('high', 'delayed'))
        ->toBe('queues:high:delayed');
});

it('builds reserved queue key', function () {
    expect($this->parser->buildKey('emails', 'reserved'))
        ->toBe('queues:emails:reserved');
});

it('throws exception for unknown queue type', function () {
    $this->parser->buildKey('default', 'invalid');
})->throws(\InvalidArgumentException::class, 'Unknown queue type: invalid');
