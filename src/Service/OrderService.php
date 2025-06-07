<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use Symfony\Component\HttpFoundation\Request;
use App\Enum\River;
use App\Enum\Package;

class OrderService {
    public function create(Request $request): Order {
        $order = new Order();

        $type = $request->query->get('type');
        $duration = $request->query->get('duration');
        $river = $request->query->get('river');

        if ($type) {
            $order->setPackage(Package::from($type));
        }
        if ($river) {
            $order->setRiver(River::from($river));
        }
        if ($duration) {
            $order->setDuration(new \DateInterval('P' . $duration . 'D'));
        }

        return $order;
    }
}