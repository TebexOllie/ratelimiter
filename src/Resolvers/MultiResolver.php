<?php

namespace ArtisanSdk\RateLimiter\Resolvers;

use App\Models\Basket\Repository;
use ArtisanSdk\RateLimiter\Contracts\Resolver;
use Closure;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

class MultiResolver implements Resolver, \Iterator
{
    /**
     * The request available to the resolver.
     *
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * The max number of requests allowed by the rate limiter.
     *
     * @var int|string
     */
    protected $max;

    /**
     * The replenish rate in requests per second for the rate limiter.
     *
     * @var int|string
     */
    protected $rate;

    /**
     * The duration in minutes the rate limiter will timeout.
     *
     * @var int|string
     */
    protected $duration;

    /**
     * The user resolver closure.
     *
     * @var \Closure
     */
    protected $userResolver;

    private $keys = [];

    /**
     * Setup the resolver.
     *
     * @param \Illuminate\Http\Request $request
     * @param int|string               $max
     * @param int|float|string         $rate
     * @param int|string               $duration
     */
    public function __construct(Request $request, $max = null, $rate = null, $duration = null)
    {
        $this->request = $request;
        $this->max = $max ?? 60;
        $this->rate = $rate ?? 1;
        $this->duration = $duration ?? 1;

        $this->generateKeys();
    }

    public function generateKeys(): self
    {
        $route = $this->request->route();
        $basketId = $route->parameter('checkoutId');

        /**
         * @var Repository $basketRepo
         */
        $basketRepo = app(Repository::class);

        $basket = $basketRepo->getBySecret($basketId);

        $keys = [
            sha1($this->request->ip()),
        ];

        if ($basket) {
            $keys = array_merge(
                $keys,
                [
                    sha1($basket->email),
                    sha1($basket->id),
                    sha1($basket->username)
                ]
            );
        }

        $this->keys = $keys;

        return $this;
    }

    /**
     * Get the resolver key used by the rate limiter for the unique request.
     *
     * @throws \RuntimeException
     *
     * @return string
     */
    public function key(): string
    {
        return current($this->keys);
    }

    /**
     * Get the max number of requests allowed by the rate limiter.
     *
     * @return int
     */
    public function max(): int
    {
        return (int) $this->max;
    }

    /**
     * Get the replenish rate in requests per second for the rate limiter.
     *
     * @return float
     */
    public function rate(): float
    {
        return (float) $this->rate;
    }

    /**
     * Get the duration in minutes the rate limiter will timeout.
     *
     * @return int
     */
    public function duration(): int
    {
        return (int) $this->duration;
    }

    public function current()
    {
        return current($this->keys) ? $this : false;
    }

    public function next()
    {
        next($this->keys);
    }

    public function rewind()
    {
        reset($this->keys);
    }

    public function valid()
    {
        return current($this->keys) ? true : false;
    }
}
