<?php

return [
    'Sheet1' => [
        'mapping' => [
            'name' => 'A',
            'email' => 'B',
            'age' => 'C',
        ],
        'validation' => [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'age' => 'required|integer|min:18',
        ],
    ],
];
