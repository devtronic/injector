<?php
/**
 * This file is part of the Devtronic Injector package.
 *
 * Copyright {year} by Julian Finkler <julian@developer-heaven.de>
 *
 * For the full copyright and license information, please read the LICENSE
 * file that was distributed with this source code.
 */

namespace Devtronic\Tests\Injector;

class TestClass
{
    public $maxSpeed = 0;
    public $color = '';

    public function __construct($maxSpeed, $color)
    {
        $this->maxSpeed = $maxSpeed;
        $this->color = $color;
    }
}
