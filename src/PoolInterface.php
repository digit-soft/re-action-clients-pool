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
     * @return PoolClientInterface
     */
    public function getClient();

    /**
     * Get client with empty queue (in ready state)
     * @return PoolClientInterface|null
     */
    public function getClientIdle();

    /**
     * Get least busy client
     * @return PoolClientInterface|null
     */
    public function getClientLeastBusy();

    /**
     * Create client
     * @param bool $addToPool
     * @return PoolClientInterface
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