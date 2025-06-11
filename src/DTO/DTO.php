<?php

declare(strict_types=1);

namespace App\DTO;

use Dom\Entity;

interface DTO {
    function getEntity(): object;
}
