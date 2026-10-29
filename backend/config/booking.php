<?php

return [
    'interval' => 10,
    'hours' => [1 => ['08:00', '18:00'], 2 => ['08:00', '18:00'], 3 => ['08:00', '18:00'], 4 => ['08:00', '18:00'], 5 => ['08:00', '18:00'], 6 => ['08:00', '18:00']],
    'breaks' => [['13:00', '14:00']],
    'closed_dates' => [],
    'services' => [
        1 => ['name' => 'Classic Haircut', 'duration' => 45, 'price' => 220],
        2 => ['name' => 'Skin Fade', 'duration' => 50, 'price' => 250],
        3 => ['name' => 'Beard Trim', 'duration' => 30, 'price' => 140],
        4 => ['name' => 'Haircut + Beard', 'duration' => 70, 'price' => 320],
        5 => ['name' => 'Kids Haircut', 'duration' => 35, 'price' => 160],
        6 => ['name' => 'Line Up', 'duration' => 20, 'price' => 100],
    ],
    'barbers' => [1 => 'Liam Carter', 2 => 'Daniel Brooks', 3 => 'Marcus Lee'],
];
