<?php

use Clickfwd\Yoyo\Exceptions\ComponentMethodNotFound;

use function Tests\htmlformat;
use function Tests\mockYoyoGetRequest;
use function Tests\resetYoyoRequest;
use function Tests\yoyo_update;
use function Tests\yoyo_view;

uses()->group('dispatch');

beforeAll(function () {
    yoyo_view();
});

afterEach(function () {
    resetYoyoRequest();
});

// =============================================================================
// JS Dispatch - Associative (named) params: Yoyo.dispatch('event', { key: val })
// =============================================================================

it('handles JS dispatch with single named parameter', function () {
    // Simulates: Yoyo.dispatch('post-created', { postId: 42 })
    // JS sends eventParams as JSON string with associative keys
    mockYoyoGetRequest('http://example.com/', 'dispatch-listener/post-created', '', [
        'eventParams' => json_encode(['postId' => 42]),
    ]);

    $output = yoyo_update();

    expect($output)->toContain('Post created with ID: 42');
});

it('handles JS dispatch with multiple named parameters', function () {
    // Simulates: Yoyo.dispatch('status-changed', { status: 'active', reason: 'manual' })
    mockYoyoGetRequest('http://example.com/', 'dispatch-listener/status-changed', '', [
        'eventParams' => json_encode(['status' => 'active', 'reason' => 'manual']),
    ]);

    $output = yoyo_update();

    expect($output)->toContain('Status: active, Reason: manual');
});

it('handles JS dispatch with named params where optional param is omitted', function () {
    // Simulates: Yoyo.dispatch('status-changed', { status: 'paused' })
    // 'reason' has a default value of 'none', should use it
    mockYoyoGetRequest('http://example.com/', 'dispatch-listener/status-changed', '', [
        'eventParams' => json_encode(['status' => 'paused']),
    ]);

    $output = yoyo_update();

    expect($output)->toContain('Status: paused, Reason: none');
});

// =============================================================================
// JS Dispatch - No params: Yoyo.dispatch('event')
// =============================================================================

it('handles JS dispatch without parameters', function () {
    // Simulates: Yoyo.dispatch('simple-refresh')
    // JS default params = {} → JSON.stringify({}) = '{}'
    mockYoyoGetRequest('http://example.com/', 'dispatch-listener/simple-refresh', '', [
        'eventParams' => json_encode(new stdClass()),
    ]);

    $output = yoyo_update();

    expect($output)->toContain('Refreshed without params');
});

// =============================================================================
// Server-side emit - Sequential (positional) params (existing behavior)
// =============================================================================

it('handles server-side emit with sequential parameters', function () {
    // Simulates server-side: $this->emit('post-created', 99)
    // Server emit sends params as numerically indexed array
    mockYoyoGetRequest('http://example.com/', 'dispatch-listener/post-created', '', [
        'eventParams' => json_encode([99]),
    ]);

    $output = yoyo_update();

    expect($output)->toContain('Post created with ID: 99');
});

it('handles server-side emit with multiple sequential parameters', function () {
    // Simulates: $this->emit('multi-param', 'My Title', 'My Body', 5)
    mockYoyoGetRequest('http://example.com/', 'dispatch-listener/multi-param', '', [
        'eventParams' => json_encode(['My Title', 'My Body', 5]),
    ]);

    $output = yoyo_update();

    expect($output)->toContain('Title: My Title, Body: My Body, Category: 5');
});

// =============================================================================
// JS dispatchTo - Same as dispatch but targets specific component
// =============================================================================

it('handles JS dispatchTo with named parameters', function () {
    // Simulates: Yoyo.dispatchTo('dispatch-listener', 'post-created', { postId: 7 })
    mockYoyoGetRequest('http://example.com/', 'dispatch-listener/post-created', '', [
        'eventParams' => json_encode(['postId' => 7]),
    ]);

    $output = yoyo_update();

    expect($output)->toContain('Post created with ID: 7');
});

it('handles JS dispatchTo with array syntax', function () {
    // Simulates: Yoyo.dispatchTo('dispatch-listener', ['post-created', { postId: 15 }])
    // After JS processing, this becomes the same request format
    mockYoyoGetRequest('http://example.com/', 'dispatch-listener/post-created', '', [
        'eventParams' => json_encode(['postId' => 15]),
    ]);

    $output = yoyo_update();

    expect($output)->toContain('Post created with ID: 15');
});

// =============================================================================
// Security - only declared listeners can be triggered
// =============================================================================

it('rejects dispatch to non-listener method', function () {
    // Attempting to call 'render' method directly via dispatch should fail
    // because 'render' is in the excluded base class methods
    mockYoyoGetRequest('http://example.com/', 'dispatch-listener/nonExistentEvent', '', [
        'eventParams' => json_encode(['evil' => 'data']),
    ]);

    yoyo_update();
})->throws(ComponentMethodNotFound::class);

// =============================================================================
// isSequentialArray unit tests
// =============================================================================

it('correctly identifies sequential arrays', function () {
    $method = new ReflectionMethod(\Clickfwd\Yoyo\ComponentManager::class, 'isSequentialArray');
    $method->setAccessible(true);

    // Sequential arrays (from server-side emit)
    expect($method->invoke(null, [1, 2, 3]))->toBeTrue();
    expect($method->invoke(null, ['a', 'b']))->toBeTrue();
    expect($method->invoke(null, [42]))->toBeTrue();

    // Associative arrays (from JS dispatch)
    expect($method->invoke(null, ['postId' => 2]))->toBeFalse();
    expect($method->invoke(null, ['status' => 'active', 'reason' => 'manual']))->toBeFalse();

    // Edge cases
    expect($method->invoke(null, []))->toBeFalse();
    expect($method->invoke(null, 'not-array'))->toBeFalse();
});


