<?php

namespace Reaction\ClientsPool;

use Reaction\Base\Component;

/**
 * Class PoolClient.
 * Just an example class, use your own with `PoolClientTrait` instead.
 * @package Reaction\ClientsPool
 */
class PoolClient extends Component implements PoolClientInterface
{
    use PoolClientTrait;
}