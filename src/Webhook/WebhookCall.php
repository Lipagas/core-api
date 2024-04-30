<?php

namespace Fleetbase\Webhook;

use Fleetbase\Webhook\BackoffStrategy\BackoffStrategy;
use Fleetbase\Webhook\Exceptions\CouldNotCallWebhook;
use Fleetbase\Webhook\Exceptions\InvalidBackoffStrategy;
use Fleetbase\Webhook\Exceptions\InvalidSigner;
use Fleetbase\Webhook\Exceptions\InvalidWebhookJob;
use Fleetbase\Webhook\Signer\Signer;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Support\Str;

class WebhookCall
{
    protected CallWebhookJob $callWebhookJob;

    protected string $uuid = '';

    protected string $secret;

    protected Signer $signer;

    protected array $headers = [];

    private array $payload = [];

    private $signWebhook = true;

    public static function create(): self
    {
        $config = config('webhook-server');

        return (new static())
            ->useJob(data_get($config, 'webhook_job', CallWebhookJob::class))
            ->uuid(Str::uuid())
            ->onQueue(data_get($config, 'queue', 'default'))
            ->onConnection(data_get($config, 'connection'))
            ->useHttpVerb(data_get($config, 'http_verb', 'post'))
            ->maximumTries(data_get($config, 'tries', 3))
            ->useBackoffStrategy(data_get($config, 'backoff_strategy', \Fleetbase\Webhook\BackoffStrategy\ExponentialBackoffStrategy::class))
            ->timeoutInSeconds(data_get($config, 'timeout_in_seconds'))
            ->signUsing(data_get($config, 'signer', \Fleetbase\Webhook\Signer\DefaultSigner::class))
            ->withHeaders(data_get($config, 'headers', ['Content-Type' => 'application/json']))
            ->withTags(data_get($config, 'tags', []))
            ->verifySsl(data_get($config, 'verify_ssl', false))
            ->throwExceptionOnFailure(data_get($config, 'throw_exception_on_failure', false))
            ->useProxy(data_get($config, 'proxy'));
    }

    public function __construct()
    {
    }

    public function url(string $url): self
    {
        $this->callWebhookJob->webhookUrl = $url;

        return $this;
    }

    public function payload(array $payload): self
    {
        $this->payload = $payload;

        $this->callWebhookJob->payload = $payload;

        return $this;
    }

    public function uuid(string $uuid): self
    {
        $this->uuid = $uuid;

        $this->callWebhookJob->uuid = $uuid;

        return $this;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function onQueue(?string $queue): self
    {
        $this->callWebhookJob->queue = $queue;

        return $this;
    }

    public function onConnection(?string $connection): self
    {
        $this->callWebhookJob->connection = $connection;

        return $this;
    }

    public function useSecret(string $secret): self
    {
        $this->secret = $secret;

        return $this;
    }

    public function useHttpVerb(string $verb): self
    {
        $this->callWebhookJob->httpVerb = $verb;

        return $this;
    }

    public function maximumTries(int $tries): self
    {
        $this->callWebhookJob->tries = $tries;

        return $this;
    }

    public function useBackoffStrategy(string $backoffStrategyClass): self
    {
        if (!is_subclass_of($backoffStrategyClass, BackoffStrategy::class)) {
            throw InvalidBackoffStrategy::doesNotExtendBackoffStrategy($backoffStrategyClass);
        }

        $this->callWebhookJob->backoffStrategyClass = $backoffStrategyClass;

        return $this;
    }

    public function timeoutInSeconds(int $timeoutInSeconds): self
    {
        $this->callWebhookJob->requestTimeout = $timeoutInSeconds;

        return $this;
    }

    public function signUsing(string $signerClass): self
    {
        if (!is_subclass_of($signerClass, Signer::class)) {
            throw InvalidSigner::doesNotImplementSigner($signerClass);
        }

        $this->signer = app($signerClass);

        return $this;
    }

    public function doNotSign(): self
    {
        $this->signWebhook = false;

        return $this;
    }

    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);

        return $this;
    }

    public function verifySsl(bool $verifySsl = true): self
    {
        $this->callWebhookJob->verifySsl = $verifySsl;

        return $this;
    }

    public function doNotVerifySsl(): self
    {
        $this->verifySsl(false);

        return $this;
    }

    public function throwExceptionOnFailure(bool $throwExceptionOnFailure = true): self
    {
        $this->callWebhookJob->throwExceptionOnFailure = $throwExceptionOnFailure;

        return $this;
    }

    public function useProxy(array|string|null $proxy = null): self
    {
        $this->callWebhookJob->proxy = $proxy;

        return $this;
    }

    public function meta(array $meta): self
    {
        $this->callWebhookJob->meta = $meta;

        return $this;
    }

    public function withTags(array $tags): self
    {
        $this->callWebhookJob->tags = $tags;

        return $this;
    }

    public function useJob(string $webhookJobClass): self
    {
        $job = app($webhookJobClass);

        if (!$job instanceof CallWebhookJob) {
            throw InvalidWebhookJob::doesNotExtendCallWebhookJob($webhookJobClass);
        }

        $this->callWebhookJob = $job;

        return $this;
    }

    public function dispatch(): PendingDispatch
    {
        $this->prepareForDispatch();

        return dispatch($this->callWebhookJob);
    }

    public function dispatchIf($condition): ?PendingDispatch
    {
        if ($condition) {
            return $this->dispatch();
        }

        return null;
    }

    public function dispatchUnless($condition): ?PendingDispatch
    {
        return $this->dispatchIf(!$condition);
    }

    public function dispatchSync(): void
    {
        $this->prepareForDispatch();

        dispatch_sync($this->callWebhookJob);
    }

    public function dispatchSyncIf($condition): void
    {
        if ($condition) {
            $this->dispatchSync();
        }
    }

    public function dispatchSyncUnless($condition): void
    {
        $this->dispatchSyncIf(!$condition);
    }

    protected function prepareForDispatch(): void
    {
        if (!$this->callWebhookJob->webhookUrl) {
            throw CouldNotCallWebhook::urlNotSet();
        }

        if ($this->signWebhook && empty($this->secret)) {
            throw CouldNotCallWebhook::secretNotSet();
        }

        $this->callWebhookJob->headers = $this->getAllHeaders();
    }

    protected function getAllHeaders(): array
    {
        $headers = $this->headers;

        if (!$this->signWebhook) {
            return $headers;
        }

        $signature = $this->signer->calculateSignature($this->callWebhookJob->webhookUrl, $this->payload, $this->secret);

        $headers[$this->signer->signatureHeaderName()] = $signature;

        return $headers;
    }
}
