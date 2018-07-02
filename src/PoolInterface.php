<?php

namespace Reaction\ClientsPool;

use Evenement\EventEmitterInterface;

/**
 * Interface PoolInterface
 * @package Reaction\ClientsPool
 */
interface PoolInterface extends EventEmitterInterface
{
    /**
     * Get client from pool
     * @return ClientInterface
     */
    public function getClient();

    /**
     * Get client with empty queue (in ready state)
     * @return ClientInterface|null
     */
    public function getClientIdle();

    /**
     * Get least busy client
     * @return ClientInterface|null
     */
    public function getClientLeastBusy();

    /**
     * Create client
     * @param bool $addToPool
     * @return ClientInterface
     */
    public function createClient($addToPool = true);

    /**
     * Check that clients max count reached
     * @return bool
     */
    public function isReachedMaxClients();

    /**
     * Close all clients/connections
     */
    public function closeAll();
}