<?php

namespace Reaction\ClientsPool;

use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;
use Reaction\Base\Component;

/**
 * Class Pool
 * @package Reaction\ClientsPool
 */
class Pool extends Component implements PoolInterface
{
    /**
     * @var int|null Time to live client
     */
    public $clientTtl = null;
    /**
     * @var int Maximum clients count
     */
    public $maxCount = 10;
    /**
     * @var int|null Maximum client queue count
     */
    public $maxQueueCount = null;
    /**
     * @var array Client config array used to create clients
     */
    public $clientConfig = [];
    /**
     * @var LoopInterface
     */
    public $loop;

    /**
     * @var ClientInterface[] Clients pool
     */
    protected $_clients = [];
    /**
     * @var string[] Client states data
     */
    protected $_clientsStates = [];
    /**
     * @var string[] Client queue counters
     */
    protected $_clientsQueueCounters = [];
    /**
     * @var TimerInterface
     */
    protected $_clientsCleanupTimer;

    public function init()
    {
        parent::init();
        $this->createCleanupTimer();
    }

    /**
     * Get client from pool
     * @return ClientInterface
     */
    public function getClient()
    {
        if (($client = $this->getClientIdle()) !== null) {
            return $client;
        } elseif (($client = $this->getClientLeastBusy()) !== null) {
            return $client;
        }
        return $this->createClient();
    }

    /**
     * Get client with empty queue
     * @return ClientInterface|null
     */
    public function getClientIdle()
    {
        if (!empty($this->_clientsStates)) {
            $states = $this->_clientsStates;
            asort($states);
            if (reset($states) === ClientInterface::CLIENT_POOL_STATE_READY) {
                $clientId = key($states);
                return isset($this->_clients[$clientId]) ? $this->_clients[$clientId] : null;
            }
        }
        return null;
    }

    /**
     * Get least busy client
     * @return ClientInterface|null
     */
    public function getClientLeastBusy()
    {
        if (!empty($this->_clientsQueueCounters)) {
            $counters = $this->_clientsQueueCounters;
            asort($counters);
            $minCounter = reset($counters);
            if (isset($this->maxQueueCount) && $minCounter >= $this->maxQueueCount && !$this->isReachedMaxClients()) {
                return null;
            }
            $clientId = key($counters);
            return isset($this->_clients[$clientId]) ? $this->_clients[$clientId] : null;
        }
        return null;
    }

    /**
     * Check that clients max count reached
     * @return bool
     */
    protected function isReachedMaxClients()
    {
        return isset($this->maxCount) && $this->maxCount <= count($this->_clients);
    }

    /**
     * Create client
     * @param bool $addToPool
     * @return ClientInterface
     */
    protected function createClient($addToPool = true)
    {
        $config = $this->clientConfig;
        /** @var ClientInterface $client */
        $client = \Reaction::create($config);
        if ($addToPool) {
            $clientId = $client->getClientId();
            $this->_clients[$clientId] = $client;
            //Bind event handlers to client
            $this->bindClientEvents($client);
        }
        return $client;
    }

    /**
     * Bind event handlers to client instance
     * @param ClientInterface $client
     */
    protected function bindClientEvents(ClientInterface $client)
    {
        $clientId = $client->getClientId();
        $this->_clientsStates[$clientId] = ClientInterface::CLIENT_POOL_STATE_READY;
        $this->_clientsQueueCounters[$clientId] = 0;

        //Remove client from pool on close
        $client->once(ClientInterface::CLIENT_POOL_EVENT_CLOSE, function() use ($client, $clientId) {
            unset($this->_clients[$clientId]);
            unset($this->_clientsStates[$clientId]);
            unset($this->_clientsQueueCounters[$clientId]);
            $client->removeAllListeners(ClientInterface::CLIENT_POOL_EVENT_CHANGE_STATE);
            $client->removeAllListeners(ClientInterface::CLIENT_POOL_EVENT_CHANGE_QUEUE);
        });
        //Change client state
        $client->on(ClientInterface::CLIENT_POOL_EVENT_CHANGE_STATE, function($state) use ($client, $clientId) {
            $this->_clientsStates[$clientId] = $state;
        });
        //Change client queue count
        $client->on(ClientInterface::CLIENT_POOL_EVENT_CHANGE_QUEUE, function($queueCount) use ($client, $clientId) {
            $this->_clientsQueueCounters[$clientId] = $queueCount;
        });
    }

    /**
     * Create cleanup timer if Client TTL is configured
     */
    protected function createCleanupTimer()
    {
        if (!isset($this->clientTtl)) {
            return;
        }
        $this->_clientsCleanupTimer = $this->loop->addPeriodicTimer(1, function($timer) {
            $expireTime = time() - $this->clientTtl;
            foreach ($this->_clients as $client) {
                if ($client->createdAt < $expireTime) {
                    $client->clientClose();
                }
            }
        });
    }
}