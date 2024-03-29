<?php

return [
    'gosend' => [
        'type'     => 'GO-SEND',
        'text'     => 'GoSend',
        'logo'     => env('STORAGE_URL_API').'default_image/delivery_method/gosend.png',
        'helper'   => \App\Lib\GoSend::class,
        'status'   => 1,
    ],
    'grab' => [
        'type'     => 'Grab',
        'text'     => 'GrabExpress',
        'logo'     => env('STORAGE_URL_API').'default_image/delivery_method/grab.png',
        'helper'   => \App\Lib\Grab::class,
        'status'   => 0,
    ],
    'outlet' => [
        'type'     => 'Internal Delivery',
        'text'     => 'Internal Delivery',
        'logo'     => env('STORAGE_URL_API').'default_image/delivery_method/outlet.png',
        'nolimit'  => true,
        'helper'   => \App\Lib\OutletDelivery::class,
        'status'   => 1,
    ],
];
